#!/usr/bin/env python3
"""Worker de envio de WhatsApp para el motor de prospeccion de Limpia Oeste.

Corre en una PC local (no en el hosting) con Chrome + un perfil persistente de
WhatsApp Web. Sin cron: hace polling a la API del sistema (next-batch) y duerme
cuando no hay trabajo o los envios estan en pausa. El panel web es quien manda
(activar/pausar campanias, pausa global) — este script solo ejecuta.

Cada ciclo, antes de mandar mensajes de la cola, escanea los chats con mensajes
sin leer y reporta las respuestas al sistema (POST /api/outreach/responses).
Nunca responde ni envia nada que no venga de la cola del sistema.
"""
from __future__ import annotations

import json
import logging
import random
import re
import sys
import time
import urllib.parse
from datetime import date, datetime
from pathlib import Path
from typing import Optional

import requests
from selenium import webdriver
from selenium.common.exceptions import InvalidSessionIdException, NoSuchWindowException, TimeoutException, WebDriverException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

WORKER_VERSION = "1.0.0"
BASE_DIR = Path(__file__).parent
CONFIG_PATH = BASE_DIR / "config.json"
LOG_PATH = BASE_DIR / "worker.log"

IDLE_SLEEP_SECONDS = 300
CHAT_LOAD_TIMEOUT = 25
SEND_CONFIRM_TIMEOUT = 15
CONSECUTIVE_FAILURE_PAUSE_SECONDS = 1800
MAX_CONSECUTIVE_FAILURES = 3

# WhatsApp Web cambia su DOM con cierta frecuencia; si el envio empieza a fallar
# siempre con "no se encontro el cuadro de mensaje", revisar/actualizar esto.
# Se prueban en orden y se usa el primero que matchee; "footer" acota la busqueda
# al chat abierto (evita agarrar el buscador de arriba, que tambien es contenteditable).
MESSAGE_BOX_SELECTORS = (
    "footer div[contenteditable='true'][data-tab]",
    "#main footer div[contenteditable='true']",
    "footer div[contenteditable='true'][aria-placeholder]",
    "div[contenteditable='true'][data-tab='10']",
)
INVALID_NUMBER_HINTS = (
    "phone number shared via url is invalid",
    "el número de teléfono compartido a través de una url no es válido",
)

# Lectura de respuestas (chats no leidos). Igual de fragil que lo anterior: si
# WhatsApp Web cambia el DOM de la lista de chats, esto es lo primero a revisar.
CHAT_LIST_UNREAD_BADGE_XPATH = (
    "//div[@id='pane-side']//span[@aria-label and "
    "contains(translate(@aria-label, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'unread')]"
)
INCOMING_MESSAGE_SELECTOR = "div.message-in .selectable-text"
PHONE_FROM_JID_RE = re.compile(r"(\d{8,15})@c\.us")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.FileHandler(LOG_PATH, encoding="utf-8"), logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("whatsapp_worker")


def load_config() -> dict:
    if not CONFIG_PATH.is_file():
        log.error("Falta config.json. Copia config.example.json a config.json y completalo.")
        sys.exit(1)
    with CONFIG_PATH.open("r", encoding="utf-8") as f:
        return json.load(f)


class OutreachApiClient:
    def __init__(self, base_url: str, token: str):
        self.base_url = base_url.rstrip("/")
        self.session = requests.Session()
        self.session.headers.update({"X-Outreach-Token": token, "Content-Type": "application/json"})

    def next_batch(self) -> dict:
        r = self.session.get(f"{self.base_url}/api/outreach/next-batch", timeout=30)
        r.raise_for_status()
        return r.json()

    def report(self, uuid: str, status: str, error: Optional[str] = None) -> None:
        payload = {"uuid": uuid, "status": status}
        if error:
            payload["error"] = error[:500]
        try:
            r = self.session.post(f"{self.base_url}/api/outreach/report", json=payload, timeout=30)
            r.raise_for_status()
        except requests.RequestException as exc:
            log.error("No se pudo reportar %s (%s): %s", uuid, status, exc)

    def send_responses(self, items: list) -> None:
        if not items:
            return
        try:
            r = self.session.post(f"{self.base_url}/api/outreach/responses", json=items, timeout=30)
            r.raise_for_status()
            log.info("Reportadas %s respuestas al sistema.", len(items))
        except requests.RequestException as exc:
            log.error("No se pudieron reportar respuestas: %s", exc)

    def heartbeat(self, sent_today: int, last_error: str = "") -> None:
        payload = {"version": WORKER_VERSION, "sent_today": sent_today, "last_error": last_error}
        try:
            r = self.session.post(f"{self.base_url}/api/outreach/heartbeat", json=payload, timeout=30)
            r.raise_for_status()
        except requests.RequestException as exc:
            log.error("No se pudo enviar heartbeat: %s", exc)


def _clear_stale_chrome_locks(chrome_profile_path: str) -> None:
    """Si Chrome se cerro de mala manera (crash), deja archivos de lock que
    impiden arrancar una sesion nueva con el mismo perfil ('DevToolsActivePort
    file doesn't exist' / 'Chrome failed to start: crashed'). Son seguros de
    borrar: Chrome los recrea solo en cada arranque normal."""
    profile_dir = Path(chrome_profile_path)
    for name in ("SingletonLock", "SingletonCookie", "SingletonSocket"):
        lock_file = profile_dir / name
        try:
            if lock_file.exists() or lock_file.is_symlink():
                lock_file.unlink()
                log.info("Se borro un lock viejo de Chrome: %s", lock_file)
        except OSError as exc:
            log.warning("No se pudo borrar %s: %s", lock_file, exc)


def build_driver(chrome_profile_path: str) -> "webdriver.Chrome":
    _clear_stale_chrome_locks(chrome_profile_path)
    options = Options()
    options.add_argument(f"--user-data-dir={chrome_profile_path}")
    options.add_argument("--profile-directory=Default")
    options.add_argument("--start-maximized")
    options.add_experimental_option("excludeSwitches", ["enable-logging"])
    return webdriver.Chrome(options=options)


def wait_for_whatsapp_login(driver: "webdriver.Chrome") -> None:
    driver.get("https://web.whatsapp.com")
    log.info("Esperando login de WhatsApp Web (escaneá el QR la primera vez, despues queda guardado)...")
    WebDriverWait(driver, 120).until(EC.presence_of_element_located((By.XPATH, "//div[@id='pane-side']")))
    log.info("WhatsApp Web logueado.")


def is_driver_alive(driver: "webdriver.Chrome") -> bool:
    """Chequeo barato para detectar si Chrome se cerro/crasheo por fuera del worker."""
    try:
        _ = driver.title
        return True
    except WebDriverException:
        return False


def recreate_driver(old_driver: "webdriver.Chrome", chrome_profile_path: str) -> "webdriver.Chrome":
    """Tira el driver muerto y levanta uno nuevo. El perfil persistente evita pedir el QR de nuevo."""
    try:
        old_driver.quit()
    except Exception:
        pass
    log.warning("Recreando la sesion de Chrome/WhatsApp Web...")
    new_driver = build_driver(chrome_profile_path)
    wait_for_whatsapp_login(new_driver)
    log.info("Sesion de Chrome recreada.")
    return new_driver


def scan_unread_chats(driver: "webdriver.Chrome") -> list:
    """Escanea la lista de chats buscando mensajes sin leer y devuelve
    [{phone, body, received_at}, ...] listo para /api/outreach/responses.

    Best-effort: abrir un chat para leer el numero/mensaje lo marca como leido
    en WhatsApp (no hay forma de evitarlo desde la UI). Si un chat falla al
    procesarlo, se saltea y se loguea; nunca corta el ciclo completo.
    """
    driver.get("https://web.whatsapp.com")
    try:
        WebDriverWait(driver, CHAT_LOAD_TIMEOUT).until(
            EC.presence_of_element_located((By.XPATH, "//div[@id='pane-side']"))
        )
    except TimeoutException:
        log.warning("No cargo la lista de chats, se saltea el escaneo de respuestas de este ciclo.")
        return []

    try:
        unread_badges = driver.find_elements(By.XPATH, CHAT_LIST_UNREAD_BADGE_XPATH)
    except WebDriverException:
        unread_badges = []

    results = []
    for badge in unread_badges:
        try:
            chat_row = badge.find_element(By.XPATH, "./ancestor::div[@role='listitem']")
            chat_row.click()
            WebDriverWait(driver, 10).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, INCOMING_MESSAGE_SELECTOR))
            )
        except Exception:
            log.warning("No se pudo abrir un chat con mensajes sin leer, se saltea.")
            continue

        try:
            phone = extract_phone_from_open_chat(driver)
            body = extract_last_incoming_message(driver)
        except Exception:
            log.exception("Error inesperado leyendo un chat, se saltea.")
            continue

        if not phone or not body:
            log.warning("No se pudo extraer telefono/mensaje de un chat sin leer, se saltea.")
            continue

        results.append({"phone": phone, "body": body, "received_at": datetime.now().isoformat()})

    return results


def extract_phone_from_open_chat(driver: "webdriver.Chrome") -> str:
    try:
        header = driver.find_element(By.CSS_SELECTOR, "header span[dir='auto']")
        text = header.get_attribute("title") or header.text or ""
    except Exception:
        text = ""
    digits = re.sub(r"\D", "", text)
    if len(digits) >= 8:
        return "+" + digits
    match = PHONE_FROM_JID_RE.search(driver.page_source)
    return f"+{match.group(1)}" if match else ""


def extract_last_incoming_message(driver: "webdriver.Chrome") -> str:
    bubbles = driver.find_elements(By.CSS_SELECTOR, INCOMING_MESSAGE_SELECTOR)
    return bubbles[-1].text.strip() if bubbles else ""


def find_message_box(driver: "webdriver.Chrome") -> list:
    """Prueba los selectores candidatos en orden y devuelve el primer match."""
    for selector in MESSAGE_BOX_SELECTORS:
        boxes = driver.find_elements(By.CSS_SELECTOR, selector)
        if boxes:
            return boxes
    return []


def _is_invalid_number_page(driver: "webdriver.Chrome") -> bool:
    page_text = driver.page_source.lower()
    return any(hint in page_text for hint in INVALID_NUMBER_HINTS)


def send_message(driver: "webdriver.Chrome", phone: str, body: str) -> tuple[bool, str]:
    """Devuelve (ok, motivo_si_fallo). No levanta excepciones hacia afuera."""
    clean_phone = phone.lstrip("+")
    url = f"https://web.whatsapp.com/send?phone={clean_phone}&text={urllib.parse.quote(body)}"
    driver.get(url)

    try:
        WebDriverWait(driver, CHAT_LOAD_TIMEOUT).until(
            lambda d: find_message_box(d) or _is_invalid_number_page(d)
        )
    except TimeoutException:
        return False, "timeout esperando que cargue el chat"

    if _is_invalid_number_page(driver):
        return False, "el numero no tiene WhatsApp"

    boxes = find_message_box(driver)
    if not boxes:
        return False, "no se encontro el cuadro de mensaje (revisar selector, WhatsApp Web pudo haber cambiado)"

    try:
        boxes[-1].click()
        boxes[-1].send_keys(Keys.ENTER)
    except WebDriverException as exc:
        return False, f"error al enviar: {exc}"

    def _box_is_empty(d: "webdriver.Chrome") -> bool:
        current = find_message_box(d)
        return not current or all((b.text or "").strip() == "" for b in current)

    try:
        WebDriverWait(driver, SEND_CONFIRM_TIMEOUT).until(_box_is_empty)
    except TimeoutException:
        return False, "no se pudo confirmar que el mensaje salio (el cuadro no se vacio)"

    return True, ""


def run_batch(driver, api: OutreachApiClient, messages: list, delays: dict, sent_today: int) -> tuple[int, int, bool]:
    """Devuelve (enviados, fallidos, sesion_perdida). Si Chrome muere a mitad del
    batch, corta ahi mismo sin marcar el resto como fallido — al perder la
    'claim' en el sistema (recovery automatico a los 30 min), esos mensajes
    vuelven solos a la cola para el proximo intento con una sesion sana."""
    sent = 0
    consecutive_failures = 0
    min_delay = max(1, int(delays.get("min_delay", 60)))
    max_delay = max(min_delay, int(delays.get("max_delay", 180)))

    for i, msg in enumerate(messages):
        uuid, phone, body = msg["uuid"], msg["phone"], msg["body"]
        try:
            ok, reason = send_message(driver, phone, body)
        except (InvalidSessionIdException, NoSuchWindowException) as exc:
            log.error("Se perdio la sesion de Chrome a mitad de un envio (%s). Corto el batch.", exc)
            return sent, len(messages) - sent, True
        except Exception as exc:  # nunca crashear el loop por un mensaje puntual
            log.exception("Error inesperado enviando a %s", phone)
            ok, reason = False, str(exc)

        if ok:
            log.info("Enviado a %s (uuid=%s)", phone, uuid)
            api.report(uuid, "sent")
            sent += 1
            consecutive_failures = 0
        else:
            log.warning("Fallo al enviar a %s: %s", phone, reason)
            api.report(uuid, "failed", reason)
            consecutive_failures += 1

        if consecutive_failures >= MAX_CONSECUTIVE_FAILURES:
            msg_err = f"{consecutive_failures} fallos consecutivos, pausado localmente 30 min"
            log.error(msg_err + " (posible problema de sesion de WhatsApp)")
            api.heartbeat(sent_today + sent, last_error=msg_err)
            time.sleep(CONSECUTIVE_FAILURE_PAUSE_SECONDS)
            consecutive_failures = 0

        if i < len(messages) - 1:
            time.sleep(random.uniform(min_delay, max_delay))

    return sent, len(messages) - sent, False


def main() -> None:
    config = load_config()
    api = OutreachApiClient(config["system_url"], config["api_token"])
    driver = build_driver(config["chrome_profile_path"])
    wait_for_whatsapp_login(driver)

    sent_today = 0
    current_day = date.today()
    log.info("Worker %s arrancado. Ctrl+C para salir.", WORKER_VERSION)

    while True:
        if date.today() != current_day:
            current_day = date.today()
            sent_today = 0

        if not is_driver_alive(driver):
            log.warning("La sesion de Chrome no responde (¿se cerro la ventana o crasheo?). La recreo.")
            try:
                driver = recreate_driver(driver, config["chrome_profile_path"])
            except Exception:
                log.exception("No se pudo recrear la sesion de Chrome, reintento en %s segundos.", IDLE_SLEEP_SECONDS)
                time.sleep(IDLE_SLEEP_SECONDS)
                continue

        try:
            responses = scan_unread_chats(driver)
            api.send_responses(responses)
        except (InvalidSessionIdException, NoSuchWindowException):
            log.warning("Sesion de Chrome perdida escaneando respuestas, se recrea en el proximo ciclo.")
            continue
        except Exception:
            log.exception("Error inesperado escaneando respuestas, se continua con el envio.")

        try:
            api.heartbeat(sent_today)
            batch = api.next_batch()
        except requests.RequestException as exc:
            log.error("No se pudo contactar al sistema: %s", exc)
            time.sleep(IDLE_SLEEP_SECONDS)
            continue
        except Exception:
            log.exception("Error inesperado consultando next-batch")
            time.sleep(IDLE_SLEEP_SECONDS)
            continue

        if batch.get("paused"):
            log.info("Envios en pausa global desde el panel. Durmiendo %s segundos.", IDLE_SLEEP_SECONDS)
            time.sleep(IDLE_SLEEP_SECONDS)
            continue

        messages = batch.get("messages", [])
        if not messages:
            time.sleep(IDLE_SLEEP_SECONDS)
            continue

        sent, failed, session_lost = run_batch(driver, api, messages, batch.get("settings", {}), sent_today)
        sent_today += sent
        log.info("Batch terminado: %s enviados, %s fallidos. Total hoy: %s.", sent, failed, sent_today)
        if session_lost:
            try:
                driver = recreate_driver(driver, config["chrome_profile_path"])
            except Exception:
                log.exception("No se pudo recrear la sesion de Chrome, se reintenta en el proximo ciclo.")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log.info("Worker detenido por el usuario.")

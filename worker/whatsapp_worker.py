#!/usr/bin/env python3
"""Worker de envio de WhatsApp para el motor de prospeccion de Limpia Oeste.

Corre en una PC local (no en el hosting) con Chrome + un perfil persistente de
WhatsApp Web. Sin cron: hace polling a la API del sistema (next-batch) y duerme
cuando no hay trabajo o los envios estan en pausa. El panel web es quien manda
(activar/pausar campanias, pausa global) — este script solo ejecuta.

Fase 3 va a extender este archivo para leer chats no leidos y reportar
respuestas via POST /api/outreach/responses, antes de la parte de envio.
"""
from __future__ import annotations

import json
import logging
import random
import sys
import time
import urllib.parse
from datetime import date
from pathlib import Path
from typing import Optional

import requests
from selenium import webdriver
from selenium.common.exceptions import TimeoutException, WebDriverException
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
MESSAGE_BOX_SELECTOR = "div[contenteditable='true'][data-tab='10']"
SENT_TICK_SELECTOR = "span[data-icon='msg-time'], span[data-icon='msg-check'], span[data-icon='msg-dblcheck']"
INVALID_NUMBER_HINTS = (
    "phone number shared via url is invalid",
    "el número de teléfono compartido a través de una url no es válido",
)

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

    def heartbeat(self, sent_today: int, last_error: str = "") -> None:
        payload = {"version": WORKER_VERSION, "sent_today": sent_today, "last_error": last_error}
        try:
            r = self.session.post(f"{self.base_url}/api/outreach/heartbeat", json=payload, timeout=30)
            r.raise_for_status()
        except requests.RequestException as exc:
            log.error("No se pudo enviar heartbeat: %s", exc)


def build_driver(chrome_profile_path: str) -> "webdriver.Chrome":
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


def send_message(driver: "webdriver.Chrome", phone: str, body: str) -> tuple[bool, str]:
    """Devuelve (ok, motivo_si_fallo). No levanta excepciones hacia afuera."""
    clean_phone = phone.lstrip("+")
    url = f"https://web.whatsapp.com/send?phone={clean_phone}&text={urllib.parse.quote(body)}"
    driver.get(url)

    try:
        WebDriverWait(driver, CHAT_LOAD_TIMEOUT).until(
            lambda d: d.find_elements(By.CSS_SELECTOR, MESSAGE_BOX_SELECTOR)
            or "invalid" in d.page_source.lower()
        )
    except TimeoutException:
        return False, "timeout esperando que cargue el chat"

    page_text = driver.page_source.lower()
    if any(hint in page_text for hint in INVALID_NUMBER_HINTS):
        return False, "el numero no tiene WhatsApp"

    boxes = driver.find_elements(By.CSS_SELECTOR, MESSAGE_BOX_SELECTOR)
    if not boxes:
        return False, "no se encontro el cuadro de mensaje (revisar selector, WhatsApp Web pudo haber cambiado)"

    try:
        boxes[-1].click()
        boxes[-1].send_keys(Keys.ENTER)
    except WebDriverException as exc:
        return False, f"error al enviar: {exc}"

    try:
        WebDriverWait(driver, SEND_CONFIRM_TIMEOUT).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, SENT_TICK_SELECTOR))
        )
    except TimeoutException:
        return False, "no se pudo confirmar que el mensaje salio"

    return True, ""


def run_batch(driver, api: OutreachApiClient, messages: list, delays: dict, sent_today: int) -> tuple[int, int]:
    sent = 0
    consecutive_failures = 0
    min_delay = max(1, int(delays.get("min_delay", 60)))
    max_delay = max(min_delay, int(delays.get("max_delay", 180)))

    for i, msg in enumerate(messages):
        uuid, phone, body = msg["uuid"], msg["phone"], msg["body"]
        try:
            ok, reason = send_message(driver, phone, body)
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

    return sent, len(messages) - sent


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

        sent, failed = run_batch(driver, api, messages, batch.get("settings", {}), sent_today)
        sent_today += sent
        log.info("Batch terminado: %s enviados, %s fallidos. Total hoy: %s.", sent, failed, sent_today)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log.info("Worker detenido por el usuario.")

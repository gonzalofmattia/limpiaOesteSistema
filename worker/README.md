# Worker de WhatsApp — Prospección Limpia Oeste

Este script corre **en tu PC** (no en el hosting — DonWeb no puede mandar WhatsApp).
Se conecta a WhatsApp Web con Chrome y un perfil persistente, y le pregunta al
sistema cada tanto si hay mensajes para mandar. Vos controlás todo desde el panel
(`/prospeccion`, `/prospeccion/campanas`, `/prospeccion/cola`): activar/pausar
campañas, pausa global, ver si el worker está vivo.

## Instalación

1. Python 3.10+ instalado y en el PATH.
2. Instalar dependencias:
   ```
   cd worker
   pip install -r requirements.txt
   ```
   Selenium 4.6+ maneja el driver de Chrome solo (no hace falta bajar chromedriver
   a mano), siempre que tengas Google Chrome instalado.
3. Copiar `config.example.json` a `config.json` y completar:
   - `system_url`: la URL del sistema. En Laragon local es
     `http://sistema.limpiaoeste.test`; en producción, `https://limpiaoeste.com.ar/sistema`.
   - `api_token`: lo encontrás en el panel, en **Configuración → Token del worker
     de WhatsApp**.
   - `chrome_profile_path`: una carpeta cualquiera (se crea sola) donde Chrome
     va a guardar la sesión de WhatsApp Web, para no escanear el QR cada vez.
     Ejemplo: `C:/Users/gonzalo/whatsapp-worker-profile`.

## Primer uso

```
python whatsapp_worker.py
```

Se abre una ventana de Chrome con WhatsApp Web. La primera vez va a pedir escanear
el QR con el WhatsApp del celular del negocio (Configuración → Dispositivos
vinculados → Vincular un dispositivo). Una vez escaneado, la sesión queda guardada
en `chrome_profile_path` — los próximos arranques no piden QR de nuevo, mientras
no cierres la sesión desde el celular.

Dejá la ventana de Chrome abierta y la terminal corriendo. El worker:
- Manda un "heartbeat" al sistema cada vez que pide trabajo (así el panel sabe
  que está vivo).
- Pide hasta 5 mensajes por vez (`next-batch`), respetando pausa global, ventana
  horaria y tope diario que se configuran desde el panel.
- Si no hay mensajes o los envíos están en pausa, espera 5 minutos y vuelve a
  preguntar.
- Entre mensaje y mensaje espera un delay aleatorio (configurable desde el panel,
  60-180 segundos por defecto) para no parecer un bot.
- Si un número no tiene WhatsApp, lo reporta como fallido con el motivo y sigue
  con el siguiente — nunca corta el loop por un error puntual.
- Si se acumulan 3 fallos seguidos, para 30 minutos por las dudas (puede ser que
  se haya cerrado la sesión de WhatsApp Web) y lo avisa en el heartbeat.
- Antes de mandar mensajes, en cada ciclo escanea los chats con mensajes sin
  leer y le avisa al sistema (`/api/outreach/responses`) para registrar
  respuestas, disparar seguimientos automáticos y detectar opt-outs. Solo lee
  y reporta — nunca contesta nada por su cuenta, ni manda algo que no venga de
  la cola del sistema.

Para cortarlo: `Ctrl+C` en la terminal.

## Notas

- **No uses el WhatsApp personal** para esto — usá un número de línea del negocio.
  Aun así, esto no es la API oficial de WhatsApp Business; ver el CLAUDE.md de la
  raíz del proyecto para los límites de mensajes/día y delays ya configurados por
  default para reducir el riesgo de baneo.
- WhatsApp Web cambia su interfaz de tanto en tanto. Si el worker empieza a
  reportar fallos raros (`no se encontro el cuadro de mensaje`), puede que haya
  que actualizar los selectores en `whatsapp_worker.py` (`MESSAGE_BOX_SELECTOR`,
  `SENT_TICK_SELECTOR`). Lo mismo aplica a la lectura de respuestas
  (`CHAT_LIST_UNREAD_BADGE_XPATH`, `INCOMING_MESSAGE_SELECTOR`) — es la parte
  más frágil de todo el worker porque WhatsApp no expone una forma oficial de
  leer chats no leídos.
- Abrir un chat para leer el número y el último mensaje lo marca como leído en
  WhatsApp — no hay forma de evitar esto desde la interfaz web. Es una
  limitación conocida, no un bug.
- Logs en `worker/worker.log` (se agranda con el tiempo, se puede borrar cuando
  quieras con el worker apagado).
- Esta carpeta **no se deploya** al hosting — corre aparte, en tu PC.

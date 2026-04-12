# Deploy a producción — LIMPIA OESTE (Ferozo / donweb)

**Sitio:** [https://sistema.limpiaoeste.com.ar](https://sistema.limpiaoeste.com.ar)  
**FTP (tu cuenta):** host `a0051423.ferozo.com`, usuario `a0051423` (la clave la tenés en el panel y en tu `.env` local; rotala cuando termines los deploys).

---

## Cómo saber `FTP_PATH` en Ferozo (si no lo muestra el panel)

El panel a veces no muestra la ruta con una etiqueta clara. Hacelo así:

### Opción A — Explorador FTP (recomendado)

1. Instalá **FileZilla** (o usá el explorador FTP del panel donweb/Ferozo).
2. Conectá con:
   - **Host:** `a0051423.ferozo.com`
   - **Usuario:** `a0051423`
   - **Contraseña:** la de tu cuenta FTP
   - **Puerto:** 21
3. Mirá el panel **derecho** (servidor remoto). La barra superior o el árbol muestran la carpeta actual (ej. `/public_html`).
4. Entrá a **`public_html`** y fijate si existe una carpeta del subdominio, por ejemplo:
   - `sistema.limpiaoeste.com.ar`
   - `sistema`
   - u otra que hayas creado al dar de alta el subdominio
5. **Regla para `FTP_PATH` en tu `.env` local:** es la ruta **desde la raíz FTP** hasta la carpeta donde tiene que quedar el proyecto (misma carpeta que el **document root** del subdominio, o su padre si subís todo el repo).
   - Si el subdominio sirve archivos **directamente** desde `public_html` (sin subcarpeta):  
     `FTP_PATH=/public_html`
   - Si el sitio del subdominio está en `public_html/sistema.limpiaoeste.com.ar`:  
     `FTP_PATH=/public_html/sistema.limpiaoeste.com.ar`
   - Si está en `public_html/sistema`:  
     `FTP_PATH=/public_html/sistema`
6. Guardá el `.env`, ejecutá `php deploy.php --dry-run` y revisá que las rutas listadas empiecen donde esperás (ej. `public/index.php` debajo de esa raíz).

### Opción B — Panel donweb / Ferozo

1. Buscá **Subdominios** o **Dominios** → **sistema.limpiaoeste.com.ar**.
2. Al **editar** el subdominio, fijate si hay texto tipo **carpeta**, **directorio**, **raíz** o **apuntar subdominio**; suele coincidir con el nombre de la carpeta dentro de `public_html`.
3. Si solo ves **“Sin redirección”** y no la ruta, usá la **Opción A**.

### Tipo de subdominio en el panel

Dejá **Sin redirección** (no uses redirección a URL/carpeta salvo que el soporte confirme que no hay redirección HTTP).

---

## Primera vez en producción — paso a paso

### A. Hosting y base de datos

1. [ ] Confirmar subdominio **sistema.limpiaoeste.com.ar** activo y tipo **Sin redirección**.
2. [ ] En Ferozo: **Bases de datos MySQL** → crear base y usuario; anotar **nombre de la base**, **usuario**, **contraseña** (en Ferozo `DB_HOST` casi siempre es `localhost`).
3. [ ] Averiguar `FTP_PATH` con la sección de arriba y dejarlo en tu **`.env` de tu PC** (junto a `FTP_HOST`, `FTP_USER`, `FTP_PASS`).

### B. Tu PC (Laragon)

4. [ ] En PHP, tener habilitada la extensión **FTP** (`extension=ftp` en `php.ini` de Laragon) y reiniciar Apache.
5. [ ] En la carpeta del proyecto: `php deploy.php --dry-run` → comprobar cantidad de archivos y que no aparezcan `export_*.sql`.
6. [ ] Exportar la base local: `php db_export.php` → queda un archivo `database/export_FECHA.sql` (backup; no se sube con `deploy.php`).

### C. Base de datos en el servidor

7. [ ] Entrá a **phpMyAdmin** en Ferozo (con el usuario de la base de **producción**).
8. [ ] Seleccioná la base creada en el paso 2 → **Importar** → elegí el `export_....sql` → ejecutar. Si falla por tamaño, subí el `.sql` por FTP fuera de `public_html` y pedí a soporte o usá import por partes.

### D. Código en el servidor (FTP)

9. [ ] Desde la carpeta del proyecto en tu PC: **`php deploy.php`** (sin `--dry-run`). Esperá a que termine sin errores.
10. [ ] El script **no sube** `.env`: en el servidor hay que crear el archivo a mano.

### E. Archivo `.env` en el servidor

11. [ ] Por **FileZilla** o administrador de archivos del panel, en la carpeta **raíz del proyecto** en el servidor (donde están `app/`, `public/`, `vendor/`), creá el archivo **`.env`**.
12. [ ] Copiá el contenido de **`.env.example`** del repo y completá:
    - `APP_ENV=production`
    - `APP_DEBUG=false`
    - `APP_URL=https://sistema.limpiaoeste.com.ar` (o `http://` si aún no tenés SSL)
    - `DB_HOST=localhost`
    - `DB_NAME=`, `DB_USER=`, `DB_PASS=` → los de la base **de producción** del paso 2
    - Las líneas `FTP_*` en el servidor **no hacen falta** para que el sitio funcione (solo para si algún día corrés deploy desde el servidor).

### F. Permisos y prueba

13. [ ] En el panel de archivos o FTP, carpetas **`storage/pdfs`**, **`storage/logs`** → permisos **755** o **775** (que PHP pueda escribir).
14. [ ] Abrí **https://sistema.limpiaoeste.com.ar** (o http). Debería cargar el login del ABM.
15. [ ] Probá login y generar un PDF. Si hay error 500, activá temporalmente `APP_DEBUG=true` en el `.env` del servidor, recargá, anotá el error y volvé a `false`.

### G. Raíz web = carpeta `public`

16. [ ] Lo ideal es que el **document root** del subdominio sea la carpeta **`public`** del proyecto (donde está `index.php`). Si Ferozo te deja elegir “carpeta pública” o “directorio”, apuntá a `.../public`.
17. [ ] Si **no** podés apuntar a `public` y el document root es la raíz del repo, necesitás que el hosting sirva con reglas tipo “todo a `public/`” (`.htaccess` en la raíz del proyecto ya existe para eso en muchos casos). Si ves listado de carpetas en vez del login, revisá con soporte cómo fijar el docroot a `public`.

---

## Cada deploy posterior (solo código)

1. `php db_export.php` (backup local, por si acaso).
2. `php deploy.php`.
3. Probar `https://sistema.limpiaoeste.com.ar`.
4. Si cambió la base de datos en local: exportar de nuevo e **importar** el `.sql` en phpMyAdmin en producción (con cuidado: puede pisar datos).

---

## Resumen rápido (comandos en tu PC)

```text
php deploy.php --dry-run    → simular qué se sube
php db_export.php           → backup SQL local
php deploy.php              → subir proyecto por FTP
```

---

## Permisos

- `storage/pdfs/` → 755 o 775  
- `storage/logs/` → 755 o 775  

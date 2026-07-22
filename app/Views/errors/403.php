<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso restringido — LIMPIA OESTE</title>
</head>
<body style="font-family: system-ui, sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; background:#F8FAFC; color:#1E293B;">
    <div style="text-align:center; max-width:28rem; padding:1.5rem;">
        <h1 style="font-size:1.5rem; margin-bottom:0.5rem;">403 — Acceso restringido</h1>
        <p style="color:#64748B; margin-bottom:1rem;">Tu usuario no tiene permiso para acceder a esta sección.</p>
        <p><a href="<?= e(url('/')) ?>" style="color:#1565C0;">Volver al dashboard</a></p>
    </div>
</body>
</html>

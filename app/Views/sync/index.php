<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900 mb-5">
    Esta operación sobrescribe completamente la base de destino (estructura y datos).
    Usar solo en entorno controlado.
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <h2 class="text-lg font-semibold mb-3">Flujo recomendado por archivo SQL</h2>
    <ol class="list-decimal pl-5 text-sm text-gray-700 space-y-1">
        <li>En producción, exportar la base desde phpMyAdmin/cPanel.</li>
        <li>Descargar el archivo <code>.sql</code> a tu PC.</li>
        <li>En esta pantalla, importar ese SQL a localhost (sobrescribe todo).</li>
        <li>Si querés subir local a producción, exportá local e importá en hosting.</li>
    </ol>
    <div class="mt-4 flex flex-wrap gap-2">
        <form method="post" action="<?= e(url('/sincronizacion/export-local')) ?>">
            <?= csrfField() ?>
            <button type="submit" class="px-4 py-2 rounded-lg bg-[#1565C0] text-white text-sm font-medium">
                Exportar localhost a SQL
            </button>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <h2 class="text-lg font-semibold mb-3">Importar SQL de producción a localhost</h2>
    <form method="post" action="<?= e(url('/sincronizacion/import-local')) ?>" enctype="multipart/form-data" class="space-y-3">
        <?= csrfField() ?>
        <input type="file" name="sql_file" accept=".sql" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="confirm_import" value="yes" class="mt-0.5">
            <span>Confirmo que deseo reemplazar toda la base local con este archivo SQL.</span>
        </label>
        <button type="submit" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">
            Importar SQL a localhost
        </button>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h2 class="text-lg font-semibold mb-4">Sincronizar bases de datos</h2>

    <form method="post" action="<?= e(url('/sincronizacion')) ?>" class="space-y-4">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium mb-1">Dirección</label>
            <select name="direction" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="pull" <?= (($defaults['direction'] ?? 'pull') === 'pull') ? 'selected' : '' ?>>Producción (remoto) -> Localhost</option>
                <option value="push" <?= (($defaults['direction'] ?? '') === 'push') ? 'selected' : '' ?>>Localhost -> Producción (remoto)</option>
            </select>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <h3 class="font-medium mb-2">Servidor remoto</h3>
                <div class="space-y-2">
                    <input type="text" name="remote_host" value="<?= e((string) ($defaults['remote_host'] ?? '')) ?>" placeholder="Host remoto (ej: 127.0.0.1 o dominio)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <input type="text" name="remote_db" value="<?= e((string) ($defaults['remote_db'] ?? '')) ?>" placeholder="Base remota" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <input type="text" name="remote_user" value="<?= e((string) ($defaults['remote_user'] ?? '')) ?>" placeholder="Usuario remoto" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <input type="password" name="remote_pass" placeholder="Clave remota" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <input type="text" name="remote_charset" value="utf8mb4" placeholder="Charset" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <h3 class="font-medium mb-2">Destino local (detectado)</h3>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm text-gray-700 space-y-1">
                    <p><strong>Host:</strong> <?= e((string) ($local['host'] ?? '')) ?></p>
                    <p><strong>Base:</strong> <?= e((string) ($local['database'] ?? '')) ?></p>
                    <p><strong>Usuario:</strong> <?= e((string) ($local['username'] ?? '')) ?></p>
                    <p><strong>Charset:</strong> <?= e((string) ($local['charset'] ?? 'utf8mb4')) ?></p>
                </div>
            </div>
        </div>

        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="confirm" value="yes" class="mt-0.5">
            <span>Confirmo que deseo sobrescribir la base de destino con toda la información de origen.</span>
        </label>

        <button type="submit" class="px-4 py-2 rounded-lg bg-[#1a6b3c] text-white text-sm font-medium">
            Ejecutar sincronización completa
        </button>
    </form>
</div>


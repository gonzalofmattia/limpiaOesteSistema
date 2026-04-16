<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\DatabaseSynchronizer;
use mysqli;
use RuntimeException;

final class SyncController extends Controller
{
    public function index(): void
    {
        $local = $this->localDbConfig();
        $this->view('sync/index', [
            'title' => 'Sincronización',
            'local' => $local,
            'defaults' => [
                'direction' => (string) ($this->query('direction', 'pull')),
                'remote_host' => (string) ($this->query('remote_host', '')),
                'remote_db' => (string) ($this->query('remote_db', '')),
                'remote_user' => (string) ($this->query('remote_user', '')),
            ],
        ]);
    }

    public function run(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/sincronizacion');
        }

        $direction = (string) $this->input('direction', 'pull');
        if (!in_array($direction, ['pull', 'push'], true)) {
            flash('error', 'Dirección inválida.');
            redirect('/sincronizacion');
        }

        $remote = [
            'host' => trim((string) $this->input('remote_host', '')),
            'database' => trim((string) $this->input('remote_db', '')),
            'username' => trim((string) $this->input('remote_user', '')),
            'password' => (string) $this->input('remote_pass', ''),
            'charset' => trim((string) $this->input('remote_charset', 'utf8mb4')) ?: 'utf8mb4',
        ];

        if ($remote['host'] === '' || $remote['database'] === '' || $remote['username'] === '') {
            flash('error', 'Completá host, base y usuario del servidor remoto.');
            redirect('/sincronizacion?direction=' . urlencode($direction)
                . '&remote_host=' . urlencode($remote['host'])
                . '&remote_db=' . urlencode($remote['database'])
                . '&remote_user=' . urlencode($remote['username']));
        }

        if ((string) $this->input('confirm', '') !== 'yes') {
            flash('error', 'Debés confirmar que querés sobrescribir la base de destino.');
            redirect('/sincronizacion');
        }

        $local = $this->localDbConfig();
        $source = $direction === 'pull' ? $remote : $local;
        $target = $direction === 'pull' ? $local : $remote;

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        try {
            $result = DatabaseSynchronizer::sync($source, $target);
            flash(
                'success',
                sprintf(
                    'Sincronización completada. %d tablas, %d filas. Origen: %s → Destino: %s',
                    $result['tables'],
                    $result['rows'],
                    $result['source'],
                    $result['target']
                )
            );
        } catch (\Throwable $e) {
            flash('error', 'Error de sincronización: ' . $e->getMessage());
        }

        redirect('/sincronizacion');
    }

    public function exportLocalSql(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/sincronizacion');
        }
        try {
            $local = $this->localDbConfig();
            $projectRoot = dirname(APP_PATH);
            $dir = $projectRoot . '/database';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear la carpeta database/');
            }
            $fileName = 'export_local_' . date('Y-m-d_His') . '.sql';
            $fullPath = $dir . '/' . $fileName;
            $this->dumpDatabaseToFile($local, $fullPath);
            flash('success', 'Exportación local generada: database/' . $fileName);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo exportar la base local: ' . $e->getMessage());
        }
        redirect('/sincronizacion');
    }

    public function importSqlToLocal(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/sincronizacion');
        }
        if ((string) $this->input('confirm_import', '') !== 'yes') {
            flash('error', 'Debés confirmar la importación completa a localhost.');
            redirect('/sincronizacion');
        }

        $file = $_FILES['sql_file'] ?? null;
        if (!is_array($file) || !isset($file['tmp_name'], $file['error'])) {
            flash('error', 'No se recibió archivo SQL.');
            redirect('/sincronizacion');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Error al subir archivo SQL.');
            redirect('/sincronizacion');
        }

        $tmpPath = (string) $file['tmp_name'];
        $name = (string) ($file['name'] ?? '');
        if (!str_ends_with(strtolower($name), '.sql')) {
            flash('error', 'El archivo debe tener extensión .sql');
            redirect('/sincronizacion');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        try {
            $local = $this->localDbConfig();
            $this->importSqlFile($local, $tmpPath);
            flash('success', 'Importación completada en localhost.');
        } catch (\Throwable $e) {
            flash('error', 'Error al importar SQL: ' . $e->getMessage());
        }

        redirect('/sincronizacion');
    }

    /** @return array{host:string,database:string,username:string,password:string,charset:string} */
    private function localDbConfig(): array
    {
        $configPath = APP_PATH . '/config/database.php';
        if (!is_file($configPath)) {
            throw new RuntimeException('No se encontró app/config/database.php');
        }
        /** @var array{host:string,database:string,username:string,password:string,charset:string} $cfg */
        $cfg = require $configPath;
        return $cfg;
    }

    /** @param array{host:string,database:string,username:string,password:string,charset:string} $cfg */
    private function dumpDatabaseToFile(array $cfg, string $outputFile): void
    {
        $pdo = new \PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['database'], $cfg['charset'] ?: 'utf8mb4'),
            $cfg['username'],
            $cfg['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
        );

        $sql = "-- Export local LIMPIA OESTE\n";
        $sql .= '-- Fecha: ' . date('Y-m-d H:i:s') . "\n";
        $sql .= '-- Base: ' . $cfg['database'] . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $tableRaw) {
            $table = (string) $tableRaw;
            if ($table === '') {
                continue;
            }
            $safe = str_replace('`', '``', $table);
            $create = $pdo->query("SHOW CREATE TABLE `{$safe}`")->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($create) || !isset($create['Create Table'])) {
                continue;
            }
            $sql .= "DROP TABLE IF EXISTS `{$safe}`;\n";
            $sql .= $create['Create Table'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `{$safe}`")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $colsSql = '`' . implode('`, `', array_map(static fn (string $c): string => str_replace('`', '``', $c), $cols)) . '`';
                $valsSql = implode(', ', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row)));
                $sql .= "INSERT INTO `{$safe}` ({$colsSql}) VALUES ({$valsSql});\n";
            }
            if ($rows !== []) {
                $sql .= "\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        if (file_put_contents($outputFile, $sql) === false) {
            throw new RuntimeException('No se pudo escribir el archivo SQL de exportación.');
        }
    }

    /** @param array{host:string,database:string,username:string,password:string,charset:string} $cfg */
    private function importSqlFile(array $cfg, string $sqlFile): void
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli = new mysqli($cfg['host'], $cfg['username'], $cfg['password']);
        $mysqli->set_charset($cfg['charset'] ?: 'utf8mb4');
        $dbSafe = str_replace('`', '``', $cfg['database']);
        $mysqli->query("CREATE DATABASE IF NOT EXISTS `{$dbSafe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $mysqli->select_db($cfg['database']);
        $this->truncateSchema($mysqli);

        $sql = file_get_contents($sqlFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Archivo SQL vacío o ilegible.');
        }

        if (!$mysqli->multi_query($sql)) {
            throw new RuntimeException($mysqli->error);
        }
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }

    private function truncateSchema(mysqli $mysqli): void
    {
        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        $res = $mysqli->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        if ($res instanceof \mysqli_result) {
            while ($row = $res->fetch_row()) {
                $table = (string) ($row[0] ?? '');
                if ($table === '') {
                    continue;
                }
                $safe = str_replace('`', '``', $table);
                $mysqli->query("DROP TABLE IF EXISTS `{$safe}`");
            }
            $res->free();
        }
        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    }
}


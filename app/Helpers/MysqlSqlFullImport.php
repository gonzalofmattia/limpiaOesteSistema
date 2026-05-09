<?php

declare(strict_types=1);

namespace App\Helpers;

use mysqli;
use RuntimeException;

/**
 * Importación completa de un dump .sql en una base MySQL local:
 * recrea la base, ignora USE/CREATE DATABASE del archivo y detecta errores en multi_query.
 */
final class MysqlSqlFullImport
{
    public static function assertSafeImportDatabaseName(string $database): void
    {
        $deny = ['mysql', 'information_schema', 'performance_schema', 'sys'];
        if (in_array(strtolower($database), $deny, true)) {
            throw new RuntimeException(
                'El nombre de base configurado no permite importación destructiva. Revisá DB_NAME en .env.'
            );
        }
    }

    public static function recreateEmptyDatabase(mysqli $mysqli, string $database): void
    {
        $dbSafe = str_replace('`', '``', $database);
        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        $mysqli->query("DROP DATABASE IF EXISTS `{$dbSafe}`");
        $mysqli->query("CREATE DATABASE `{$dbSafe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$mysqli->select_db($database)) {
            throw new RuntimeException('No se pudo seleccionar la base tras recrearla.');
        }
        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Evita que CREATE DATABASE / USE del dump redirijan los datos a otra base que no lee la app.
     */
    public static function rewriteDumpSqlForTargetDatabase(string $sql, string $targetDatabase): string
    {
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        }

        $sql = preg_replace(
            '/CREATE\s+DATABASE(\s+IF\s+NOT\s+EXISTS)?\s+(?:`[^`]+`|\'[^\']+\'|"[^"]+"|[a-zA-Z0-9_]+)\s*[^;]*;/is',
            '',
            $sql
        ) ?? $sql;

        $sql = preg_replace(
            '/^\s*USE\s+(?:`[^`]+`|\'[^\']+\'|"[^"]+"|[a-zA-Z0-9_]+)\s*;\s*/mi',
            '',
            $sql
        ) ?? $sql;

        $escaped = str_replace('`', '``', $targetDatabase);
        $header = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nUSE `{$escaped}`;\n\n";

        return $header . ltrim($sql);
    }

    public static function drainMultiQueryResults(mysqli $mysqli): void
    {
        do {
            if ($mysqli->errno !== 0) {
                throw new RuntimeException(
                    'Error en sentencia SQL del dump: ' . $mysqli->error . ' (código ' . (string) $mysqli->errno . ')'
                );
            }
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());

        if ($mysqli->errno !== 0) {
            throw new RuntimeException(
                'Error al finalizar el dump: ' . $mysqli->error . ' (código ' . (string) $mysqli->errno . ')'
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseSynchronizer
{
    /**
     * @param array{host:string,database:string,username:string,password:string,charset?:string} $source
     * @param array{host:string,database:string,username:string,password:string,charset?:string} $target
     * @return array{tables:int,rows:int,started_at:string,finished_at:string,source:string,target:string}
     */
    public static function sync(array $source, array $target, ?callable $logger = null): array
    {
        $startedAt = date('Y-m-d H:i:s');
        $sourcePdo = self::connect($source);
        $targetPdo = self::connect($target);

        $tables = self::listTables($sourcePdo);
        if ($tables === []) {
            throw new RuntimeException('No se encontraron tablas para sincronizar.');
        }

        $targetPdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $rowsCopied = 0;
        $tableCount = 0;

        foreach ($tables as $table) {
            $tableCount++;
            self::log($logger, "Sincronizando tabla {$table}...");
            self::recreateTable($sourcePdo, $targetPdo, $table);
            $rowsCopied += self::copyTableData($sourcePdo, $targetPdo, $table);
        }

        $targetPdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $finishedAt = date('Y-m-d H:i:s');

        return [
            'tables' => $tableCount,
            'rows' => $rowsCopied,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'source' => "{$source['host']}/{$source['database']}",
            'target' => "{$target['host']}/{$target['database']}",
        ];
    }

    /** @param array{host:string,database:string,username:string,password:string,charset?:string} $cfg */
    private static function connect(array $cfg): PDO
    {
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['database'],
            $charset
        );
        try {
            return new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a ' . $cfg['host'] . ': ' . $e->getMessage());
        }
    }

    /** @return list<string> */
    private static function listTables(PDO $pdo): array
    {
        $rows = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = (string) ($row[0] ?? '');
        }
        return array_values(array_filter($tables, static fn (string $t): bool => $t !== ''));
    }

    private static function recreateTable(PDO $sourcePdo, PDO $targetPdo, string $table): void
    {
        $safe = str_replace('`', '``', $table);
        $create = $sourcePdo->query("SHOW CREATE TABLE `{$safe}`")->fetch(PDO::FETCH_ASSOC);
        if (!is_array($create) || !isset($create['Create Table'])) {
            throw new RuntimeException("No se pudo obtener CREATE TABLE de {$table}");
        }
        $targetPdo->exec("DROP TABLE IF EXISTS `{$safe}`");
        $targetPdo->exec((string) $create['Create Table']);
    }

    private static function copyTableData(PDO $sourcePdo, PDO $targetPdo, string $table): int
    {
        $safe = str_replace('`', '``', $table);
        $stmt = $sourcePdo->query("SELECT * FROM `{$safe}`");
        $firstRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($firstRow)) {
            return 0;
        }

        $columns = array_keys($firstRow);
        $colSql = '`' . implode('`, `', array_map(static fn (string $c): string => str_replace('`', '``', $c), $columns)) . '`';
        $placeholders = implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns));
        $insertSql = "INSERT INTO `{$safe}` ({$colSql}) VALUES ({$placeholders})";
        $insertStmt = $targetPdo->prepare($insertSql);

        $count = 0;
        self::insertRow($insertStmt, $firstRow, $columns);
        $count++;
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                continue;
            }
            self::insertRow($insertStmt, $row, $columns);
            $count++;
        }
        return $count;
    }

    /** @param list<string> $columns @param array<string,mixed> $row */
    private static function insertRow(\PDOStatement $stmt, array $row, array $columns): void
    {
        $params = [];
        foreach ($columns as $col) {
            $params[$col] = $row[$col] ?? null;
        }
        $stmt->execute($params);
    }

    private static function log(?callable $logger, string $message): void
    {
        if ($logger !== null) {
            $logger($message);
        }
    }
}


<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use PDOStatement;

final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $configPath = dirname(__DIR__) . '/config/database.php';
        if (!is_file($configPath)) {
            throw new \RuntimeException('Falta app/config/database.php. Ejecutá php install.php');
        }
        /** @var array{host:string,database:string,username:string,password:string,charset:string} $c */
        $c = require $configPath;
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $c['host'],
            $c['database'],
            $c['charset']
        );
        $this->pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $st = $this->query($sql, $params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $st = $this->query($sql, $params);
        return $st->fetchColumn($column);
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn ($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            str_replace('`', '``', $table),
            implode('`,`', $cols),
            implode(',', $placeholders)
        );
        $st = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        $st->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(string $table, array $data, string $whereSql, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "`{$col}` = :{$col}";
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            str_replace('`', '``', $table),
            implode(', ', $sets),
            $whereSql
        );
        $st = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        foreach ($whereParams as $k => $v) {
            $p = is_string($k) && str_starts_with($k, ':') ? $k : ':' . ltrim((string) $k, ':');
            $st->bindValue($p, $v);
        }
        $st->execute();
        return $st->rowCount();
    }

    public function delete(string $table, string $whereSql, array $whereParams = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', str_replace('`', '``', $table), $whereSql);
        $st = $this->pdo->prepare($sql);
        $st->execute($whereParams);
        return $st->rowCount();
    }

    public function count(string $table, string $whereSql = '1=1', array $params = []): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', str_replace('`', '``', $table), $whereSql);
        return (int) $this->fetchColumn($sql, $params);
    }
}

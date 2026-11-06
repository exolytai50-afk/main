<?php
/**
 * GigaChefAI — единый слой доступа к БД (PDO).
 * Все запросы к базе выполняются ТОЛЬКО через функции этого файла.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

/**
 * Возвращает singleton-подключение PDO.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, defined('DB_PORT') ? DB_PORT : '3306', DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+03:00'",
        ]);
    } catch (PDOException $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            die('DB connection error: ' . $e->getMessage());
        }
        http_response_code(500);
        die('Ошибка подключения к базе данных');
    }
    return $pdo;
}

/**
 * Выполняет подготовленный запрос и возвращает PDOStatement.
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Одна строка (или null) */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Массив строк */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Одно скалярное значение */
function db_value(string $sql, array $params = [])
{
    $val = db_query($sql, $params)->fetchColumn();
    return $val === false ? null : $val;
}

/** INSERT/UPDATE/DELETE → число затронутых строк */
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/** INSERT → id последней вставленной записи */
function db_insert(string $sql, array $params = []): int
{
    db_query($sql, $params);
    return (int) db()->lastInsertId();
}

/** Транзакция: принимает callable, откатывает при исключении */
function db_transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
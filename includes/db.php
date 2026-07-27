<?php
/**
 * PDO database connection (singleton).
 */
// config.php is deliberately not in version control (it holds credentials).
// Fail with a clear instruction rather than a bare "file not found".
if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    die('<pre style="font:14px monospace;padding:2rem">Configuration missing.'
      . "\n\nCopy includes/config.example.php to includes/config.php"
      . "\nand fill in your database, email and API settings.</pre>");
}
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (APP_ENV === 'dev') {
            http_response_code(500);
            die('<pre style="font:14px monospace;padding:2rem">Database connection failed: '
                . htmlspecialchars($e->getMessage())
                . "\n\nHave you imported sql/schema.sql?\n"
                . 'Check credentials in includes/config.php.</pre>');
        }
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('Service temporarily unavailable. Please call us to book.');
    }

    return $pdo;
}

/** Fetch all rows. */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Fetch a single row (or null). */
function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Execute a write; returns affected row count. */
function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

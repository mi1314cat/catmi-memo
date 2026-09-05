<?php
declare(strict_types=1);

/**
 * 数据库连接（PDO 单例）。
 * 配置读取自 config/config.php（被 .htaccess 保护，浏览器无法直接访问）。
 * 全站一律使用 PDO prepared statements，禁止任何 SQL 字符串拼接用户输入。
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = require dirname(__DIR__) . '/config/config.php';
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        (string)$config['DB_HOST'],
        (string)$config['DB_NAME']
    );
    try {
        $pdo = new PDO($dsn, (string)$config['DB_USER'], (string)$config['DB_PASS'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $exception) {
        http_response_code(500);
        // 不向访客泄露任何数据库细节
        exit('数据库暂时无法连接，请稍后再试。');
    }
    return $pdo;
}

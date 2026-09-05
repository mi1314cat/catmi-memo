<?php
declare(strict_types=1);

/**
 * CSRF 防护：所有 POST 表单都必须携带 csrf_token 字段，服务端用 hash_equals 比对。
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('页面已过期或请求不合法，请返回刷新后重试。');
    }
}

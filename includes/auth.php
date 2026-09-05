<?php
declare(strict_types=1);

// 统一时区：时间线显示、图片目录命名都基于此时区
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

// 基础安全响应头
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

// Session：HttpOnly + SameSite=Lax，HTTPS 下自动加 Secure
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,
    ]);
    session_name('catmi_session');
    session_start();
}

/**
 * 当前登录用户（每请求只查一次库），未登录/被禁用/被删除返回 null。
 * 禁用用户在会话中途也会被立即踢出（服务端校验，不依赖前端）。
 */
function current_user(): ?array
{
    static $cachedUser = false;
    if ($cachedUser !== false) {
        return $cachedUser;
    }
    $cachedUser = null;
    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, username, role, status, avatar, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row && $row['status'] === 'active') {
            $cachedUser = $row;
        } else {
            unset($_SESSION['user_id']); // 账号已删除或已被禁用
        }
    }
    return $cachedUser;
}

function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

/** 未登录直接跳转登录页；已登录返回用户行 */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

/**
 * 管理员专用页面/API 的服务端权限验证。
 * 普通用户访问一律 404，不暴露管理员入口的存在。
 */
function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        http_response_code(404);
        exit('页面不存在。');
    }
    return $user;
}

/**
 * 注册输入校验（不写库）。
 * 返回 null 表示通过，否则返回用户可读的错误信息。
 */
function validate_registration(string $username, string $password): ?string
{
    $username = trim($username);
    if (mb_strlen($username) < 2 || mb_strlen($username) > 20) {
        return '用户名需要 2-20 个字符。';
    }
    if (!preg_match('/^[\p{Han}a-zA-Z0-9_]+$/u', $username)) {
        return '用户名只能包含中文、字母、数字和下划线。';
    }
    if (strlen($password) < 6 || strlen($password) > 72) {
        return '密码长度需要 6-72 个字符。';
    }
    return null;
}

/**
 * 登录校验。
 * 返回：1 登录成功；0 用户名或密码错误；-1 账号已被禁用。
 * 凭据错误与账号禁用分开提示，但都不泄露「用户名是否存在」。
 */
function attempt_login(string $username, string $password): int
{
    $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, (string)$row['password_hash'])) {
        return 0;
    }
    if (($row['status'] ?? 'active') !== 'active') {
        return -1;
    }
    session_regenerate_id(true); // 防会话固定
    $_SESSION['user_id'] = (int)$row['id'];
    return 1;
}

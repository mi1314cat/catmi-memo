<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// 只接受 POST（导航栏是一个小表单按钮），并校验 CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $_SESSION = [];
    session_destroy();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
}
redirect('index.php');

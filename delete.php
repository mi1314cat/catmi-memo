<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);

// 权限检查（防 IDOR）：作者本人或管理员才能删除；否则按不存在处理
$stmt = db()->prepare('SELECT id, user_id FROM posts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post || ((int)$post['user_id'] !== (int)$user['id'] && !is_admin($user))) {
    http_response_code(404);
    exit('没有找到这条动态。');
}

delete_post_absolutely($id);

flash('ok', (int)$post['user_id'] === (int)$user['id'] ? '已删除这条动态。' : '已删除该内容（管理员操作）。');
redirect('index.php');

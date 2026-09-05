<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

// post_max_size 溢出时 PHP 会丢弃整个请求体（$_POST/$_FILES 全空），优雅提示而非 CSRF 报错
if (empty($_POST) && empty($_FILES)) {
    flash('error', '上传内容总大小超过了服务器限制，请减少附件数量或大小后重试。');
    redirect('index.php');
}
verify_csrf();

$content = trim((string)($_POST['content'] ?? ''));
if (mb_strlen($content) > POST_MAX_CHARS) {
    flash('error', '内容太长了（最多 ' . POST_MAX_CHARS . ' 字）。');
    redirect('index.php');
}

// 可见范围：白名单校验，默认「仅自己可见」防误公开
$visibility = ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

[$ok, $error, $images] = process_uploaded_images($_FILES['images'] ?? []);
if (!$ok) {
    flash('error', (string)$error);
    redirect('index.php');
}

[$ok, $error, $attachments] = process_uploaded_attachments($_FILES['attachments'] ?? []);
if (!$ok) {
    cleanup_saved_images($images);
    flash('error', (string)$error);
    redirect('index.php');
}

if ($content === '' && !$images && !$attachments) {
    cleanup_saved_images($images);
    cleanup_saved_images($attachments);
    flash('error', '写点内容，或者选一张图片吧。');
    redirect('index.php');
}

db()->prepare('INSERT INTO posts (user_id, content, visibility, created_at) VALUES (?, ?, ?, ?)')
    ->execute([$user['id'], $content, $visibility, date('Y-m-d H:i:s')]);
$postId = (int)db()->lastInsertId();

$insertImage = db()->prepare(
    'INSERT INTO post_images (post_id, user_id, file_path, original_name, mime_type, file_size)
     VALUES (?, ?, ?, ?, ?, ?)'
);
foreach ($images as $image) {
    $insertImage->execute([
        $postId,
        $user['id'],
        $image['path'],
        $image['original'],
        $image['mime'],
        $image['size'],
    ]);
}

$insertAtt = db()->prepare(
    'INSERT INTO post_attachments (post_id, user_id, kind, original_name, stored_name, mime_type, file_size, file_path, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($attachments as $att) {
    $insertAtt->execute([
        $postId,
        $user['id'],
        $att['kind'],
        $att['original'],
        basename($att['path']),
        $att['mime'],
        $att['size'],
        $att['path'],
        date('Y-m-d H:i:s'),
    ]);
}

flash('ok', $visibility === 'public' ? '已发布到公开动态。' : '已发布（仅自己可见）。');
redirect($visibility === 'public' ? 'public.php' : 'my.php');

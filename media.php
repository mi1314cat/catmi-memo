<?php
declare(strict_types=1);

/**
 * 图片 / 附件统一权限网关。
 *   ?f=<path>   图片（post_images，按路径定位）
 *   ?a=<id>     附件（post_attachments，按 id 定位；视频内联播放，文件下载）
 *   &dl=1       强制以附件形式下载
 * uploads/.htaccess 为 deny-all，所有文件必须经过本网关做可见性校验后流出。
 */
require_once __DIR__ . '/includes/auth.php';

function deny_404(): never
{
    http_response_code(404);
    exit('Not Found');
}

/** 从浏览器可能发来的下载名里剔除危险字符 */
function safe_download_name(string $name): string
{
    $name = str_replace(["\r", "\n", "\0", '"', '\\', '/'], '', $name);
    $name = trim($name);
    return $name !== '' ? mb_substr($name, 0, 200) : 'download';
}

/** 输出文件内容（支持 HTTP Range 单区间，iOS Safari 播放视频必需 206） */
function stream_file(string $file, string $mime, bool $asAttachment, string $downloadName): never
{
    $size = (int)filesize($file);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    if ($asAttachment) {
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $downloadName);
        header("Content-Disposition: attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($downloadName));
    }
    $start = 0;
    $end = $size - 1;
    if (!$asAttachment && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', (string)$_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') {
            $start = (int)$m[1];
        } elseif ($m[2] !== '') {
            $start = max(0, $size - (int)$m[2]); // 后缀区间 bytes=-500
        }
        if ($m[2] !== '') {
            $end = min($end, (int)$m[2]);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    $length = $end - $start + 1;
    header('Content-Length: ' . $length);
    header_remove('X-Frame-Options');
    $fh = @fopen($file, 'rb');
    if ($fh === false) {
        deny_404();
    }
    fseek($fh, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($fh)) {
        $chunk = fread($fh, min(65536, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($fh);
    exit;
}

$user = current_user();
$guestView = get_setting('guest_view_public', '') === '1';

/* ---------- 附件（视频 / 文件） ---------- */
if (isset($_GET['a'])) {
    $stmt = db()->prepare(
        'SELECT pa.file_path, pa.mime_type, pa.original_name, pa.kind, pa.file_size, pa.user_id,
                p.visibility, p.user_id AS post_owner
         FROM post_attachments pa JOIN posts p ON p.id = pa.post_id
         WHERE pa.id = ? LIMIT 1'
    );
    $stmt->execute([(int)$_GET['a']]);
    $att = $stmt->fetch();
    if (!$att) {
        deny_404();
    }
    // 游客仅在站长开启 guest_view_public 时可看公开附件；登录用户走统一权限模型
    $allowed = ($guestView && $att['visibility'] === 'public')
        || ($user !== null && can_view_post(['visibility' => $att['visibility'], 'user_id' => (int)$att['post_owner']], $user))
        || (int)$att['user_id'] === (int)($user['id'] ?? 0);
    if (!$allowed) {
        deny_404();
    }
    $file = app_root() . '/uploads/' . $att['file_path'];
    if (!is_file($file)) {
        deny_404();
    }
    $mime = (string)$att['mime_type'];
    if ($att['kind'] === 'video' && !isset($_GET['dl'])) {
        stream_file($file, $mime, false, '');
    }
    stream_file($file, 'application/octet-stream', true, safe_download_name((string)$att['original_name']));
}

/* ---------- 图片 ---------- */
$f = (string)($_GET['f'] ?? '');
if ($f === '' || strpos($f, '..') !== false || !preg_match('/^[0-9]{4}\/[0-9]{2}\/[A-Za-z0-9._\-]+$/', $f)) {
    deny_404();
}
$stmt = db()->prepare(
    'SELECT pi.file_path, p.visibility, p.user_id AS post_owner
     FROM post_images pi JOIN posts p ON p.id = pi.post_id
     WHERE pi.file_path = ? LIMIT 1'
);
$stmt->execute([$f]);
$image = $stmt->fetch();
if (!$image) {
    deny_404();
}
$allowed = ($guestView && $image['visibility'] === 'public')
    || ($user !== null && can_view_post(['visibility' => $image['visibility'], 'user_id' => (int)$image['post_owner']], $user))
    || (int)$image['post_owner'] === (int)($user['id'] ?? 0);
if (!$allowed) {
    deny_404();
}
$file = app_root() . '/uploads/' . $image['file_path'];
if (!is_file($file)) {
    deny_404();
}
$mime = (string)@getimagesize($file)['mime'];
if ($mime === '') {
    deny_404();
}
stream_file($file, $mime, false, '');

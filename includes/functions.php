<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

const UPLOAD_MAX_BYTES = 5242880;  // 单张图片上限：5MB
const UPLOAD_MAX_COUNT = 9;        // 每条动态图片上限
const POST_MAX_CHARS   = 5000;     // 正文长度上限
const PAGE_SIZE        = 20;       // 时间线分页大小

/**
 * 网站文件系统根目录（htdocs）。
 * includes/ 在根目录下一级，因此恒为 dirname(__DIR__)。
 * 所有"根目录下的文件/目录"路径一律经由本函数拼装，杜绝 dirname 方向写错。
 */
function app_root(): string
{
    return dirname(__DIR__);
}

/* ==================== 配置与限制 ==================== */

/** 读取 config/config.php 中的配置项（每请求缓存一次） */
function site_config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = [];
        $file = app_root() . '/config/config.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }
    }
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

/** 把 PHP ini 的 8M/1G 之类写法转成字节 */
function ini_bytes(string $key): int
{
    $raw = trim((string)ini_get($key));
    if ($raw === '' || $raw === '-1') {
        return 0;
    }
    $unit = strtolower(substr($raw, -1));
    $num = (float)$raw;
    return (int)match ($unit) {
        'g' => $num * 1073741824,
        'm' => $num * 1048576,
        'k' => $num * 1024,
        default => $num,
    };
}

/**
 * 有效大小限制 = min(配置值, upload_max_filesize, post_max_size - 表单开销)。
 * 部署者把配置写大也不会突破主机限制，只会按有效值拒绝。
 */
function effective_limit(string $key, int $default): int
{
    $limit = (int)site_config($key, $default);
    if ($limit < 1024) {
        $limit = $default;
    }
    $postMax = ini_bytes('post_max_size');
    if ($postMax > 0) {
        $limit = min($limit, max(1024, $postMax - 262144));
    }
    $upMax = ini_bytes('upload_max_filesize');
    if ($upMax > 0) {
        $limit = min($limit, $upMax);
    }
    return $limit;
}

function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/** 每条动态附件（视频+文件）上限 */
function max_attachments_per_post(): int
{
    return max(1, (int)site_config('MAX_ATTACHMENTS_PER_POST', 5));
}

/* ==================== 头像 ==================== */

const AVATAR_PIXELS = 256; // 服务端裁剪输出尺寸（GD 可用时）

/** 用户头像 HTML：有头像用图片（object-fit 圆形裁切），无头像用首字母色块，绝不出现碎图 */
function user_avatar_html(?string $avatarPath, string $username, string $extraClass = ''): string
{
    if ($avatarPath !== null && $avatarPath !== '') {
        return '<img class="avatar avatar-img' . ($extraClass !== '' ? ' ' . e($extraClass) : '') . '" src="/' . e($avatarPath) . '" alt="' . e($username) . '" loading="lazy">';
    }
    return '<span class="avatar' . ($extraClass !== '' ? ' ' . e($extraClass) : '') . '" style="background:' . e(avatar_color($username)) . '">' . e(mb_substr($username, 0, 1)) . '</span>';
}

/**
 * 头像上传处理：JPG/PNG/WebP ≤ MAX_AVATAR_SIZE，随机文件名，
 * GD 可用时服务端中心裁剪成正方形并缩放到 AVATAR_PIXELS；GD 不可用则保留原图（前端 object-fit 兜底）。
 * 返回 [bool ok, ?string error, ?string relativePath]
 */
function process_avatar_upload(array $file): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return [false, upload_error_text($error), null];
    }
    $limit = effective_limit('MAX_AVATAR_SIZE', 2097152);
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $limit) {
        return [false, '头像不能超过 ' . human_size($limit) . '。', null];
    }
    $info = @getimagesize((string)$file['tmp_name']);
    $mime = (string)($info['mime'] ?? '');
    $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($info === false || !isset($extByMime[$mime])) {
        return [false, '头像只支持 JPG / PNG / WebP 图片。', null];
    }
    $dir = app_root() . '/assets/avatars';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [false, '无法创建头像目录，请联系站长检查权限。', null];
    }
    $name = 'av_' . date('d_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extByMime[$mime];
    $target = $dir . '/' . $name;

    $saved = false;
    if ($info !== false && function_exists('imagecreatetruecolor')) {
        // GD 中心裁剪 → 正方形 → 缩放；WebP 源需要 imagecreatefromwebp（不可用则走原图分支）
        $src = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg((string)$file['tmp_name']) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng((string)$file['tmp_name']) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp((string)$file['tmp_name']) : false,
            default => false,
        };
        if ($src !== false && $src !== null) {
            $w = imagesx($src);
            $h = imagesy($src);
            $side = min($w, $h);
            $dst = imagecreatetruecolor(AVATAR_PIXELS, AVATAR_PIXELS);
            // PNG/WebP 保留透明度
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, AVATAR_PIXELS, AVATAR_PIXELS, $transparent);
            imagecopyresampled(
                $dst, $src,
                0, 0,
                (int)(($w - $side) / 2), (int)(($h - $side) / 2),
                AVATAR_PIXELS, AVATAR_PIXELS,
                $side, $side
            );
            $saved = match ($extByMime[$mime]) {
                'jpg' => imagejpeg($dst, $target, 88),
                'png' => imagepng($dst, $target, 6),
                'webp' => function_exists('imagewebp') ? imagewebp($dst, $target, 88) : false,
                default => false,
            };
            imagedestroy($dst);
            imagedestroy($src);
        }
    }
    if (!$saved) {
        // GD 不可用或处理失败：保存原图（前端 object-fit: cover 保证圆形显示）
        if (!@move_uploaded_file((string)$file['tmp_name'], $target)) {
            return [false, '头像保存失败，请重试。', null];
        }
    }
    @chmod($target, 0644);
    return [true, null, 'assets/avatars/' . $name];
}

/** 删除磁盘上的头像文件（相对站点根的路径） */
function delete_avatar_file(string $relativePath): void
{
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return;
    }
    @unlink(app_root() . '/' . $relativePath);
}

/* ==================== 附件（视频 / 文件） ==================== */

/** 附件 MIME 白名单：视频内联播放，文件仅提供下载 */
const ATTACHMENT_VIDEO_MIMES = [
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
];
const ATTACHMENT_FILE_MIMES = [
    'application/pdf'               => 'pdf',
    'text/plain'                    => 'txt',
    'text/csv'                      => 'csv',
    'application/zip'               => 'zip',
    'application/x-zip-compressed'  => 'zip',
];

function attachment_url(int $id): string
{
    return '/media.php?a=' . $id;
}

/**
 * 附件（视频 / 文件）上传处理。
 * 安全链：错误码 → finfo 真实 MIME 检测（不信任扩展名）→ 白名单分类 →
 *         按类别大小上限校验 → 随机文件名 + uploads/att/年/月/。
 * 返回 [bool ok, ?string error, array saved]；saved 元素含 kind/mime/path/original/size。
 */
function process_uploaded_attachments(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [true, null, []];
    }
    $total = count($files['name']);
    if ($total === 0) {
        return [true, null, []];
    }
    $maxCount = max_attachments_per_post();
    // 只统计真实选择的文件：合并多个输入时，未选择的输入会带一个 NO_FILE 条目
    $filled = 0;
    foreach ($files['error'] as $err) {
        if ((int)$err !== UPLOAD_ERR_NO_FILE) {
            $filled++;
        }
    }
    if ($filled > $maxCount) {
        return [false, '每条动态最多 ' . $maxCount . ' 个附件（视频+文件）。', []];
    }
    if (!function_exists('finfo_open')) {
        return [false, '服务器缺少 fileinfo 扩展，无法安全校验附件类型，已拒绝上传。', []];
    }
    $videoLimit = effective_limit('MAX_VIDEO_SIZE', 8388608);
    $fileLimit = effective_limit('MAX_FILE_SIZE', 8388608);
    $saved = [];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $total; $i++) {
        $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            cleanup_saved_images($saved);
            finfo_close($finfo);
            return [false, upload_error_text($error), []];
        }
        $tmpPath = (string)$files['tmp_name'][$i];
        $size = (int)($files['size'][$i] ?? 0);
        // finfo 检测真实内容类型（改扩展名无法绕过）
        $mime = (string)finfo_file($finfo, $tmpPath);
        if (isset(ATTACHMENT_VIDEO_MIMES[$mime])) {
            $kind = 'video';
            $ext = ATTACHMENT_VIDEO_MIMES[$mime];
            $limit = $videoLimit;
            $label = '视频';
        } elseif (isset(ATTACHMENT_FILE_MIMES[$mime])) {
            $kind = 'file';
            $ext = ATTACHMENT_FILE_MIMES[$mime];
            $limit = $fileLimit;
            $label = '文件';
        } else {
            cleanup_saved_images($saved);
            finfo_close($finfo);
            return [false, '不支持的附件类型（' . ($mime !== '' ? e($mime) : '未知') . '）。视频支持 MP4/WebM，文件支持 PDF/TXT/CSV/ZIP。', []];
        }
        if ($size <= 0 || $size > $limit) {
            cleanup_saved_images($saved);
            finfo_close($finfo);
            return [false, $label . '过大，当前最大允许 ' . human_size($limit) . '。', []];
        }
        $subDir = 'att/' . date('Y') . '/' . date('m');
        $targetDir = app_root() . '/uploads/' . $subDir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            cleanup_saved_images($saved);
            finfo_close($finfo);
            return [false, '服务器无法创建上传目录，请联系站长检查权限。', []];
        }
        $fileName = date('d_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!@move_uploaded_file($tmpPath, $targetDir . '/' . $fileName)) {
            cleanup_saved_images($saved);
            finfo_close($finfo);
            return [false, '附件保存失败，请重试。', []];
        }
        @chmod($targetDir . '/' . $fileName, 0644);
        $saved[] = [
            'kind'     => $kind,
            'mime'     => $mime,
            'path'     => $subDir . '/' . $fileName,
            'original' => mb_substr((string)($files['name'][$i] ?? ''), 0, 200),
            'size'     => $size,
        ];
    }
    finfo_close($finfo);
    return [true, null, $saved];
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** 用用户名生成稳定的头像底色 */
function avatar_color(string $name): string
{
    $palette = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ef4444', '#14b8a6', '#f97316', '#6366f1'];
    return $palette[abs(crc32($name)) % count($palette)];
}

/** 时间线时间显示：今天 19:30 / 昨天 08:12 / 9月1日 12:00 / 2025年3月1日 10:00 */
function display_time(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $todayStart = strtotime('today');
    if ($ts >= $todayStart) {
        return '今天 ' . date('H:i', $ts);
    }
    if ($ts >= $todayStart - 86400) {
        return '昨天 ' . date('H:i', $ts);
    }
    if (date('Y', $ts) === date('Y')) {
        return date('n月j日 H:i', $ts);
    }
    return date('Y年n月j日 H:i', $ts);
}

/* ==================== 站点设置 ==================== */

/** 读取设置（每请求缓存一次；site_settings 表不可用时安静回退默认值） */
function get_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (PDOException $exception) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}

/* ==================== 可见性与查询 ==================== */

/** 图片一律经 media.php 鉴权输出，绝不直接暴露 uploads/ 物理路径（站点根部署，用绝对路径） */
function media_url(string $path): string
{
    return '/media.php?f=' . rawurlencode($path);
}

/** 单条动态的可见性判断（服务端权限模型的核心） */
function can_view_post(array $post, ?array $user): bool
{
    if (($post['visibility'] ?? 'public') === 'public') {
        return true; // 调用方需自行保证登录态（本站整体在登录墙内）
    }
    if ($user === null) {
        return false;
    }
    return (int)$post['user_id'] === (int)$user['id'] || $user['role'] === 'admin';
}

function attach_media(array $posts): array
{
    if (!$posts) {
        return $posts;
    }
    $ids = array_map('intval', array_column($posts, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT id, post_id, file_path FROM post_images WHERE post_id IN ($placeholders) ORDER BY id"
    );
    $stmt->execute($ids);
    $byPost = [];
    foreach ($stmt->fetchAll() as $image) {
        $byPost[(int)$image['post_id']]['images'][] = $image;
    }
    $stmt = db()->prepare(
        "SELECT id, post_id, kind, original_name, file_size FROM post_attachments WHERE post_id IN ($placeholders) ORDER BY id"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $att) {
        $byPost[(int)$att['post_id']]['attachments'][] = $att;
    }
    foreach ($posts as &$post) {
        $post['images'] = $byPost[(int)$post['id']]['images'] ?? [];
        $post['attachments'] = $byPost[(int)$post['id']]['attachments'] ?? [];
    }
    unset($post);
    return $posts;
}

/** 公开动态时间线（含未登录访客视图；自动排除被禁用用户的内容） */
function fetch_public_posts(int $limit = PAGE_SIZE, int $offset = 0): array
{
    $stmt = db()->prepare(
        "SELECT p.id, p.user_id, p.content, p.visibility, p.created_at, p.updated_at, u.username, u.avatar
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.visibility = 'public' AND u.status = 'active'
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return attach_media($stmt->fetchAll());
}

/** 某用户的动态（仅作者本人或管理员查询他人时使用；页面层负责权限） */
function fetch_user_posts(int $userId, ?string $visibility = null, int $limit = PAGE_SIZE, int $offset = 0): array
{
    $sql = 'SELECT p.id, p.user_id, p.content, p.visibility, p.created_at, p.updated_at, u.username, u.avatar
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.user_id = ?';
    $params = [$userId];
    if ($visibility === 'public' || $visibility === 'private') {
        $sql .= ' AND p.visibility = ?';
        $params[] = $visibility;
    }
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?';
    $stmt = db()->prepare($sql);
    foreach (array_values($params) as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return attach_media($stmt->fetchAll());
}

function count_user_posts(int $userId, ?string $visibility = null): int
{
    $sql = 'SELECT COUNT(*) FROM posts WHERE user_id = ?';
    $params = [$userId];
    if ($visibility === 'public' || $visibility === 'private') {
        $sql .= ' AND visibility = ?';
        $params[] = $visibility;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function count_public_posts(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM posts WHERE visibility = 'public'")->fetchColumn();
}

function count_all_users(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function count_user_images(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM post_images WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/* ==================== 删除与上传 ==================== */

/** 删除 uploads/ 下的相对路径文件（带路径穿越防护） */
function delete_upload_file(string $relativePath): void
{
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return;
    }
    @unlink(app_root() . '/uploads/' . $relativePath);
}

/** 删除某条动态的全部图片文件（数据库行随后由外键级联清理） */
function delete_post_image_files(int $postId): void
{
    $stmt = db()->prepare('SELECT file_path FROM post_images WHERE post_id = ?');
    $stmt->execute([$postId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        delete_upload_file((string)$path);
    }
}

/** 彻底删除一条动态（图片 + 附件 + 数据库行）；调用方负责权限检查 */
function delete_post_absolutely(int $postId): void
{
    delete_post_image_files($postId);
    $stmt = db()->prepare('SELECT file_path FROM post_attachments WHERE post_id = ?');
    $stmt->execute([$postId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        delete_upload_file((string)$path);
    }
    db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);
}

/** 删除某用户的全部图片与附件文件（删号前调用；帖子行由外键级联清理） */
function delete_user_image_files(int $userId): void
{
    $stmt = db()->prepare(
        'SELECT pi.file_path FROM post_images pi JOIN posts p ON p.id = pi.post_id WHERE p.user_id = ?
         UNION
         SELECT pa.file_path FROM post_attachments pa WHERE pa.user_id = ?'
    );
    $stmt->execute([$userId, $userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        delete_upload_file((string)$path);
    }
}

/** 清理已保存到磁盘但最终校验失败的图片（防孤儿文件） */
function cleanup_saved_images(array $images): void
{
    foreach ($images as $image) {
        if (isset($image['path'])) {
            delete_upload_file((string)$image['path']);
        }
    }
}

function upload_error_text(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return '单个文件超过了服务器上传限制。';
        case UPLOAD_ERR_PARTIAL:
            return '文件上传不完整，请重试。';
        case UPLOAD_ERR_NO_FILE:
            return '没有选择文件。';
        default:
            return '文件上传失败，请重试。';
    }
}

/**
 * 处理一组上传图片，返回 [bool ok, ?string error, array savedImages]
 *
 * 服务端校验链：
 *   1. 检查 $_FILES 上传错误码
 *   2. 检查文件大小（5MB 上限）
 *   3. getimagesize() 解析真实图片内容 —— 不信任扩展名和表单 MIME
 *   4. 按校验出的 MIME 走白名单扩展名（jpg/png/gif/webp），拒绝 PHP/JS/HTML/SVG 等
 *   5. 随机文件名 + 年/月子目录，绝不使用用户原始文件名
 */
function process_uploaded_images(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [true, null, []];
    }
    $total = count($files['name']);
    $maxImages = max(1, (int)site_config('MAX_IMAGES_PER_POST', UPLOAD_MAX_COUNT));
    if ($total > $maxImages) {
        return [false, '一次最多上传 ' . $maxImages . ' 张图片。', []];
    }
    $imageLimit = effective_limit('MAX_IMAGE_SIZE', UPLOAD_MAX_BYTES);
    // MIME -> 扩展名白名单（命中即白名单，其余一律拒绝）
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $saved = [];
    for ($i = 0; $i < $total; $i++) {
        $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            cleanup_saved_images($saved);
            return [false, upload_error_text($error), []];
        }
        $tmpPath = (string)$files['tmp_name'][$i];
        $size = (int)($files['size'][$i] ?? 0);
        if ($size <= 0 || $size > $imageLimit) {
            cleanup_saved_images($saved);
            return [false, '单张图片不能超过 ' . human_size($imageLimit) . '。', []];
        }
        // getimagesize 直接解析图片魔数与结构，伪造的内容通不过
        $info = @getimagesize($tmpPath);
        if ($info === false) {
            cleanup_saved_images($saved);
            return [false, '只支持真实的 JPG / PNG / GIF / WebP 图片。', []];
        }
        $mime = (string)($info['mime'] ?? '');
        if (!isset($allowedMimes[$mime])) {
            cleanup_saved_images($saved);
            return [false, '只支持 JPG / PNG / GIF / WebP 图片。', []];
        }
        $subDir = date('Y') . '/' . date('m');
        $targetDir = app_root() . '/uploads/' . $subDir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            cleanup_saved_images($saved);
            return [false, '服务器无法创建上传目录，请联系站长检查权限。', []];
        }
        // 随机文件名：日期前缀 + 16 字节随机数
        $fileName = date('d_His') . '_' . bin2hex(random_bytes(8)) . '.' . $allowedMimes[$mime];
        $targetPath = $targetDir . '/' . $fileName;
        if (!@move_uploaded_file($tmpPath, $targetPath)) {
            cleanup_saved_images($saved);
            return [false, '图片保存失败，请重试。', []];
        }
        @chmod($targetPath, 0644);
        $saved[] = [
            'path'     => $subDir . '/' . $fileName,
            'mime'     => $mime,
            'size'     => $size,
            'original' => mb_substr((string)($files['name'][$i] ?? ''), 0, 250),
        ];
    }
    return [true, null, $saved];
}

/* ==================== 邀请码 ==================== */

function generate_invite_code(): string
{
    return 'CATMI-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * 创建邀请码。返回 [bool ok, ?string error, ?string code]
 * $expiresAt 为 NULL 表示永不过期。
 */
function create_invite_code(int $creatorId, int $maxUses, ?string $expiresAt, ?string $customCode = null): array
{
    if ($customCode !== null && $customCode !== '') {
        $code = strtoupper((string)preg_replace('/[^A-Za-z0-9\-]/', '', $customCode));
    } else {
        $code = generate_invite_code();
    }
    if (strlen($code) < 4 || strlen($code) > 32) {
        return [false, '自定义邀请码只能是 4-32 位（字母/数字/短横线）。', null];
    }
    if ($maxUses < 1 || $maxUses > 999) {
        return [false, '最大使用次数需要在 1-999 之间。', null];
    }
    try {
        db()->prepare('INSERT INTO invite_codes (code, creator_id, max_uses, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$code, $creatorId, $maxUses, $expiresAt]);
    } catch (PDOException $exception) {
        return [false, '这个邀请码已经存在，换一个吧。', null];
    }
    return [true, null, $code];
}

/**
 * 原子消费邀请码（服务端唯一验证入口）。
 * 仅当：存在 + 启用 + 未过期 + 未用完 时 used_count+1 并返回 true。
 * 基于 UPDATE 行数判断，天然防并发抢码。
 */
function consume_invite_code(string $code): bool
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return false;
    }
    $stmt = db()->prepare(
        'UPDATE invite_codes SET used_count = used_count + 1
         WHERE code = ? AND enabled = 1 AND used_count < max_uses
           AND (expires_at IS NULL OR expires_at > ?)'
    );
    $stmt->execute([$code, date('Y-m-d H:i:s')]);
    return $stmt->rowCount() === 1;
}

/* ==================== 时间线卡片渲染（多页共用） ==================== */

/**
 * 渲染一条动态卡片（信息层级：作者 → 时间·可见性 → 正文 → 图片 → 操作）。
 * 编辑仅作者可见；删除作者或管理员可见；操作用低视觉权重的文字按钮。
 */
function render_memo_card(array $post, array $currentUser): void
{
    $isOwner = (int)$post['user_id'] === (int)$currentUser['id'];
    $isAdmin = ($currentUser['role'] ?? '') === 'admin';
    $canEdit = $isOwner;
    $canDelete = $isOwner || $isAdmin;
    $isPrivate = ($post['visibility'] ?? 'public') === 'private';
    ?>
    <article class="card memo">
      <header class="memo-head">
        <?= user_avatar_html($post['avatar'] ?? '', $post['username']) ?>
        <div class="memo-meta">
          <span class="memo-author"><?= e($post['username']) ?></span>
          <span class="memo-sub">
            <time title="<?= e($post['created_at']) ?>"><?= e(display_time($post['created_at'])) ?></time>
            <span class="dot">·</span>
            <span class="memo-vis<?= $isPrivate ? ' is-private' : ' is-public' ?>"><?= $isPrivate ? '仅自己可见' : '公开' ?></span>
            <?php if (!empty($post['updated_at'])): ?><span class="dot">·</span><span>已编辑</span><?php endif; ?>
          </span>
        </div>
      </header>
      <?php if ($post['content'] !== ''): ?>
      <div class="memo-content"><?= nl2br(e($post['content'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($post['images'])): ?>
      <div class="image-grid img-count-<?= min(count($post['images']), 4) ?>">
        <?php foreach ($post['images'] as $image): ?>
        <a class="image-cell" href="<?= e(media_url($image['file_path'])) ?>" data-lightbox>
          <img src="<?= e(media_url($image['file_path'])) ?>" alt="动态图片" loading="lazy">
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($post['attachments'])): ?>
      <div class="attachment-list">
        <?php foreach ($post['attachments'] as $att): ?>
          <?php if ($att['kind'] === 'video'): ?>
          <video class="memo-video" controls preload="metadata" src="<?= e(attachment_url((int)$att['id'])) ?>"></video>
          <?php else: ?>
          <a class="file-chip" href="<?= e(attachment_url((int)$att['id'])) ?>&dl=1" download="<?= e($att['original_name']) ?>">
            <span class="file-chip-icon">📎</span>
            <span class="file-chip-name" title="<?= e($att['original_name']) ?>"><?= e($att['original_name']) ?></span>
            <span class="file-chip-size"><?= e(human_size((int)$att['file_size'])) ?></span>
            <span class="file-chip-dl">下载</span>
          </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($canEdit || $canDelete): ?>
      <footer class="memo-actions">
        <?php if ($canEdit): ?>
        <a class="btn-text" href="/edit.php?id=<?= (int)$post['id'] ?>">编辑</a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
        <form method="post" action="/delete.php" class="inline-form" data-confirm="确定删除这条动态吗？删除后无法恢复。<?= (!$isOwner && $isAdmin) ? '（管理员删除他人的内容）' : '' ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
          <button class="btn-text btn-text-danger" type="submit">删除</button>
        </form>
        <?php endif; ?>
      </footer>
      <?php endif; ?>
    </article>
    <?php
}

/** 简单分页条 */
function render_pager(int $total, int $page, string $baseQuery): void
{
    $pages = max(1, (int)ceil($total / PAGE_SIZE));
    if ($pages <= 1) {
        return;
    }
    ?>
    <div class="pager">
      <?php if ($page > 1): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e($baseQuery . 'page=' . ($page - 1)) ?>">← 上一页</a>
      <?php endif; ?>
      <span class="pager-info">第 <?= $page ?> / <?= $pages ?> 页 · 共 <?= $total ?> 条</span>
      <?php if ($page < $pages): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e($baseQuery . 'page=' . ($page + 1)) ?>">下一页 →</a>
      <?php endif; ?>
    </div>
    <?php
}

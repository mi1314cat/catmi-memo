<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

/* ---------- 设置保存 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        // 背景图片上传（真实内容校验 + 随机文件名，与动态图片同一套安全标准）
        // 上传了新图时优先使用新图；只有未上传时才处理「路径/清空」输入
        $uploadedApplied = false;
        $uploaded = $_FILES['bg_file'] ?? null;
        if (is_array($uploaded) && ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $error = (int)($uploaded['error'] ?? UPLOAD_ERR_NO_FILE);
            $size = (int)($uploaded['size'] ?? 0);
            if ($error !== UPLOAD_ERR_OK) {
                flash('error', upload_error_text($error));
                redirect('/admin/settings.php');
            }
            if ($size <= 0 || $size > 10 * 1024 * 1024) {
                flash('error', '背景图片不能超过 10MB。');
                redirect('/admin/settings.php');
            }
            $info = @getimagesize((string)$uploaded['tmp_name']);
            $mime = (string)($info['mime'] ?? '');
            if ($info === false || !isset($allowedMimes[$mime])) {
                flash('error', '背景必须是真实的 JPG / PNG / GIF / WebP 图片。');
                redirect('/admin/settings.php');
            }
            $bgDir = app_root() . '/assets/bg';
            if (!is_dir($bgDir) && !@mkdir($bgDir, 0755, true)) {
                flash('error', '无法创建 assets/bg 目录，请检查权限。');
                redirect('/admin/settings.php');
            }
            $name = 'bg_' . date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedMimes[$mime];
            if (!@move_uploaded_file((string)$uploaded['tmp_name'], $bgDir . '/' . $name)) {
                flash('error', '背景图片保存失败，请重试。');
                redirect('/admin/settings.php');
            }
            set_setting('background_image', 'assets/bg/' . $name);
            flash('ok', '背景图片已更新。');
            $uploadedApplied = true;
        }

        // 背景路径手动填写（也可以 FTP 直接覆盖 assets/background.jpg 后填这个路径）
        if (!$uploadedApplied && isset($_POST['bg_path'])) {
            $bgPath = trim((string)$_POST['bg_path']);
            $bgPath = ltrim($bgPath, '/'); // 统一存站点根相对路径，避免 url('//...') 双斜杠
            if ($bgPath !== '' && (!preg_match('/^[A-Za-z0-9_\-\.\/]+$/', $bgPath) || strpos($bgPath, '..') !== false)) {
                flash('error', '背景路径格式不合法。');
                redirect('/admin/settings.php');
            }
            if ($bgPath !== '' && !is_file(app_root() . '/' . $bgPath)) {
                flash('error', '文件不存在：' . $bgPath);
                redirect('/admin/settings.php');
            }
            set_setting('background_image', $bgPath);
            flash('ok', $bgPath === '' ? '已恢复默认背景。' : '背景图片已设置为 ' . $bgPath);
        }

        // 游客可见公开动态开关
        set_setting('guest_view_public', isset($_POST['guest_view_public']) ? '1' : '0');
        flash('ok', '设置已保存。');
        redirect('/admin/settings.php');
    }
    redirect('/admin/settings.php');
}

$bgImage = get_setting('background_image', '');
$bgImageDisplay = $bgImage !== '' ? ltrim($bgImage, '/') : '';
$guestView = get_setting('guest_view_public', '0') === '1';

$adminCurrent = 'settings.php';
$pageTitle = '网站设置';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>🖼 网站设置</h1>
</section>
<?php require __DIR__ . '/tabs.php'; ?>

<section class="card">
  <h2 class="section-title">背景图片</h2>
  <p class="page-sub">当前背景：<?= $bgImage !== '' ? '<code>' . e($bgImageDisplay) . '</code>' : '默认渐变（未设置）' ?></p>
  <?php if ($bgImage !== ''): ?>
  <div class="bg-preview" style="background-image:url('/<?= e($bgImageDisplay) ?>')"></div>
  <?php endif; ?>
  <form method="post" action="/admin/settings.php" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <label>上传新背景图片（JPG / PNG / GIF / WebP，≤10MB，保存后立即生效）
      <input type="file" name="bg_file" accept="image/jpeg,image/png,image/gif,image/webp">
    </label>
    <label>或填写站点内图片路径（如 assets/background.jpg，留空 = 恢复默认渐变）
      <input type="text" name="bg_path" value="<?= e($bgImageDisplay) ?>" maxlength="255" placeholder="assets/background.jpg">
    </label>
    <label class="check-line">
      <input type="checkbox" name="guest_view_public" value="1" <?= $guestView ? 'checked' : '' ?>>
      允许未登录访客浏览「公开动态」（默认关闭，站点整体保持私密）
    </label>
    <button class="btn btn-primary" type="submit">保存设置</button>
  </form>
  <p class="page-sub tip-line">提示：也可以用 FTP 把图片上传为 <code>htdocs/assets/background.jpg</code>，然后在这里把路径填为 <code>assets/background.jpg</code>。保存后刷新前台立即生效。</p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

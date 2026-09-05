<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$error = null;

/* ---------- 头像：上传 / 更换 / 删除 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_avatar') {
    [$ok, $error, $avatarPath] = process_avatar_upload($_FILES['avatar_file'] ?? []);
    if (!$ok) {
        flash('error', (string)$error);
        redirect('profile.php');
    }
    // 先写库再删旧文件，避免中途失败出现"无头像可显示"
    $old = (string)$user['avatar'];
    db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$avatarPath, $user['id']]);
    if ($old !== '') {
        delete_avatar_file($old);
    }
    flash('ok', '头像已更新。');
    redirect('profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_avatar') {
    $old = (string)$user['avatar'];
    db()->prepare("UPDATE users SET avatar = '' WHERE id = ?")->execute([$user['id']]);
    delete_avatar_file($old);
    flash('ok', '头像已删除，恢复为默认。');
    redirect('profile.php');
}

/* ---------- 修改密码 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $oldPassword = (string)($_POST['old_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirm     = (string)($_POST['new_password_confirm'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($oldPassword, (string)$row['password_hash'])) {
        $error = '当前密码不正确。';
    } elseif (strlen($newPassword) < 6 || strlen($newPassword) > 72) {
        $error = '新密码长度需要 6-72 个字符。';
    } elseif ($newPassword !== $confirm) {
        $error = '两次输入的新密码不一致。';
    } elseif (password_verify($newPassword, (string)$row['password_hash'])) {
        $error = '新密码不能和当前密码相同。';
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        flash('ok', '密码已更新。');
        redirect('profile.php');
    }
}

$pageTitle = '账户资料';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <h1>👤 账户资料</h1>
</section>

<section class="card">
  <div class="profile-grid">
    <?= user_avatar_html((string)$user['avatar'], (string)$user['username'], 'avatar-lg') ?>
    <div class="profile-info">
      <div class="profile-name"><?= e($user['username']) ?>
        <?php if ($user['role'] === 'admin'): ?><span class="chip chip-admin">管理员</span><?php endif; ?>
      </div>
      <div class="profile-meta">
        注册于 <?= e(display_time((string)$user['created_at'])) ?> ·
        <?= count_user_posts((int)$user['id']) ?> 条动态 ·
        <?= count_user_images((int)$user['id']) ?> 张图片
      </div>
    </div>
  </div>

  <div class="avatar-manager">
    <form method="post" action="profile.php" enctype="multipart/form-data" class="avatar-form js-avatar-form"
          data-max="<?= effective_limit('MAX_AVATAR_SIZE', 2097152) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_avatar">
      <label class="btn btn-ghost file-label">🖼 上传新头像
        <input type="file" class="js-avatar-input" name="avatar_file"
               accept="image/jpeg,image/png,image/webp" hidden>
      </label>
      <span class="avatar-hint">JPG / PNG / WebP · 最大 <?= e(human_size(effective_limit('MAX_AVATAR_SIZE', 2097152))) ?></span>
      <div class="js-avatar-preview avatar-preview"></div>
      <p class="composer-error js-form-error" hidden></p>
    </form>
    <?php if ((string)$user['avatar'] !== ''): ?>
    <form method="post" action="profile.php" class="inline-form" data-confirm="确定删除头像吗？将恢复为默认头像。">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_avatar">
      <button class="btn-text btn-text-danger" type="submit">删除头像</button>
    </form>
    <?php endif; ?>
  </div>
</section>

<section class="card auth-card-wide">
  <h2 class="section-title">修改密码</h2>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="profile.php" class="stack">
    <?= csrf_field() ?>
    <label>当前密码
      <input type="password" name="old_password" required maxlength="72" autocomplete="current-password">
    </label>
    <label>新密码（至少 6 位）
      <input type="password" name="new_password" required minlength="6" maxlength="72" autocomplete="new-password">
    </label>
    <label>确认新密码
      <input type="password" name="new_password_confirm" required minlength="6" maxlength="72" autocomplete="new-password">
    </label>
    <button class="btn btn-primary" type="submit">更新密码</button>
  </form>
</section>

<section class="card">
  <div class="profile-logout">
    <form method="post" action="/logout.php" class="inline-form">
      <?= csrf_field() ?>
      <button class="btn-text btn-text-danger" type="submit">退出登录</button>
    </form>
    <span class="profile-logout-hint">退出后需重新登录才能继续记录</span>
  </div>
</section>

<?php if ($user['role'] === 'admin'): ?>
<section class="card">
  <h2 class="section-title">管理员工具</h2>
  <div class="admin-quick">
    <a class="btn btn-ghost" href="/admin/users.php">用户管理</a>
    <a class="btn btn-ghost" href="/admin/invites.php">邀请码管理</a>
    <a class="btn btn-ghost" href="/admin/content.php">内容管理</a>
    <a class="btn btn-ghost" href="/admin/settings.php">网站设置</a>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

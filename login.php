<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $result = attempt_login($username, $password);
    if ($result === 1) {
        flash('ok', '欢迎回来！');
        redirect('index.php');
    } elseif ($result === -1) {
        $error = '该账号已被禁用，请联系管理员。';
    } else {
        // 统一提示，不泄露是「用户名不存在」还是「密码错误」
        $error = '用户名或密码错误。';
    }
}

$pageTitle = '登录';
require __DIR__ . '/includes/header.php';
?>
<section class="card auth-card">
  <h1>登录</h1>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="login.php" class="stack">
    <?= csrf_field() ?>
    <label>用户名
      <input type="text" name="username" value="<?= e($username) ?>" required maxlength="32" autofocus>
    </label>
    <label>密码
      <input type="password" name="password" required maxlength="72" autocomplete="current-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">登录</button>
  </form>
  <p class="auth-alt">还没有账号？<a href="register.php">使用邀请码注册</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

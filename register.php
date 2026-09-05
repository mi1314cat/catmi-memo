<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
$username = '';
$invite = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');
    $invite   = strtoupper(trim((string)($_POST['invite_code'] ?? '')));

    if ($password !== $confirm) {
        $error = '两次输入的密码不一致。';
    } elseif (($validationError = validate_registration($username, $password)) !== null) {
        $error = $validationError;
    } elseif ($invite === '') {
        $error = '本站为邀请制，请填写管理员提供的邀请码。';
    } else {
        /* 先做一次只读预检查，给用户更友好的错误提示 */
        $stmt = db()->prepare('SELECT used_count, max_uses, enabled, expires_at FROM invite_codes WHERE code = ?');
        $stmt->execute([$invite]);
        $inviteRow = $stmt->fetch();
        if (!$inviteRow) {
            $error = '邀请码不存在，请向管理员获取。';
        } elseif ((int)$inviteRow['enabled'] !== 1) {
            $error = '该邀请码已被停用。';
        } elseif ($inviteRow['expires_at'] !== null && (string)$inviteRow['expires_at'] < date('Y-m-d H:i:s')) {
            $error = '该邀请码已过期。';
        } elseif ((int)$inviteRow['used_count'] >= (int)$inviteRow['max_uses']) {
            $error = '该邀请码的使用次数已用完。';
        }
    }

    if ($error === null) {
        /* 事务：原子消费邀请码 + 创建用户，任何一步失败一起回滚 */
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if (!consume_invite_code($invite)) {
                throw new RuntimeException('该邀请码无效、已停用、已过期或次数用完。');
            }
            $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->commit();
            flash('ok', '注册成功，现在可以登录了。');
            redirect('login.php');
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getCode() === '23000'
                ? '这个用户名已经被使用了，换一个试试。'
                : '注册失败，请稍后再试。';
        }
    }
}

$pageTitle = '注册';
require __DIR__ . '/includes/header.php';
?>
<section class="card auth-card">
  <h1>注册</h1>
  <p class="auth-note">本站为邀请制，需要管理员提供的邀请码才能注册。</p>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="register.php" class="stack">
    <?= csrf_field() ?>
    <label>邀请码
      <input type="text" name="invite_code" value="<?= e($invite) ?>" required maxlength="32" placeholder="CATMI-XXXXXXXX" autofocus>
    </label>
    <label>用户名（2-20 位，中文/字母/数字/下划线）
      <input type="text" name="username" value="<?= e($username) ?>" required maxlength="20">
    </label>
    <label>密码（至少 6 位）
      <input type="password" name="password" required minlength="6" maxlength="72" autocomplete="new-password">
    </label>
    <label>确认密码
      <input type="password" name="password_confirm" required minlength="6" maxlength="72" autocomplete="new-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">创建账号</button>
  </form>
  <p class="auth-alt">已经有账号了？<a href="login.php">去登录</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

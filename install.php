<?php
declare(strict_types=1);

/**
 * Catmi Memo 安装向导（首次部署用）
 *
 * 流程：校验数据库配置 → 执行 database/schema.sql 建表（幂等）→ 创建管理员账号 → 写入锁文件自锁
 * 安全：锁文件（config/installed.lock）或数据库已有用户时拒绝运行；安装完成后建议删除本文件。
 */
require_once __DIR__ . '/includes/auth.php';

$lockFile = __DIR__ . '/config/installed.lock';
$schemaFile = __DIR__ . '/database/schema.sql';
$errors = [];
$done = false;

header('X-Content-Type-Options: nosniff');

if (is_file($lockFile)) {
    http_response_code(410);
    exit('<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:560px;margin:60px auto">'
        . '<h2>本站点已完成安装</h2><p>如确需重新安装，请先通过 FTP 删除 <code>config/installed.lock</code> 与本文件。</p></body>');
}

// 预检：config 是否存在、数据库能否连通（给出比 db() 更具体的指引）
if (!is_file(__DIR__ . '/config/config.php')) {
    http_response_code(500);
    exit('<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:560px;margin:60px auto">'
        . '<h2>缺少配置文件</h2><p>请先复制 <code>config/config.example.php</code> 为 <code>config/config.php</code>，'
        . '填入你的 MySQL 数据库信息后再运行安装向导。</p></body>');
}
$config = require __DIR__ . '/config/config.php';
try {
    $probe = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', (string)$config['DB_HOST'], (string)$config['DB_NAME']),
        (string)$config['DB_USER'],
        (string)$config['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:560px;margin:60px auto">'
        . '<h2>无法连接数据库</h2><p>请确认 <code>config/config.php</code> 中的 DB_HOST / DB_NAME / DB_USER / DB_PASS '
        . '正确，且该 MySQL 账号允许从当前主机连接。</p></body>');
}

// 已有用户数据 = 已安装（数据库被复用时防覆盖）
$tablesReady = false;
try {
    $tablesReady = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (PDOException) {
    $tablesReady = false;
}
if ($tablesReady) {
    @file_put_contents($lockFile, 'installed ' . date('c'));
    http_response_code(410);
    exit('<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:560px;margin:60px auto">'
        . '<h2>站点已安装</h2><p>数据库中已存在用户数据。请删除服务器上的 install.php。</p></body>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    $validateError = validate_registration($username, $password);
    if ($validateError !== null) {
        $errors[] = $validateError;
    } elseif ($password !== $confirm) {
        $errors[] = '两次输入的密码不一致。';
    }

    if (!$errors) {
        if (!is_file($schemaFile)) {
            $errors[] = '未找到 database/schema.sql，请确认完整上传了项目文件。';
        } else {
            // ① 建表（幂等：CREATE TABLE IF NOT EXISTS）
            $lines = [];
            foreach (preg_split('/\r?\n/', (string)file_get_contents($schemaFile)) as $line) {
                if (strpos(ltrim($line), '--') === 0) {
                    continue;
                }
                $lines[] = $line;
            }
            $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', implode("\n", $lines)))));
            try {
                foreach ($statements as $statement) {
                    if ($statement !== '') {
                        db()->exec($statement);
                    }
                }
                // ② 创建管理员（密码只存 password_hash 散列）
                db()->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')")
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                // ③ 自锁
                @file_put_contents($lockFile, 'installed ' . date('c') . ' admin=' . $username);
                $done = true;
            } catch (PDOException $exception) {
                $errors[] = '安装执行失败：' . $exception->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>安装 Catmi Memo</title>
<style>
  body { font-family: -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; background: #f2f4f8; color: #26303e; margin: 0; }
  .box { max-width: 460px; margin: 60px auto; background: #fff; border-radius: 16px; padding: 30px 32px; box-shadow: 0 8px 28px rgba(30,41,59,.1); }
  h1 { font-size: 20px; margin: 0 0 6px; }
  .sub { color: #66717f; font-size: 13.5px; margin: 0 0 20px; line-height: 1.6; }
  label { display: block; font-size: 13px; color: #66717f; margin: 14px 0 6px; }
  input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #d7dde6; border-radius: 10px; font-size: 15px; }
  button { width: 100%; margin-top: 20px; padding: 11px; border: 0; border-radius: 10px; background: #4263eb; color: #fff; font-size: 15px; cursor: pointer; }
  .err { background: #fdecec; color: #c0392b; border-radius: 10px; padding: 10px 14px; font-size: 13.5px; margin-bottom: 8px; }
  .ok { background: #e8f7ef; color: #1d7a4f; border-radius: 10px; padding: 14px 16px; font-size: 14px; line-height: 1.7; }
  code { background: #f0f2f6; border-radius: 5px; padding: 1px 6px; font-size: 12.5px; }
</style>
</head>
<body>
<div class="box">
  <h1>Catmi Memo 安装向导</h1>
  <p class="sub">数据库连接正常（<?= e((string)$config['DB_NAME']) ?>）。本向导将建表并创建管理员账号，完成后自动锁定。</p>
  <?php if ($done): ?>
  <div class="ok">
    ✅ 安装完成！管理员账号 <b><?= e($username) ?></b> 已创建。<br><br>
    请立即做两件事：<br>
    1. 通过 FTP <b>删除服务器上的 install.php</b>（锁文件已生成，本页也无法再次运行）；<br>
    2. 打开网站首页登录，到「设置」完善资料，并在管理后台创建邀请码开始使用。
  </div>
  <?php else: ?>
    <?php foreach ($errors as $error): ?><div class="err"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" action="install.php">
    <?= csrf_field() ?>
    <label>管理员用户名（2-20 位，中文/字母/数字/下划线）</label>
    <input type="text" name="username" required maxlength="20" autofocus>
    <label>管理员密码（至少 6 位）</label>
    <input type="password" name="password" required minlength="6" maxlength="72">
    <label>确认密码</label>
    <input type="password" name="password_confirm" required minlength="6" maxlength="72">
    <button type="submit">执行安装</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>

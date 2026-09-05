<?php
require_once __DIR__ . '/auth.php';
$currentUser = current_user();
$isAdminUser = $currentUser !== null && $currentUser['role'] === 'admin';
$bgImageSetting = get_setting('background_image', '');
// 规范化为站点根相对路径（防手动填 /assets/... 时注入 // 开头的协议相对 URL）
$bgImageSetting = $bgImageSetting !== '' ? ltrim($bgImageSetting, '/') : '';
$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$inAdminArea = strpos((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') === 0;
$navActive = static function (string $script) use ($currentScript, $inAdminArea): string {
    if ($script === '@admin') {
        return $inAdminArea ? ' active' : '';
    }
    return $script === $currentScript ? ' active' : '';
};
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#eef1f6">
<title><?= isset($pageTitle) ? e($pageTitle) . ' · ' : '' ?>Catmi Memo</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%90%B1%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="/assets/css/style.css?v=4">
<?php if ($bgImageSetting !== ''): ?>
<style>:root { --bg-image: url('/<?= e($bgImageSetting) ?>'); }</style>
<?php endif; ?>
</head>
<body class="<?= $bgImageSetting !== '' ? 'has-bg' : '' ?>">
<div class="layout">
  <aside class="sidebar">
    <a class="brand" href="/">🐱 Catmi Memo</a>
    <?php if ($currentUser): ?>
    <nav class="side-nav">
      <a class="side-link<?= $navActive('index.php') ?>" href="/"><span class="side-ico">🏠</span>首页</a>
      <a class="side-link<?= $navActive('public.php') ?>" href="/public.php"><span class="side-ico">🌐</span>公开动态</a>
      <a class="side-link<?= $navActive('my.php') ?>" href="/my.php"><span class="side-ico">🔒</span>我的内容</a>
      <a class="side-link<?= $navActive('profile.php') ?>" href="/profile.php"><span class="side-ico">⚙️</span>设置</a>
      <?php if ($isAdminUser): ?>
      <hr class="side-sep">
      <a class="side-link<?= $navActive('@admin') ?>" href="/admin/"><span class="side-ico">🛠️</span>管理后台</a>
      <?php endif; ?>
    </nav>
    <div class="side-user">
      <a class="side-user-chip" href="/profile.php" title="账户设置">
        <?= user_avatar_html((string)$currentUser['avatar'], (string)$currentUser['username']) ?>
        <span class="side-user-name"><?= e($currentUser['username']) ?></span>
      </a>
      <form method="post" action="/logout.php" class="inline-form">
        <?= csrf_field() ?>
        <button class="btn-text" type="submit">退出</button>
      </form>
    </div>
    <?php else: ?>
    <nav class="side-nav">
      <a class="side-link" href="/login.php"><span class="side-ico">→</span>登录</a>
      <a class="side-link" href="/register.php"><span class="side-ico">✉️</span>注册</a>
    </nav>
    <div class="side-user side-user-guest">邀请制 · 仅限站长授权</div>
    <?php endif; ?>
  </aside>

  <div class="maincol">
    <header class="mobile-topbar">
      <a class="brand" href="/">🐱 Catmi Memo</a>
      <div class="mobile-topbar-actions">
        <?php if ($currentUser): ?>
        <a class="mobile-topbar-avatar" href="/profile.php" title="账户设置">
          <?= user_avatar_html((string)$currentUser['avatar'], (string)$currentUser['username'], 'avatar-sm') ?>
        </a>
        <?php else: ?>
        <a class="btn-text" href="/login.php">登录</a>
        <?php endif; ?>
      </div>
    </header>
    <main class="container">
<?php foreach (take_flashes() as $flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

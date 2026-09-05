<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin(); // 服务端权限验证：非管理员 404

$userCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$postCount = (int)db()->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$publicCount = (int)db()->query("SELECT COUNT(*) FROM posts WHERE visibility = 'public'")->fetchColumn();
$imageCount = (int)db()->query('SELECT COUNT(*) FROM post_images')->fetchColumn();
$inviteActive = (int)db()->query('SELECT COUNT(*) FROM invite_codes WHERE enabled = 1 AND used_count < max_uses AND (expires_at IS NULL OR expires_at > NOW())')->fetchColumn();

$adminTabs = [
    ['/admin/', '总览'],
    ['/admin/users.php', '用户管理'],
    ['/admin/invites.php', '邀请码'],
    ['/admin/content.php', '内容管理'],
    ['/admin/settings.php', '网站设置'],
];
$adminCurrent = 'index.php';
$pageTitle = '管理后台';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>🛠 管理后台</h1>
  <p class="page-sub">仅管理员可见。普通用户不会看到这里的任何入口。</p>
</section>
<?php require __DIR__ . '/tabs.php'; ?>

<section class="card admin-overview">
  <h2 class="section-title">站点概况</h2>
  <div class="overview-grid">
    <div class="overview-item"><span class="overview-num"><?= $userCount ?></span><span class="overview-label">用户</span></div>
    <div class="overview-item"><span class="overview-num"><?= $postCount ?></span><span class="overview-label">动态</span></div>
    <div class="overview-item"><span class="overview-num"><?= $publicCount ?></span><span class="overview-label">公开动态</span></div>
    <div class="overview-item"><span class="overview-num"><?= $imageCount ?></span><span class="overview-label">图片</span></div>
    <div class="overview-item"><span class="overview-num"><?= $inviteActive ?></span><span class="overview-label">可用邀请码</span></div>
  </div>
</section>

<section class="admin-links">
  <a class="card admin-link-card" href="/admin/users.php">
    <span class="admin-link-title">👤 用户管理</span>
    <span class="admin-link-desc">禁用 / 启用 / 删除用户，管理账号状态</span>
  </a>
  <a class="card admin-link-card" href="/admin/invites.php">
    <span class="admin-link-title">🎟 邀请码</span>
    <span class="admin-link-desc">创建、停用注册邀请码，查看使用情况</span>
  </a>
  <a class="card admin-link-card" href="/admin/content.php">
    <span class="admin-link-title">🗂 内容管理</span>
    <span class="admin-link-desc">全站内容一览，处理违规动态</span>
  </a>
  <a class="card admin-link-card" href="/admin/settings.php">
    <span class="admin-link-title">🖼 网站设置</span>
    <span class="admin-link-desc">背景图片、游客浏览开关</span>
  </a>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
/* 管理后台公共标签页（由 admin/ 下各页面 include） */
$adminCurrent = $adminCurrent ?? basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$adminTabs = $adminTabs ?? [
    ['/admin/', '总览'],
    ['/admin/users.php', '用户管理'],
    ['/admin/invites.php', '邀请码'],
    ['/admin/content.php', '内容管理'],
    ['/admin/settings.php', '网站设置'],
];
?>
<nav class="tabs">
  <?php foreach ($adminTabs as [$tabHref, $tabLabel]): ?>
  <a class="tab<?= basename($tabHref) === $adminCurrent || ($tabHref === '/admin/' && $adminCurrent === 'index.php') ? ' active' : '' ?>" href="<?= $tabHref ?>"><?= $tabLabel ?></a>
  <?php endforeach; ?>
</nav>

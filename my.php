<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = require_login();

$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'public', 'private'], true)) {
    $filter = 'all';
}
$filterParam = $filter === 'all' ? null : $filter;

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PAGE_SIZE;
$total = count_user_posts((int)$user['id'], $filterParam);
$posts = fetch_user_posts((int)$user['id'], $filterParam, PAGE_SIZE, $offset);

$pageTitle = '我的内容';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <h1>🗂 我的内容</h1>
  <p class="page-sub">这里只有你自己能看到，包含公开与私密的所有动态。</p>
</section>

<nav class="tabs">
  <a class="tab<?= $filter === 'all' ? ' active' : '' ?>" href="my.php?filter=all">全部</a>
  <a class="tab<?= $filter === 'public' ? ' active' : '' ?>" href="my.php?filter=public">🌐 公开</a>
  <a class="tab<?= $filter === 'private' ? ' active' : '' ?>" href="my.php?filter=private">🔒 仅自己可见</a>
</nav>

<?php if ($posts): ?>
  <?php foreach ($posts as $post) { render_memo_card($post, $user); } ?>
<?php else: ?>
  <div class="card empty">这里还没有内容。</div>
<?php endif; ?>

<?php render_pager($total, $page, 'my.php?filter=' . $filter . '&'); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

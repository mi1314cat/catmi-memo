<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentUser = current_user();
$guestAllowed = get_setting('guest_view_public', '0') === '1';

// 公开动态页默认在登录墙内；管理员可在后台开放游客浏览
if ($currentUser === null && !$guestAllowed) {
    redirect('login.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PAGE_SIZE;
$total = count_public_posts();
$posts = fetch_public_posts(PAGE_SIZE, $offset);

$pageTitle = '公开动态';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <h1>🌐 公开动态</h1>
  <p class="page-sub">所有用户选择「公开」的内容，按时间倒序。私密内容不会出现在这里。</p>
</section>

<?php if ($posts): ?>
  <?php foreach ($posts as $post) { render_memo_card($post, $currentUser ?? ['id' => 0, 'role' => 'guest', 'username' => '']); } ?>
<?php else: ?>
  <div class="card empty">还没有公开动态。</div>
<?php endif; ?>

<?php render_pager($total, $page, 'public.php?'); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

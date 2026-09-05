<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

/* ---------- 删除违规内容 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($action === 'delete' && $postId > 0) {
        $stmt = db()->prepare('SELECT id FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        if ($stmt->fetch()) {
            delete_post_absolutely($postId);
            flash('ok', '已删除动态 #' . $postId . '（含图片文件）。');
        } else {
            flash('error', '动态不存在或已被删除。');
        }
    }
    redirect('/admin/content.php');
}

/* ---------- 最新内容列表 ---------- */
$posts = db()->query(
    'SELECT p.id, p.content, p.visibility, p.created_at, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.id DESC
     LIMIT 100'
)->fetchAll();

$adminCurrent = 'content.php';
$pageTitle = '内容管理';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>🗂 内容管理</h1>
  <p class="page-sub">全站最新 100 条动态（含私密内容，仅管理员可见此页）。</p>
</section>
<?php require __DIR__ . '/tabs.php'; ?>

<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>ID</th><th>作者</th><th>可见性</th><th>内容摘要</th><th>发布时间</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php if (!$posts): ?>
        <tr><td colspan="6" class="td-muted">还没有任何动态。</td></tr>
        <?php endif; ?>
        <?php foreach ($posts as $row): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td class="td-strong"><?= e($row['username']) ?></td>
          <td><span class="chip <?= $row['visibility'] === 'public' ? '' : 'chip-muted' ?>"><?= $row['visibility'] === 'public' ? '公开' : '私密' ?></span></td>
          <td class="td-excerpt"><?= e(mb_substr(preg_replace('/\s+/u', ' ', $row['content']) ?? '', 0, 60)) ?: '<span class="td-muted">（无文字）</span>' ?></td>
          <td><?= e(display_time((string)$row['created_at'])) ?></td>
          <td>
            <form method="post" action="/admin/content.php" class="inline-form" data-confirm="确定删除动态 #<?= (int)$row['id'] ?> 吗？">
              <?= csrf_field() ?>
              <input type="hidden" name="post_id" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn-text btn-text-danger" type="submit">删除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

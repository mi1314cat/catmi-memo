<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin(); // 服务端权限验证：非管理员 404

/* ---------- 用户管理操作 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $targetId = (int)($_POST['uid'] ?? 0);

    $stmt = db()->prepare('SELECT id, username, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();

    if (!$target) {
        flash('error', '用户不存在。');
    } elseif ($targetId === (int)$admin['id']) {
        flash('error', '不能对自己执行该操作。');
    } elseif ($target['role'] === 'admin') {
        flash('error', '不能对其他管理员执行该操作。');
    } elseif ($action === 'disable') {
        db()->prepare("UPDATE users SET status = 'disabled' WHERE id = ?")->execute([$targetId]);
        flash('ok', '已禁用用户 ' . $target['username'] . '，其会话将立即失效。');
    } elseif ($action === 'enable') {
        db()->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
        flash('ok', '已启用用户 ' . $target['username'] . '。');
    } elseif ($action === 'delete') {
        delete_user_image_files($targetId);
        // SQL 层再加一道 role 保险；帖子/图片行由外键级联清理
        db()->prepare("DELETE FROM users WHERE id = ? AND role <> 'admin'")->execute([$targetId]);
        flash('ok', '已删除用户 ' . $target['username'] . ' 及其全部内容。');
    } else {
        flash('error', '未知操作。');
    }
    redirect('/admin/users.php');
}

/* ---------- 用户列表 ---------- */
$users = db()->query(
    'SELECT u.id, u.username, u.role, u.status, u.avatar, u.created_at, COUNT(p.id) AS post_count
     FROM users u
     LEFT JOIN posts p ON p.user_id = u.id
     GROUP BY u.id, u.username, u.role, u.status, u.avatar, u.created_at
     ORDER BY u.id'
)->fetchAll();

$adminCurrent = 'users.php';
$pageTitle = '用户管理';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>👤 用户管理</h1>
</section>
<?php require __DIR__ . '/tabs.php'; ?>

<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>ID</th><th>用户名</th><th>角色</th><th>状态</th><th>动态数</th><th>注册时间</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $row): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><span class="td-user-cell"><?= user_avatar_html((string)$row['avatar'], (string)$row['username'], 'avatar-xs') ?><?= e($row['username']) ?></span></td>
          <td><span class="chip <?= $row['role'] === 'admin' ? 'chip-admin' : '' ?>"><?= $row['role'] === 'admin' ? '管理员' : '用户' ?></span></td>
          <td><span class="chip <?= $row['status'] === 'active' ? '' : 'chip-disabled' ?>"><?= $row['status'] === 'active' ? '正常' : '已禁用' ?></span></td>
          <td><?= (int)$row['post_count'] ?></td>
          <td><?= e(display_time((string)$row['created_at'])) ?></td>
          <td>
            <?php if ((int)$row['id'] !== (int)$admin['id'] && $row['role'] !== 'admin'): ?>
              <?php if ($row['status'] === 'active'): ?>
              <form method="post" action="/admin/users.php" class="inline-form" data-confirm="确定禁用用户 <?= e($row['username']) ?> 吗？其会话将立即失效。">
                <?= csrf_field() ?><input type="hidden" name="uid" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="disable">
                <button class="btn-text" type="submit">禁用</button>
              </form>
              <?php else: ?>
              <form method="post" action="/admin/users.php" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="uid" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="enable">
                <button class="btn-text" type="submit">启用</button>
              </form>
              <?php endif; ?>
              <form method="post" action="/admin/users.php" class="inline-form" data-confirm="确定删除用户 <?= e($row['username']) ?> 吗？其全部动态和图片将一并删除，不可恢复！">
                <?= csrf_field() ?><input type="hidden" name="uid" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="delete">
                <button class="btn-text btn-text-danger" type="submit">删除</button>
              </form>
            <?php else: ?>
              <span class="td-muted"><?= (int)$row['id'] === (int)$admin['id'] ? '（当前登录）' : '—' ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

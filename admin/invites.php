<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

/* ---------- 邀请码操作 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $custom = trim((string)($_POST['custom_code'] ?? ''));
        $maxUses = (int)($_POST['max_uses'] ?? 1);
        $expiresDays = (int)($_POST['expires_days'] ?? 0);
        $expiresAt = $expiresDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . min($expiresDays, 3650) . ' days')) : null;
        [$ok, $error, $code] = create_invite_code((int)$admin['id'], $maxUses, $expiresAt, $custom !== '' ? $custom : null);
        if ($ok) {
            flash('ok', '邀请码创建成功：' . $code);
        } else {
            flash('error', (string)$error);
        }
    } else {
        $inviteId = (int)($_POST['invite_id'] ?? 0);
        $stmt = db()->prepare('SELECT id FROM invite_codes WHERE id = ?');
        $stmt->execute([$inviteId]);
        if (!$stmt->fetch()) {
            flash('error', '邀请码不存在。');
        } elseif ($action === 'disable') {
            db()->prepare('UPDATE invite_codes SET enabled = 0 WHERE id = ?')->execute([$inviteId]);
            flash('ok', '邀请码已停用。');
        } elseif ($action === 'enable') {
            db()->prepare('UPDATE invite_codes SET enabled = 1 WHERE id = ?')->execute([$inviteId]);
            flash('ok', '邀请码已启用。');
        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM invite_codes WHERE id = ?')->execute([$inviteId]);
            flash('ok', '邀请码已删除。');
        } else {
            flash('error', '未知操作。');
        }
    }
    redirect('/admin/invites.php');
}

/* ---------- 邀请码列表 ---------- */
$invites = db()->query(
    'SELECT ic.id, ic.code, ic.max_uses, ic.used_count, ic.expires_at, ic.enabled, ic.created_at, u.username AS creator
     FROM invite_codes ic
     LEFT JOIN users u ON u.id = ic.creator_id
     ORDER BY ic.id DESC
     LIMIT 200'
)->fetchAll();

$now = date('Y-m-d H:i:s');
$adminCurrent = 'invites.php';
$pageTitle = '邀请码管理';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>🎟 邀请码</h1>
</section>
<?php require __DIR__ . '/tabs.php'; ?>

<section class="card">
  <h2 class="section-title">创建邀请码</h2>
  <form method="post" action="/admin/invites.php" class="invite-create">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label>自定义码（留空则随机生成）
      <input type="text" name="custom_code" maxlength="32" placeholder="如 CATMI-CAT2026">
    </label>
    <label>最大使用次数
      <input type="number" name="max_uses" value="1" min="1" max="999" required>
    </label>
    <label>有效天数（0 = 永不过期）
      <input type="number" name="expires_days" value="0" min="0" max="3650">
    </label>
    <button class="btn btn-primary" type="submit">创建</button>
  </form>
</section>

<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>邀请码</th><th>创建人</th><th>使用情况</th><th>剩余</th><th>有效期</th><th>状态</th><th>创建时间</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php if (!$invites): ?>
        <tr><td colspan="8" class="td-muted">还没有邀请码，用上面的表单创建一个。</td></tr>
        <?php endif; ?>
        <?php foreach ($invites as $row): ?>
          <?php
            $usedOut = (int)$row['used_count'] >= (int)$row['max_uses'];
            $expired = $row['expires_at'] !== null && (string)$row['expires_at'] < $now;
            if ((int)$row['enabled'] !== 1) {
                $statusLabel = '已停用';
                $statusClass = 'chip-disabled';
            } elseif ($expired) {
                $statusLabel = '已过期';
                $statusClass = 'chip-disabled';
            } elseif ($usedOut) {
                $statusLabel = '已用完';
                $statusClass = 'chip-disabled';
            } else {
                $statusLabel = '启用中';
                $statusClass = '';
            }
            $remaining = max(0, (int)$row['max_uses'] - (int)$row['used_count']);
          ?>
        <tr>
          <td class="td-strong td-code"><?= e($row['code']) ?></td>
          <td><?= e((string)$row['creator']) ?></td>
          <td><?= (int)$row['used_count'] ?> / <?= (int)$row['max_uses'] ?></td>
          <td><?= $remaining ?></td>
          <td><?= $row['expires_at'] !== null ? e(display_time((string)$row['expires_at'])) : '永久' ?></td>
          <td><span class="chip <?= $statusClass ?>"><?= $statusLabel ?></span></td>
          <td><?= e(display_time((string)$row['created_at'])) ?></td>
          <td>
            <?php if ((int)$row['enabled'] === 1): ?>
            <form method="post" action="/admin/invites.php" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="invite_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="disable">
              <button class="btn-text" type="submit">停用</button>
            </form>
            <?php else: ?>
            <form method="post" action="/admin/invites.php" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="invite_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="enable">
              <button class="btn-text" type="submit">启用</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/admin/invites.php" class="inline-form" data-confirm="确定删除邀请码 <?= e($row['code']) ?> 吗？">
              <?= csrf_field() ?><input type="hidden" name="invite_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="delete">
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

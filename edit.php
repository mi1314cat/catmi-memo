<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = require_login();

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    redirect('index.php');
}

$stmt = db()->prepare('SELECT id, user_id, content, visibility, created_at FROM posts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$post = $stmt->fetch();

// 权限检查（防 IDOR）：只有作者本人能编辑；他人的或不存在的一律 404
if (!$post || (int)$post['user_id'] !== (int)$user['id']) {
    http_response_code(404);
    exit('没有找到这条动态。');
}

$imageStmt = db()->prepare('SELECT id, file_path FROM post_images WHERE post_id = ? ORDER BY id');
$imageStmt->execute([$id]);
$postImages = $imageStmt->fetchAll();

$attStmt = db()->prepare('SELECT id, kind, original_name, file_size FROM post_attachments WHERE post_id = ? ORDER BY id');
$attStmt->execute([$id]);
$postAttachments = $attStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES)) {
        flash('error', '上传内容总大小超过了服务器限制，请减少附件数量或大小后重试。');
        redirect('edit.php?id=' . $id);
    }
    verify_csrf();

    $content = trim((string)($_POST['content'] ?? ''));
    if (mb_strlen($content) > POST_MAX_CHARS) {
        flash('error', '内容太长了（最多 ' . POST_MAX_CHARS . ' 字）。');
        redirect('edit.php?id=' . $id);
    }
    $visibility = ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

    // 移除勾选的旧图片（只允许操作属于本条动态的图片）
    $removeIds = array_values(array_unique(array_map('intval', (array)($_POST['remove_image'] ?? []))));
    if ($removeIds) {
        $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
        $delStmt = db()->prepare(
            "SELECT id, file_path FROM post_images WHERE post_id = ? AND id IN ($placeholders)"
        );
        $delStmt->execute(array_merge([$id], $removeIds));
        foreach ($delStmt->fetchAll() as $image) {
            delete_upload_file((string)$image['file_path']);
            db()->prepare('DELETE FROM post_images WHERE id = ?')->execute([$image['id']]);
        }
    }

    // 移除勾选的旧附件
    $removeAttIds = array_values(array_unique(array_map('intval', (array)($_POST['remove_attachment'] ?? []))));
    if ($removeAttIds) {
        $placeholders = implode(',', array_fill(0, count($removeAttIds), '?'));
        $delStmt = db()->prepare(
            "SELECT id, file_path FROM post_attachments WHERE post_id = ? AND id IN ($placeholders)"
        );
        $delStmt->execute(array_merge([$id], $removeAttIds));
        foreach ($delStmt->fetchAll() as $att) {
            delete_upload_file((string)$att['file_path']);
            db()->prepare('DELETE FROM post_attachments WHERE id = ?')->execute([$att['id']]);
        }
    }

    [$ok, $error, $newImages] = process_uploaded_images($_FILES['images'] ?? []);
    if (!$ok) {
        flash('error', (string)$error);
        redirect('edit.php?id=' . $id);
    }

    [$ok, $error, $newAttachments] = process_uploaded_attachments($_FILES['attachments'] ?? []);
    if (!$ok) {
        cleanup_saved_images($newImages);
        flash('error', (string)$error);
        redirect('edit.php?id=' . $id);
    }

    $countStmt = db()->prepare('SELECT COUNT(*) FROM post_images WHERE post_id = ?');
    $countStmt->execute([$id]);
    $imageTotal = (int)$countStmt->fetchColumn() + count($newImages);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM post_attachments WHERE post_id = ?');
    $countStmt->execute([$id]);
    $attTotal = (int)$countStmt->fetchColumn() + count($newAttachments);

    if ($imageTotal > UPLOAD_MAX_COUNT) {
        cleanup_saved_images($newImages);
        cleanup_saved_images($newAttachments);
        flash('error', '每条动态最多 ' . UPLOAD_MAX_COUNT . ' 张图片。');
        redirect('edit.php?id=' . $id);
    }
    if ($attTotal > max_attachments_per_post()) {
        cleanup_saved_images($newImages);
        cleanup_saved_images($newAttachments);
        flash('error', '每条动态最多 ' . max_attachments_per_post() . ' 个附件（视频+文件）。');
        redirect('edit.php?id=' . $id);
    }
    if ($content === '' && $imageTotal === 0 && $attTotal === 0) {
        cleanup_saved_images($newImages);
        cleanup_saved_images($newAttachments);
        flash('error', '内容、图片和附件不能同时为空。');
        redirect('edit.php?id=' . $id);
    }

    db()->prepare('UPDATE posts SET content = ?, visibility = ?, updated_at = ? WHERE id = ? AND user_id = ?')
        ->execute([$content, $visibility, date('Y-m-d H:i:s'), $id, $user['id']]);

    $insertImage = db()->prepare(
        'INSERT INTO post_images (post_id, user_id, file_path, original_name, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($newImages as $image) {
        $insertImage->execute([
            $id,
            $user['id'],
            $image['path'],
            $image['original'],
            $image['mime'],
            $image['size'],
        ]);
    }

    $insertAtt = db()->prepare(
        'INSERT INTO post_attachments (post_id, user_id, kind, original_name, stored_name, mime_type, file_size, file_path, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($newAttachments as $att) {
        $insertAtt->execute([
            $id,
            $user['id'],
            $att['kind'],
            $att['original'],
            basename($att['path']),
            $att['mime'],
            $att['size'],
            $att['path'],
            date('Y-m-d H:i:s'),
        ]);
    }

    flash('ok', '修改已保存。');
    redirect('my.php');
}

$imageLimit = effective_limit('MAX_IMAGE_SIZE', UPLOAD_MAX_BYTES);
$videoLimit = effective_limit('MAX_VIDEO_SIZE', 8388608);
$fileLimit = effective_limit('MAX_FILE_SIZE', 8388608);
$pageTitle = '编辑动态';
require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1 class="page-title">编辑动态</h1>
  <form method="post" action="edit.php" enctype="multipart/form-data" class="stack"
        data-max-att="<?= max_attachments_per_post() ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
    <textarea name="content" rows="5" maxlength="<?= POST_MAX_CHARS ?>"><?= e($post['content']) ?></textarea>

    <div class="composer-visibility">
      <span class="composer-visibility-label">可见范围</span>
      <label class="radio-pill">
        <input type="radio" name="visibility" value="private" <?= ($post['visibility'] ?? 'private') === 'private' ? 'checked' : '' ?>>
        <span>🔒 仅自己可见</span>
      </label>
      <label class="radio-pill">
        <input type="radio" name="visibility" value="public" <?= ($post['visibility'] ?? '') === 'public' ? 'checked' : '' ?>>
        <span>🌐 公开</span>
      </label>
    </div>

    <?php if ($postImages): ?>
    <div class="edit-images">
      <?php foreach ($postImages as $image): ?>
      <div class="edit-image">
        <img src="<?= e(media_url($image['file_path'])) ?>" alt="" loading="lazy">
        <label class="edit-image-remove">
          <input type="checkbox" name="remove_image[]" value="<?= (int)$image['id'] ?>"> 移除这张
        </label>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($postAttachments): ?>
    <div class="edit-att-list">
      <?php foreach ($postAttachments as $att): ?>
      <label class="edit-att-item">
        <input type="checkbox" name="remove_attachment[]" value="<?= (int)$att['id'] ?>">
        <span><?= $att['kind'] === 'video' ? '🎬' : '📎' ?> <?= e($att['original_name']) ?>（<?= e(human_size((int)$att['file_size'])) ?>）</span>
      </label>
      <?php endforeach; ?>
      <span class="edit-att-hint">勾选 = 移除该附件</span>
    </div>
    <?php endif; ?>

    <div class="preview-grid js-preview-grid"></div>
    <p class="composer-error js-form-error" hidden></p>
    <div class="composer-footer">
      <label class="btn btn-ghost file-label">📷 图片
        <input type="file" class="js-file-input" name="images[]" data-kind="image" data-max="<?= $imageLimit ?>"
               accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
      </label>
      <label class="btn btn-ghost file-label">🎬 视频
        <input type="file" class="js-file-input" name="attachments[]" data-kind="video" data-max="<?= $videoLimit ?>"
               accept="video/mp4,video/webm" hidden>
      </label>
      <label class="btn btn-ghost file-label">📎 文件
        <input type="file" class="js-file-input" name="attachments[]" data-kind="file" data-max="<?= $fileLimit ?>"
               accept=".pdf,.txt,.csv,.zip" hidden>
      </label>
      <button class="btn btn-primary" type="submit">保存修改</button>
    </div>
  </form>
  <p class="page-back"><a href="my.php">← 返回我的内容</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

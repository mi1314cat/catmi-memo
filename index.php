<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentUser = current_user();

/* ---------- 访客：欢迎页 ---------- */
if (!$currentUser):
    $pageTitle = '首页';
    require __DIR__ . '/includes/header.php';
?>
<section class="card hero">
  <div class="hero-logo">🐱</div>
  <h1>Catmi Memo</h1>
  <p>一个私密的个人碎片记录站。<br>记录想法、收藏瞬间，公开或仅自己可见，由你决定。</p>
  <div class="hero-actions">
    <a class="btn btn-primary" href="/login.php">登录</a>
    <a class="btn btn-ghost" href="/register.php">使用邀请码注册</a>
  </div>
</section>
<?php
    require __DIR__ . '/includes/footer.php';
    exit;
endif;

/* ---------- 登录用户：快速发布 + 最近内容 ---------- */
$myTotal = count_user_posts((int)$currentUser['id']);
$myPublic = count_user_posts((int)$currentUser['id'], 'public');
$myImages = count_user_images((int)$currentUser['id']);
$recentMine = fetch_user_posts((int)$currentUser['id'], null, 10);

$imageLimit = effective_limit('MAX_IMAGE_SIZE', UPLOAD_MAX_BYTES);
$videoLimit = effective_limit('MAX_VIDEO_SIZE', 8388608);
$fileLimit = effective_limit('MAX_FILE_SIZE', 8388608);
$pageTitle = '首页';
require __DIR__ . '/includes/header.php';
?>

<section class="card composer">
  <h2 class="composer-title">今天想记录什么？</h2>
  <form method="post" action="/post.php" enctype="multipart/form-data" data-max-att="<?= max_attachments_per_post() ?>">
    <?= csrf_field() ?>
    <textarea name="content" id="composer-text" rows="3" maxlength="<?= POST_MAX_CHARS ?>"
              placeholder="写点什么……"></textarea>
    <div class="preview-grid js-preview-grid"></div>
    <p class="composer-error js-form-error" hidden></p>
    <div class="composer-bar">
      <div class="composer-tools">
        <label class="tool-btn" title="图片">
          📷
          <input type="file" class="js-file-input" name="images[]" data-kind="image" data-max="<?= $imageLimit ?>"
                 accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
        </label>
        <label class="tool-btn" title="视频（MP4/WebM）">
          🎬
          <input type="file" class="js-file-input" name="attachments[]" data-kind="video" data-max="<?= $videoLimit ?>"
                 accept="video/mp4,video/webm" hidden>
        </label>
        <label class="tool-btn" title="文件（PDF/TXT/CSV/ZIP）">
          📎
          <input type="file" class="js-file-input" name="attachments[]" data-kind="file" data-max="<?= $fileLimit ?>"
                 accept=".pdf,.txt,.csv,.zip" hidden>
        </label>
      </div>
      <span class="char-count" id="char-count"></span>
      <div class="vis-toggle" role="radiogroup" aria-label="可见范围">
        <label class="radio-pill">
          <input type="radio" name="visibility" value="private" checked>
          <span>🔒 仅自己可见</span>
        </label>
        <label class="radio-pill">
          <input type="radio" name="visibility" value="public">
          <span>🌐 公开</span>
        </label>
      </div>
      <button class="btn btn-primary" type="submit">发布</button>
    </div>
  </form>
</section>

<p class="stat-line">已记录 <b><?= $myTotal ?></b> 条 · 公开 <?= $myPublic ?> 条 · 图片 <?= $myImages ?> 张</p>

<section class="section">
  <div class="section-head">
    <h2>我的最近</h2>
    <a class="section-more" href="/my.php">全部内容 →</a>
  </div>
  <?php if ($recentMine): ?>
    <?php foreach ($recentMine as $post) { render_memo_card($post, $currentUser); } ?>
  <?php else: ?>
    <div class="card empty">还没有内容，在上面写下第一条吧 ✍️</div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

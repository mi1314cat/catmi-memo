    </main>
    <footer class="site-footer">🐱 Catmi Memo · 私人碎片记录站</footer>
  </div>
</div>

<?php if ($currentUser ?? null): ?>
<nav class="bottom-nav">
  <a class="bottom-link<?= $navActive('index.php') ?>" href="/"><span class="bottom-ico">🏠</span>首页</a>
  <a class="bottom-link<?= $navActive('public.php') ?>" href="/public.php"><span class="bottom-ico">🌐</span>公开</a>
  <a class="bottom-link<?= $navActive('my.php') ?>" href="/my.php"><span class="bottom-ico">🔒</span>我的</a>
  <a class="bottom-link<?= $navActive('profile.php') ?>" href="/profile.php"><span class="bottom-ico">⚙️</span>设置</a>
  <?php if ($isAdminUser ?? false): ?>
  <a class="bottom-link<?= $navActive('@admin') ?>" href="/admin/"><span class="bottom-ico">🛠️</span>后台</a>
  <?php endif; ?>
</nav>
<?php endif; ?>

<div class="lightbox" id="lightbox" hidden><img src="" alt="大图预览"></div>
<script src="/assets/js/app.js?v=4"></script>
</body>
</html>

<section class="container py-4">
  <h1 class="section-title mb-3">Postimet</h1>

  <?php if (Auth::check() && (Auth::role() === 'provider' || Auth::role() === 'client')): ?>
    <p class="mb-3">
      <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/posts/create">
        <i class="bi bi-plus-lg"></i> Posto
      </a>
    </p>
  <?php endif; ?>

  <?php if (!$posts): ?>
    <div class="empty-state">
      <i class="bi bi-postcard"></i>
      <p>Asnjë postim ende. Bëhu i pari!</p>
      <?php if (!Auth::check()): ?>
        <p><a href="<?= e(CONFIG['base_url']) ?>/register">Regjistrohu</a> ose <a href="<?= e(CONFIG['base_url']) ?>/login">hyr</a> për të postuar.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($posts as $p): ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <?php View::partial('post-card', ['p' => $p]); ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

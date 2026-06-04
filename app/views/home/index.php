<section class="hero">
  <div class="container py-4">
    <h1 class="mb-3">Keni nevoje per nje punetor per problemin ne shtepi?</h1>

    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="row g-2 mb-3">
      <div class="col-md-8">
        <select name="city" class="form-select form-select-lg">
          <option value="">Shkruani lokacionin tuaj</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-grid">
        <button class="btn btn-helppy btn-lg" type="submit">
          <i class="bi bi-search"></i> Kerko
        </button>
      </div>
    </form>

    <div class="mb-2">
      <?php foreach ($categories as $cat): ?>
        <a class="category-chip"
           href="<?= e(CONFIG['base_url']) ?>/search?category=<?= (int)$cat['id'] ?>">
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container py-4">
  <h2 class="mb-3">Punonjesit me te afert</h2>
  <div class="row">
    <?php foreach ($featured as $p): ?>
      <div class="col-md-6 col-lg-6">
        <?php View::partial('provider-card', ['p' => $p]); ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$featured): ?>
      <p class="text-muted">Asnje punetor i regjistruar ende.</p>
    <?php endif; ?>
  </div>
</section>

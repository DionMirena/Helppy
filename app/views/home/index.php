<section class="hero">
  <div class="container">
    <h1>Keni nevoje per nje punetor per problemin ne shtepi?</h1>

    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="helppy-search">
      <span class="location-icon"><i class="bi bi-geo-alt-fill"></i></span>
      <select name="city" class="form-select" aria-label="Lokacioni">
        <option value="">Zgjidh lokacionin tuaj</option>
        <?php foreach ($cities as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-helppy" type="submit" aria-label="Kerko">
        <i class="bi bi-search"></i>
      </button>
    </form>

    <div class="category-chips">
      <?php foreach ($categories as $cat): ?>
        <a class="category-chip"
           href="<?= e(CONFIG['base_url']) ?>/search?category=<?= (int)$cat['id'] ?>">
          <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container py-3">
  <h2 class="section-title">Punonjesit me te afert</h2>
  <div class="row g-3">
    <?php foreach ($featured as $p): ?>
      <div class="col-12 col-sm-6">
        <?php View::partial('provider-card', ['p' => $p]); ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$featured): ?>
      <div class="col-12">
        <p class="text-muted">Asnje punetor i regjistruar ende.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

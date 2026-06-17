<section class="hero">
  <div class="container">
    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="helppy-search">
      <span class="location-icon"><i class="bi bi-geo-alt-fill"></i></span>
      <select name="city" class="form-select" aria-label="Lokacioni">
        <option value="">Te gjitha qytetet</option>
        <?php foreach ($cities as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $city && $city['id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="helppy-search-divider d-none d-sm-block"></div>
      <select name="category" class="form-select" aria-label="Kategoria">
        <option value="">Te gjitha kategorite</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= $category && $category['id']==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-helppy" type="submit">
        <i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Kerko</span>
      </button>
    </form>

    <div class="category-chips">
      <?php foreach ($categories as $cat):
        $isActive = $category && $category['id'] == $cat['id'];
      ?>
        <a class="category-chip <?= $isActive ? 'is-active' : '' ?>"
           href="<?= e(CONFIG['base_url']) ?>/search?category=<?= (int)$cat['id'] ?><?= $city ? '&city=' . (int)$city['id'] : '' ?>">
          <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container py-3">
  <h2 class="section-title">
    <?php if ($category): ?><?= e($category['name']) ?> <?php endif; ?>
    <?php if ($city): ?>ne <?= e($city['name']) ?><?php endif; ?>
    <?php if (!$category && !$city): ?>Te gjithe punonjesit<?php endif; ?>
    <small class="text-muted fw-normal" style="font-size: 16px;">(<?= count($providers) ?>)</small>
  </h2>

  <?php if (!$providers): ?>
    <div class="alert alert-light text-center" style="background:#fff; border-radius: 14px; box-shadow: var(--helppy-shadow);">
      <i class="bi bi-search" style="font-size: 32px; color: var(--helppy-muted);"></i>
      <p class="mb-0 mt-2 text-muted">Asnje punetor i gjetur. Provoni te zgjeroni filterat.</p>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($providers as $p): ?>
        <div class="col-12 col-sm-6"><?php View::partial('provider-card', ['p' => $p]); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

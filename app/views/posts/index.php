<?php
$activeType = $filters['type'] ?? null;
$activeCategory = $filters['category_id'] ?? null;
$activeCity = $filters['city_id'] ?? null;
$qsBase = function(array $override) use ($activeType, $activeCategory, $activeCity) {
    $qs = [];
    $type     = array_key_exists('type',     $override) ? $override['type']     : $activeType;
    $category = array_key_exists('category', $override) ? $override['category'] : $activeCategory;
    $city     = array_key_exists('city',     $override) ? $override['city']     : $activeCity;
    if ($type)     $qs['type'] = $type;
    if ($category) $qs['category'] = (int)$category;
    if ($city)     $qs['city'] = (int)$city;
    return $qs ? '?' . http_build_query($qs) : '';
};
?>
<section class="container py-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h1 class="section-title mb-0">Postimet</h1>
    <?php if (Auth::check() && (Auth::role() === 'provider' || Auth::role() === 'client' || Auth::role() === 'admin')): ?>
      <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/posts/create">
        <i class="bi bi-plus-lg"></i> Posto
      </a>
    <?php endif; ?>
  </div>

  <!-- Type tabs -->
  <div class="post-type-tabs mb-3">
    <a class="post-type-tab <?= !$activeType ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['type' => false]) ?>">Të gjitha</a>
    <a class="post-type-tab <?= $activeType === 'offer' ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['type' => 'offer']) ?>">Ofertat</a>
    <a class="post-type-tab <?= $activeType === 'request' ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['type' => 'request']) ?>">Kërkesat</a>
  </div>

  <!-- City + category dropdowns -->
  <form method="get" action="<?= e(CONFIG['base_url']) ?>/posts" class="helppy-search mb-3">
    <?php if ($activeType): ?><input type="hidden" name="type" value="<?= e($activeType) ?>"><?php endif; ?>
    <span class="location-icon"><i class="bi bi-geo-alt-fill"></i></span>
    <select name="city" class="form-select" aria-label="Qyteti">
      <option value="">Të gjitha qytetet</option>
      <?php foreach ($cities as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $activeCity == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="helppy-search-divider d-none d-sm-block"></div>
    <select name="category" class="form-select" aria-label="Kategoria">
      <option value="">Të gjitha kategoritë</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= $activeCategory == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-helppy" type="submit">
      <i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Kërko</span>
    </button>
  </form>

  <!-- Category chip strip -->
  <div class="category-chips mb-3">
    <a class="category-chip <?= !$activeCategory ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['category' => false]) ?>">
      Të gjitha
    </a>
    <?php foreach ($categories as $cat): ?>
      <a class="category-chip <?= $activeCategory == $cat['id'] ? 'is-active' : '' ?>"
         href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['category' => $cat['id']]) ?>">
        <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
        <?= e($cat['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$posts): ?>
    <div class="empty-state">
      <i class="bi bi-postcard"></i>
      <p>Asnjë postim nuk përputhet me filtrat.</p>
      <p><a href="<?= e(CONFIG['base_url']) ?>/posts">Hiq filtrat</a></p>
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

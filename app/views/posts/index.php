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
  <?php
    $activeCityName = '';
    foreach ($cities as $c) { if ($activeCity == $c['id']) { $activeCityName = $c['name']; break; } }
    $activeCategoryName = '';
    foreach ($categories as $cat) { if ($activeCategory == $cat['id']) { $activeCategoryName = $cat['name']; break; } }
  ?>
  <form method="get" action="<?= e(CONFIG['base_url']) ?>/posts" class="helppy-search mb-3">
    <?php if ($activeType): ?><input type="hidden" name="type" value="<?= e($activeType) ?>"><?php endif; ?>
    <span class="location-icon"><i class="bi bi-geo-alt-fill"></i></span>

    <div class="helppy-citypicker<?= $activeCityName ? ' is-selected' : '' ?>" data-citypicker>
      <input type="hidden" name="city" value="<?= $activeCity ? (int)$activeCity : '' ?>" data-citypicker-value>
      <button type="button" class="helppy-citypicker-toggle"
              aria-haspopup="listbox" aria-expanded="false" data-citypicker-toggle>
        <span class="helppy-citypicker-label" data-citypicker-label><?= $activeCityName ? e($activeCityName) : 'Të gjitha qytetet' ?></span>
        <i class="bi bi-chevron-down helppy-citypicker-caret" aria-hidden="true"></i>
      </button>
      <div class="helppy-citypicker-panel" role="listbox" data-citypicker-panel hidden>
        <div class="helppy-citypicker-search">
          <i class="bi bi-search"></i>
          <input type="text" placeholder="Kërko qytetin…" autocomplete="off" data-citypicker-search aria-label="Kërko qytetin">
        </div>
        <ul class="helppy-citypicker-list" data-citypicker-list>
          <li class="helppy-citypicker-item is-clear" role="option" data-citypicker-option data-value="" tabindex="-1">
            <i class="bi bi-globe2"></i> Të gjitha qytetet
          </li>
          <?php foreach ($cities as $c): ?>
            <li class="helppy-citypicker-item<?= $activeCity == $c['id'] ? ' is-selected' : '' ?>" role="option"
                data-citypicker-option data-value="<?= (int)$c['id'] ?>" data-name="<?= e(mb_strtolower($c['name'])) ?>" tabindex="-1">
              <i class="bi bi-geo-alt"></i> <?= e($c['name']) ?>
            </li>
          <?php endforeach; ?>
          <li class="helppy-citypicker-empty" data-citypicker-empty hidden>
            <i class="bi bi-emoji-frown"></i> Nuk u gjet asnjë qytet.
          </li>
        </ul>
      </div>
    </div>

    <div class="helppy-search-divider d-none d-sm-block"></div>

    <div class="helppy-citypicker<?= $activeCategoryName ? ' is-selected' : '' ?>" data-citypicker>
      <input type="hidden" name="category" value="<?= $activeCategory ? (int)$activeCategory : '' ?>" data-citypicker-value>
      <button type="button" class="helppy-citypicker-toggle"
              aria-haspopup="listbox" aria-expanded="false" data-citypicker-toggle>
        <span class="helppy-citypicker-label" data-citypicker-label><?= $activeCategoryName ? e($activeCategoryName) : 'Të gjitha kategoritë' ?></span>
        <i class="bi bi-chevron-down helppy-citypicker-caret" aria-hidden="true"></i>
      </button>
      <div class="helppy-citypicker-panel" role="listbox" data-citypicker-panel hidden>
        <div class="helppy-citypicker-search">
          <i class="bi bi-search"></i>
          <input type="text" placeholder="Kërko kategorinë…" autocomplete="off" data-citypicker-search aria-label="Kërko kategorinë">
        </div>
        <ul class="helppy-citypicker-list" data-citypicker-list>
          <li class="helppy-citypicker-item is-clear" role="option" data-citypicker-option data-value="" tabindex="-1">
            <i class="bi bi-grid"></i> Të gjitha kategoritë
          </li>
          <?php foreach ($categories as $cat): ?>
            <li class="helppy-citypicker-item<?= $activeCategory == $cat['id'] ? ' is-selected' : '' ?>" role="option"
                data-citypicker-option data-value="<?= (int)$cat['id'] ?>" data-name="<?= e(mb_strtolower($cat['name'])) ?>" tabindex="-1">
              <i class="bi <?= !empty($cat['icon']) ? e($cat['icon']) : 'bi-tag' ?>"></i> <?= e($cat['name']) ?>
            </li>
          <?php endforeach; ?>
          <li class="helppy-citypicker-empty" data-citypicker-empty hidden>
            <i class="bi bi-emoji-frown"></i> Nuk u gjet asnjë kategori.
          </li>
        </ul>
      </div>
    </div>

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

  <?php
    $hasExactPosts  = !empty($posts);
    $hasNearbyPosts = ($selected_city && !$hasExactPosts && !empty($nearby_posts));
    $registerUrl    = CONFIG['base_url'] . '/register';
  ?>

  <?php if ($hasExactPosts): ?>
    <!-- Exact-city results only. -->
    <div class="cards-shell">
      <div class="row g-3">
        <?php foreach ($posts as $p): ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <?php View::partial('post-card', ['p' => $p]); ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  <?php elseif ($hasNearbyPosts): ?>
    <div class="alert alert-info nearby-banner" role="status">
      <div class="d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div class="flex-grow-1">
          <strong>
            Asnjë postim në <?= e($selected_city['name']) ?><?= $activeCategoryName ? ' për ' . e(strtolower($activeCategoryName)) : '' ?>.
          </strong>
          <div class="text-muted small mt-1">
            Më poshtë janë postime afër vendit tuaj<?= $nearby_district ? ', në rajonin e ' . e($nearby_district) : '' ?>.
          </div>
        </div>
        <button type="button" class="btn btn-helppy btn-sm flex-shrink-0"
                data-helppy-share
                data-share-title="Helppy.com"
                data-share-text="Bëhu i pari në <?= e($selected_city['name']) ?> në Helppy.com"
                data-share-url="<?= e($registerUrl) ?>">
          <i class="bi bi-share"></i> Fto dikë
        </button>
      </div>
    </div>

    <h3 class="section-title h5 mt-3 mb-2">
      <i class="bi bi-pin-map"></i> Postime afër
      <small class="text-muted fw-normal" style="font-size: 14px;">(<?= count($nearby_posts) ?>)</small>
    </h3>
    <div class="cards-shell">
      <div class="row g-3">
        <?php foreach ($nearby_posts as $p): ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <?php View::partial('post-card', ['p' => $p]); ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  <?php else: ?>
    <div class="empty-state">
      <i class="bi bi-postcard"></i>
      <?php if ($selected_city): ?>
        <p>Asnjë postim në <?= e($selected_city['name']) ?>, dhe asnjë në rajonin përreth.</p>
        <p>
          <a class="btn btn-helppy" href="<?= e($registerUrl) ?>">
            <i class="bi bi-person-plus"></i> Bëhu i pari në <?= e($selected_city['name']) ?>
          </a>
          <button type="button" class="btn btn-helppy-outline ms-2"
                  data-helppy-share
                  data-share-title="Helppy.com"
                  data-share-text="Po kërkoj njerëz në <?= e($selected_city['name']) ?> në Helppy.com — regjistrohu!"
                  data-share-url="<?= e($registerUrl) ?>">
            <i class="bi bi-share"></i> Fto dikë
          </button>
        </p>
      <?php else: ?>
        <p>Asnjë postim nuk përputhet me filtrat.</p>
        <p><a href="<?= e(CONFIG['base_url']) ?>/posts">Hiq filtrat</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

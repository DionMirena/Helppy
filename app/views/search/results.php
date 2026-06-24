<section class="hero">
  <div class="container">
    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="helppy-search">
      <span class="location-icon"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control helppy-search-q"
             placeholder="Kërko emrin e punëtorit…" autocomplete="off"
             value="<?= e((string)($query ?? '')) ?>"
             aria-label="Kërko sipas emrit">
      <div class="helppy-search-divider d-none d-sm-block"></div>

      <span class="location-icon"><i class="bi bi-geo-alt-fill"></i></span>

      <div class="helppy-citypicker<?= $city ? ' is-selected' : '' ?>" data-citypicker>
        <input type="hidden" name="city" value="<?= $city ? (int)$city['id'] : '' ?>" data-citypicker-value>
        <button type="button" class="helppy-citypicker-toggle"
                aria-haspopup="listbox" aria-expanded="false" data-citypicker-toggle>
          <span class="helppy-citypicker-label" data-citypicker-label><?= $city ? e($city['name']) : 'Te gjitha qytetet' ?></span>
          <i class="bi bi-chevron-down helppy-citypicker-caret" aria-hidden="true"></i>
        </button>
        <div class="helppy-citypicker-panel" role="listbox" data-citypicker-panel hidden>
          <div class="helppy-citypicker-search">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Kërko qytetin…" autocomplete="off" data-citypicker-search aria-label="Kërko qytetin">
          </div>
          <ul class="helppy-citypicker-list" data-citypicker-list>
            <li class="helppy-citypicker-item is-clear" role="option" data-citypicker-option data-value="" tabindex="-1">
              <i class="bi bi-globe2"></i> Te gjitha qytetet
            </li>
            <?php foreach ($cities as $c): ?>
              <li class="helppy-citypicker-item<?= $city && $city['id']==$c['id'] ? ' is-selected' : '' ?>" role="option"
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

      <div class="helppy-citypicker<?= $category ? ' is-selected' : '' ?>" data-citypicker>
        <input type="hidden" name="category" value="<?= $category ? (int)$category['id'] : '' ?>" data-citypicker-value>
        <button type="button" class="helppy-citypicker-toggle"
                aria-haspopup="listbox" aria-expanded="false" data-citypicker-toggle>
          <span class="helppy-citypicker-label" data-citypicker-label><?= $category ? e($category['name']) : 'Te gjitha kategorite' ?></span>
          <i class="bi bi-chevron-down helppy-citypicker-caret" aria-hidden="true"></i>
        </button>
        <div class="helppy-citypicker-panel" role="listbox" data-citypicker-panel hidden>
          <div class="helppy-citypicker-search">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Kërko kategorinë…" autocomplete="off" data-citypicker-search aria-label="Kërko kategorinë">
          </div>
          <ul class="helppy-citypicker-list" data-citypicker-list>
            <li class="helppy-citypicker-item is-clear" role="option" data-citypicker-option data-value="" tabindex="-1">
              <i class="bi bi-grid"></i> Te gjitha kategorite
            </li>
            <?php foreach ($categories as $cat): ?>
              <li class="helppy-citypicker-item<?= $category && $category['id']==$cat['id'] ? ' is-selected' : '' ?>" role="option"
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

<?php
  // The grid only ever shows workers from the EXACT picked city. The nearby
  // list is shown as a clearly separate section below, only when the exact
  // city had zero matches.
  $hasExact     = !empty($providers);
  $hasNearby    = ($city && !$hasExact && !empty($nearby_providers));
  $registerUrl  = CONFIG['base_url'] . '/register';
?>
<section class="container py-3">
  <h2 class="section-title">
    <?php if ($category): ?><?= e($category['name']) ?> <?php endif; ?>
    <?php if ($city): ?>ne <?= e($city['name']) ?><?php endif; ?>
    <?php if (!$category && !$city): ?>Te gjithe punonjesit<?php endif; ?>
    <small class="text-muted fw-normal" style="font-size: 16px;">(<?= count($providers) ?>)</small>
  </h2>

  <?php if ($hasExact): ?>
    <!-- Exact-city results only. No nearby pollution. -->
    <div class="cards-shell">
      <div class="row g-3">
        <?php foreach ($providers as $p): ?>
          <div class="col-12 col-sm-6"><?php View::partial('provider-card', ['p' => $p]); ?></div>
        <?php endforeach; ?>
      </div>
    </div>

  <?php elseif ($hasNearby): ?>
    <!-- Zero in the exact city, but the rest of the district has some. -->
    <div class="alert alert-info nearby-banner" role="status">
      <div class="d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div class="flex-grow-1">
          <strong>
            Nuk ka punëtorë në <?= e($city['name']) ?><?= $category ? ' për ' . e(strtolower($category['name'])) : '' ?>.
          </strong>
          <div class="text-muted small mt-1">
            Më poshtë janë punëtorë afër vendit tuaj<?= $nearby_district ? ', në rajonin e ' . e($nearby_district) : '' ?>.
          </div>
        </div>
        <button type="button" class="btn btn-helppy btn-sm flex-shrink-0"
                data-helppy-share
                data-share-title="Helppy.com"
                data-share-text="Bëhu i pari punëtor në <?= e($city['name']) ?> në Helppy.com"
                data-share-url="<?= e($registerUrl) ?>">
          <i class="bi bi-share"></i> Fto dikë
        </button>
      </div>
    </div>

    <h3 class="section-title h5 mt-3 mb-2">
      <i class="bi bi-pin-map"></i> Punëtorë afër
      <small class="text-muted fw-normal" style="font-size: 14px;">(<?= count($nearby_providers) ?>)</small>
    </h3>
    <div class="cards-shell">
      <div class="row g-3">
        <?php foreach ($nearby_providers as $p): ?>
          <div class="col-12 col-sm-6"><?php View::partial('provider-card', ['p' => $p]); ?></div>
        <?php endforeach; ?>
      </div>
    </div>

  <?php else: ?>
    <!-- Nothing here, nothing nearby. -->
    <div class="alert alert-light text-center" style="background:#fff; border-radius: 14px; box-shadow: var(--helppy-shadow);">
      <i class="bi bi-search" style="font-size: 32px; color: var(--helppy-muted);"></i>
      <p class="mb-2 mt-2 text-muted">
        <?php if ($city): ?>
          Nuk u gjet asnjë punëtor në <?= e($city['name']) ?><?= $category ? ' për ' . e(strtolower($category['name'])) : '' ?>,
          dhe asnjë në rajonin përreth.
        <?php else: ?>
          Asnje punetor i gjetur. Provoni te zgjeroni filterat.
        <?php endif; ?>
      </p>
      <?php if ($city): ?>
        <p class="mb-0">
          <a class="btn btn-helppy" href="<?= e($registerUrl) ?>">
            <i class="bi bi-person-plus"></i> Bëhu i pari në <?= e($city['name']) ?>
          </a>
          <button type="button" class="btn btn-helppy-outline ms-2"
                  data-helppy-share
                  data-share-title="Helppy.com"
                  data-share-text="Po kërkoj <?= $category ? e(strtolower($category['name'])) . ' në ' : 'një punëtor në ' ?><?= e($city['name']) ?> në Helppy.com — regjistrohu!"
                  data-share-url="<?= e($registerUrl) ?>">
            <i class="bi bi-share"></i> Fto dikë
          </button>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

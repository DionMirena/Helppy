<section class="hero">
  <div class="container">
    <h1>Keni nevoje per nje punetor per problemin ne shtepi?</h1>

    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="helppy-search">
      <span class="location-icon"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control helppy-search-q"
             placeholder="Kërko emrin e punëtorit…" autocomplete="off"
             aria-label="Kërko sipas emrit">
      <div class="helppy-search-divider d-none d-sm-block"></div>

      <span class="location-icon"><i class="bi bi-geo-alt-fill"></i></span>

      <div class="helppy-citypicker" data-citypicker>
        <input type="hidden" name="city" value="" data-citypicker-value>
        <button type="button" class="helppy-citypicker-toggle"
                aria-haspopup="listbox" aria-expanded="false" data-citypicker-toggle>
          <span class="helppy-citypicker-label" data-citypicker-label>Zgjidh lokacionin tuaj</span>
          <i class="bi bi-chevron-down helppy-citypicker-caret" aria-hidden="true"></i>
        </button>

        <div class="helppy-citypicker-panel" role="listbox" data-citypicker-panel hidden>
          <div class="helppy-citypicker-search">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Kërko qytetin…" autocomplete="off"
                   data-citypicker-search aria-label="Kërko qytetin">
          </div>
          <ul class="helppy-citypicker-list" data-citypicker-list>
            <li class="helppy-citypicker-item is-clear" role="option"
                data-citypicker-option data-value="" tabindex="-1">
              <i class="bi bi-globe2"></i> Të gjitha lokacionet
            </li>
            <?php foreach ($cities as $c): ?>
              <li class="helppy-citypicker-item" role="option"
                  data-citypicker-option
                  data-value="<?= (int)$c['id'] ?>"
                  data-name="<?= e(mb_strtolower($c['name'])) ?>"
                  tabindex="-1">
                <i class="bi bi-geo-alt"></i> <?= e($c['name']) ?>
              </li>
            <?php endforeach; ?>
            <li class="helppy-citypicker-empty" data-citypicker-empty hidden>
              <i class="bi bi-emoji-frown"></i> Nuk u gjet asnjë qytet.
            </li>
          </ul>
        </div>
      </div>

      <button class="btn btn-helppy" type="submit" aria-label="Kerko">
        <i class="bi bi-search"></i>
      </button>
    </form>

    <?php
      $baseUrl = e(CONFIG['base_url']);
    ?>
    <div class="category-strip" data-category-strip>
      <?php if ($openCat): ?>
        <!-- Drill-down mode: show the picked parent + its children, hide siblings -->
        <div class="category-chips category-chips-drilled">
          <a class="category-chip is-back" href="<?= $baseUrl ?>/">
            <i class="bi bi-arrow-left"></i> Të gjitha
          </a>
          <span class="category-chip is-active" aria-current="true">
            <?php if (!empty($openCat['icon'])): ?><i class="bi <?= e($openCat['icon']) ?>"></i><?php endif; ?>
            <?= e($openCat['name']) ?>
          </span>
          <?php if (!empty($openCatChildren)): ?>
            <?php foreach ($openCatChildren as $child): ?>
              <a class="category-chip category-chip-child"
                 href="<?= $baseUrl ?>/search?category=<?= (int)$child['id'] ?>">
                <?php if (!empty($child['icon'])): ?><i class="bi <?= e($child['icon']) ?>"></i><?php endif; ?>
                <?= e($child['name']) ?>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <a class="category-chip"
               href="<?= $baseUrl ?>/search?category=<?= (int)$openCat['id'] ?>">
              <i class="bi bi-search"></i> Shfaq punëtorë
            </a>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <!-- Umbrella categories only (those with children). Everything else
             remains discoverable via the Kërko kategori search panel. -->
        <div class="category-chips category-chips-desktop">
          <?php foreach ($topCategories as $cat): ?>
            <a class="category-chip has-children" href="<?= $baseUrl ?>/?cat=<?= (int)$cat['id'] ?>">
              <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
              <?= e($cat['name']) ?>
              <i class="bi bi-chevron-right has-children-caret"></i>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="category-dropdown-mobile" data-cat-dropdown>
          <button type="button" class="category-dropdown-toggle" data-cat-dropdown-toggle
                  aria-haspopup="true" aria-expanded="false">
            <i class="bi bi-grid"></i>
            <span>Zgjidh kategorinë</span>
            <i class="bi bi-chevron-down ms-auto"></i>
          </button>
          <ul class="category-dropdown-menu" data-cat-dropdown-menu hidden>
            <?php foreach ($topCategories as $cat): ?>
              <li>
                <a class="category-dropdown-item" href="<?= $baseUrl ?>/?cat=<?= (int)$cat['id'] ?>">
                  <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
                  <?= e($cat['name']) ?>
                  <i class="bi bi-chevron-right ms-auto"></i>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- "Kërko kategori" sits on the RIGHT side of the strip. -->
      <button type="button" class="category-search-btn category-search-btn-right"
              data-cat-search-toggle aria-haspopup="true" aria-expanded="false">
        <i class="bi bi-search"></i> Kërko kategori
      </button>

      <!-- Search panel: appears when "Kërko kategori" is clicked -->
      <div class="category-search-panel" data-cat-search-panel hidden>
        <div class="category-search-input-wrap">
          <i class="bi bi-search"></i>
          <input type="text" placeholder="Kërko kategori (p.sh. pastrim, elektrike, çelje)…"
                 autocomplete="off" data-cat-search-input aria-label="Kërko kategori">
          <button type="button" class="category-search-close" data-cat-search-close title="Mbyll">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <ul class="category-search-results" data-cat-search-results>
          <?php foreach ($categories as $cat):
            $parentName = '';
            if (!empty($cat['parent_id'])) {
              foreach ($categories as $maybe) {
                if ((int)$maybe['id'] === (int)$cat['parent_id']) { $parentName = (string)$maybe['name']; break; }
              }
            }
            $href = empty($cat['parent_id']) && in_array((int)$cat['id'], array_column($topCategories, 'id'))
                  ? $baseUrl . '/?cat=' . (int)$cat['id']
                  : $baseUrl . '/search?category=' . (int)$cat['id'];
          ?>
            <li class="category-search-item"
                data-cat-name="<?= e(mb_strtolower($cat['name'] . ' ' . $parentName)) ?>">
              <a href="<?= $href ?>">
                <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
                <span><?= e($cat['name']) ?></span>
                <?php if ($parentName): ?><small class="text-muted ms-auto"><?= e($parentName) ?></small><?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="category-search-empty text-muted small text-center py-3" data-cat-search-empty hidden>
          <i class="bi bi-emoji-frown"></i> Asnjë kategori nuk u gjet.
        </div>
      </div>
    </div>
  </div>
</section>

<section class="container py-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
    <h2 class="section-title mb-0">
      <?php if ($activeType === 'company'): ?>Kompanitë
      <?php elseif ($activeType === 'person'): ?>Punëtorët
      <?php else: ?>Punonjesit me te afert<?php endif; ?>
    </h2>
    <span class="text-muted small">Po shfaqen <span data-providers-shown><?= count($featured) ?></span> / <?= (int)$totalCount ?></span>
  </div>

  <?php
    $baseUrl = e(CONFIG['base_url']);
    $isAll     = $activeType === '';
    $isPerson  = $activeType === 'person';
    $isCompany = $activeType === 'company';
  ?>
  <div class="provider-type-toggle" role="tablist" aria-label="Filtro sipas llojit">
    <a class="provider-type-btn<?= $isAll     ? ' is-active' : '' ?>" role="tab"
       aria-selected="<?= $isAll ? 'true' : 'false' ?>"
       href="<?= $baseUrl ?>/">
      <i class="bi bi-grid-fill"></i> Të gjithë
    </a>
    <a class="provider-type-btn<?= $isPerson  ? ' is-active' : '' ?>" role="tab"
       aria-selected="<?= $isPerson ? 'true' : 'false' ?>"
       href="<?= $baseUrl ?>/?type=person">
      <i class="bi bi-person-fill"></i> Punëtor
    </a>
    <a class="provider-type-btn<?= $isCompany ? ' is-active' : '' ?>" role="tab"
       aria-selected="<?= $isCompany ? 'true' : 'false' ?>"
       href="<?= $baseUrl ?>/?type=company">
      <i class="bi bi-building-fill"></i> Kompani
    </a>
  </div>

  <?php if (!$featured): ?>
    <p class="text-muted">Asnje punetor i regjistruar ende.</p>
  <?php else: ?>
    <div class="providers-scroller"
         data-providers-scroller
         data-next-offset="<?= (int)$pageSize ?>"
         data-total="<?= (int)$totalCount ?>"
         data-type="<?= e($activeType) ?>">
      <div class="row g-3" data-providers-grid>
        <?php foreach ($featured as $p): ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <?php View::partial('provider-card', ['p' => $p]); ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="providers-more">
        <button type="button" class="btn btn-helppy load-more-btn"
                data-providers-load-more
                <?= (int)$totalCount <= (int)$pageSize ? 'hidden' : '' ?>>
          <img class="load-more-logo" src="<?= e(CONFIG['base_url']) ?>/assets/img/logo.svg" alt="">
          <span class="load-more-label">Shfaq më shumë</span>
          <span class="load-more-loading" hidden>
            <i class="bi bi-arrow-clockwise"></i> Duke shfaqur të tjerë...
          </span>
        </button>

        <div class="providers-end" data-providers-end hidden>
          <i class="bi bi-check2-circle"></i> Të gjithë janë shfaqur.
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>

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
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
    <h2 class="section-title mb-0">Punonjesit me te afert</h2>
    <span class="text-muted small">Po shfaqen <span data-providers-shown><?= count($featured) ?></span> / <?= (int)$totalCount ?></span>
  </div>

  <?php if (!$featured): ?>
    <p class="text-muted">Asnje punetor i regjistruar ende.</p>
  <?php else: ?>
    <div class="providers-scroller"
         data-providers-scroller
         data-next-offset="<?= (int)$pageSize ?>"
         data-total="<?= (int)$totalCount ?>">
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

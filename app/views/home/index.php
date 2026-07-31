<?php $baseUrl = e(CONFIG['base_url']); ?>

<!-- ========================= HERO ========================= -->
<section class="home-hero">
  <div class="container">
    <div class="home-hero-grid">

      <div class="home-hero-copy">
        <h1 class="home-hero-title">Gjeje mjeshtrin e duhur<br>për shtëpinë tënde</h1>
        <p class="home-hero-sub">Mijëra mjeshtër të besueshëm, pranë teje.</p>

        <form method="get" action="<?= $baseUrl ?>/search" class="home-hero-search">

          <div class="home-hero-field">
            <span class="home-hero-label">Çfarë shërbimi kërkon?</span>
            <div class="helppy-catpick" data-catpick>
              <input type="hidden" name="category" value="" data-catpick-value>
              <button type="button" class="helppy-catpick-toggle" data-catpick-toggle
                      aria-haspopup="listbox" aria-expanded="false">
                <i class="bi bi-grid helppy-catpick-icon" aria-hidden="true"></i>
                <span class="helppy-catpick-label" data-catpick-label>Zgjidh shërbimin</span>
                <i class="bi bi-chevron-down helppy-catpick-caret" aria-hidden="true"></i>
              </button>
              <div class="helppy-catpick-panel" data-catpick-panel hidden>
                <div class="helppy-catpick-search">
                  <i class="bi bi-search"></i>
                  <input type="text" placeholder="Kërko shërbimin…" autocomplete="off"
                         data-catpick-input aria-label="Kërko shërbimin">
                </div>
                <ul class="helppy-catpick-list" data-catpick-list>
                  <?php foreach ($categories as $cat):
                    $cpParent = '';
                    if (!empty($cat['parent_id'])) {
                      foreach ($categories as $maybe) {
                        if ((int)$maybe['id'] === (int)$cat['parent_id']) { $cpParent = $maybe['name']; break; }
                      }
                    }
                  ?>
                    <li class="helppy-catpick-item" tabindex="-1" role="option"
                        data-catpick-option
                        data-value="<?= (int)$cat['id'] ?>"
                        data-name="<?= e(mb_strtolower($cat['name'] . ' ' . $cpParent)) ?>">
                      <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
                      <span><?= e($cat['name']) ?></span>
                      <?php if ($cpParent): ?><small><?= e($cpParent) ?></small><?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
                <div class="helppy-catpick-empty" data-catpick-empty hidden>
                  <i class="bi bi-emoji-frown"></i> Asnjë shërbim nuk u gjet.
                </div>
              </div>
            </div>
          </div>

          <div class="home-hero-divider d-none d-sm-block"></div>

          <div class="home-hero-field">
            <span class="home-hero-label">Në cilin qytet?</span>
            <div class="helppy-citypicker" data-citypicker>
              <input type="hidden" name="city" value="" data-citypicker-value>
              <button type="button" class="helppy-citypicker-toggle"
                      aria-haspopup="listbox" aria-expanded="false" data-citypicker-toggle>
                <span class="helppy-citypicker-label" data-citypicker-label>Zgjidh qytetin</span>
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
                    <i class="bi bi-globe2"></i> Të gjitha qytetet
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
          </div>

          <button class="btn-hero-search" type="submit">
            <i class="bi bi-search"></i> Kërko mjeshtër
          </button>

        </form>

        <ul class="home-hero-trust">
          <li><i class="bi bi-shield-check-fill"></i> Mjeshtër të verifikuar</li>
          <li><i class="bi bi-star-fill"></i> Vlerësime reale</li>
          <li><i class="bi bi-lock-fill"></i> Të sigurt dhe të besueshëm</li>
        </ul>
      </div><!-- /.home-hero-copy -->

      <div class="home-hero-media" aria-hidden="true">
        <div class="hero-illustration">
          <div class="hero-illus-badge"><i class="bi bi-person-workspace"></i></div>
          <span class="hero-float hero-float-1"><i class="bi bi-lightning-charge-fill"></i></span>
          <span class="hero-float hero-float-2"><i class="bi bi-hammer"></i></span>
          <span class="hero-float hero-float-3"><i class="bi bi-droplet-fill"></i></span>
          <span class="hero-float hero-float-4"><i class="bi bi-wrench-adjustable"></i></span>
        </div>
      </div><!-- /.home-hero-media -->

    </div><!-- /.home-hero-grid -->
  </div>
</section>

<!-- =============== SHËRBIMET MË TË KËRKUARA =============== -->
<section class="home-section">
  <div class="container">
    <div class="home-section-head">
      <h2 class="home-section-title">Shërbimet më të kërkuara</h2>
      <a class="home-section-link" href="<?= $baseUrl ?>/search">Shiko të gjitha shërbimet <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="services-scroller-wrap">
      <button class="services-arrow services-arrow-prev" id="svcPrev" aria-label="E mëparshme">
        <i class="bi bi-chevron-left"></i>
      </button>
      <div class="services-scroller" id="svcScroller">
        <?php foreach ($topCategories as $cat): ?>
          <a class="service-card" href="<?= $baseUrl ?>/search?category=<?= (int)$cat['id'] ?>">
            <span class="service-icon">
              <i class="bi <?= e(!empty($cat['icon']) ? $cat['icon'] : 'bi-tools') ?>"></i>
            </span>
            <span class="service-name"><?= e($cat['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <button class="services-arrow services-arrow-next" id="svcNext" aria-label="E ardhshme">
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>
  </div>
</section>

<!-- ===================== SI FUNKSIONON ===================== -->
<section class="home-how">
  <div class="container">
    <h2 class="home-section-title text-center">Si funksionon?</h2>
    <span class="home-how-divider"></span>
    <div class="how-columns">

      <div class="how-col">
        <h3 class="how-col-title">Për klientët</h3>
        <div class="how-steps">
          <div class="how-step">
            <span class="how-step-num">1</span>
            <div class="how-step-body">
              <h4>Zgjidh shërbimin dhe qytetin</h4>
              <p>Gjej shpejt shërbimin që të nevojitet, sipas qytetit tënd.</p>
            </div>
          </div>
          <div class="how-step">
            <span class="how-step-num">2</span>
            <div class="how-step-body">
              <h4>Krahaso mjeshtrit dhe vlerësimet</h4>
              <p>Shiko profilet, çmimet dhe komentet e klientëve të tjerë.</p>
            </div>
          </div>
          <div class="how-step">
            <span class="how-step-num">3</span>
            <div class="how-step-body">
              <h4>Kontakto ose rezervo mieshtrin</h4>
              <p>Dërgo kërkesën ose reservo direkt nga platforma.</p>
            </div>
          </div>
        </div>
        <a class="btn-hero-search how-cta" href="<?= $baseUrl ?>/search"><i class="bi bi-search"></i> Gjej mjeshtër tani</a>
      </div>

      <div class="how-col how-col-provider">
        <h3 class="how-col-title">Për mjeshttit</h3>
        <div class="how-steps">
          <div class="how-step">
            <span class="how-step-num">1</span>
            <div class="how-step-body">
              <h4>Krijo profilin</h4>
              <p>Regjistrohu dhe krijo profilin tënd profesional falas.</p>
            </div>
          </div>
          <div class="how-step">
            <span class="how-step-num">2</span>
            <div class="how-step-body">
              <h4>Shto shërbimet dhe punët e tua</h4>
              <p>Prezanto përvojën, shërbimet dhe foto punësh të realizuara.</p>
            </div>
          </div>
          <div class="how-step">
            <span class="how-step-num">3</span>
            <div class="how-step-body">
              <h4>Prano kërkesa nga klientët</h4>
              <p>Merr kërkesa dhe rezervime direkt në platformë.</p>
            </div>
          </div>
        </div>
        <a class="btn-hero-outline how-cta" href="<?= $baseUrl ?>/register"><i class="bi bi-person-plus"></i> Regjistrohu si mjeshtër</a>
      </div>

    </div>
  </div>
</section>

<!-- =========== MJESHTËR TË VERIFIKUAR PRANË TEJE =========== -->
<section class="home-section">
  <div class="container">
    <div class="home-section-head">
      <h2 class="home-section-title">Mjeshtër të verifikuar pranë teje</h2>
      <a class="home-section-link" href="<?= $baseUrl ?>/search">Shiko të gjithë <i class="bi bi-arrow-right"></i></a>
    </div>

    <?php
      $isAll     = $activeType === '';
      $isPerson  = $activeType === 'person';
      $isCompany = $activeType === 'company';
    ?>
    <div class="provider-type-toggle mb-4" role="tablist" aria-label="Filtro sipas llojit">
      <a class="provider-type-btn<?= $isAll ? ' is-active' : '' ?>" role="tab"
         aria-selected="<?= $isAll ? 'true' : 'false' ?>" href="<?= $baseUrl ?>/">
        <i class="bi bi-grid-fill"></i> Të gjithë
      </a>
      <a class="provider-type-btn<?= $isPerson ? ' is-active' : '' ?>" role="tab"
         aria-selected="<?= $isPerson ? 'true' : 'false' ?>" href="<?= $baseUrl ?>/?type=person">
        <i class="bi bi-person-fill"></i> Punëtor
      </a>
      <a class="provider-type-btn<?= $isCompany ? ' is-active' : '' ?>" role="tab"
         aria-selected="<?= $isCompany ? 'true' : 'false' ?>" href="<?= $baseUrl ?>/?type=company">
        <i class="bi bi-building-fill"></i> Kompani
      </a>
    </div>

    <?php if (!$featured): ?>
      <p class="text-muted">Asnjë punëtor i regjistruar ende.</p>
    <?php else: ?>
      <div class="home-providers-scroller">
        <?php foreach ($featured as $p):
          $photoUrl = !empty($p['photo'])
            ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
            : CONFIG['base_path'] . '/assets/img/default-avatar.svg';
          $avg = isset($p['avg_rating']) && $p['avg_rating'] !== null ? round((float)$p['avg_rating'], 1) : null;
          $phoneRaw = !empty($p['phone']) ? preg_replace('/[^0-9+]/', '', $p['phone']) : '';
          $isOnline = Presence::isOnline($p['last_seen_at'] ?? null);
        ?>
          <div class="home-provider-card">
            <div class="home-pcard-photo-wrap">
              <img src="<?= e($photoUrl) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
              <?php if ($isOnline): ?>
                <span class="home-pcard-badge">Online</span>
              <?php elseif (!empty($p['is_premium'])): ?>
                <span class="home-pcard-badge" style="background:#F5A623">Premium</span>
              <?php else: ?>
                <span class="home-pcard-badge" style="background:#6b7280">Verifikuar</span>
              <?php endif; ?>
            </div>
            <div class="home-pcard-body">
              <p class="home-pcard-name"><?= e($p['name']) ?></p>
              <p class="home-pcard-meta">
                <?= e($p['profession']) ?>
                <?php if (!empty($p['city'])): ?> · <?= e($p['city']) ?><?php endif; ?>
              </p>
              <div class="home-pcard-stars">
                <?php if ($avg !== null): ?>
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="bi <?= $i <= round($avg) ? 'bi-star-fill' : 'bi-star' ?>" style="font-size:12px"></i>
                  <?php endfor; ?>
                  <span><?= e((string)$avg) ?></span>
                <?php else: ?>
                  <span style="color:var(--helppy-muted)">Pa vlerësime</span>
                <?php endif; ?>
              </div>
              <p class="home-pcard-rate">
                <?php if (isset($p['hourly_rate']) && $p['hourly_rate'] !== null): ?>
                  Nga <strong>€<?= e(rtrim(rtrim(number_format((float)$p['hourly_rate'], 2, '.', ''), '0'), '.')) ?></strong> / shërbim
                <?php else: ?>
                  <strong>Çmimi me marrëveshje</strong>
                <?php endif; ?>
              </p>
              <div class="home-pcard-actions">
                <a class="home-pcard-btn home-pcard-btn-outline"
                   href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>">
                  Shiko profilin
                </a>
                <?php if (!empty($p['phone'])): ?>
                  <a class="home-pcard-btn home-pcard-btn-fill"
                     href="tel:<?= e($phoneRaw) ?>">
                    Kontakto
                  </a>
                <?php else: ?>
                  <a class="home-pcard-btn home-pcard-btn-fill"
                     href="<?= e(CONFIG['base_url']) ?>/chat/with/<?= (int)$p['id'] ?>">
                    Bisedo
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ((int)$totalCount > (int)$pageSize): ?>
        <div class="text-center mt-4">
          <a class="btn-hero-outline" href="<?= $baseUrl ?>/search">
            <i class="bi bi-arrow-right-circle"></i> Shiko të gjithë punëtorët
          </a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ==================== CTA BANNER ==================== -->
<section class="home-cta">
  <div class="container">
    <div class="home-cta-inner">
      <div class="home-cta-copy">
        <span class="home-cta-icon"><i class="bi bi-house-heart-fill"></i></span>
        <div>
          <h2>Gati për ta gjetur<br>mieshtrin e duhur?</h2>
          <p>Mijëra klientë na besojnë çdo ditë.</p>
        </div>
      </div>
      <div class="home-cta-actions">
        <a class="btn-hero-search" href="<?= $baseUrl ?>/search"><i class="bi bi-search"></i> Gjej mjeshtër tani</a>
        <a class="btn-hero-outline is-light" href="<?= $baseUrl ?>/register"><i class="bi bi-person-plus"></i> Regjistrohu si mjeshtër</a>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  var scroller = document.getElementById('svcScroller');
  var btnPrev  = document.getElementById('svcPrev');
  var btnNext  = document.getElementById('svcNext');
  if (!scroller) return;

  var cardWidth = function () {
    var card = scroller.querySelector('.service-card');
    if (!card) return 150;
    var style = getComputedStyle(scroller);
    return card.offsetWidth + parseInt(style.gap || 14);
  };

  function scroll(dir) {
    scroller.scrollBy({ left: dir * cardWidth() * 3, behavior: 'smooth' });
  }

  btnPrev.addEventListener('click', function () { scroll(-1); });
  btnNext.addEventListener('click', function () { scroll(1); });

  function updateArrows() {
    btnPrev.disabled = scroller.scrollLeft <= 2;
    btnNext.disabled = scroller.scrollLeft + scroller.offsetWidth >= scroller.scrollWidth - 2;
  }

  scroller.addEventListener('scroll', updateArrows, { passive: true });
  updateArrows();
})();
</script>

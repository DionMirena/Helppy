<?php
$baseUrl = e(CONFIG['base_url']);
$vids    = $videos ?? [];

function hiwVideo(string $slot, array $vids, string $fallbackLabel): string {
    $row = $vids[$slot] ?? null;
    $url = $row['video_url'] ?? '';
    if ($url === '') {
        return '<div class="hiw-video-placeholder"><i class="bi bi-play-circle-fill"></i><span>' . e($fallbackLabel) . '</span></div>';
    }
    $embedUrl = TutorialVideo::toEmbed($url);
    if (TutorialVideo::isDirect($url)) {
        return '<video src="' . e($embedUrl) . '" controls class="hiw-video-player"></video>';
    }
    return '<iframe src="' . e($embedUrl) . '" allowfullscreen loading="lazy" class="hiw-video-player"></iframe>';
}
?>

<section class="hiw-hero">
  <div class="container">
    <h1 class="hiw-hero-title">Si funksionon Helppy?</h1>
    <p class="hiw-hero-sub">Shiko videot hap pas hapi dhe fillo të përdorësh platformën sot.</p>
  </div>
</section>

<div class="container hiw-container">

  <!-- ── FOR CLIENTS ── -->
  <div class="hiw-group">
    <div class="hiw-group-header">
      <span class="hiw-group-badge hiw-badge-navy">Për klientët</span>
      <h2 class="hiw-group-title">Si të gjesh dhe të kontaktosh një mjeshtër</h2>
    </div>

    <div class="hiw-cards">

      <div class="hiw-card">
        <div class="hiw-card-num">1</div>
        <div class="hiw-video-wrap">
          <?= hiwVideo('client_register', $vids, 'Si të regjistrohesh') ?>
        </div>
        <div class="hiw-card-body">
          <h3 class="hiw-card-title">Si të regjistrohesh</h3>
          <p class="hiw-card-desc">Mëso si të krijosh llogarinë tënde si klient në Helppy dhe të fillosh të kërkosh shërbime.</p>
          <a class="hiw-card-btn" href="<?= $baseUrl ?>/register">
            <i class="bi bi-person-plus"></i> Regjistrohu tani
          </a>
        </div>
      </div>

      <div class="hiw-card">
        <div class="hiw-card-num">2</div>
        <div class="hiw-video-wrap">
          <?= hiwVideo('client_search', $vids, 'Si të kërkosh mjeshtër') ?>
        </div>
        <div class="hiw-card-body">
          <h3 class="hiw-card-title">Si të kërkosh një mjeshtër</h3>
          <p class="hiw-card-desc">Shiko si të filtrosh sipas kategorisë dhe qytetit, dhe të gjesh mjeshtrin e duhur për nevojën tënde.</p>
          <a class="hiw-card-btn" href="<?= $baseUrl ?>/search">
            <i class="bi bi-search"></i> Kërko tani
          </a>
        </div>
      </div>

      <div class="hiw-card">
        <div class="hiw-card-num">3</div>
        <div class="hiw-video-wrap">
          <?= hiwVideo('client_book', $vids, 'Si të rezervosh') ?>
        </div>
        <div class="hiw-card-body">
          <h3 class="hiw-card-title">Si të rezervosh dhe të kontaktosh</h3>
          <p class="hiw-card-desc">Mëso si të dërgosh kërkesë, të rezervosh një takim dhe të bisedosh drejtpërdrejt me månëstrin.</p>
          <a class="hiw-card-btn" href="<?= $baseUrl ?>/search">
            <i class="bi bi-calendar-check"></i> Gjej mjeshtër
          </a>
        </div>
      </div>

    </div>
  </div>

  <!-- ── FOR PROVIDERS ── -->
  <div class="hiw-group">
    <div class="hiw-group-header">
      <span class="hiw-group-badge hiw-badge-orange">Për mjeshttit</span>
      <h2 class="hiw-group-title">Si të fillosh të punosh me Helppy</h2>
    </div>

    <div class="hiw-cards">

      <div class="hiw-card">
        <div class="hiw-card-num hiw-num-orange">1</div>
        <div class="hiw-video-wrap">
          <?= hiwVideo('provider_register', $vids, 'Si të regjistrohesh si mjeshtër') ?>
        </div>
        <div class="hiw-card-body">
          <h3 class="hiw-card-title">Si të regjistrohesh si mjeshtër</h3>
          <p class="hiw-card-desc">Hap pas hapi — krijo profilin tënd profesional, shto fotot dhe shërbimet që ofron.</p>
          <a class="hiw-card-btn hiw-card-btn-orange" href="<?= $baseUrl ?>/register">
            <i class="bi bi-person-badge"></i> Regjistrohu si mjeshtër
          </a>
        </div>
      </div>

      <div class="hiw-card">
        <div class="hiw-card-num hiw-num-orange">2</div>
        <div class="hiw-video-wrap">
          <?= hiwVideo('provider_subscribe', $vids, 'Si të abonohesh') ?>
        </div>
        <div class="hiw-card-body">
          <h3 class="hiw-card-title">Si të abonohesh</h3>
          <p class="hiw-card-desc">Mëso si të aktivizosh abonamanin premium dhe të shfaqesh i pari në rezultatet e kërkimit.</p>
          <a class="hiw-card-btn hiw-card-btn-orange" href="<?= $baseUrl ?>/subscribe">
            <i class="bi bi-stars"></i> Shiko planet
          </a>
        </div>
      </div>

      <div class="hiw-card">
        <div class="hiw-card-num hiw-num-orange">3</div>
        <div class="hiw-video-wrap">
          <?= hiwVideo('provider_manage', $vids, 'Si të pranosh kërkesa') ?>
        </div>
        <div class="hiw-card-body">
          <h3 class="hiw-card-title">Si të pranosh dhe menaxhosh kërkesa</h3>
          <p class="hiw-card-desc">Shiko si funksionojnë rezervimet, bisedat me klientët dhe menaxhimi i profilit tënd.</p>
          <a class="hiw-card-btn hiw-card-btn-orange" href="<?= $baseUrl ?>/provider/dashboard">
            <i class="bi bi-layout-text-sidebar-reverse"></i> Shko te paneli
          </a>
        </div>
      </div>

    </div>
  </div>

  <!-- ── CTA ── -->
  <div class="hiw-cta">
    <h2>Gati të fillosh?</h2>
    <p>Bashkohu me mijëra klientë dhe mjeshtra që tashmë e përdorin Helppy çdo ditë.</p>
    <div class="hiw-cta-btns">
      <a class="btn-hero-search" href="<?= $baseUrl ?>/search"><i class="bi bi-search"></i> Gjej mjeshtër tani</a>
      <a class="btn-hero-outline" href="<?= $baseUrl ?>/register"><i class="bi bi-person-plus"></i> Regjistrohu falas</a>
    </div>
  </div>

</div>

<?php $baseUrl = e(CONFIG['base_url']); ?>

<section class="hiw-hero">
  <div class="container">
    <h1 class="hiw-hero-title">Rreth Helppy.com</h1>
    <p class="hiw-hero-sub">Platforma shqiptare që lidh klientët me mjeshttrit më të mirë.</p>
  </div>
</section>

<div class="container hiw-container" style="max-width:820px">

  <!-- About card -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4 p-md-5">

      <div class="d-flex align-items-center gap-3 mb-4">
        <span style="font-size:48px; line-height:1">🔧</span>
        <div>
          <h2 class="h4 fw-bold mb-0" style="color:var(--helppy-navy)">Çfarë është Helppy?</h2>
          <span class="text-muted small">Themeluar në 2026</span>
        </div>
      </div>

      <p class="fs-6" style="line-height:1.8">
        <strong>Helppy.com</strong> është platforma e parë kosovare e dedikuar për lidhjen e klientëve me profesionistë dhe mjeshtra vendas — hidraulikë, elektricistë, karpentierë, pastrim, rinovim dhe shumë shërbime të tjera shtëpiake e profesionale.
      </p>
      <p class="fs-6" style="line-height:1.8">
        E themeluar në vitin <strong>2026</strong>, Helppy lindi nga nevoja reale për të gjetur shërbime të besueshme shpejt dhe pa ndërmjetës. Platforma ofron një vend të vetëm ku klientët mund të kërkojnë, krahasojnë dhe kontaktojnë mjeshtra të verifikuar, ndërkohë që mjeshttrit fitojnë dukshmëri dixhitale dhe klientë të rinj çdo ditë.
      </p>

      <hr class="my-4">

      <div class="row g-4 text-center">
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-3" style="color:var(--helppy-amber)">100+</div>
          <div class="text-muted small">Mjeshtra aktiv</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-3" style="color:var(--helppy-amber)">10+</div>
          <div class="text-muted small">Qytete të mbuluara</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-3" style="color:var(--helppy-amber)">500+</div>
          <div class="text-muted small">Klientë të regjistruar</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-3" style="color:var(--helppy-amber)">2026</div>
          <div class="text-muted small">Viti i themelimit</div>
        </div>
      </div>

      <hr class="my-4">

      <h3 class="h5 fw-bold mb-3" style="color:var(--helppy-navy)">Misioni ynë</h3>
      <p class="fs-6" style="line-height:1.8">
        Të bëjmë punën e çdo mjeshttri të dukshme dhe të aksesueshme — dhe çdo klient të ndihet i sigurt kur zgjedh dikë për shtëpinë ose biznesin e tij. Besimi, cilësia dhe lehtësia janë vlerat tona themelore.
      </p>

      <div class="d-flex gap-3 mt-4 flex-wrap">
        <a class="btn-hero-search" href="<?= $baseUrl ?>/search">
          <i class="bi bi-search"></i> Gjej mjeshtër
        </a>
        <a class="btn-hero-outline" href="<?= $baseUrl ?>/kontakt">
          <i class="bi bi-envelope"></i> Na kontakto
        </a>
      </div>

    </div>
  </div>

  <!-- Reviews section -->
  <div class="mb-2 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h2 class="h4 fw-bold mb-1" style="color:var(--helppy-navy)">Çfarë thonë përdoruesit</h2>
      <p class="text-muted small mb-0">Opinione reale nga klientë dhe mjeshtra të platformës.</p>
    </div>
    <a class="btn-hero-search" href="<?= $baseUrl ?>/register"
       style="font-size:14px; padding:9px 18px">
      <i class="bi bi-star"></i> Lër një opinion
    </a>
  </div>

  <div class="row g-3 mb-5">

    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="about-review-avatar" style="background:var(--helppy-navy)">A</div>
          <div>
            <div class="fw-bold" style="color:var(--helppy-navy)">Artan Krasniqi</div>
            <div class="text-muted small">Klient · Prishtinë</div>
          </div>
          <div class="ms-auto text-warning">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
          </div>
        </div>
        <p class="text-muted mb-0" style="line-height:1.7; font-size:14px">
          "Gjeta hidraulikun brenda 10 minutave. Erdhi në kohë, punoi profesionalisht dhe çmimi ishte shumë i arsyeshëm. Helppy e bëri të lehtë gjithë procesin."
        </p>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="about-review-avatar" style="background:var(--helppy-amber)">L</div>
          <div>
            <div class="fw-bold" style="color:var(--helppy-navy)">Lirinda Berisha</div>
            <div class="text-muted small">Kliente · Prishtinë</div>
          </div>
          <div class="ms-auto text-warning">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
          </div>
        </div>
        <p class="text-muted mb-0" style="line-height:1.7; font-size:14px">
          "Shërbim fantastik! Porosita një elektricist për riparimin e pajisjeve në shtëpi dhe gjithçka u krye në mënyrë perfekte. Do ta përdor sërish patjetër."
        </p>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="about-review-avatar" style="background:#1e3a5f">B</div>
          <div>
            <div class="fw-bold" style="color:var(--helppy-navy)">Besnik Morina</div>
            <div class="text-muted small">Mjeshtër · Prishtinë</div>
          </div>
          <div class="ms-auto text-warning">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
          </div>
        </div>
        <p class="text-muted mb-0" style="line-height:1.7; font-size:14px">
          "Që kur u regjistrova si karpentier në Helppy, klientët e rinj nuk kanë reshtur. Platforma është shumë e lehtë për t'u përdorur dhe mbeshtetja është e shkëlqyer."
        </p>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="about-review-avatar" style="background:#e67e22">V</div>
          <div>
            <div class="fw-bold" style="color:var(--helppy-navy)">Vjosa Ahmeti</div>
            <div class="text-muted small">Kliante · Prishtinë</div>
          </div>
          <div class="ms-auto text-warning">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
          </div>
        </div>
        <p class="text-muted mb-0" style="line-height:1.7; font-size:14px">
          "Mora shërbim pastrimi për shtëpinë time dhe rezultati ishte i jashtëzakonshëm. Rezervova direkt nga telefoni, pa asnjë ndërmjetës. Rekomandoj me gjithë zemër!"
        </p>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="about-review-avatar" style="background:var(--helppy-navy)">F</div>
          <div>
            <div class="fw-bold" style="color:var(--helppy-navy)">Fisnik Gashi</div>
            <div class="text-muted small">Mjeshtër · Prishtinë</div>
          </div>
          <div class="ms-auto text-warning">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
          </div>
        </div>
        <p class="text-muted mb-0" style="line-height:1.7; font-size:14px">
          "Si hidraulik, Helppy më ka dhënë mundësinë të rris biznesin tim. Klientët vijnë vetë — unë fokusohem vetëm në punë. Investimi më i mirë profesional që kam bërë."
        </p>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100 p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="about-review-avatar" style="background:var(--helppy-amber)">D</div>
          <div>
            <div class="fw-bold" style="color:var(--helppy-navy)">Drita Llazi</div>
            <div class="text-muted small">Kliante · Prishtinë</div>
          </div>
          <div class="ms-auto text-warning">
            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
          </div>
        </div>
        <p class="text-muted mb-0" style="line-height:1.7; font-size:14px">
          "Aplikacioni është shumë intuitiv. Gjeta bojaxhi për banesën time në pak minuta, komunikova direkt me të dhe caktova kohën e punës vetë. Shumë praktik!"
        </p>
      </div>
    </div>

  </div>

  <!-- Bottom CTA -->
  <div class="hiw-cta">
    <h2>Bashkohu me komunitetin Helppy</h2>
    <p>Mbi 500 klientë dhe mjeshtra tashmë e besojnë platformën tonë. Radha jote!</p>
    <div class="hiw-cta-btns">
      <a class="btn-hero-search" href="<?= $baseUrl ?>/search"><i class="bi bi-search"></i> Gjej mjeshtër tani</a>
      <a class="btn-hero-outline is-light" href="<?= $baseUrl ?>/register"><i class="bi bi-person-plus"></i> Regjistrohu falas</a>
    </div>
  </div>

</div>

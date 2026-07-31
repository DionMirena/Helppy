<?php $baseUrl = e(CONFIG['base_url']); ?>

<section class="hiw-hero">
  <div class="container">
    <h1 class="hiw-hero-title">Na kontakto</h1>
    <p class="hiw-hero-sub">Jemi këtu për çdo pyetje, sugjerim ose bashkëpunim.</p>
  </div>
</section>

<div class="container hiw-container" style="max-width:680px">

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4 p-md-5">

      <h2 class="h5 fw-bold mb-4" style="color:var(--helppy-navy)">Të dhënat tona</h2>

      <ul class="list-unstyled d-flex flex-column gap-4 mb-0">

        <li class="d-flex align-items-center gap-3">
          <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                style="width:48px;height:48px;background:var(--helppy-navy);color:#fff;font-size:20px">
            <i class="bi bi-telephone-fill"></i>
          </span>
          <div>
            <div class="text-muted small mb-1">Telefon</div>
            <a href="tel:045378220" class="fw-bold fs-5 text-decoration-none" style="color:var(--helppy-navy)">
              045-378-220
            </a>
          </div>
        </li>

        <li class="d-flex align-items-center gap-3">
          <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                style="width:48px;height:48px;background:var(--helppy-amber);color:#fff;font-size:20px">
            <i class="bi bi-envelope-fill"></i>
          </span>
          <div>
            <div class="text-muted small mb-1">Email</div>
            <a href="mailto:dionmirena01@gmail.com" class="fw-bold fs-5 text-decoration-none" style="color:var(--helppy-navy)">
              dionmirena01@gmail.com
            </a>
          </div>
        </li>

        <li class="d-flex align-items-center gap-3">
          <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                style="width:48px;height:48px;background:var(--helppy-navy-light);color:#fff;font-size:20px">
            <i class="bi bi-clock-fill"></i>
          </span>
          <div>
            <div class="text-muted small mb-1">Orari i punës</div>
            <div class="fw-semibold" style="color:var(--helppy-navy)">E Hënë – E Shtunë, 08:00 – 20:00</div>
          </div>
        </li>

        <li class="d-flex align-items-center gap-3">
          <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                style="width:48px;height:48px;background:var(--helppy-navy-light);color:#fff;font-size:20px">
            <i class="bi bi-geo-alt-fill"></i>
          </span>
          <div>
            <div class="text-muted small mb-1">Vendndodhja</div>
            <div class="fw-semibold" style="color:var(--helppy-navy)">Kosovë</div>
          </div>
        </li>

      </ul>

    </div>
  </div>

  <div class="text-center mt-2 mb-5">
    <a class="btn-hero-search" href="<?= $baseUrl ?>/rreth-nesh">
      <i class="bi bi-info-circle"></i> Rreth Helppy.com
    </a>
  </div>

</div>

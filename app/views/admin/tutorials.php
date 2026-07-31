<?php
$baseUrl = e(CONFIG['base_url']);
$csrfTok = e(Request::csrfToken());

$labels = [
    'client_register'     => ['Klienti – Regjistrimi',          'bi-person-plus',          'navy'],
    'client_search'       => ['Klienti – Kërko mjeshtër',       'bi-search',               'navy'],
    'client_book'         => ['Klienti – Rezervimi',            'bi-calendar-check',        'navy'],
    'provider_register'   => ['Mjeshttri – Regjistrimi',        'bi-person-badge',         'orange'],
    'provider_subscribe'  => ['Mjeshttri – Abonohesh',          'bi-stars',                'orange'],
    'provider_manage'     => ['Mjeshttri – Menaxho kërkesat',   'bi-layout-text-sidebar-reverse', 'orange'],
];
?>

<div class="container py-5">
  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= $baseUrl ?>/admin" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Admin
    </a>
    <h1 class="h3 mb-0">Videot tutoriale</h1>
  </div>
  <p class="text-muted mb-5">
    Për çdo hap ngarko një video (mp4/webm/ogg) ose ngjit URL-in e YouTube/Vimeo.
    Videot shfaqen te faqja <strong>Si funksionon</strong>.
  </p>

  <?php foreach ($labels as $slot => [$label, $icon, $color]): ?>
    <?php $row = $videos[$slot] ?? ['video_url' => '']; ?>
    <?php $hasVideo = $row['video_url'] !== ''; ?>
    <div class="card mb-4 shadow-sm">
      <div class="card-header d-flex align-items-center gap-2
                  <?= $color === 'orange' ? 'bg-warning bg-opacity-10' : 'bg-primary bg-opacity-10' ?>">
        <i class="bi <?= $icon ?> <?= $color === 'orange' ? 'text-warning' : 'text-primary' ?> fs-5"></i>
        <strong><?= e($label) ?></strong>
        <?php if ($hasVideo): ?>
          <span class="badge bg-success ms-auto">Video e ngarkuar</span>
        <?php else: ?>
          <span class="badge bg-secondary ms-auto">Pa video</span>
        <?php endif; ?>
      </div>
      <div class="card-body">

        <?php if ($hasVideo): ?>
          <div class="mb-3">
            <label class="form-label text-muted small">Video aktuale:</label>
            <?php
              $embedUrl = TutorialVideo::toEmbed($row['video_url']);
              $isDirect = TutorialVideo::isDirect($row['video_url']);
            ?>
            <?php if ($isDirect): ?>
              <video src="<?= e($embedUrl) ?>" controls class="w-100 rounded" style="max-height:220px"></video>
            <?php else: ?>
              <div class="ratio ratio-16x9" style="max-width:400px">
                <iframe src="<?= e($embedUrl) ?>" allowfullscreen loading="lazy" class="rounded"></iframe>
              </div>
            <?php endif; ?>
            <div class="mt-1">
              <code class="small text-muted"><?= e($row['video_url']) ?></code>
            </div>
          </div>
        <?php endif; ?>

        <form method="post"
              action="<?= $baseUrl ?>/admin/tutorials/<?= e($slot) ?>"
              enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= $csrfTok ?>">

          <div class="row g-3 align-items-end">
            <div class="col-md-7">
              <label class="form-label fw-semibold">URL (YouTube / Vimeo)</label>
              <input type="url" name="video_url" class="form-control"
                     placeholder="https://www.youtube.com/watch?v=..."
                     value="<?= e($row['video_url']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">OSE ngarko skedar (mp4/webm/ogg)</label>
              <input type="file" name="video_file" class="form-control"
                     accept="video/mp4,video/webm,video/ogg">
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-save"></i>
              </button>
            </div>
          </div>

          <div class="form-text mt-1">
            Lër URL-in bosh dhe ngarko skedar për të zëvendësuar me video lokale.
            Skedari i ngarkuar zëvendëson URL-in nëse të dyja janë të plotësuara.
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="text-center mt-4">
    <a href="<?= $baseUrl ?>/si-funksionon" target="_blank" class="btn btn-outline-primary">
      <i class="bi bi-eye"></i> Shiko faqen Si funksionon
    </a>
  </div>
</div>

<?php
$typeLabel = $p['type'] === 'offer' ? 'Ofertë' : 'Kërkesë';
$typeClass = $p['type'] === 'offer' ? 'post-badge-offer' : 'post-badge-request';

$priceLabel = '';
if ($p['type'] === 'offer' && ($p['price_from'] !== null || $p['price_to'] !== null)) {
    $from = $p['price_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['price_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = trim($from . ($from && $to ? ' – ' : '') . $to);
} elseif ($p['type'] === 'request' && ($p['budget_from'] !== null || $p['budget_to'] !== null)) {
    $from = $p['budget_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['budget_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = 'Buxhet: ' . trim($from . ($from && $to ? ' – ' : '') . $to);
}

$urgencyLabels = ['low' => 'I ulët', 'normal' => 'Normal', 'high' => 'I lartë'];
$phoneRaw = !empty($p['author_phone']) ? preg_replace('/[^0-9+]/', '', $p['author_phone']) : '';
?>
<section class="container py-4">
  <?php if ($p['status'] === 'hidden'): ?>
    <div class="alert alert-warning">
      <i class="bi bi-eye-slash"></i> Ky postim është i fshehur nga administratori. Vetëm ti dhe administratori e shihni.
    </div>
  <?php elseif ($p['status'] === 'closed'): ?>
    <div class="alert alert-secondary">
      <i class="bi bi-check2-circle"></i> Ky postim është i mbyllur.
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <?php if ($photos): ?>
        <div id="postCarousel" class="carousel slide post-carousel mb-3" data-bs-ride="carousel">
          <div class="carousel-inner">
            <?php foreach ($photos as $i => $ph): ?>
              <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <img src="<?= e(CONFIG['upload_url'] . '/' . rawurlencode($ph['filename'])) ?>" alt="<?= e($p['title']) ?>">
                <?php if (Auth::role() === 'admin'): ?>
                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/post-photos/<?= (int)$ph['id'] ?>/delete"
                        class="carousel-admin-delete"
                        onsubmit="return confirm('Fshi këtë foto nga postimi?');">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="bi bi-trash"></i> Fshi foton
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (count($photos) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#postCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#postCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon"></span>
            </button>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="profile-card">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="post-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
          <?php if ($p['type'] === 'request' && !empty($p['urgency'])): ?>
            <span class="urgency-badge urgency-<?= e($p['urgency']) ?>">
              <i class="bi bi-exclamation-triangle"></i> <?= e($urgencyLabels[$p['urgency']]) ?>
            </span>
          <?php endif; ?>
        </div>
        <h1 class="mb-1"><?= e($p['title']) ?></h1>
        <p class="text-muted small mb-3">
          <i class="bi <?= e($p['category_icon'] ?: 'bi-tag') ?>"></i> <?= e($p['category_name']) ?>
          &middot; <i class="bi bi-geo-alt"></i> <?= e($p['city_name']) ?>
          &middot; <i class="bi bi-person"></i> <?= e($p['author_name']) ?>
          &middot; <?= e(timeAgoSq((string)$p['created_at'])) ?>
        </p>

        <div class="mb-3 long-text"><?= nl2br(e($p['description'])) ?></div>

        <?php if ($p['type'] === 'offer'): ?>
          <ul class="list-unstyled small">
            <?php if ($priceLabel): ?><li><strong>Çmimi:</strong> <?= e($priceLabel) ?></li><?php endif; ?>
            <?php if (!empty($p['working_hours'])): ?><li><strong>Orari:</strong> <?= e($p['working_hours']) ?></li><?php endif; ?>
            <?php if (!empty($p['contact_preferences'])): ?><li><strong>Kontakti:</strong> <?= e($p['contact_preferences']) ?></li><?php endif; ?>
          </ul>
        <?php else: ?>
          <ul class="list-unstyled small">
            <?php if ($priceLabel): ?><li><?= e($priceLabel) ?></li><?php endif; ?>
            <?php if (!empty($p['deadline'])): ?>
              <li><strong>Afati:</strong> <?= e(date('d M Y', strtotime((string)$p['deadline']))) ?></li>
            <?php endif; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="profile-card mb-3">
        <p class="small text-muted mb-1">Kontakto</p>
        <p class="mb-2"><strong><?= e($p['author_name']) ?></strong></p>
        <?php if (!empty($p['author_phone'])): ?>
          <a class="btn btn-helppy w-100 mb-2" href="tel:<?= e($phoneRaw) ?>">
            <i class="bi bi-telephone-fill"></i> Telefono Tani
          </a>
        <?php endif; ?>
        <?php if (!empty($p['author_email'])): ?>
          <a class="btn btn-helppy-outline w-100" href="mailto:<?= e($p['author_email']) ?>">
            <i class="bi bi-envelope"></i> Email
          </a>
        <?php endif; ?>
      </div>

      <?php if ($viewerIsOwner || $viewerIsAdmin): ?>
        <div class="profile-card">
          <p class="small text-muted mb-2">Veprime</p>
          <?php if ($viewerIsOwner): ?>
            <a class="btn btn-link w-100 text-start" href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/edit">
              <i class="bi bi-pencil"></i> Modifiko
            </a>
            <?php if ($p['status'] === 'active'): ?>
              <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/close" class="d-inline w-100">
                <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                <button class="btn btn-link w-100 text-start" type="submit">
                  <i class="bi bi-check2-circle"></i> Mbyll postimin
                </button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($viewerIsAdmin && $p['status'] !== 'hidden'): ?>
            <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/posts/<?= (int)$p['id'] ?>/hide" class="d-inline w-100">
              <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
              <button class="btn btn-link w-100 text-start" type="submit">
                <i class="bi bi-eye-slash"></i> Fsheh
              </button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/delete" class="d-inline w-100"
                onsubmit="return confirm('Të fshihet ky postim përgjithmonë?');">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-link text-danger w-100 text-start" type="submit">
              <i class="bi bi-trash"></i> Fshi
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

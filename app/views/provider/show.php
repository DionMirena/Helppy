<?php
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : CONFIG['base_url'] . '/assets/img/default-avatar.svg';
$avg = $p['avg_rating'] !== null ? round((float)$p['avg_rating'], 1) : null;
?>
<div class="container py-4">
  <?php $isViewerSelf  = Auth::check() && (int)Auth::user()['id'] === (int)$p['id']; ?>
  <?php $isViewerAdmin = Auth::role() === 'admin'; ?>

  <div class="row g-3 mb-3 provider-top-row">
    <!-- LEFT: identity card (photo / name / chips / rate / actions) -->
    <div class="col-lg-6">
      <div class="profile-card profile-card-compact h-100">
        <div class="profile-header">
          <div class="profile-header-photo">
            <img class="profile-photo" src="<?= e($photoUrl) ?>" alt="<?= e($p['name']) ?>">
            <?php if ($isViewerAdmin && !empty($p['photo'])): ?>
              <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/providers/<?= (int)$p['id'] ?>/photo/delete"
                    class="mt-2" onsubmit="return confirm('Fshi foton e profilit të këtij përdoruesi?');">
                <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-trash"></i> Fshi (admin)
                </button>
              </form>
            <?php endif; ?>
          </div>
          <div class="profile-header-info">
            <h1 class="profile-name mb-1">
              <?= e($p['name']) ?>
              <?php if (!empty($p['is_premium'])): ?><span class="premium-badge">PREMIUM</span><?php endif; ?>
            </h1>
            <p class="text-muted mb-2 small">
              <?= e($p['profession']) ?>
              <?php if (!empty($p['is_company'])): ?>&middot; <i class="bi bi-building"></i> <?= e($p['company_name'] ?? '') ?><?php endif; ?>
              <?php if (!empty($p['city'])): ?>&middot; <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?><?php endif; ?>
              <?php if (!empty($p['district'])): ?>&middot; <span class="badge district-badge"><i class="bi bi-pin-map"></i> <?= e($p['district']) ?></span><?php endif; ?>
            </p>
            <p class="stars mb-2">
              <?php if ($avg !== null): ?>
                <?php for ($i=1;$i<=5;$i++): ?>
                  <i class="bi <?= $i <= round($avg) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                <?php endfor; ?>
                <span class="ms-1 text-muted small"><?= e((string)$avg) ?> &middot; <?= (int)$p['review_count'] ?> vleresime</span>
              <?php else: ?>
                <span class="text-muted small">Pa vleresime</span>
              <?php endif; ?>
            </p>
            <?php if (!empty($p['categories'])): ?>
              <div class="profile-chips mb-2">
                <?php foreach ($p['categories'] as $cat): ?>
                  <span class="category-chip category-chip-sm"><?= e($cat['name']) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <p class="mb-0">
              <?php if ($p['hourly_rate'] !== null): ?>
                <span class="rate-badge"><i class="bi bi-cash-coin"></i>
                  <strong>€<?= e(rtrim(rtrim(number_format((float)$p['hourly_rate'], 2, '.', ''), '0'), '.')) ?></strong> / orë
                </span>
              <?php else: ?>
                <span class="rate-badge rate-badge-muted"><i class="bi bi-chat-left-text"></i>
                  Çmimi sipas marrëveshjes
                </span>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <div class="provider-actions">
          <?php if (!empty($p['phone'])): ?>
            <a class="btn btn-helppy" href="tel:<?= e(preg_replace('/[^0-9+]/','',$p['phone'])) ?>">
              <i class="bi bi-telephone-fill"></i> Telefono
            </a>
          <?php endif; ?>
          <?php if (!empty($p['latitude']) && !empty($p['longitude'])):
            $lat = number_format((float)$p['latitude'], 7, '.', '');
            $lng = number_format((float)$p['longitude'], 7, '.', '');
            $mapsUrl = 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
          ?>
            <a class="btn btn-helppy-outline" target="_blank" rel="noopener"
               href="<?= e($mapsUrl) ?>">
              <i class="bi bi-geo-alt-fill"></i> Hape në Google Maps
            </a>
          <?php endif; ?>
          <?php if (!$isViewerSelf): ?>
            <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>/book">
              <i class="bi bi-calendar-check"></i> Rezervo Tani
            </a>
            <a class="btn btn-helppy-outline"
               href="<?= e(CONFIG['base_url']) ?>/chat/with/<?= (int)$p['id'] ?>"
               data-helppy-chat
               data-user-id="<?= (int)$p['id'] ?>"
               data-user-name="<?= e($p['name']) ?>">
              <i class="bi bi-chat-dots"></i> Bisedo
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: portfolio gallery -->
    <div class="col-lg-6">
      <div class="profile-card profile-card-compact provider-gallery-card h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h3 class="provider-gallery-title mb-0">
            <i class="bi bi-images"></i> Foto të punës
            <small class="text-muted fw-normal">(<?= count($gallery) ?>)</small>
          </h3>
        </div>

        <?php if (!empty($gallery)): ?>
          <div class="gallery-grid">
            <?php foreach ($gallery as $g): ?>
              <div class="gallery-item">
                <img src="<?= e(CONFIG['upload_url'] . '/' . rawurlencode((string)$g['filename'])) ?>"
                     alt="<?= e((string)($g['caption'] ?? '')) ?>"
                     loading="lazy">
                <?php if ($isViewerAdmin): ?>
                  <form method="post"
                        action="<?= e(CONFIG['base_url']) ?>/provider/work-photo/<?= (int)$g['id'] ?>/delete"
                        class="gallery-delete"
                        onsubmit="return confirm('Fshi këtë foto?');">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <input type="hidden" name="return" value="profile">
                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Fshi (admin)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="gallery-empty">
            <i class="bi bi-camera"></i>
            <p class="text-muted mb-0">
              <?php if ($isViewerSelf): ?>
                Ende s'ke shtuar foto të punës.
                <a href="<?= e(CONFIG['base_url']) ?>/provider/dashboard#work-photos">Shto foto te punës &rarr;</a>
              <?php else: ?>
                Ky punëtor s'ka shtuar ende foto të punës.
              <?php endif; ?>
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($p['bio']) || !empty($p['skills_services'])): ?>
    <div class="row g-3 mb-4">
      <?php if (!empty($p['bio'])): ?>
        <div class="col-md-6">
          <div class="profile-card h-100">
            <h5 class="mb-2"><i class="bi bi-person-vcard"></i> Rreth meje</h5>
            <p class="mb-0 long-text"><?= nl2br(e($p['bio'])) ?></p>
          </div>
        </div>
      <?php endif; ?>
      <?php if (!empty($p['skills_services'])): ?>
        <div class="col-md-6">
          <div class="profile-card h-100">
            <h5 class="mb-2"><i class="bi bi-tools"></i> Aftësitë & Shërbimet</h5>
            <p class="mb-0 long-text"><?= nl2br(e($p['skills_services'])) ?></p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <h3 class="section-title">Vleresime (<?= count($reviews) ?>)</h3>

  <?php if (Auth::check() && Auth::role() === 'client' && !$alreadyReviewed): ?>
    <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>/review" class="mb-4 review-card">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
      <div class="mb-2">
        <label class="form-label">Vleresimi</label>
        <select name="rating" class="form-select w-auto d-inline-block">
          <?php for ($i=5;$i>=1;$i--): ?>
            <option value="<?= $i ?>"><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label">Komenti (opsional)</label>
        <textarea name="comment" class="form-control" rows="3" maxlength="2000"></textarea>
      </div>
      <button class="btn btn-helppy" type="submit">Ler vleresim</button>
    </form>
  <?php elseif (Auth::check() && Auth::role() === 'client' && $alreadyReviewed): ?>
    <div class="alert alert-secondary">Keni vleresuar tashme kete punetor.</div>
  <?php elseif (!Auth::check()): ?>
    <div class="alert alert-light">
      <a href="<?= e(CONFIG['base_url']) ?>/login">Hyni</a> ose
      <a href="<?= e(CONFIG['base_url']) ?>/register">regjistrohuni</a> per te lene nje vleresim.
    </div>
  <?php endif; ?>

  <?php foreach ($reviews as $r): ?>
    <?php View::partial('review-card', ['r' => $r]); ?>
  <?php endforeach; ?>
  <?php if (!$reviews): ?>
    <p class="text-muted">Asnje vleresim ende.</p>
  <?php endif; ?>
</div>

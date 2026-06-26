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
              <?php $online = Presence::isOnline($p['last_seen_at'] ?? null); ?>
              <span class="presence-pill <?= $online ? 'is-online' : 'is-offline' ?>"
                    title="<?= $online ? 'Aktiv tani' : ('Parë ' . e((string)Presence::lastSeenLabel($p['last_seen_at'] ?? null))) ?>">
                <span class="presence-dot <?= $online ? 'is-online' : 'is-offline' ?>"></span>
                <?= $online ? 'Online' : 'Offline' ?>
              </span>
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

  <?php if (!empty($p['latitude']) && !empty($p['longitude'])):
    $lat = number_format((float)$p['latitude'], 7, '.', '');
    $lng = number_format((float)$p['longitude'], 7, '.', '');
    $mapsUrl = 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
    $gmapsEnabled = !empty(CONFIG['google_maps']['enabled']) && !empty(CONFIG['google_maps']['api_key']);
  ?>
    <div class="profile-card mb-4 profile-map-card" id="provider-mini-map-card">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h5 class="mb-0">
          <i class="bi bi-geo-alt-fill"></i> Lokacioni
          <?php if (!empty($p['city'])): ?>
            <span class="text-muted fw-normal small">· <?= e($p['city']) ?></span>
          <?php endif; ?>
        </h5>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-helppy-outline" target="_blank" rel="noopener" href="<?= e($mapsUrl) ?>">
            <i class="bi bi-box-arrow-up-right"></i> Hape në Google Maps
          </a>
          <button type="button" class="btn btn-sm btn-helppy" data-mini-map-fullscreen>
            <i class="bi bi-arrows-fullscreen"></i> Plot ekran
          </button>
        </div>
      </div>
      <div id="provider-mini-map" class="provider-mini-map"
           data-provider="<?= $gmapsEnabled ? 'gmaps' : 'leaflet' ?>"
           data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>"></div>
      <button type="button" class="provider-mini-map-close" data-mini-map-close hidden>
        <i class="bi bi-x-lg"></i> Mbyll
      </button>
    </div>
  <?php endif; ?>

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

<?php if (!empty($p['latitude']) && !empty($p['longitude'])):
  $gmapsEnabled = !empty(CONFIG['google_maps']['enabled']) && !empty(CONFIG['google_maps']['api_key']);
?>
<!-- Read-only location preview map (Google Maps if key configured, else Leaflet). -->
<?php if ($gmapsEnabled): ?>
  <script>
  (function () {
    var el = document.getElementById('provider-mini-map');
    if (!el) return;
    var lat = parseFloat(el.getAttribute('data-lat'));
    var lng = parseFloat(el.getAttribute('data-lng'));
    if (isNaN(lat) || isNaN(lng)) return;
    window.__helppyInitMiniGMap = function () {
      var map = new google.maps.Map(el, {
        center: { lat: lat, lng: lng },
        zoom: 16,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
        gestureHandling: 'cooperative',
      });
      new google.maps.Marker({ map: map, position: { lat: lat, lng: lng } });
      // Re-center after the container is resized (fullscreen toggle).
      el.__helppyMap = map;
    };
  })();
  </script>
  <script async defer
          src="https://maps.googleapis.com/maps/api/js?key=<?= e(CONFIG['google_maps']['api_key']) ?>&loading=async&callback=__helppyInitMiniGMap"></script>
<?php else: ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
  (function () {
    var el = document.getElementById('provider-mini-map');
    if (!el || typeof L === 'undefined') return;
    var lat = parseFloat(el.getAttribute('data-lat'));
    var lng = parseFloat(el.getAttribute('data-lng'));
    if (isNaN(lat) || isNaN(lng)) return;
    var map = L.map(el, { scrollWheelZoom: false, zoomControl: true }).setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(map);
    L.marker([lat, lng]).addTo(map);
    map.on('focus', function () { map.scrollWheelZoom.enable(); });
    map.on('blur',  function () { map.scrollWheelZoom.disable(); });
    el.__helppyMap = map;
  })();
  </script>
<?php endif; ?>

<script>
(function () {
  var card     = document.getElementById('provider-mini-map-card');
  var openBtn  = document.querySelector('[data-mini-map-fullscreen]');
  var closeBtn = document.querySelector('[data-mini-map-close]');
  var mapEl    = document.getElementById('provider-mini-map');
  if (!card || !openBtn || !closeBtn || !mapEl) return;

  function resizeMap() {
    var m = mapEl.__helppyMap;
    if (!m) return;
    // Both Leaflet and Google Maps need a nudge after the container resizes.
    setTimeout(function () {
      if (typeof m.invalidateSize === 'function') {
        m.invalidateSize();                          // Leaflet
      } else if (typeof google !== 'undefined' && google.maps && google.maps.event) {
        google.maps.event.trigger(m, 'resize');      // Google Maps
        var lat = parseFloat(mapEl.getAttribute('data-lat'));
        var lng = parseFloat(mapEl.getAttribute('data-lng'));
        m.setCenter({ lat: lat, lng: lng });
      }
    }, 60);
  }
  function enterFull() {
    card.classList.add('is-fullscreen');
    closeBtn.hidden = false;
    document.body.classList.add('helppy-no-scroll');
    resizeMap();
  }
  function exitFull() {
    card.classList.remove('is-fullscreen');
    closeBtn.hidden = true;
    document.body.classList.remove('helppy-no-scroll');
    resizeMap();
  }
  openBtn.addEventListener('click', enterFull);
  closeBtn.addEventListener('click', exitFull);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && card.classList.contains('is-fullscreen')) exitFull();
  });
})();
</script>
<?php endif; ?>

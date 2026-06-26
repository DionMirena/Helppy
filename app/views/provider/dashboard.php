<?php
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : CONFIG['base_url'] . '/assets/img/default-avatar.svg';
$selectedCats = array_column($p['categories'], 'id');
?>
<div class="container py-4">
  <h2>Profili im</h2>
  <p class="text-muted">
    <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$user['id'] ?>" target="_blank">Shiko profilin publik &rarr;</a>
  </p>

  <div class="row mt-4">
    <div class="col-md-4">
      <div class="text-center mb-3">
        <img class="profile-photo mb-2" src="<?= e($photoUrl) ?>" alt="profil">
        <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/photo" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
          <input class="form-control form-control-sm" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
          <button class="btn btn-helppy btn-sm mt-2" type="submit">Ngarko foto</button>
        </form>
      </div>

      <div class="bg-white p-3 rounded">
        <h6>Statistika</h6>
        <p class="mb-1">Vleresimi mesatar: <strong><?= $p['avg_rating'] !== null ? round($p['avg_rating'],1) : '—' ?></strong></p>
        <p class="mb-1">Numri i vleresimeve: <strong><?= (int)$p['review_count'] ?></strong></p>
        <p class="mb-0">Vizita ne profil: <strong><?= (int)$p['views'] ?></strong></p>
      </div>

      <div class="bg-white p-3 rounded mt-3">
        <h6>Abonimi</h6>
        <?php if ($subscription): ?>
          <p class="mb-1">Tier: <strong><?= e(ucfirst((string)$subscription['tier'])) ?></strong></p>
          <p class="mb-1">Skadon: <strong><?= e(date('d M Y', strtotime((string)$subscription['expires_at']))) ?></strong></p>
          <p class="mb-2 small text-muted">
            <?= max(0, (int)floor((strtotime((string)$subscription['expires_at']) - time()) / 86400)) ?> ditë të mbetura
          </p>
          <a class="btn btn-helppy-outline btn-sm w-100" href="<?= e(CONFIG['base_url']) ?>/subscribe">Menaxho</a>
        <?php else: ?>
          <p class="mb-2 small text-muted">Nuk ke abonim aktiv. Aktivizo një tier për të postuar oferta.</p>
          <a class="btn btn-helppy btn-sm w-100" href="<?= e(CONFIG['base_url']) ?>/subscribe">
            <i class="bi bi-credit-card"></i> Abonohu
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-8">
      <?php
        $currentCityName = '';
        foreach ($cities as $c) { if ((int)$user['city_id']===(int)$c['id']) { $currentCityName = $c['name']; break; } }
        $gmapsEnabled = !empty(CONFIG['google_maps']['enabled']) && !empty(CONFIG['google_maps']['api_key']);
        $hasPin       = $p['latitude'] !== null && $p['longitude'] !== null;
      ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/edit" class="provider-form">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">

        <!-- ===== Të dhënat bazë ===== -->
        <fieldset class="form-card">
          <legend class="form-card-title"><i class="bi bi-person-circle"></i> Të dhënat bazë</legend>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Emri</label>
              <input class="form-control" name="name" value="<?= e($user['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefoni</label>
              <input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+383 ...">
            </div>
          </div>
        </fieldset>

        <!-- ===== Profesioni ===== -->
        <fieldset class="form-card">
          <legend class="form-card-title"><i class="bi bi-briefcase-fill"></i> Profesioni</legend>
          <div class="row g-3">
            <div class="col-md-<?= !empty($p['is_company']) ? 6 : 8 ?>">
              <label class="form-label">Profesioni</label>
              <input class="form-control" name="profession" value="<?= e($p['profession']) ?>" required placeholder="p.sh. Hidraulik">
            </div>
            <?php if (!empty($p['is_company'])): ?>
              <div class="col-md-6">
                <label class="form-label">Emri i kompanisë</label>
                <input class="form-control" name="company_name" value="<?= e($p['company_name'] ?? '') ?>">
              </div>
            <?php endif; ?>
            <div class="col-md-4">
              <label class="form-label">Tarifa (€/orë)</label>
              <input class="form-control" type="number" step="0.01" min="0" max="999999"
                     name="hourly_rate" value="<?= e($p['hourly_rate'] !== null ? (string)$p['hourly_rate'] : '') ?>"
                     placeholder="p.sh. 25.00">
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Kategoritë</label>
            <?php
              // Group categories: parents → children, plus standalone top-levels.
              $catById = [];
              foreach ($categories as $c) { $catById[(int)$c['id']] = $c; }
              $parents = [];           // top-level rows that have children
              $standalones = [];       // top-level rows without children
              $childrenOf = [];        // parent_id => [children]
              foreach ($categories as $c) {
                if (!empty($c['parent_id'])) {
                  $childrenOf[(int)$c['parent_id']][] = $c;
                }
              }
              foreach ($categories as $c) {
                if (!empty($c['parent_id'])) continue;
                if (!empty($childrenOf[(int)$c['id']])) $parents[] = $c;
                else                                   $standalones[] = $c;
              }
              // Selected-count helper per umbrella.
              $selectedInGroup = function(array $kids) use ($selectedCats) {
                $n = 0;
                foreach ($kids as $k) if (in_array((int)$k['id'], $selectedCats, true)) $n++;
                return $n;
              };
            ?>
            <div class="category-picker" data-cat-picker>
              <div class="category-picker-top" data-cat-picker-top>
                <?php foreach ($parents as $p):
                  $kids = $childrenOf[(int)$p['id']] ?? [];
                  $nSel = $selectedInGroup($kids);
                ?>
                  <div class="category-picker-group" data-cat-group="<?= (int)$p['id'] ?>">
                    <button type="button" class="category-picker-toggle<?= $nSel ? ' has-selected' : '' ?>"
                            data-cat-toggle="<?= (int)$p['id'] ?>">
                      <?php if (!empty($p['icon'])): ?><i class="bi <?= e($p['icon']) ?>"></i><?php endif; ?>
                      <span><?= e($p['name']) ?></span>
                      <?php if ($nSel): ?><span class="picker-count" data-cat-count="<?= (int)$p['id'] ?>"><?= $nSel ?></span><?php else: ?><span class="picker-count d-none" data-cat-count="<?= (int)$p['id'] ?>">0</span><?php endif; ?>
                      <i class="bi bi-chevron-right has-children-caret"></i>
                    </button>
                    <!-- All children rendered but hidden by default; show on drill-down. -->
                    <div class="category-picker-children" data-cat-children="<?= (int)$p['id'] ?>" hidden>
                      <?php foreach ($kids as $kid): $checked = in_array((int)$kid['id'], $selectedCats, true); ?>
                        <label class="category-check">
                          <input type="checkbox" name="categories[]" value="<?= (int)$kid['id'] ?>" <?= $checked?'checked':'' ?>
                                 data-cat-child-of="<?= (int)$p['id'] ?>">
                          <?php if (!empty($kid['icon'])): ?><i class="bi <?= e($kid['icon']) ?>"></i><?php endif; ?>
                          <span><?= e($kid['name']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>

                <!-- Standalone top-level categories without children stay as flat checks. -->
                <div class="category-picker-standalones" data-cat-standalones>
                  <?php foreach ($standalones as $cat):
                    $checked = in_array((int)$cat['id'], $selectedCats, true);
                  ?>
                    <label class="category-check">
                      <input type="checkbox" name="categories[]" value="<?= (int)$cat['id'] ?>" <?= $checked?'checked':'' ?>>
                      <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
                      <span><?= e($cat['name']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Drill-down breadcrumb (shown when a group is open). -->
              <div class="category-picker-drilled" data-cat-picker-drilled hidden>
                <button type="button" class="btn btn-sm btn-helppy-outline" data-cat-back>
                  <i class="bi bi-arrow-left"></i> Mbrapa
                </button>
                <span class="category-picker-active-label" data-cat-active-label></span>
              </div>
            </div>

            <div class="input-group input-group-sm mt-2 new-category-input" data-new-cat>
              <span class="input-group-text"><i class="bi bi-plus-circle"></i></span>
              <input class="form-control" type="text" maxlength="80"
                     placeholder="Nuk po e gjeni? Shkruani kategori të re (p.sh. Saldator)"
                     data-new-cat-input
                     aria-label="Kategori e re">
              <button type="button" class="btn btn-helppy" data-new-cat-submit>
                <i class="bi bi-plus-lg"></i> Shto
              </button>
            </div>
            <small class="text-muted d-block mt-1" data-new-cat-hint>
              Klikoni <strong>Shto</strong> për ta shtuar menjëherë (pa nevojë të ruani profilin).
            </small>
          </div>
          <small class="text-muted d-block mt-2">
            Tarifa është opsionale. Lëre bosh për <em>“Çmimi sipas marrëveshjes”</em>.
          </small>
        </fieldset>

        <!-- ===== Përshkrimi ===== -->
        <fieldset class="form-card">
          <legend class="form-card-title"><i class="bi bi-card-text"></i> Përshkrimi</legend>
          <div class="mb-3">
            <label class="form-label">Rreth meje</label>
            <textarea class="form-control" name="bio" rows="4" maxlength="2000"
                      placeholder="Përshkruaj veten — eksperiencë, çfarë të dallon..."><?= e($p['bio'] ?? '') ?></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label">Aftësitë & Shërbimet</label>
            <textarea class="form-control" name="skills_services" rows="4" maxlength="2000"
                      placeholder="Lista e shërbimeve që ofron, p.sh.:&#10;• Instalim & riparim radiatori&#10;• Çelje bllokimi tubacioni&#10;• Ndërrim bateri/lavaman"><?= e($p['skills_services'] ?? '') ?></textarea>
          </div>
        </fieldset>

        <!-- ===== Lokacioni — two-column layout (form left, map right) ===== -->
        <fieldset class="form-card location-card">
          <legend class="form-card-title"><i class="bi bi-geo-alt-fill"></i> Lokacioni</legend>

          <div class="row g-3 location-grid">
            <!-- LEFT: city + address + coords -->
            <div class="col-lg-5 location-controls">
              <div class="mb-3">
                <label class="form-label">Qyteti</label>
                <div class="helppy-citypicker<?= $currentCityName ? ' is-selected' : '' ?>" data-citypicker>
                  <input type="hidden" name="city_id" value="<?= (int)($user['city_id'] ?? 0) ?: '' ?>" data-citypicker-value>
                  <button type="button" class="helppy-citypicker-toggle"
                          aria-haspopup="listbox" aria-expanded="false" data-citypicker-toggle>
                    <span class="helppy-citypicker-label" data-citypicker-label><?= $currentCityName ? e($currentCityName) : '— Zgjidh —' ?></span>
                    <i class="bi bi-chevron-down helppy-citypicker-caret" aria-hidden="true"></i>
                  </button>
                  <div class="helppy-citypicker-panel" role="listbox" data-citypicker-panel hidden>
                    <div class="helppy-citypicker-search">
                      <i class="bi bi-search"></i>
                      <input type="text" placeholder="Kërko qytetin…" autocomplete="off" data-citypicker-search aria-label="Kërko qytetin">
                    </div>
                    <ul class="helppy-citypicker-list" data-citypicker-list>
                      <li class="helppy-citypicker-item is-clear" role="option" data-citypicker-option data-value="" tabindex="-1">
                        <i class="bi bi-globe2"></i> — Asnjë —
                      </li>
                      <?php foreach ($cities as $c): ?>
                        <li class="helppy-citypicker-item<?= (int)$user['city_id']===(int)$c['id'] ? ' is-selected' : '' ?>" role="option"
                            data-citypicker-option data-value="<?= (int)$c['id'] ?>" data-name="<?= e(mb_strtolower($c['name'])) ?>" tabindex="-1">
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

              <div class="mb-3">
                <label class="form-label" for="provider-address">Adresa e saktë (opsionale)</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-search"></i></span>
                  <input class="form-control" id="provider-address" type="text"
                         placeholder="<?= $gmapsEnabled ? 'Shkruaj adresën, p.sh. \'Rr. Bill Clinton 12, Prishtinë\'…' : 'Shkruaj adresën dhe kliko Kërko' ?>"
                         autocomplete="off">
                  <?php if (!$gmapsEnabled): ?>
                    <button type="button" class="btn btn-helppy-outline" id="provider-address-search">
                      <i class="bi bi-search"></i> Kërko
                    </button>
                  <?php endif; ?>
                </div>
                <small class="text-muted">
                  <?php if ($gmapsEnabled): ?>
                    Adresa autoplotësohet; shenja vendoset vetë.
                  <?php else: ?>
                    Shkruaj dhe kliko <em>Kërko</em>, ose kliko në hartë.
                  <?php endif; ?>
                </small>
              </div>

              <div class="location-coords">
                <span id="provider-coords" class="text-muted small">
                  <?php if ($hasPin): ?>
                    <i class="bi bi-pin-map-fill"></i>
                    <?= e(number_format((float)$p['latitude'], 6)) ?>, <?= e(number_format((float)$p['longitude'], 6)) ?>
                  <?php else: ?>
                    <i class="bi bi-pin-map"></i> Nuk është vendosur ende.
                  <?php endif; ?>
                </span>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                  <button type="button" class="btn btn-sm btn-helppy-outline" id="provider-locate">
                    <i class="bi bi-crosshair"></i> Vendndodhja ime
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger" id="provider-clear-pin">
                    <i class="bi bi-x-circle"></i> Hiq shenjën
                  </button>
                </div>
              </div>
            </div>

            <!-- RIGHT: the map itself -->
            <div class="col-lg-7 location-map-col">
              <p class="small text-muted mb-2">
                <i class="bi bi-pin-map"></i>
                Kliko në hartë për të vendosur shenjën. Klientët do ta hapin direkt në Google Maps.
              </p>
              <div id="provider-map" class="provider-map"
                   data-provider="<?= $gmapsEnabled ? 'gmaps' : 'leaflet' ?>"
                   data-init-lat="<?= $hasPin ? e((string)$p['latitude'])  : '' ?>"
                   data-init-lng="<?= $hasPin ? e((string)$p['longitude']) : '' ?>"></div>
              <input type="hidden" name="latitude"  id="provider-lat" value="<?= $hasPin ? e((string)$p['latitude'])  : '' ?>">
              <input type="hidden" name="longitude" id="provider-lng" value="<?= $hasPin ? e((string)$p['longitude']) : '' ?>">
            </div>
          </div>
        </fieldset>

        <div class="d-flex justify-content-end">
          <button class="btn btn-helppy btn-lg" type="submit">
            <i class="bi bi-check2"></i> Ruaj ndryshimet
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ Foto të punës (portfolio) ============ -->
  <section id="work-photos" class="mt-4">
    <h3 class="section-title"><i class="bi bi-images"></i> Foto të punës</h3>

    <div class="row g-3">
      <div class="col-lg-5">
        <?php if (!$subscription): ?>
          <div class="alert alert-warning mb-0">
            <strong><i class="bi bi-lock"></i> Funksion me abonim aktiv.</strong>
            <p class="mb-2 small">
              Aktivizo një abonim për të shtuar foto të punës që klientët t'i shohin
              në profilin tënd publik.
            </p>
            <a class="btn btn-helppy btn-sm w-100" href="<?= e(CONFIG['base_url']) ?>/subscribe">
              <i class="bi bi-credit-card"></i> Abonohu
            </a>
          </div>
        <?php else: ?>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/work-photo"
                enctype="multipart/form-data" class="form-card">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <p class="text-muted small mb-2">
              JPG, PNG ose WEBP. Maksimumi 3MB për foto. Deri në 30 foto në total.
            </p>
            <div class="mb-2">
              <label class="form-label">Foto</label>
              <input class="form-control" type="file" name="photo"
                     accept="image/jpeg,image/png,image/webp" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Përshkrim (opsional)</label>
              <input class="form-control" type="text" name="caption" maxlength="120"
                     placeholder="p.sh. Banjë e rinovuar, Prishtinë">
            </div>
            <button class="btn btn-helppy w-100" type="submit">
              <i class="bi bi-plus-lg"></i> Shto foto të punës
            </button>
          </form>
        <?php endif; ?>
      </div>

      <div class="col-lg-7">
        <?php if (!$workPhotos): ?>
          <div class="gallery-empty">
            <i class="bi bi-camera"></i>
            <p class="text-muted mb-0">
              <?php if ($subscription): ?>
                Ende s'ke shtuar foto. Ngarko të parën nga ana e majtë.
              <?php else: ?>
                Ende s'ke shtuar foto. Aktivizo një abonim për të filluar.
              <?php endif; ?>
            </p>
          </div>
        <?php else: ?>
          <div class="gallery-grid">
            <?php foreach ($workPhotos as $w): ?>
              <div class="gallery-item">
                <img src="<?= e(CONFIG['upload_url'] . '/' . rawurlencode((string)$w['filename'])) ?>"
                     alt="<?= e((string)($w['caption'] ?? '')) ?>" loading="lazy">
                <form method="post"
                      action="<?= e(CONFIG['base_url']) ?>/provider/work-photo/<?= (int)$w['id'] ?>/delete"
                      class="gallery-delete"
                      onsubmit="return confirm('Fshi këtë foto?');">
                  <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                  <button type="submit" class="btn btn-sm btn-danger" title="Fshi">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<?php
  $gmapsEnabled = !empty(CONFIG['google_maps']['enabled']) && !empty(CONFIG['google_maps']['api_key']);
  $gm = (array)(CONFIG['google_maps'] ?? []);
?>

<!-- Shared coords/buttons helpers used by both Google Maps and Leaflet modes -->
<script>
window.HELPPY_MAP_CFG = {
  defaultLat:  <?= json_encode((float)($gm['default_lat']  ?? 42.6629)) ?>,
  defaultLng:  <?= json_encode((float)($gm['default_lng']  ?? 21.1655)) ?>,
  defaultZoom: <?= json_encode((int)  ($gm['default_zoom'] ?? 9)) ?>,
  country:     <?= json_encode((string)($gm['country']      ?? 'XK')) ?>
};
</script>

<?php if ($gmapsEnabled): ?>
  <!-- Google Maps mode -->
  <script>
  (function () {
    var mapEl  = document.getElementById('provider-map');
    if (!mapEl) return;
    var latInp = document.getElementById('provider-lat');
    var lngInp = document.getElementById('provider-lng');
    var coords = document.getElementById('provider-coords');
    var locBtn = document.getElementById('provider-locate');
    var clrBtn = document.getElementById('provider-clear-pin');
    var addrIn = document.getElementById('provider-address');
    var cfg    = window.HELPPY_MAP_CFG;
    var initLat = parseFloat(mapEl.getAttribute('data-init-lat'));
    var initLng = parseFloat(mapEl.getAttribute('data-init-lng'));
    var hasPin  = !isNaN(initLat) && !isNaN(initLng);

    function writePin(lat, lng) {
      latInp.value = lat.toFixed(7);
      lngInp.value = lng.toFixed(7);
      coords.innerHTML = '<i class="bi bi-pin-map-fill"></i> ' + lat.toFixed(6) + ', ' + lng.toFixed(6);
    }
    function clearOutputs() {
      latInp.value = '';
      lngInp.value = '';
      coords.innerHTML = '<i class="bi bi-pin-map"></i> Nuk është vendosur ende.';
    }

    window.__helppyInitGMaps = function () {
      var center = hasPin ? { lat: initLat, lng: initLng } : { lat: cfg.defaultLat, lng: cfg.defaultLng };
      var map = new google.maps.Map(mapEl, {
        center: center,
        zoom:   hasPin ? 16 : cfg.defaultZoom,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
      });

      var marker = null;
      function setPin(lat, lng) {
        if (!marker) {
          marker = new google.maps.Marker({
            map: map, position: { lat: lat, lng: lng }, draggable: true,
          });
          marker.addListener('dragend', function (e) {
            writePin(e.latLng.lat(), e.latLng.lng());
          });
        } else {
          marker.setPosition({ lat: lat, lng: lng });
        }
        writePin(lat, lng);
      }
      function clearPin() {
        if (marker) { marker.setMap(null); marker = null; }
        clearOutputs();
      }
      if (hasPin) setPin(initLat, initLng);

      map.addListener('click', function (e) { setPin(e.latLng.lat(), e.latLng.lng()); });

      if (locBtn) locBtn.addEventListener('click', function () {
        if (!navigator.geolocation) return alert('Vendndodhja nuk mbështetet.');
        navigator.geolocation.getCurrentPosition(function (pos) {
          var lat = pos.coords.latitude, lng = pos.coords.longitude;
          map.setCenter({ lat: lat, lng: lng });
          map.setZoom(17);
          setPin(lat, lng);
        }, function () { alert('Nuk munda të lexoj vendndodhjen.'); });
      });
      if (clrBtn) clrBtn.addEventListener('click', clearPin);

      // Places Autocomplete on the address input.
      if (addrIn && google.maps.places) {
        var ac = new google.maps.places.Autocomplete(addrIn, {
          fields: ['geometry', 'formatted_address'],
          componentRestrictions: cfg.country ? { country: cfg.country.toLowerCase() } : undefined,
        });
        ac.addListener('place_changed', function () {
          var place = ac.getPlace();
          if (!place || !place.geometry || !place.geometry.location) return;
          var loc = place.geometry.location;
          map.setCenter(loc);
          map.setZoom(17);
          setPin(loc.lat(), loc.lng());
        });
        // Don't submit the form when pressing Enter to pick an autocomplete suggestion.
        addrIn.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') e.preventDefault();
        });
      }
    };
  })();
  </script>
  <script async defer
          src="https://maps.googleapis.com/maps/api/js?key=<?= e(CONFIG['google_maps']['api_key']) ?>&libraries=places&loading=async&callback=__helppyInitGMaps"></script>

<?php else: ?>
  <!-- Leaflet/OpenStreetMap fallback (no API key) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
  (function () {
    var mapEl  = document.getElementById('provider-map');
    if (!mapEl || typeof L === 'undefined') return;
    var latInp = document.getElementById('provider-lat');
    var lngInp = document.getElementById('provider-lng');
    var coords = document.getElementById('provider-coords');
    var locBtn = document.getElementById('provider-locate');
    var clrBtn = document.getElementById('provider-clear-pin');
    var addrIn = document.getElementById('provider-address');
    var srchBt = document.getElementById('provider-address-search');
    var cfg    = window.HELPPY_MAP_CFG;
    var initLat = parseFloat(mapEl.getAttribute('data-init-lat'));
    var initLng = parseFloat(mapEl.getAttribute('data-init-lng'));
    var hasPin  = !isNaN(initLat) && !isNaN(initLng);

    var map = L.map(mapEl, { scrollWheelZoom: false })
      .setView(hasPin ? [initLat, initLng] : [cfg.defaultLat, cfg.defaultLng], hasPin ? 16 : cfg.defaultZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(map);
    map.on('focus', function () { map.scrollWheelZoom.enable(); });
    map.on('blur',  function () { map.scrollWheelZoom.disable(); });

    var marker = null;
    function writePin(lat, lng) {
      latInp.value = lat.toFixed(7);
      lngInp.value = lng.toFixed(7);
      coords.innerHTML = '<i class="bi bi-pin-map-fill"></i> ' + lat.toFixed(6) + ', ' + lng.toFixed(6);
    }
    function setPin(lat, lng) {
      if (marker) marker.setLatLng([lat, lng]);
      else        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function () { var pos = marker.getLatLng(); writePin(pos.lat, pos.lng); });
      writePin(lat, lng);
    }
    function clearPin() {
      if (marker) { map.removeLayer(marker); marker = null; }
      latInp.value = '';
      lngInp.value = '';
      coords.innerHTML = '<i class="bi bi-pin-map"></i> Nuk është vendosur ende.';
    }
    if (hasPin) setPin(initLat, initLng);
    map.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng); });
    if (locBtn) locBtn.addEventListener('click', function () {
      if (!navigator.geolocation) return alert('Vendndodhja nuk mbështetet.');
      navigator.geolocation.getCurrentPosition(function (pos) {
        var lat = pos.coords.latitude, lng = pos.coords.longitude;
        map.setView([lat, lng], 17);
        setPin(lat, lng);
      }, function () { alert('Nuk munda të lexoj vendndodhjen.'); });
    });
    if (clrBtn) clrBtn.addEventListener('click', clearPin);

    // Free address geocoding via Nominatim (no key). 1 req/sec rate limit applies.
    function geocode() {
      var q = (addrIn.value || '').trim();
      if (!q) return;
      var url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q);
      fetch(url, { headers: { 'Accept': 'application/json' }})
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (rows) {
          if (!rows || !rows.length) return alert('Nuk u gjet asnjë adresë.');
          var lat = parseFloat(rows[0].lat), lng = parseFloat(rows[0].lon);
          map.setView([lat, lng], 17);
          setPin(lat, lng);
        })
        .catch(function () { alert('Kërkimi i adresës dështoi.'); });
    }
    if (srchBt) srchBt.addEventListener('click', geocode);
    if (addrIn) addrIn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); geocode(); }
    });
  })();
  </script>
<?php endif; ?>

<!-- Drill-down category picker -->
<script>
(function () {
  var picker = document.querySelector('[data-cat-picker]');
  if (!picker) return;
  var topView    = picker.querySelector('[data-cat-picker-top]');
  var drilledBar = picker.querySelector('[data-cat-picker-drilled]');
  var backBtn    = picker.querySelector('[data-cat-back]');
  var activeLbl  = picker.querySelector('[data-cat-active-label]');
  var groups     = Array.prototype.slice.call(picker.querySelectorAll('[data-cat-group]'));
  var standalones = picker.querySelector('[data-cat-standalones]');

  function openGroup(parentId, label) {
    groups.forEach(function (g) {
      var match = g.getAttribute('data-cat-group') === String(parentId);
      g.classList.toggle('is-active-group', match);
      g.classList.toggle('is-hidden', !match);
      var kidsBox = g.querySelector('[data-cat-children]');
      if (kidsBox) kidsBox.hidden = !match;
    });
    if (standalones) standalones.hidden = true;
    if (drilledBar)  drilledBar.hidden  = false;
    if (activeLbl)   activeLbl.textContent = label;
  }
  function closeGroup() {
    groups.forEach(function (g) {
      g.classList.remove('is-active-group');
      g.classList.remove('is-hidden');
      var kidsBox = g.querySelector('[data-cat-children]');
      if (kidsBox) kidsBox.hidden = true;
    });
    if (standalones) standalones.hidden = false;
    if (drilledBar)  drilledBar.hidden  = true;
  }
  groups.forEach(function (g) {
    var toggle = g.querySelector('[data-cat-toggle]');
    if (!toggle) return;
    toggle.addEventListener('click', function () {
      var pid = toggle.getAttribute('data-cat-toggle');
      var labelEl = toggle.querySelector('span');
      openGroup(pid, labelEl ? labelEl.textContent : '');
    });
  });
  if (backBtn) backBtn.addEventListener('click', closeGroup);

  // Keep the per-umbrella selected count badge in sync as checkboxes flip.
  picker.addEventListener('change', function (e) {
    var box = e.target;
    if (!box.matches || !box.matches('input[type=checkbox]')) return;
    var pid = box.getAttribute('data-cat-child-of');
    if (!pid) return;
    var count = picker.querySelectorAll('input[data-cat-child-of="' + pid + '"]:checked').length;
    var badge = picker.querySelector('[data-cat-count="' + pid + '"]');
    var toggle = picker.querySelector('[data-cat-toggle="' + pid + '"]');
    if (badge) {
      badge.textContent = String(count);
      badge.classList.toggle('d-none', count === 0);
    }
    if (toggle) toggle.classList.toggle('has-selected', count > 0);
  });
})();
</script>

<!-- Inline add-category (no page reload) -->
<script>
(function () {
  var root = document.querySelector('[data-new-cat]');
  if (!root) return;
  var input  = root.querySelector('[data-new-cat-input]');
  var btn    = root.querySelector('[data-new-cat-submit]');
  var hint   = document.querySelector('[data-new-cat-hint]');
  var standalones = document.querySelector('[data-cat-standalones]');
  if (!input || !btn || !standalones) return;

  function escapeHTML(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function setHint(msg, ok) {
    if (!hint) return;
    hint.innerHTML = msg;
    hint.classList.toggle('text-success', !!ok);
    hint.classList.toggle('text-danger', ok === false);
    hint.classList.toggle('text-muted', ok === undefined || ok === null);
  }
  function add() {
    var name = (input.value || '').trim();
    if (name.length < 2) {
      setHint('Emri duhet të jetë së paku 2 karaktere.', false);
      input.focus();
      return;
    }
    btn.disabled = true;
    var prevHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

    var fd = new FormData();
    fd.append('name', name);
    fd.append('_csrf', (document.querySelector('input[name=_csrf]') || {}).value || '');

    fetch((window.HELPPY_BASE || '') + '/provider/categories/add', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      body: fd
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (!res.ok || !res.data || !res.data.ok) {
          var msg = (res.data && res.data.error) || 'Gabim i panjohur.';
          setHint(msg, false);
          return;
        }
        var cat = res.data.category;
        var picker = document.querySelector('[data-cat-picker]');
        // Pick the right destination: child of an existing umbrella, or standalone.
        var dest = standalones;
        if (cat.parent_id && picker) {
          var kidsBox = picker.querySelector('[data-cat-children="' + cat.parent_id + '"]');
          if (kidsBox) dest = kidsBox;
        }
        // If already in the list, just check it.
        var existing = picker
          ? picker.querySelector('input[type=checkbox][value="' + cat.id + '"]')
          : standalones.querySelector('input[type=checkbox][value="' + cat.id + '"]');
        if (existing) {
          existing.checked = true;
          // Trigger change so the umbrella's count badge updates.
          existing.dispatchEvent(new Event('change', { bubbles: true }));
          var lbl = existing.closest('.category-check');
          if (lbl) flash(lbl);
          setHint('Kjo kategori ishte tashmë në listën tënde — e shënuam si të zgjedhur.', true);
        } else {
          var label = document.createElement('label');
          label.className = 'category-check is-just-added';
          var childAttr = cat.parent_id ? ' data-cat-child-of="' + cat.parent_id + '"' : '';
          label.innerHTML =
            '<input type="checkbox" name="categories[]" value="' + cat.id + '" checked' + childAttr + '>' +
            (cat.icon ? '<i class="bi ' + escapeHTML(cat.icon) + '"></i> ' : '') +
            '<span>' + escapeHTML(cat.name) + '</span>';
          dest.appendChild(label);
          // Fire change so the umbrella's count badge updates if applicable.
          label.querySelector('input').dispatchEvent(new Event('change', { bubbles: true }));
          flash(label);
          setHint('U shtua <strong>' + escapeHTML(cat.name) + '</strong> dhe u zgjodh automatikisht.', true);
        }
        input.value = '';
        // Auto-clear the hint after a moment so it doesn't linger.
        setTimeout(function () { setHint('Klikoni <strong>Shto</strong> për ta shtuar menjëherë (pa nevojë të ruani profilin).', null); }, 4500);
      })
      .catch(function () { setHint('Lidhja dështoi. Provo përsëri.', false); })
      .finally(function () {
        btn.disabled = false;
        btn.innerHTML = prevHtml;
      });
  }
  function flash(el) {
    el.classList.add('is-just-added');
    setTimeout(function () { el.classList.remove('is-just-added'); }, 2400);
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  btn.addEventListener('click', add);
  // Don't let Enter inside this input submit the surrounding profile form.
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); add(); }
  });
})();
</script>

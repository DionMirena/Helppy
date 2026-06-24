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
            <div class="category-checks">
              <?php foreach ($categories as $cat):
                $checked = in_array((int)$cat['id'], $selectedCats, true); ?>
                <label class="category-check">
                  <input type="checkbox" name="categories[]" value="<?= (int)$cat['id'] ?>" <?= $checked?'checked':'' ?>>
                  <span><?= e($cat['name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
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

        <!-- ===== Lokacioni ===== -->
        <fieldset class="form-card">
          <legend class="form-card-title"><i class="bi bi-geo-alt-fill"></i> Lokacioni</legend>

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
                Adresa do të autoplotësohet ndërsa shkruan; shenja do të vendoset automatikisht në hartë.
              <?php else: ?>
                Shkruaj adresën dhe kliko <em>Kërko</em> — ose kliko direkt në hartë.
              <?php endif; ?>
            </small>
          </div>

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
          <div class="d-flex align-items-center gap-2 mt-2 small flex-wrap">
            <span id="provider-coords" class="text-muted">
              <?php if ($hasPin): ?>
                <i class="bi bi-pin-map-fill"></i>
                <?= e(number_format((float)$p['latitude'], 6)) ?>, <?= e(number_format((float)$p['longitude'], 6)) ?>
              <?php else: ?>
                <i class="bi bi-pin-map"></i> Nuk është vendosur ende.
              <?php endif; ?>
            </span>
            <button type="button" class="btn btn-sm btn-link p-0 ms-auto" id="provider-locate">
              <i class="bi bi-crosshair"></i> Përdor vendndodhjen time
            </button>
            <button type="button" class="btn btn-sm btn-link p-0 text-danger" id="provider-clear-pin">
              <i class="bi bi-x-circle"></i> Hiq shenjën
            </button>
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

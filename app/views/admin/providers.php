<section class="container py-4">
  <div id="provider-search-header" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h1 class="section-title mb-0">Punetoret (<span id="provider-count"><?= count($providers) ?></span>)</h1>

    <div class="input-group" style="max-width:280px;">
      <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
      <input type="text" id="provider-search" class="form-control border-start-0 ps-0"
             placeholder="Kërko punëtor…" autocomplete="off">
    </div>
  </div>

  <div class="bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow); overflow: hidden;">
    <div class="admin-scroll-list" style="max-height: 780px; overflow-y: auto; overscroll-behavior: contain;">
      <table class="table table-hover align-middle mb-0" id="providers-table">
        <thead class="sticky-top bg-white" style="z-index: 2;">
          <tr>
            <th style="width:44px">#</th>
            <th>Emri</th>
            <th>Email</th>
            <th>Profesioni</th>
            <th>Verifikuar</th>
            <th>Aktiv</th>
            <th>Premium</th>
            <th>Veprime</th>
          </tr>
        </thead>
        <tbody>
          <?php $n = 1; foreach ($providers as $p): ?>
          <tr data-search="<?= e(mb_strtolower($p['name'] . ' ' . $p['email'] . ' ' . $p['profession'])) ?>">
            <td class="text-muted"><?= $n++ ?></td>
            <td><a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>" target="_blank"><?= e($p['name']) ?></a></td>
            <td><small><?= e($p['email']) ?></small></td>
            <td><?= e($p['profession']) ?></td>
            <td><?= $p['email_verified'] ? '<i class="bi bi-check-circle-fill text-success" title="I verifikuar"></i>' : '<i class="bi bi-x-circle-fill text-danger" title="I paverifikuar"></i>' ?></td>
            <td><?= $p['is_active']  ? '<span class="status-badge status-accepted">Aktiv</span>' : '<span class="status-badge status-cancelled">Joaktiv</span>' ?></td>
            <td><?= $p['is_premium'] ? '<span class="premium-badge">PREMIUM</span>' : '<span class="text-muted">—</span>' ?></td>
            <td>
              <div class="inline-actions">
                <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/providers/<?= (int)$p['id'] ?>/active" class="d-inline">
                  <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                  <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $p['is_active'] ? 'Çaktivizo' : 'Aktivizo' ?></button>
                </form>
                <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/providers/<?= (int)$p['id'] ?>/premium" class="d-inline">
                  <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                  <button class="btn btn-sm btn-outline-warning" type="submit"><?= $p['is_premium'] ? 'Hiq premium' : 'Bëje premium' ?></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
  (function () {
    var input  = document.getElementById('provider-search');
    var count  = document.getElementById('provider-count');
    var rows   = Array.prototype.slice.call(document.querySelectorAll('#providers-table tbody tr'));

    input.addEventListener('input', function () {
      var q = input.value.toLowerCase().trim();
      var visible = 0;
      rows.forEach(function (tr) {
        var match = !q || (tr.getAttribute('data-search') || '').indexOf(q) !== -1;
        tr.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      count.textContent = q ? visible : rows.length;
    });
  })();
  </script>
</section>

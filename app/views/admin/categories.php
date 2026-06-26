<section class="container py-4">
  <h1 class="section-title">Kategorite</h1>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var row = document.querySelector('[data-just-added]');
    if (!row) return;
    // Bring the new row into view smoothly.
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    // Clean URL so refreshing the page doesn't re-trigger the highlight.
    if (window.history && window.history.replaceState) {
      var u = new URL(window.location.href);
      u.searchParams.delete('new');
      window.history.replaceState({}, document.title, u.pathname + (u.search ? u.search : ''));
    }
  });
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-icon-picker]');
    if (!root) return;
    var toggle  = root.querySelector('[data-icon-picker-toggle]');
    var panel   = root.querySelector('[data-icon-picker-panel]');
    var label   = root.querySelector('[data-icon-picker-label]');
    var value   = root.querySelector('[data-icon-picker-value]');
    var preview = document.getElementById('icon-preview');
    var filter  = root.querySelector('[data-icon-picker-filter]');
    var manual  = root.querySelector('[data-icon-picker-manual]');
    var closeBt = root.querySelector('[data-icon-picker-close]');
    var empty   = root.querySelector('[data-icon-picker-empty]');
    var items   = Array.prototype.slice.call(root.querySelectorAll('.icon-picker-item'));
    var groups  = Array.prototype.slice.call(root.querySelectorAll('.icon-picker-group'));

    function setIcon(name) {
      var bare = name.indexOf('bi-') === 0 ? name.slice(3) : name;
      value.value      = 'bi-' + bare;
      label.textContent = 'bi-' + bare;
      preview.className = 'bi bi-' + bare;
    }
    function open()  { panel.hidden = false; root.classList.add('is-open');  if (filter) filter.focus(); }
    function close() { panel.hidden = true;  root.classList.remove('is-open'); }
    function applyFilter(q) {
      q = q.toLowerCase().trim();
      var anyVisible = false;
      groups.forEach(function (g) {
        var groupName = g.getAttribute('data-icon-group') || '';
        var visibleInGroup = 0;
        g.querySelectorAll('.icon-picker-item').forEach(function (it) {
          var n = (it.getAttribute('data-icon-name') || '').toLowerCase();
          var match = !q || n.indexOf(q) !== -1 || groupName.indexOf(q) !== -1;
          it.style.display = match ? '' : 'none';
          if (match) { visibleInGroup++; anyVisible = true; }
        });
        g.style.display = visibleInGroup > 0 ? '' : 'none';
      });
      if (empty) empty.hidden = anyVisible;
    }

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (panel.hidden) open(); else close();
    });
    items.forEach(function (it) {
      it.addEventListener('click', function () {
        setIcon(it.getAttribute('data-icon-name') || 'bi-tag');
        close();
      });
    });
    if (filter) filter.addEventListener('input', function () { applyFilter(filter.value); });
    if (manual) manual.addEventListener('input', function () {
      var v = manual.value.trim();
      if (v) setIcon(v);
    });
    if (closeBt) closeBt.addEventListener('click', close);
    document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
  });
  </script>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="table-responsive bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow);">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>ID</th><th>Emri</th><th>Slug</th><th>Ikona</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($categories as $c):
              $isJustAdded = !empty($highlightId) && (int)$c['id'] === (int)$highlightId;
            ?>
            <tr class="<?= $isJustAdded ? 'is-just-added' : '' ?>" <?= $isJustAdded ? 'data-just-added' : '' ?>>
              <td><?= (int)$c['id'] ?></td>
              <td><?= e($c['name']) ?></td>
              <td><code><?= e($c['slug']) ?></code></td>
              <td>
                <?php $iconClass = trim((string)($c['icon'] ?? '')); ?>
                <?php if ($iconClass !== ''): ?>
                  <i class="bi <?= e($iconClass) ?> category-icon-preview"></i>
                <?php endif; ?>
                <small class="text-muted ms-1"><?= e($iconClass) ?></small>
              </td>
              <td>
                <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/categories/<?= (int)$c['id'] ?>/delete" class="d-inline"
                      onsubmit="return confirm('Fshi kete kategori?');">
                  <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">
                    <i class="bi bi-trash"></i> Fshi
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-5">
      <?php $iconGroups = require APP_ROOT . '/app/data/bootstrap_icons.php'; ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/categories" class="form-card" id="category-form">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <h5 class="mb-3">Shto kategori</h5>
        <div class="mb-3"><label class="form-label">Emri</label>
          <input class="form-control" name="name" required></div>
        <div class="mb-3"><label class="form-label">Slug</label>
          <input class="form-control" name="slug" placeholder="vetem-shkronja-pa-hapesira" required></div>

        <div class="mb-3">
          <label class="form-label">Ikona</label>
          <div class="icon-picker" data-icon-picker>
            <button type="button" class="icon-picker-toggle" data-icon-picker-toggle>
              <span class="icon-picker-preview"><i id="icon-preview" class="bi bi-tag"></i></span>
              <span class="icon-picker-label" data-icon-picker-label>bi-tag</span>
              <i class="bi bi-chevron-down ms-auto"></i>
            </button>
            <input type="hidden" name="icon" value="bi-tag" data-icon-picker-value>

            <div class="icon-picker-panel" data-icon-picker-panel hidden>
              <div class="icon-picker-search">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Kërko ikonë (p.sh. car, paint, key)…"
                       data-icon-picker-filter aria-label="Filtro ikonat">
                <button type="button" class="btn btn-sm btn-link p-0 ms-auto"
                        data-icon-picker-close title="Mbyll">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
              <div class="icon-picker-groups">
                <?php foreach ($iconGroups as $group => $icons): ?>
                  <div class="icon-picker-group" data-icon-group="<?= e(mb_strtolower($group)) ?>">
                    <div class="icon-picker-group-title"><?= e($group) ?></div>
                    <div class="icon-picker-grid">
                      <?php foreach ($icons as $name): ?>
                        <button type="button" class="icon-picker-item"
                                data-icon-name="<?= e($name) ?>"
                                title="<?= e($name) ?>">
                          <i class="bi <?= e($name) ?>"></i>
                        </button>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="icon-picker-empty text-muted small text-center py-3"
                     data-icon-picker-empty hidden>
                  <i class="bi bi-emoji-frown"></i> Asnjë ikonë nuk përputhet.
                </div>
              </div>
              <div class="icon-picker-footer">
                <small class="text-muted">Ose shkruani vetë:</small>
                <input type="text" class="form-control form-control-sm icon-picker-manual"
                       placeholder="bi-..." data-icon-picker-manual>
              </div>
            </div>
          </div>
        </div>

        <button class="btn btn-helppy w-100" type="submit"><i class="bi bi-plus-lg"></i> Shto</button>
      </form>
    </div>
  </div>
</section>

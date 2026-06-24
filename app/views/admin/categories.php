<section class="container py-4">
  <h1 class="section-title">Kategorite</h1>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="table-responsive bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow);">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>ID</th><th>Emri</th><th>Slug</th><th>Ikona</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td><?= e($c['name']) ?></td>
              <td><code><?= e($c['slug']) ?></code></td>
              <td><i class="<?= e($c['icon'] ?? '') ?>"></i> <small class="text-muted"><?= e($c['icon'] ?? '') ?></small></td>
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
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/categories" class="form-card">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <h5 class="mb-3">Shto kategori</h5>
        <div class="mb-3"><label class="form-label">Emri</label>
          <input class="form-control" name="name" required></div>
        <div class="mb-3"><label class="form-label">Slug</label>
          <input class="form-control" name="slug" placeholder="vetem-shkronja-pa-hapesira" required></div>
        <div class="mb-3"><label class="form-label">Ikona (bootstrap-icons)</label>
          <input class="form-control" name="icon" placeholder="bi-wrench"></div>
        <button class="btn btn-helppy w-100" type="submit"><i class="bi bi-plus-lg"></i> Shto</button>
      </form>
    </div>
  </div>
</section>

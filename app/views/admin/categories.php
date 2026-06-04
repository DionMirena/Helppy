<div class="container py-4">
  <h2>Kategorite</h2>

  <div class="row">
    <div class="col-md-7">
      <table class="table bg-white">
        <thead><tr><th>ID</th><th>Emri</th><th>Slug</th><th>Ikona</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><?= e($c['name']) ?></td>
            <td><code><?= e($c['slug']) ?></code></td>
            <td><i class="<?= e($c['icon'] ?? '') ?>"></i> <small class="text-muted"><?= e($c['icon'] ?? '') ?></small></td>
            <td>
              <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/categories/<?= (int)$c['id'] ?>/delete">
                <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"
                        onclick="return confirm('Fshi kete kategori?');">Fshi</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="col-md-5">
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/categories" class="bg-white p-3 rounded">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <h5>Shto kategori</h5>
        <div class="mb-2"><label class="form-label">Emri</label>
          <input class="form-control" name="name" required></div>
        <div class="mb-2"><label class="form-label">Slug</label>
          <input class="form-control" name="slug" placeholder="vetem-shkronja-pa-hapesira" required></div>
        <div class="mb-2"><label class="form-label">Ikona (bootstrap-icons)</label>
          <input class="form-control" name="icon" placeholder="bi-wrench"></div>
        <button class="btn btn-helppy" type="submit">Shto</button>
      </form>
    </div>
  </div>
</div>

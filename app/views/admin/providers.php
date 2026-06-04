<div class="container py-4">
  <h2>Punetoret</h2>
  <table class="table table-striped bg-white">
    <thead>
      <tr>
        <th>ID</th><th>Emri</th><th>Email</th><th>Profesioni</th>
        <th>Aktiv</th><th>Premium</th><th>Veprime</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($providers as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>" target="_blank"><?= e($p['name']) ?></a></td>
        <td><?= e($p['email']) ?></td>
        <td><?= e($p['profession']) ?></td>
        <td><?= $p['is_active']  ? '<span class="badge bg-success">Aktiv</span>' : '<span class="badge bg-secondary">Joaktiv</span>' ?></td>
        <td><?= $p['is_premium'] ? '<span class="badge bg-warning text-dark">Premium</span>' : '<span class="text-muted">—</span>' ?></td>
        <td>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/providers/<?= (int)$p['id'] ?>/active" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $p['is_active'] ? 'Cdeaktivizo' : 'Aktivizo' ?></button>
          </form>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/providers/<?= (int)$p['id'] ?>/premium" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-sm btn-outline-warning" type="submit"><?= $p['is_premium'] ? 'Hiq premium' : 'Beje premium' ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

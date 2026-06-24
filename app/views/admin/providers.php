<section class="container py-4">
  <h1 class="section-title">Punetoret (<?= count($providers) ?>)</h1>

  <div class="table-responsive bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow);">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
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
        <?php foreach ($providers as $p): ?>
        <tr>
          <td><?= (int)$p['id'] ?></td>
          <td><a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>" target="_blank"><?= e($p['name']) ?></a></td>
          <td><small class="text-soft-wrap"><?= e($p['email']) ?></small></td>
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
</section>

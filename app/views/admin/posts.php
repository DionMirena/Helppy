<section class="container py-4">
  <h1 class="section-title">Postimet (<?= count($posts) ?>)</h1>

  <?php if (!$posts): ?>
    <p class="text-muted">Asnjë postim.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Titulli</th>
            <th>Tipi</th>
            <th>Autori</th>
            <th>Kategori / Qytet</th>
            <th>Data</th>
            <th>Statusi</th>
            <th>Veprime</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($posts as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td>
                <a href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>">
                  <?= e($p['title']) ?>
                </a>
              </td>
              <td>
                <span class="post-badge <?= $p['type'] === 'offer' ? 'post-badge-offer' : 'post-badge-request' ?>" style="position: static;">
                  <?= $p['type'] === 'offer' ? 'Ofertë' : 'Kërkesë' ?>
                </span>
              </td>
              <td><?= e($p['author_name']) ?> <small class="text-muted">(<?= e($p['author_role']) ?>)</small></td>
              <td><small><?= e($p['category_name']) ?> &middot; <?= e($p['city_name']) ?></small></td>
              <td><small><?= e(date('d M Y', strtotime((string)$p['created_at']))) ?></small></td>
              <td>
                <?php if ($p['status'] === 'active'):  ?><span class="badge text-bg-success">aktiv</span>
                <?php elseif ($p['status'] === 'closed'): ?><span class="badge text-bg-secondary">mbyllur</span>
                <?php else: ?><span class="badge text-bg-warning">fshehur</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="inline-actions">
                  <?php if ($p['status'] !== 'hidden'): ?>
                    <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/posts/<?= (int)$p['id'] ?>/hide" class="d-inline">
                      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                      <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-eye-slash"></i> Fsheh</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/delete" class="d-inline"
                        onsubmit="return confirm('Fshi postimin përgjithmonë?');">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Fshi</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

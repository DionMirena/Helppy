<div class="container py-4">
  <h2>Llogaria ime</h2>
  <div class="bg-white p-3 rounded mb-4">
    <p class="mb-1"><strong><?= e($user['name']) ?></strong></p>
    <p class="mb-1 text-muted small"><?= e($user['email']) ?></p>
    <?php if (!empty($user['phone'])): ?>
      <p class="mb-0 text-muted small"><?= e($user['phone']) ?></p>
    <?php endif; ?>
  </div>

  <h4>Vleresimet e mia (<?= count($reviews) ?>)</h4>
  <?php if (!$reviews): ?>
    <p class="text-muted">Nuk keni lene asnje vleresim ende.</p>
  <?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <div class="review-card">
      <div class="d-flex justify-content-between">
        <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$r['provider_id'] ?>"><?= e($r['provider_name']) ?></a>
        <span class="meta"><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></span>
      </div>
      <div class="stars">
        <?php for ($i=1;$i<=5;$i++): ?>
          <i class="bi <?= $i <= (int)$r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
        <?php endfor; ?>
      </div>
      <?php if (!empty($r['comment'])): ?>
        <p class="mb-1"><?= nl2br(e($r['comment'])) ?></p>
      <?php endif; ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/review/<?= (int)$r['id'] ?>/delete" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button class="btn btn-sm btn-link text-danger p-0" type="submit"
                onclick="return confirm('Fshi vleresimin?');">Fshi vleresimin</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

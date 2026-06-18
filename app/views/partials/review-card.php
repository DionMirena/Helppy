<div class="review-card">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <strong><?= e($r['client_name']) ?></strong>
      <span class="stars ms-2">
        <?php for ($i=1;$i<=5;$i++): ?>
          <i class="bi <?= $i <= (int)$r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
        <?php endfor; ?>
      </span>
    </div>
    <div class="meta"><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></div>
  </div>
  <?php if (!empty($r['comment'])): ?>
    <p class="mb-1 mt-1 long-text"><?= nl2br(e($r['comment'])) ?></p>
  <?php endif; ?>
  <?php if (Auth::check()): ?>
    <?php $uid = (int)Auth::user()['id']; ?>
    <?php if ($uid === (int)$r['client_id']): ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/review/<?= (int)$r['id'] ?>/delete" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button class="btn btn-sm btn-link text-danger p-0" type="submit"
                onclick="return confirm('Fshi vleresimin?');">Fshi</button>
      </form>
    <?php elseif (Auth::role() === 'admin'): ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/reviews/<?= (int)$r['id'] ?>/delete" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button class="btn btn-sm btn-link text-danger p-0" type="submit"
                onclick="return confirm('Fshi vleresimin si admin?');">Fshi (admin)</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

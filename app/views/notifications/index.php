<section class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="section-title mb-0">Njoftimet</h1>
    <?php if ($notes): ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/notifications/read-all" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button class="btn btn-sm btn-helppy-outline" type="submit">Shëno të gjitha si të lexuara</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$notes): ?>
    <div class="empty-state">
      <i class="bi bi-bell-slash"></i>
      <p>Nuk ke njoftime.</p>
    </div>
  <?php else: ?>
    <div class="notification-list">
      <?php foreach ($notes as $n): ?>
        <a class="notification-item <?= $n['read_at'] === null ? 'is-unread' : '' ?>"
           href="<?= e(CONFIG['base_url']) ?><?= e($n['link'] ?? '/notifications') ?>">
          <div class="notification-icon"><i class="bi <?= e(notificationIcon((string)$n['type'])) ?>"></i></div>
          <div class="notification-body">
            <div class="notification-title"><?= e($n['title']) ?></div>
            <?php if (!empty($n['body'])): ?>
              <div class="notification-text"><?= nl2br(e($n['body'])) ?></div>
            <?php endif; ?>
            <div class="notification-meta"><?= e(timeAgoSq((string)$n['created_at'])) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

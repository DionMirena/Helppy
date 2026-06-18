<section class="container py-4">
  <h1 class="section-title">Bisedat</h1>

  <?php if (!$conversations): ?>
    <div class="empty-state">
      <i class="bi bi-chat-square-dots"></i>
      <p>Nuk ke biseda ende.</p>
      <p class="text-muted small">Hap profilin e një punëtori dhe kliko <strong>Bisedo</strong> për të nisur.</p>
    </div>
  <?php else: ?>
    <div class="conversation-list">
      <?php foreach ($conversations as $c): ?>
        <a class="conversation-item <?= (int)$c['unread_count'] > 0 ? 'has-unread' : '' ?>"
           href="<?= e(CONFIG['base_url']) ?>/chat/<?= (int)$c['id'] ?>">
          <div class="conversation-avatar"><i class="bi bi-person-circle"></i></div>
          <div class="conversation-body">
            <div class="conversation-row">
              <span class="conversation-name"><?= e($c['other_name']) ?></span>
              <?php if ($c['last_message_at']): ?>
                <span class="conversation-time"><?= e(timeAgoSq((string)$c['last_message_at'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="conversation-preview"><?= e($c['last_body'] !== null ? mb_substr((string)$c['last_body'], 0, 120) : 'Asnjë mesazh ende') ?></div>
          </div>
          <?php if ((int)$c['unread_count'] > 0): ?>
            <span class="conversation-unread"><?= (int)$c['unread_count'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

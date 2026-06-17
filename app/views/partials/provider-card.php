<?php
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : CONFIG['base_url'] . '/assets/img/default-avatar.svg';
$avg = isset($p['avg_rating']) && $p['avg_rating'] !== null ? round((float)$p['avg_rating'], 1) : null;
$phoneRaw = !empty($p['phone']) ? preg_replace('/[^0-9+]/', '', $p['phone']) : '';
?>
<div class="provider-card">
  <img class="avatar" src="<?= e($photoUrl) ?>" alt="<?= e($p['name']) ?>">

  <div class="info">
    <p class="name">
      <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>">
        <?= e($p['name']) ?>
      </a>
      <?php if (!empty($p['is_premium'])): ?><span class="premium-badge">PREMIUM</span><?php endif; ?>
    </p>
    <p class="profession">
      <?= e($p['profession']) ?>
      <?php if (!empty($p['city'])): ?> &middot; <?= e($p['city']) ?><?php endif; ?>
    </p>
    <p class="stars">
      <?php if ($avg !== null): ?>
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="bi <?= $i <= round($avg) ? 'bi-star-fill' : 'bi-star' ?>"></i>
        <?php endfor; ?>
        <span class="meta ms-1"><?= e((string)$avg) ?></span>
      <?php else: ?>
        <span class="meta">Pa vleresime</span>
      <?php endif; ?>
    </p>
  </div>

  <?php if (!empty($p['phone'])): ?>
    <div class="card-bottom">
      <span class="phone"><i class="bi bi-telephone-fill"></i> <?= e($p['phone']) ?></span>
      <a class="call-btn" href="tel:<?= e($phoneRaw) ?>">
        <i class="bi bi-telephone"></i> Telefono Tani
      </a>
    </div>
  <?php endif; ?>
</div>

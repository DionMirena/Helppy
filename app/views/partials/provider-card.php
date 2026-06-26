<?php
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : CONFIG['base_url'] . '/assets/img/default-avatar.svg';
$avg = isset($p['avg_rating']) && $p['avg_rating'] !== null ? round((float)$p['avg_rating'], 1) : null;
$phoneRaw = !empty($p['phone']) ? preg_replace('/[^0-9+]/', '', $p['phone']) : '';
$isOnline = Presence::isOnline($p['last_seen_at'] ?? null);
?>
<div class="provider-card<?= $isOnline ? ' is-online' : '' ?>">
  <div class="avatar-wrap">
    <img class="avatar" src="<?= e($photoUrl) ?>" alt="<?= e($p['name']) ?>">
    <span class="presence-dot <?= $isOnline ? 'is-online' : 'is-offline' ?>"
          title="<?= $isOnline ? 'Online tani' : 'Offline' ?>"></span>
  </div>

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
      <?php if (!empty($p['district'])): ?>
        <span class="district-badge"><i class="bi bi-pin-map"></i> <?= e($p['district']) ?></span>
      <?php endif; ?>
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
    <p class="rate">
      <?php if (isset($p['hourly_rate']) && $p['hourly_rate'] !== null): ?>
        <i class="bi bi-cash-coin"></i>
        <strong>€<?= e(rtrim(rtrim(number_format((float)$p['hourly_rate'], 2, '.', ''), '0'), '.')) ?></strong>
        <span class="meta">/ orë</span>
      <?php else: ?>
        <i class="bi bi-chat-left-text"></i>
        <span class="meta">Çmimi sipas marrëveshjes</span>
      <?php endif; ?>
    </p>
  </div>

  <div class="card-bottom">
    <div class="card-actions">
      <?php if (!empty($p['phone'])): ?>
        <a class="card-action card-action-primary"
           href="tel:<?= e($phoneRaw) ?>" title="<?= e($p['phone']) ?>">
          <i class="bi bi-telephone-fill"></i> Telefono
        </a>
      <?php else: ?>
        <span class="card-action card-action-disabled" title="Ska numër">
          <i class="bi bi-telephone-x"></i> Ska numër
        </span>
      <?php endif; ?>
      <a class="card-action card-action-outline"
         href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>">
        <i class="bi bi-person-lines-fill"></i> Hap profilin
      </a>
      <a class="card-action card-action-secondary"
         href="<?= e(CONFIG['base_url']) ?>/chat/with/<?= (int)$p['id'] ?>"
         data-helppy-chat
         data-user-id="<?= (int)$p['id'] ?>"
         data-user-name="<?= e($p['name']) ?>">
        <i class="bi bi-chat-dots"></i> Bisedo
      </a>
    </div>
  </div>
</div>

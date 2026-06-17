<?php
/** @var array $p Joined post row from Post::feed() */
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : null;

$typeLabel = $p['type'] === 'offer' ? 'Ofertë' : 'Kërkesë';
$typeClass = $p['type'] === 'offer' ? 'post-badge-offer' : 'post-badge-request';

$priceLabel = '';
if ($p['type'] === 'offer' && ($p['price_from'] !== null || $p['price_to'] !== null)) {
    $from = $p['price_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['price_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = trim($from . ($from && $to ? ' – ' : '') . $to);
} elseif ($p['type'] === 'request' && ($p['budget_from'] !== null || $p['budget_to'] !== null)) {
    $from = $p['budget_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['budget_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = 'Buxhet: ' . trim($from . ($from && $to ? ' – ' : '') . $to);
}
?>
<a class="post-card" href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>">
  <div class="post-card-photo">
    <?php if ($photoUrl): ?>
      <img src="<?= e($photoUrl) ?>" alt="<?= e($p['title']) ?>">
    <?php else: ?>
      <div class="post-card-placeholder">
        <i class="bi <?= e($p['category_icon'] ?: 'bi-image') ?>"></i>
      </div>
    <?php endif; ?>
    <span class="post-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
  </div>
  <div class="post-card-body">
    <h3 class="post-card-title"><?= e($p['title']) ?></h3>
    <p class="post-card-meta">
      <?= e($p['category_name']) ?> &middot; <?= e($p['city_name']) ?>
    </p>
    <?php if ($priceLabel): ?>
      <p class="post-card-price"><?= e($priceLabel) ?></p>
    <?php endif; ?>
    <p class="post-card-author">
      <i class="bi bi-person"></i> <?= e($p['author_name']) ?>
      <span class="text-muted">&middot; <?= e(timeAgoSq((string)$p['created_at'])) ?></span>
    </p>
  </div>
</a>

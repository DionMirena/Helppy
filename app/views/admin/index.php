<div class="container py-4">
  <h2>Admin Panel</h2>

  <div class="row g-3 mt-2">
    <?php
    $cards = [
      ['Perdorues',       $counts['users']],
      ['Punues',          $counts['providers']],
      ['Klient',          $counts['clients']],
      ['Vleresime',       $counts['reviews']],
    ]; ?>
    <?php foreach ($cards as [$label, $n]): ?>
      <div class="col-6 col-md-3">
        <div class="profile-card text-center h-100">
          <div class="text-muted small"><?= e($label) ?></div>
          <div class="display-6"><?= (int)$n ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="admin-nav-grid">
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/providers"><i class="bi bi-people"></i> Menaxho punetoret</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/categories"><i class="bi bi-tags"></i> Menaxho kategorite</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/posts"><i class="bi bi-postcard"></i> Menaxho postimet</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/subscriptions"><i class="bi bi-credit-card"></i> Abonimet</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/payouts"><i class="bi bi-bank"></i> Llogaria e admin</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/users"><i class="bi bi-person-gear"></i> Përdoruesit</a>
  </div>
</div>

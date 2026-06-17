<div class="container py-4">
  <h2>Admin Panel</h2>

  <div class="row mt-3">
    <?php
    $cards = [
      ['Perdorues',       $counts['users']],
      ['Punues',          $counts['providers']],
      ['Klient',          $counts['clients']],
      ['Vleresime',       $counts['reviews']],
    ]; ?>
    <?php foreach ($cards as [$label, $n]): ?>
      <div class="col-md-3">
        <div class="bg-white p-3 rounded text-center mb-3">
          <div class="text-muted small"><?= e($label) ?></div>
          <div class="display-6"><?= (int)$n ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-3">
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/providers">Menaxho punetoret</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/categories">Menaxho kategorite</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/posts">Menaxho postimet</a>
  </div>
</div>

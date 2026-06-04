<section class="hero">
  <div class="container py-3">
    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="row g-2">
      <div class="col-md-5">
        <select name="city" class="form-select">
          <option value="">Te gjitha qytetet</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $city && $city['id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <select name="category" class="form-select">
          <option value="">Te gjitha kategorite</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $category && $category['id']==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-helppy" type="submit">Kerko</button>
      </div>
    </form>
  </div>
</section>

<section class="container py-4">
  <h2 class="mb-3">
    <?php if ($category): ?><?= e($category['name']) ?> <?php endif; ?>
    <?php if ($city): ?>ne <?= e($city['name']) ?><?php endif; ?>
    <small class="text-muted">(<?= count($providers) ?>)</small>
  </h2>

  <?php if (!$providers): ?>
    <div class="alert alert-light text-center">
      Asnje punetor i gjetur. Provoni te zgjeroni filterat.
    </div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($providers as $p): ?>
        <div class="col-md-6"><?php View::partial('provider-card', ['p' => $p]); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

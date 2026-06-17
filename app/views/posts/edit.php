<section class="container py-4">
  <div class="form-card mx-auto" style="max-width: 720px;">
    <h1 class="section-title">Modifiko postimin</h1>

    <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">

      <div class="mb-3">
        <label class="form-label">Titulli</label>
        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
               value="<?= e((string)($old['title'] ?? '')) ?>" required maxlength="160">
        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Përshkrimi</label>
        <textarea name="description" rows="5" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" required maxlength="5000"><?= e((string)($old['description'] ?? '')) ?></textarea>
        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?= e($errors['description']) ?></div><?php endif; ?>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">Kategoria</label>
          <select name="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" required>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($old['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= e($errors['category_id']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Qyteti</label>
          <select name="city_id" class="form-select <?= isset($errors['city_id']) ? 'is-invalid' : '' ?>" required>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($old['city_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['city_id'])): ?><div class="invalid-feedback"><?= e($errors['city_id']) ?></div><?php endif; ?>
        </div>
      </div>

      <?php if ($p['type'] === 'offer'): ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Çmim nga (€)</label>
            <input type="number" step="0.01" min="0" name="price_from" class="form-control <?= isset($errors['price_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['price_from'] ?? '')) ?>">
            <?php if (isset($errors['price_from'])): ?><div class="invalid-feedback"><?= e($errors['price_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€)</label>
            <input type="number" step="0.01" min="0" name="price_to" class="form-control <?= isset($errors['price_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['price_to'] ?? '')) ?>">
            <?php if (isset($errors['price_to'])): ?><div class="invalid-feedback"><?= e($errors['price_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Orari i punës</label>
          <input type="text" name="working_hours" class="form-control <?= isset($errors['working_hours']) ? 'is-invalid' : '' ?>"
                 value="<?= e((string)($old['working_hours'] ?? '')) ?>" maxlength="120">
          <?php if (isset($errors['working_hours'])): ?><div class="invalid-feedback"><?= e($errors['working_hours']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Preferencat e kontaktit</label>
          <input type="text" name="contact_preferences" class="form-control <?= isset($errors['contact_preferences']) ? 'is-invalid' : '' ?>"
                 value="<?= e((string)($old['contact_preferences'] ?? '')) ?>" maxlength="200">
          <?php if (isset($errors['contact_preferences'])): ?><div class="invalid-feedback"><?= e($errors['contact_preferences']) ?></div><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Buxheti nga (€)</label>
            <input type="number" step="0.01" min="0" name="budget_from" class="form-control <?= isset($errors['budget_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['budget_from'] ?? '')) ?>">
            <?php if (isset($errors['budget_from'])): ?><div class="invalid-feedback"><?= e($errors['budget_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€)</label>
            <input type="number" step="0.01" min="0" name="budget_to" class="form-control <?= isset($errors['budget_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['budget_to'] ?? '')) ?>">
            <?php if (isset($errors['budget_to'])): ?><div class="invalid-feedback"><?= e($errors['budget_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Afati</label>
            <input type="date" name="deadline" class="form-control <?= isset($errors['deadline']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['deadline'] ?? '')) ?>" min="<?= date('Y-m-d') ?>">
            <?php if (isset($errors['deadline'])): ?><div class="invalid-feedback"><?= e($errors['deadline']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Urgjenca</label>
            <select name="urgency" class="form-select <?= isset($errors['urgency']) ? 'is-invalid' : '' ?>">
              <option value="">— Pa urgjencë —</option>
              <?php foreach (['low' => 'I ulët', 'normal' => 'Normal', 'high' => 'I lartë'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($old['urgency'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['urgency'])): ?><div class="invalid-feedback"><?= e($errors['urgency']) ?></div><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($photos): ?>
        <div class="mb-3">
          <label class="form-label">Foto ekzistuese</label>
          <div class="row g-2">
            <?php foreach ($photos as $ph): ?>
              <div class="col-4 col-md-3">
                <div class="position-relative">
                  <img src="<?= e(CONFIG['upload_url'] . '/' . rawurlencode($ph['filename'])) ?>" class="img-fluid rounded" alt="">
                  <label class="form-check position-absolute" style="top:6px; left:6px; background:#fff; padding:2px 6px; border-radius:6px;">
                    <input type="checkbox" class="form-check-input" name="delete_photo[]" value="<?= (int)$ph['id'] ?>">
                    <span class="form-check-label small">Fshi</span>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Shto foto të reja <span class="text-muted small">(deri 5 gjithsej)</span></label>
        <input type="file" name="photos[]" class="form-control <?= isset($errors['photos']) ? 'is-invalid' : '' ?>"
               multiple accept="image/jpeg,image/png,image/webp">
        <?php if (isset($errors['photos'])): ?><div class="invalid-feedback"><?= e($errors['photos']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-helppy btn-lg" type="submit">Ruaj ndryshimet</button>
      <a href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>" class="btn btn-link">Anulo</a>
    </form>
  </div>
</section>

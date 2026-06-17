<section class="container py-4">
  <div class="form-card mx-auto" style="max-width: 720px;">
    <h1 class="section-title"><?= e($title) ?></h1>
    <p class="text-muted small mb-3">
      <?php if ($type === 'offer'): ?>
        Shfaq shërbimin tënd në feed-in publik. Klientët mund të të kontaktojnë drejtpërdrejt.
      <?php else: ?>
        Përshkruaj punën që ke nevojë. Punëtorët dhe kompanitë do ta shohin postimin tënd.
      <?php endif; ?>
    </p>

    <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
      <?php if (Auth::role() === 'admin'): ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Titulli</label>
        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['title'] ?? '') ?>" required maxlength="160">
        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Përshkrimi</label>
        <textarea name="description" rows="5" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                  required maxlength="5000"><?= e($old['description'] ?? '') ?></textarea>
        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?= e($errors['description']) ?></div><?php endif; ?>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">Kategoria</label>
          <select name="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" required>
            <option value="">— Zgjidh —</option>
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
            <option value="">— Zgjidh —</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($old['city_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['city_id'])): ?><div class="invalid-feedback"><?= e($errors['city_id']) ?></div><?php endif; ?>
        </div>
      </div>

      <?php if ($type === 'offer'): ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Çmim nga (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="price_from" class="form-control <?= isset($errors['price_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['price_from'] ?? '') ?>">
            <?php if (isset($errors['price_from'])): ?><div class="invalid-feedback"><?= e($errors['price_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="price_to" class="form-control <?= isset($errors['price_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['price_to'] ?? '') ?>">
            <?php if (isset($errors['price_to'])): ?><div class="invalid-feedback"><?= e($errors['price_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Orari i punës <span class="text-muted small">opsional</span></label>
          <input type="text" name="working_hours" class="form-control <?= isset($errors['working_hours']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['working_hours'] ?? '') ?>" maxlength="120"
                 placeholder="p.sh. Hënë–Shtunë 08:00–18:00">
          <?php if (isset($errors['working_hours'])): ?><div class="invalid-feedback"><?= e($errors['working_hours']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Preferencat e kontaktit <span class="text-muted small">opsional</span></label>
          <input type="text" name="contact_preferences" class="form-control <?= isset($errors['contact_preferences']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['contact_preferences'] ?? '') ?>" maxlength="200"
                 placeholder="p.sh. Vetëm WhatsApp pas orës 20:00">
          <?php if (isset($errors['contact_preferences'])): ?><div class="invalid-feedback"><?= e($errors['contact_preferences']) ?></div><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Buxheti nga (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="budget_from" class="form-control <?= isset($errors['budget_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['budget_from'] ?? '') ?>">
            <?php if (isset($errors['budget_from'])): ?><div class="invalid-feedback"><?= e($errors['budget_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="budget_to" class="form-control <?= isset($errors['budget_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['budget_to'] ?? '') ?>">
            <?php if (isset($errors['budget_to'])): ?><div class="invalid-feedback"><?= e($errors['budget_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Afati <span class="text-muted small">opsional</span></label>
            <input type="date" name="deadline" class="form-control <?= isset($errors['deadline']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['deadline'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
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

      <div class="mb-3">
        <label class="form-label">Foto <span class="text-muted small">deri 5, JPG/PNG/WEBP, ≤5MB secila</span></label>
        <input type="file" name="photos[]" class="form-control <?= isset($errors['photos']) ? 'is-invalid' : '' ?>"
               multiple accept="image/jpeg,image/png,image/webp">
        <?php if (isset($errors['photos'])): ?><div class="invalid-feedback"><?= e($errors['photos']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-helppy btn-lg" type="submit">
        <i class="bi bi-send"></i> Posto
      </button>
      <a href="<?= e(CONFIG['base_url']) ?>/posts" class="btn btn-link">Anulo</a>
    </form>
  </div>
</section>

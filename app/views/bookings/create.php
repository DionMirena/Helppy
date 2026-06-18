<section class="container py-4">
  <div class="form-card mx-auto" style="max-width: 640px;">
    <h1 class="section-title">Rezervo një termin</h1>
    <p class="text-muted mb-3">Po rezervon me <strong><?= e($provider['name']) ?></strong>
      <?php if (!empty($provider['profession'])): ?>— <?= e($provider['profession']) ?><?php endif; ?>.
      <?php if ($provider['hourly_rate'] !== null): ?>
        <br>Tarifa standarde: <strong>€<?= e(rtrim(rtrim(number_format((float)$provider['hourly_rate'], 2, '.', ''), '0'), '.')) ?></strong> / orë.
      <?php endif; ?>
    </p>

    <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$provider['id'] ?>/book">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">Data</label>
          <input type="date" name="date" class="form-control <?= isset($errors['date']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['date'] ?? '') ?>" min="<?= date('Y-m-d') ?>" required>
          <?php if (isset($errors['date'])): ?><div class="invalid-feedback"><?= e($errors['date']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Ora</label>
          <input type="time" name="time" class="form-control <?= isset($errors['time']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['time'] ?? '') ?>" required>
          <?php if (isset($errors['time'])): ?><div class="invalid-feedback"><?= e($errors['time']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Kohëzgjatja (orë) <span class="text-muted small">opsionale</span></label>
        <input type="number" step="0.5" min="0.5" max="24" name="duration_hours"
               class="form-control <?= isset($errors['duration_hours']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['duration_hours'] ?? '') ?>" placeholder="p.sh. 2">
        <?php if (isset($errors['duration_hours'])): ?><div class="invalid-feedback"><?= e($errors['duration_hours']) ?></div><?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Shënime për punëtorin <span class="text-muted small">opsionale</span></label>
        <textarea name="notes" rows="4" maxlength="2000"
                  class="form-control <?= isset($errors['notes']) ? 'is-invalid' : '' ?>"
                  placeholder="Përshkruaj punën që ke nevojë..."><?= e($old['notes'] ?? '') ?></textarea>
        <?php if (isset($errors['notes'])): ?><div class="invalid-feedback"><?= e($errors['notes']) ?></div><?php endif; ?>
      </div>

      <div class="action-bar">
        <button class="btn btn-helppy btn-lg" type="submit">
          <i class="bi bi-calendar-check"></i> Dërgo kërkesën
        </button>
        <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$provider['id'] ?>" class="btn-ghost">
          <i class="bi bi-x-lg"></i> Anulo
        </a>
      </div>
    </form>
  </div>
</section>

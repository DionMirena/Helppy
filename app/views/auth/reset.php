<div class="container py-4" style="max-width: 480px;">
  <h2 class="mb-2">Vendos passwordin e ri</h2>
  <p class="text-muted">
    Një kod 6-shifror u dërgua në <strong><?= e($masked_email) ?></strong> (vlen 30 minuta).
  </p>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/password/reset" class="form-card">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <input type="hidden" name="email" value="<?= e($email) ?>">

    <div class="mb-3">
      <label class="form-label">Kodi</label>
      <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
             class="form-control form-control-lg text-center"
             style="letter-spacing: 8px; font-size: 24px;"
             autocomplete="one-time-code" required autofocus>
    </div>

    <div class="mb-3">
      <label class="form-label">Password i ri</label>
      <input type="password" name="password" class="form-control form-control-lg"
             minlength="8" required>
      <small class="text-muted">Të paktën 8 karaktere.</small>
    </div>

    <div class="mb-3">
      <label class="form-label">Konfirmo passwordin e ri</label>
      <input type="password" name="password_confirm" class="form-control form-control-lg"
             minlength="8" required>
    </div>

    <button class="btn btn-helppy w-100" type="submit">
      <i class="bi bi-check2"></i> Vendos passwordin
    </button>
  </form>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/password/forgot" class="mt-3">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <button class="btn-ghost w-100" type="submit">
      <i class="bi bi-arrow-clockwise"></i> Dërgo kodin përsëri
    </button>
  </form>
</div>

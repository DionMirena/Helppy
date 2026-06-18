<div class="container py-4" style="max-width: 480px;">
  <h2 class="mb-2">Ndrysho passwordin</h2>
  <p class="text-muted">Vendos passwordin tënd aktual dhe një të ri.</p>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/password/change" class="form-card">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">

    <div class="mb-3">
      <label class="form-label">Password aktual</label>
      <input type="password" name="current_password" class="form-control form-control-lg"
             required autofocus autocomplete="current-password">
    </div>

    <div class="mb-3">
      <label class="form-label">Password i ri</label>
      <input type="password" name="password" class="form-control form-control-lg"
             minlength="8" required autocomplete="new-password">
      <small class="text-muted">Të paktën 8 karaktere.</small>
    </div>

    <div class="mb-3">
      <label class="form-label">Konfirmo passwordin e ri</label>
      <input type="password" name="password_confirm" class="form-control form-control-lg"
             minlength="8" required autocomplete="new-password">
    </div>

    <button class="btn btn-helppy w-100" type="submit">
      <i class="bi bi-check2"></i> Ruaj passwordin e ri
    </button>
  </form>

  <div class="action-bar mt-3">
    <a href="<?= e(CONFIG['base_url']) ?>/" class="btn-ghost">
      <i class="bi bi-arrow-left"></i> Mbrapa
    </a>
  </div>
</div>

<div class="container py-4" style="max-width: 480px;">
  <h2 class="mb-2">Keni harruar passwordin?</h2>
  <p class="text-muted">Shkruaj email-in e llogarisë tënde. Ne do të të dërgojmë një kod 6-shifror për të vendosur një password të ri.</p>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/password/forgot" class="form-card">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control form-control-lg" required autofocus
             placeholder="emaili@gmail.com">
    </div>
    <button class="btn btn-helppy w-100" type="submit">
      <i class="bi bi-envelope"></i> Dërgo kodin
    </button>
  </form>

  <div class="action-bar mt-3">
    <a href="<?= e(CONFIG['base_url']) ?>/login" class="btn-ghost">
      <i class="bi bi-arrow-left"></i> Mbrapa te hyrja
    </a>
  </div>
</div>

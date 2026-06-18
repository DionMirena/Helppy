<div class="container py-4" style="max-width: 480px;">
  <h2 class="mb-3">Hyrje</h2>
  <form method="post" action="<?= e(CONFIG['base_url']) ?>/login">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="email" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Fjalekalimi</label>
      <input class="form-control" type="password" name="password" required>
    </div>
    <button class="btn btn-helppy w-100" type="submit">Hyr</button>
  </form>

  <p class="text-center mt-3 mb-1">
    <a href="<?= e(CONFIG['base_url']) ?>/password/forgot">
      Keni harruar passwordin apo dëshironi të ndryshoni passwordin?
    </a>
  </p>
  <p class="text-center mt-2">
    Nuk keni llogari? <a href="<?= e(CONFIG['base_url']) ?>/register">Regjistrohu</a>
  </p>
</div>

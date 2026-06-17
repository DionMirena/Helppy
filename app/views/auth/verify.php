<div class="container py-4" style="max-width: 480px;">
  <h2 class="mb-2">Verifiko emailin</h2>
  <p class="text-muted">Nje kod 6-shifror u dergua ne <strong><?= e($masked_email) ?></strong></p>

  <?php if (!empty(CONFIG['debug']) && !empty($_SESSION['_dev_code']['code'])):
    $devCode  = (string)$_SESSION['_dev_code']['code'];
    $devEmail = (string)($_SESSION['_dev_code']['email'] ?? '');
  ?>
    <div class="alert alert-warning" role="alert">
      <strong><i class="bi bi-cone-striped"></i> Dev mode</strong> — Mailer-i nuk është konfiguruar, kodi shfaqet këtu për testim:
      <div class="mt-2" style="font-size: 28px; letter-spacing: 6px; font-weight: 700; text-align: center; background:#fff; border-radius:8px; padding:8px;">
        <?= e($devCode) ?>
      </div>
      <div class="small text-muted mt-2">
        Për <?= e($devEmail) ?> · gjithashtu u shkrua në <code>storage/mail.log</code>.<br>
        Për ta hequr këtë: vendos <code>mailer.username</code>+<code>password</code> të vlefshme në <code>config/config.php</code>,
        ose vendos <code>'debug' =&gt; false</code>.
      </div>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/verify-email" class="bg-white p-3 rounded mb-3">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <div class="mb-3">
      <label class="form-label">Kodi i verifikimit</label>
      <input class="form-control form-control-lg text-center"
             name="code"
             inputmode="numeric"
             pattern="[0-9]{6}"
             maxlength="6"
             autocomplete="one-time-code"
             autofocus
             required
             style="letter-spacing: 8px; font-size: 24px;">
    </div>
    <button class="btn btn-helppy w-100" type="submit">Verifiko</button>
  </form>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/verify-email/resend" class="mb-3">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <?php if ($resend_in > 0): ?>
      <button class="btn btn-outline-secondary w-100" type="submit" disabled>
        Prisni <?= (int)$resend_in ?>s perpara se te dergoni perseri
      </button>
    <?php else: ?>
      <button class="btn btn-outline-secondary w-100" type="submit">Dergo perseri kodin</button>
    <?php endif; ?>
  </form>

  <?php if ($mode === 'login'): ?>
    <div class="text-center small">
      Nuk je ti?
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/verify-email/cancel" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button type="submit" class="btn btn-link p-0 small">Anulo</button>
      </form>
    </div>
  <?php endif; ?>
</div>

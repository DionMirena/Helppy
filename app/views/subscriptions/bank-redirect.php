<section class="container py-5 text-center">
  <div class="profile-card mx-auto" style="max-width: 480px;">
    <h2 class="mb-3">Po të dërgojmë te banka…</h2>
    <p class="text-muted mb-4">
      Mos e mbyll faqen. Do të ridrejtohesh te faqja e sigurt e bankës për të futur të dhënat e kartës.
    </p>
    <div class="spinner-border" role="status" aria-hidden="true"></div>
    <noscript>
      <p class="mt-3">Klikoni butonin më poshtë për të vazhduar te banka.</p>
    </noscript>
  </div>

  <form id="bank-redirect-form" method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
    <?php foreach ($fields as $name => $value): ?>
      <input type="hidden" name="<?= e($name) ?>" value="<?= e((string)$value) ?>">
    <?php endforeach; ?>
    <noscript>
      <button type="submit" class="btn btn-helppy mt-3">Vazhdo te banka</button>
    </noscript>
  </form>
</section>

<script>
  document.getElementById('bank-redirect-form').submit();
</script>

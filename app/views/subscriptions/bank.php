<section class="container py-4">
  <div class="form-card mx-auto" style="max-width: 640px;">
    <h1 class="section-title">Transfer bankar</h1>
    <p class="text-muted">Bëj transferin në llogarinë e mëposhtme. Pasi ne ta konfirmojmë, abonimi yt aktivizohet brenda 24 orëve.</p>

    <div class="profile-card mb-3">
      <dl class="row mb-0">
        <dt class="col-sm-4">Tier</dt>
        <dd class="col-sm-8"><?= e(ucfirst((string)$sub['tier'])) ?></dd>

        <dt class="col-sm-4">Shuma</dt>
        <dd class="col-sm-8"><strong>€<?= e(rtrim(rtrim(number_format((float)$sub['amount_eur'], 2, '.', ''), '0'), '.')) ?></strong></dd>

        <dt class="col-sm-4">Përfituesi</dt>
        <dd class="col-sm-8"><?= e($bank['beneficiary']) ?></dd>

        <dt class="col-sm-4">Banka</dt>
        <dd class="col-sm-8"><?= e($bank['bank_name']) ?></dd>

        <dt class="col-sm-4">IBAN</dt>
        <dd class="col-sm-8"><code><?= e($bank['iban']) ?></code></dd>

        <dt class="col-sm-4">Kodi i referencës</dt>
        <dd class="col-sm-8">
          <span class="bank-ref"><?= e((string)$sub['bank_reference']) ?></span>
          <div class="small text-muted mt-1"><?= e($bank['note']) ?></div>
        </dd>
      </dl>
    </div>

    <p class="small text-muted">
      Pasi e bën transferin, mos harro të përfshish <strong>kodin e referencës</strong> në arsyen e pagesës.
      Administratori i konfirmon transferet brenda 24 orëve dhe abonimi yt aktivizohet automatikisht.
    </p>

    <div class="action-bar">
      <a href="<?= e(CONFIG['base_url']) ?>/subscribe" class="btn-ghost">
        <i class="bi bi-arrow-left"></i> Kthehu te abonimet
      </a>
    </div>
  </div>
</section>

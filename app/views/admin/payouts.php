<section class="container py-4">
  <h1 class="section-title">Llogaria e admin</h1>
  <p class="text-muted">
    Çdo pagesë abonimi nga çdo punëtor/kompani përfundon këtu. Mbush vetëm
    <strong>një</strong> Stripe + <strong>një</strong> llogari bankare admin —
    të gjitha 5 bankat në grid do të dërgojnë në të njëjtin IBAN.
  </p>

  <div class="row g-3">
    <!-- Admin IBAN card -->
    <div class="col-md-6">
      <div class="profile-card h-100">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <p class="small text-muted mb-1">DESTINACIONI I TRANSFEREVE</p>
            <h4 class="mb-0">Llogaria bankare admin</h4>
          </div>
          <?php if ($adminConfigured): ?>
            <span class="status-badge status-accepted">Konfiguruar</span>
          <?php else: ?>
            <span class="status-badge status-pending">Pa konfigurim</span>
          <?php endif; ?>
        </div>

        <hr>

        <dl class="row mb-0">
          <dt class="col-sm-4">Përfituesi</dt>
          <dd class="col-sm-8"><?= e($admin['beneficiary'] ?: '—') ?></dd>

          <dt class="col-sm-4">IBAN</dt>
          <dd class="col-sm-8">
            <?php if ($adminConfigured): ?>
              <code><?= e($admin['iban']) ?></code>
            <?php else: ?>
              <span class="text-danger">Vendos në <code>config/config.php → payments.admin.iban</code></span>
            <?php endif; ?>
          </dd>

          <?php if (!empty($admin['bank_name'])): ?>
            <dt class="col-sm-4">Banka</dt>
            <dd class="col-sm-8"><?= e($admin['bank_name']) ?></dd>
          <?php endif; ?>

          <?php if (!empty($admin['swift'])): ?>
            <dt class="col-sm-4">SWIFT / BIC</dt>
            <dd class="col-sm-8"><code><?= e($admin['swift']) ?></code></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <!-- Stripe card -->
    <div class="col-md-6">
      <div class="profile-card h-100">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <p class="small text-muted mb-1">DESTINACIONI I KARTAVE</p>
            <h4 class="mb-0">Stripe (admin)</h4>
          </div>
          <?php if ($stripeEnabled): ?>
            <span class="status-badge status-accepted">Aktiv</span>
          <?php else: ?>
            <span class="status-badge status-pending">Pa konfigurim</span>
          <?php endif; ?>
        </div>

        <hr>

        <?php if ($stripeEnabled): ?>
          <p class="mb-0">
            Stripe pranon karta nga çdo bankë (Raiffeisen, NLB, TEB, BKT, ProCredit…).
            Pagesat zbresin në balancën tënde të Stripe dhe paguhen automatikisht
            në llogarinë bankare që ke lidhur me Stripe-in.
          </p>
        <?php else: ?>
          <p class="small text-muted mb-2">
            Konfigurimi në 3 hapa:
          </p>
          <ol class="small mb-0">
            <li>Sign up në <a href="https://dashboard.stripe.com" target="_blank" rel="noopener">dashboard.stripe.com</a> (falas).</li>
            <li>Kopjo <code>sk_test_…</code> + <code>pk_test_…</code> nga <a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">/test/apikeys</a>.</li>
            <li>Ngjit në <code>config/config.php → payments.stripe</code> dhe vendos <code>enabled =&gt; true</code>.</li>
          </ol>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <h2 class="section-title mt-4">Bankat e shfaqura në /subscribe</h2>
  <p class="text-muted">
    Të gjitha këto banka shfaqen në grid-in publik dhe dërgojnë në të njëjtin IBAN
    admin më sipër. Disabilo një bankë duke vendosur <code>enabled =&gt; false</code>
    në <code>config/config.php</code>.
  </p>

  <div class="row g-2">
    <?php foreach ($banks as $b): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <div class="profile-card h-100" style="border-left: 4px solid <?= e($b['color']) ?>;">
          <div class="d-flex align-items-center gap-2">
            <span style="width:14px; height:14px; border-radius:50%; background:<?= e($b['color']) ?>;"></span>
            <strong><?= e($b['short']) ?></strong>
          </div>
          <p class="small text-muted mb-2"><?= e($b['name']) ?></p>
          <?php if (!empty($b['swift'])): ?>
            <p class="small mb-0"><code><?= e($b['swift']) ?></code></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

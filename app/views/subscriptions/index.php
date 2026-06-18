<section class="container py-4">
  <h1 class="section-title">Abonohu</h1>

  <?php if ($current): ?>
    <div class="alert alert-success">
      <strong><i class="bi bi-check2-circle"></i> Aktiv:</strong>
      Tier <strong><?= e(ucfirst((string)$current['tier'])) ?></strong>,
      skadon më <?= e(date('d M Y, H:i', strtotime((string)$current['expires_at']))) ?>
      (<?= max(0, (int)floor((strtotime((string)$current['expires_at']) - time()) / 86400)) ?> ditë).
    </div>
  <?php elseif ($latest && $latest['status'] === 'pending'): ?>
    <div class="alert alert-warning">
      <strong><i class="bi bi-hourglass-split"></i> Në pritje:</strong>
      <?php if ($latest['payment_method'] === 'bank'): ?>
        Po presim konfirmimin e transferit bankar. Kodi i referencës: <code><?= e((string)$latest['bank_reference']) ?></code>.
        <a href="<?= e(CONFIG['base_url']) ?>/subscribe/bank/<?= (int)$latest['id'] ?>">Hap udhëzimet</a>.
      <?php else: ?>
        Pagesa me kartë nuk u kompletua. Mund ta nisësh përsëri më poshtë.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <p class="text-muted">
    Login është gjithmonë falas. Klientët shfletojnë dhe rezervojnë pa pagesë.
    <strong>Punëtorët dhe kompanitë paguajnë vetëm për të postuar oferta.</strong>
  </p>

  <div class="row g-3 mt-2">
    <?php
    $tiers = [
        ['key' => 'standard', 'name' => 'Standard', 'price' => $price_standard, 'perks' => [
            'Krijo oferta të pakufizuara',
            'Renditja e zakonshme në kërkim',
            'Vlerësime nga klientë',
            '30 ditë qasje',
        ]],
        ['key' => 'premium',  'name' => 'Premium',  'price' => $price_premium, 'perks' => [
            'Çdo gjë në Standard',
            'Rendit në krye të rezultateve',
            'Shenjë “PREMIUM” e dukshme',
            '30 ditë qasje',
        ]],
    ];
    foreach ($tiers as $t):
      $isCurrent = $current && $current['tier'] === $t['key'];
    ?>
      <div class="col-md-6">
        <div class="profile-card tier-card <?= $t['key'] === 'premium' ? 'tier-premium' : '' ?>">
          <div class="d-flex justify-content-between align-items-start">
            <h3 class="mb-0"><?= e($t['name']) ?></h3>
            <?php if ($t['key'] === 'premium'): ?><span class="premium-badge">PREMIUM</span><?php endif; ?>
          </div>
          <div class="tier-price mt-2">
            €<?= e(rtrim(rtrim(number_format($t['price'], 2, '.', ''), '0'), '.')) ?>
            <span class="text-muted small">/ <?= (int)$period_days ?> ditë</span>
          </div>
          <ul class="tier-perks mt-3">
            <?php foreach ($t['perks'] as $perk): ?>
              <li><i class="bi bi-check2"></i> <?= e($perk) ?></li>
            <?php endforeach; ?>
          </ul>

          <div class="d-grid gap-2 mt-3">
            <?php if ($isCurrent): ?>
              <button class="btn btn-success" disabled><i class="bi bi-check2"></i> Aktiv tani</button>
            <?php else: ?>
              <?php if ($stripe_enabled): ?>
                <form method="post" action="<?= e(CONFIG['base_url']) ?>/subscribe/checkout">
                  <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                  <input type="hidden" name="tier" value="<?= e($t['key']) ?>">
                  <button class="btn btn-helppy w-100" type="submit">
                    <i class="bi bi-credit-card"></i> Paguaj me kartë
                  </button>
                </form>
              <?php else: ?>
                <button class="btn btn-helppy w-100" disabled title="Stripe nuk është konfiguruar">
                  <i class="bi bi-credit-card"></i> Kartë (e çaktivizuar)
                </button>
              <?php endif; ?>
              <form method="post" action="<?= e(CONFIG['base_url']) ?>/subscribe/bank">
                <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                <input type="hidden" name="tier" value="<?= e($t['key']) ?>">
                <button class="btn btn-helppy-outline w-100" type="submit">
                  <i class="bi bi-bank"></i> Transfer bankar
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($current): ?>
    <form method="post" action="<?= e(CONFIG['base_url']) ?>/subscribe/cancel-current" class="mt-3"
          onsubmit="return confirm('Anulo abonimin aktiv? Do të mund të rikthehesh kur të duash.');">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
      <button class="btn btn-link text-danger" type="submit">Anulo abonimin aktiv</button>
    </form>
  <?php endif; ?>
</section>

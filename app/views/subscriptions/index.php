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

  <div class="row g-3 mt-2" id="plan-grid">
    <?php
    $currentPlanKey = $current['plan'] ?? '';
    foreach ($plans as $planKey => $plan):
      $isCurrent = $current && $currentPlanKey === $planKey;
      $isPremium = $plan['tier'] === 'premium';
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="profile-card tier-card<?= $isPremium ? ' tier-premium' : '' ?>"
             data-plan-key="<?= e($planKey) ?>"
             role="button"
             tabindex="0">
          <div class="d-flex justify-content-between align-items-start">
            <h3 class="mb-0"><?= e($plan['name']) ?></h3>
            <?php if ($isPremium): ?><span class="premium-badge">PREMIUM</span><?php endif; ?>
          </div>
          <div class="tier-price mt-2">
            €<?= e(rtrim(rtrim(number_format((float)$plan['price'], 2, '.', ''), '0'), '.')) ?>
            <span class="text-muted small">/ <?= (int)$plan['period_days'] ?> ditë</span>
          </div>
          <ul class="tier-perks mt-3">
            <?php foreach ($plan['perks'] as $perk): ?>
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
                  <input type="hidden" name="plan" value="<?= e($planKey) ?>">
                  <button class="btn btn-helppy w-100" type="submit">
                    <i class="bi bi-credit-card"></i> Paguaj me kartë (Stripe)
                  </button>
                </form>
              <?php endif; ?>

              <?php if (!empty($enabled_banks)): ?>
                <button class="btn btn-helppy<?= $stripe_enabled ? '-outline' : '' ?> w-100"
                        type="button"
                        onclick="event.stopPropagation(); document.getElementById('bank-picker-<?= e($planKey) ?>').classList.toggle('open');">
                  <i class="bi bi-bank"></i> Paguaj me kartë (Bankë)
                </button>
                <div class="bank-picker mt-2" id="bank-picker-<?= e($planKey) ?>" onclick="event.stopPropagation();">
                  <p class="small text-muted mb-2">Zgjidh bankën ku ke kartën:</p>
                  <div class="bank-grid">
                    <?php foreach ($enabled_banks as $b): ?>
                      <form method="post" action="<?= e(CONFIG['base_url']) ?>/subscribe/card-bank">
                        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                        <input type="hidden" name="plan" value="<?= e($planKey) ?>">
                        <input type="hidden" name="bank" value="<?= e($b['key']) ?>">
                        <button class="bank-chip" type="submit" style="--bank-color: <?= e($b['color']) ?>;">
                          <span class="bank-chip-dot"></span>
                          <?= e($b['short']) ?>
                        </button>
                      </form>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php elseif (!$stripe_enabled): ?>
                <button class="btn btn-secondary w-100" type="button" disabled>
                  <i class="bi bi-credit-card"></i> Pagesa nuk është konfiguruar ende
                </button>
                <!-- <p class="small text-muted mb-0 mt-1">
                  Shto çelësat e ndonjë banke ose të Stripe te <code>config/config.php</code>.
                </p> -->
              <?php endif; ?>
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
      <button class="btn-ghost danger" type="submit">
        <i class="bi bi-slash-circle"></i> Anulo abonimin aktiv
      </button>
    </form>
  <?php endif; ?>
</section>

<script>
(function () {
  var grid = document.getElementById('plan-grid');
  if (!grid) return;
  var cards = grid.querySelectorAll('.tier-card[data-plan-key]');
  function select(card) {
    cards.forEach(function (c) { c.classList.remove('selected'); });
    card.classList.add('selected');
  }
  cards.forEach(function (card) {
    card.addEventListener('click', function (e) {
      // Don't steal clicks meant for the buttons / forms / bank picker inside.
      var tag = (e.target && e.target.tagName) || '';
      if (e.target.closest('button, a, form, .bank-picker, input')) return;
      select(card);
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        select(card);
      }
    });
  });
})();
</script>

<?php
$statusBadge = function (string $s): string {
    return match ($s) {
        'pending'   => '<span class="status-badge status-pending">Në pritje</span>',
        'active'    => '<span class="status-badge status-accepted">Aktiv</span>',
        'expired'   => '<span class="status-badge status-cancelled">Skaduar</span>',
        'cancelled' => '<span class="status-badge status-cancelled">Anuluar</span>',
        default     => e($s),
    };
};
?>
<section class="container py-4">
  <h1 class="section-title">Abonimet</h1>

  <h4 class="mt-3 mb-2">Në pritje (bankë)</h4>
  <?php if (!$pending): ?>
    <p class="text-muted">Asnjë pagesë në pritje.</p>
  <?php else: ?>
    <div class="table-responsive bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow);">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th><th>Punëtor</th><th>Email</th><th>Tier</th><th>Shuma</th><th>Ref</th><th>Banka</th><th>Krijuar</th><th>Veprime</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $p): $b = Payments::findBank((string)($p['bank_chosen'] ?? '')); ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= e($p['provider_name']) ?></td>
              <td><small><?= e($p['provider_email']) ?></small></td>
              <td><?= e(ucfirst((string)$p['tier'])) ?></td>
              <td>€<?= e(rtrim(rtrim(number_format((float)$p['amount_eur'], 2, '.', ''), '0'), '.')) ?></td>
              <td><code><?= e((string)$p['bank_reference']) ?></code></td>
              <td><?= $b ? '<span class="bank-tag" style="background:' . e((string)$b['color']) . '20; color:#111;">' . e((string)$b['short']) . '</span>' : '<small class="text-muted">—</small>' ?></td>
              <td><small><?= e(date('d M Y, H:i', strtotime((string)$p['created_at']))) ?></small></td>
              <td>
                <div class="inline-actions">
                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/subscriptions/<?= (int)$p['id'] ?>/activate" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-helppy" type="submit"><i class="bi bi-check2"></i> Aktivizo</button>
                  </form>
                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/subscriptions/<?= (int)$p['id'] ?>/cancel" class="d-inline"
                        onsubmit="return confirm('Anulo këtë pagesë në pritje?');">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x"></i> Anulo</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h4 class="mt-4 mb-2">Të gjitha (200 të fundit)</h4>
  <div class="table-responsive bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow);">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>#</th><th>Punëtor</th><th>Tier</th><th>Shuma</th><th>Statusi</th><th>Metoda</th><th>Skadon</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($all as $s): ?>
          <tr>
            <td><?= (int)$s['id'] ?></td>
            <td><?= e($s['provider_name']) ?></td>
            <td><?= e(ucfirst((string)$s['tier'])) ?></td>
            <td>€<?= e(rtrim(rtrim(number_format((float)$s['amount_eur'], 2, '.', ''), '0'), '.')) ?></td>
            <td><?= $statusBadge((string)$s['status']) ?></td>
            <td><small><?= e($s['payment_method']) ?></small></td>
            <td><small><?= $s['expires_at'] ? e(date('d M Y', strtotime((string)$s['expires_at']))) : '—' ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

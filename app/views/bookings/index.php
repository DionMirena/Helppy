<?php
$statusBadge = function(string $s): string {
    return match ($s) {
        'pending'   => '<span class="status-badge status-pending">Në pritje</span>',
        'accepted'  => '<span class="status-badge status-accepted">Pranuar</span>',
        'rejected'  => '<span class="status-badge status-rejected">Refuzuar</span>',
        'completed' => '<span class="status-badge status-completed">Përfunduar</span>',
        'cancelled' => '<span class="status-badge status-cancelled">Anuluar</span>',
        default     => e($s),
    };
};
?>
<section class="container py-4">
  <h1 class="section-title">Rezervimet</h1>
  <p class="text-muted">
    <?php if ($perspective === 'client'): ?>
      Rezervimet që ke kërkuar te punëtorët.
    <?php elseif ($perspective === 'provider'): ?>
      Rezervimet që klientët kanë kërkuar te ti.
    <?php else: ?>
      Të gjitha rezervimet ku je palë.
    <?php endif; ?>
  </p>

  <?php if (!$bookings): ?>
    <div class="empty-state">
      <i class="bi bi-calendar-x"></i>
      <p>Asnjë rezervim ende.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow);">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th><?= $perspective === 'provider' ? 'Klienti' : 'Punëtori' ?></th>
            <th>Kur</th>
            <th>Kohëzgjatja</th>
            <th>Statusi</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td><?= (int)$b['id'] ?></td>
              <td>
                <?php if ($perspective === 'provider'): ?>
                  <?= e($b['client_name']) ?>
                  <?php if (!empty($b['client_phone'])): ?>
                    <br><small class="text-muted"><i class="bi bi-telephone"></i> <?= e($b['client_phone']) ?></small>
                  <?php endif; ?>
                <?php else: ?>
                  <?= e($b['provider_name']) ?>
                  <?php if (!empty($b['profession'])): ?>
                    <br><small class="text-muted"><?= e($b['profession']) ?></small>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td><?= e(date('d M Y, H:i', strtotime((string)$b['scheduled_at']))) ?></td>
              <td><?= $b['duration_hours'] !== null ? e((string)(float)$b['duration_hours']) . ' orë' : '—' ?></td>
              <td><?= $statusBadge((string)$b['status']) ?></td>
              <td><a class="btn btn-sm btn-helppy-outline" href="<?= e(CONFIG['base_url']) ?>/bookings/<?= (int)$b['id'] ?>">Hap</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

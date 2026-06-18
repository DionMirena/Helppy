<?php
$statusLabel = match ($b['status']) {
    'pending'   => 'Në pritje',
    'accepted'  => 'Pranuar',
    'rejected'  => 'Refuzuar',
    'completed' => 'Përfunduar',
    'cancelled' => 'Anuluar',
    default     => $b['status'],
};
$statusClass = 'status-' . $b['status'];
?>
<section class="container py-4">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="profile-card">
        <div class="d-flex justify-content-between flex-wrap align-items-center mb-2">
          <h1 class="mb-0">Rezervim #<?= (int)$b['id'] ?></h1>
          <span class="status-badge <?= $statusClass ?>"><?= e($statusLabel) ?></span>
        </div>
        <p class="text-muted small mb-3">
          Krijuar më <?= e(date('d M Y, H:i', strtotime((string)$b['created_at']))) ?>
        </p>

        <dl class="row mb-0">
          <dt class="col-sm-4">Data e takimit</dt>
          <dd class="col-sm-8"><?= e(date('d M Y, H:i', strtotime((string)$b['scheduled_at']))) ?></dd>

          <?php if ($b['duration_hours'] !== null): ?>
            <dt class="col-sm-4">Kohëzgjatja</dt>
            <dd class="col-sm-8"><?= e((string)(float)$b['duration_hours']) ?> orë</dd>
          <?php endif; ?>

          <dt class="col-sm-4">Klienti</dt>
          <dd class="col-sm-8">
            <?= e($b['client_name']) ?>
            <?php if (($isProvider || $isAdmin) && !empty($b['client_phone'])): ?>
              <br><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/','',$b['client_phone'])) ?>"><?= e($b['client_phone']) ?></a>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4">Punëtori</dt>
          <dd class="col-sm-8">
            <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$b['provider_id'] ?>"><?= e($b['provider_name']) ?></a>
            <?php if (!empty($b['profession'])): ?> — <?= e($b['profession']) ?><?php endif; ?>
            <?php if (($isClient || $isAdmin) && !empty($b['provider_phone'])): ?>
              <br><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/','',$b['provider_phone'])) ?>"><?= e($b['provider_phone']) ?></a>
            <?php endif; ?>
          </dd>

          <?php if (!empty($b['notes'])): ?>
            <dt class="col-sm-4">Shënime</dt>
            <dd class="col-sm-8 long-text"><?= nl2br(e($b['notes'])) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="profile-card mb-3">
        <p class="small text-muted mb-2">Veprime</p>
        <?php $otherUserId = $isClient ? (int)$b['provider_id'] : (int)$b['client_id']; ?>
        <a class="btn btn-helppy w-100 mb-2" href="<?= e(CONFIG['base_url']) ?>/chat/with/<?= $otherUserId ?>">
          <i class="bi bi-chat-dots"></i> Bisedo
        </a>

        <?php if ($isProvider && $b['status'] === 'pending'): ?>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/bookings/<?= (int)$b['id'] ?>/accept" class="d-grid mb-2">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-helppy" type="submit"><i class="bi bi-check2"></i> Prano</button>
          </form>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/bookings/<?= (int)$b['id'] ?>/reject" class="d-grid mb-2">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-x"></i> Refuzo</button>
          </form>
        <?php endif; ?>

        <?php if ($isProvider && $b['status'] === 'accepted'): ?>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/bookings/<?= (int)$b['id'] ?>/complete" class="d-grid mb-2">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-helppy" type="submit"><i class="bi bi-check2-circle"></i> Shëno si i përfunduar</button>
          </form>
        <?php endif; ?>

        <?php if ($isClient && in_array($b['status'], ['pending','accepted'], true)): ?>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/bookings/<?= (int)$b['id'] ?>/cancel" class="d-grid"
                onsubmit="return confirm('Anulo rezervimin?');">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-slash-circle"></i> Anulo</button>
          </form>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/bookings/<?= (int)$b['id'] ?>/delete" class="d-grid mt-2"
                onsubmit="return confirm('FSHI rezervimin përgjithmonë?');">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Fshi (admin)</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

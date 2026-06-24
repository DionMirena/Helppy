<?php
$roleBadge = function (string $r): string {
    return match ($r) {
        'admin'    => '<span class="status-badge status-rejected">Admin</span>',
        'provider' => '<span class="status-badge status-accepted">Punëtor</span>',
        'client'   => '<span class="status-badge status-pending">Klient</span>',
        default    => e($r),
    };
};
$activeBadge = function (int $a, int $v): string {
    if ($v !== 1) return '<span class="status-badge status-cancelled">Pa verifikim</span>';
    return $a === 1
        ? '<span class="status-badge status-accepted">Aktiv</span>'
        : '<span class="status-badge status-rejected">Çaktiv</span>';
};
$me = (int)Auth::user()['id'];
?>
<section class="container py-4">
  <h1 class="section-title">Përdoruesit (<?= count($users) ?>)</h1>
  <p class="text-muted">Admini ka pushtet të plotë: ndrysho rolin, çaktivizo, fshi.
    Fshirja është e pakthyeshme dhe heq automatikisht postimet, fotot, rezervimet dhe bisedat e atij përdoruesi.</p>

  <div class="table-responsive bg-white" style="border-radius: var(--helppy-radius); box-shadow: var(--helppy-shadow);">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Emri</th>
          <th>Email</th>
          <th>Roli</th>
          <th>Qyteti / Profesioni</th>
          <th>Statusi</th>
          <th>Krijuar</th>
          <th>Veprime</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): $uid = (int)$u['id']; $isMe = $uid === $me; ?>
          <tr <?= $isMe ? 'style="background:#fafff5;"' : '' ?>>
            <td><?= $uid ?><?php if ($isMe): ?> <small class="text-muted">(ti)</small><?php endif; ?></td>
            <td>
              <strong><?= e($u['name']) ?></strong>
              <?php if ($u['role'] === 'provider' && !empty($u['is_company'])): ?>
                <br><small class="text-muted"><i class="bi bi-building"></i> <?= e($u['company_name'] ?? '') ?></small>
              <?php endif; ?>
            </td>
            <td><small class="text-soft-wrap"><?= e($u['email']) ?></small></td>
            <td><?= $roleBadge((string)$u['role']) ?></td>
            <td>
              <?php if (!empty($u['city_name'])): ?><small><?= e($u['city_name']) ?></small><br><?php endif; ?>
              <?php if (!empty($u['profession'])): ?><small class="text-muted"><?= e($u['profession']) ?></small><?php endif; ?>
            </td>
            <td><?= $activeBadge((int)$u['is_active'], (int)$u['email_verified']) ?></td>
            <td><small><?= e(date('d M Y', strtotime((string)$u['created_at']))) ?></small></td>
            <td>
              <?php if (!$isMe): ?>
                <div class="user-actions">
                  <?php $activeLabel = (int)$u['is_active'] === 1 ? 'Çaktivizo' : 'Aktivizo';
                        $activeIcon  = (int)$u['is_active'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off'; ?>
                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/users/<?= $uid ?>/active">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-outline-secondary user-action-btn" type="submit" title="<?= e($activeLabel) ?>">
                      <i class="bi <?= e($activeIcon) ?>"></i> <span><?= e($activeLabel) ?></span>
                    </button>
                  </form>

                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary user-action-btn dropdown-toggle"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="bi bi-person-fill-gear"></i> <span>Ndrysho rolin</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <?php
                        $roleLabels = ['client' => 'Klient', 'provider' => 'Punëtor', 'admin' => 'Admin'];
                        $roleIcons  = ['client' => 'bi-person', 'provider' => 'bi-tools', 'admin' => 'bi-shield-fill-check'];
                        foreach ($roleLabels as $r => $label):
                          if ($r === $u['role']) continue;
                      ?>
                        <li>
                          <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/users/<?= $uid ?>/role"
                                onsubmit="return confirm('Ndrysho rolin e <?= e(addslashes((string)$u['name'])) ?> në <?= e($label) ?>?');">
                            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                            <input type="hidden" name="role" value="<?= e($r) ?>">
                            <button class="dropdown-item" type="submit">
                              <i class="bi <?= e($roleIcons[$r]) ?>"></i> <?= e($label) ?>
                            </button>
                          </form>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>

                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/users/<?= $uid ?>/delete"
                        onsubmit="return confirm('FSHI përdoruesin <?= e(addslashes((string)$u['name'])) ?> përgjithmonë?\nTë gjitha postimet, fotot, rezervimet dhe bisedat e tij/saj do të fshihen.');">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-outline-danger user-action-btn" type="submit" title="Fshi përgjithmonë">
                      <i class="bi bi-trash"></i> <span>Fshi</span>
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

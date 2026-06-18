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
              <div class="d-flex flex-wrap gap-1">
                <?php if (!$isMe): ?>
                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/users/<?= $uid ?>/active" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit" title="Aktivizo/Çaktivizo">
                      <i class="bi bi-toggle-on"></i>
                    </button>
                  </form>

                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/users/<?= $uid ?>/role" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <select name="role" class="form-select form-select-sm d-inline-block" style="width:auto;"
                            onchange="if(confirm('Ndrysho rolin në '+this.value+'?')) this.form.submit();">
                      <option disabled selected>Ndrysho rolin...</option>
                      <?php foreach (['client','provider','admin'] as $r): if ($r === $u['role']) continue; ?>
                        <option value="<?= $r ?>"><?= e($r) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>

                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/users/<?= $uid ?>/delete" class="d-inline"
                        onsubmit="return confirm('FSHI përdoruesin <?= e(addslashes((string)$u['name'])) ?> përgjithmonë?\nTë gjitha postimet, fotot, rezervimet dhe bisedat e tij/saj do të fshihen.');">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Fshi përgjithmonë">
                      <i class="bi bi-trash"></i> Fshi
                    </button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

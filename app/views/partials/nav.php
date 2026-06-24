<?php
// Show the back button on every page except the homepage.
$__currentPath = trim((string)($_GET['url'] ?? ''), '/');
$__isHome      = $__currentPath === '';
?>
<nav class="navbar navbar-expand-lg helppy-nav">
  <div class="container-fluid">
    <a class="navbar-brand text-white d-flex align-items-center" href="<?= e(CONFIG['base_url']) ?>/">
      <img src="<?= e(CONFIG['base_url']) ?>/assets/img/logo.svg" alt="Helppy" height="32" class="me-2">
      <span class="fw-bold">Helppy</span>
    </a>
    <?php if (!$__isHome): ?>
      <button type="button" class="helppy-back-btn" data-helppy-back
              aria-label="Kthehu mbrapa" title="Kthehu mbrapa">
        <i class="bi bi-arrow-left"></i>
        <span class="d-none d-md-inline ms-1">Kthehu mbrapa</span>
      </button>
    <?php endif; ?>
    <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navmenu">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/posts">Postimet</a>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link text-white nav-icon-link theme-toggle-btn"
                  data-theme-toggle title="Ndrysho temë (e errët / e ndritshme)"
                  aria-label="Ndrysho temë">
            <i class="bi bi-moon-stars-fill" data-theme-icon></i>
          </button>
        </li>
        <?php if (Auth::check()): ?>
          <?php $u = Auth::user(); ?>

          <li class="nav-item">
            <a class="nav-link text-white nav-icon-link" href="<?= e(CONFIG['base_url']) ?>/chat" title="Bisedat" aria-label="Bisedat">
              <i class="bi bi-chat-dots"></i>
              <span class="nav-badge" data-helppy-badge="chat" hidden>0</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white nav-icon-link" href="<?= e(CONFIG['base_url']) ?>/notifications" title="Njoftimet" aria-label="Njoftimet">
              <i class="bi bi-bell"></i>
              <span class="nav-badge" data-helppy-badge="notifications" hidden>0</span>
            </a>
          </li>
          <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/bookings">Rezervimet</a></li>

          <?php
          // Build the role-specific "my dashboard" target once.
          $myDashUrl   = CONFIG['base_url'] . '/client/dashboard';
          $myDashLabel = 'Llogaria ime';
          if (Auth::role() === 'admin')          { $myDashUrl = CONFIG['base_url'] . '/admin';              $myDashLabel = 'Paneli i admin'; }
          elseif (Auth::role() === 'provider')   { $myDashUrl = CONFIG['base_url'] . '/provider/dashboard'; $myDashLabel = 'Profili im'; }
          ?>

          <li class="nav-item dropdown profile-menu">
            <a class="nav-link text-white d-flex align-items-center gap-2" href="#"
               role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="profile-avatar"><?= e(mb_strtoupper(mb_substr((string)$u['name'], 0, 1))) ?></span>
              <span class="d-none d-lg-inline"><?= e($u['name']) ?></span>
              <i class="bi bi-caret-down-fill profile-caret"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
              <li class="profile-dropdown-header">
                <div class="fw-bold"><?= e($u['name']) ?></div>
                <div class="small text-muted text-soft-wrap"><?= e($u['email'] ?? '') ?></div>
                <div class="profile-role-pill"><?= e(Auth::role() ?? '') ?></div>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="<?= e($myDashUrl) ?>">
                  <i class="bi bi-person-circle"></i> <?= e($myDashLabel) ?>
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="<?= e(CONFIG['base_url']) ?>/password/change">
                  <i class="bi bi-key"></i> Ndrysho passwordin
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="<?= e(CONFIG['base_url']) ?>/logout" class="m-0">
                  <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                  <button class="dropdown-item text-danger" type="submit">
                    <i class="bi bi-box-arrow-right"></i> Dilni
                  </button>
                </form>
              </li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/login">Hyrje</a></li>
          <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/register">Regjistrohu</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

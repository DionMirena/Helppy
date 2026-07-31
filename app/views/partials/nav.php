<?php
$__currentPath = trim((string)($_GET['url'] ?? ''), '/');
$__isHome      = $__currentPath === '';

// Resolve profile URL/label before rendering so we can use it in both mobile bar and collapse
$__myDashUrl   = CONFIG['base_url'] . '/client/dashboard';
$__myDashLabel = 'Llogaria ime';
if (Auth::check()) {
    if (Auth::role() === 'admin')        { $__myDashUrl = CONFIG['base_url'] . '/admin';              $__myDashLabel = 'Paneli i admin'; }
    elseif (Auth::role() === 'provider') { $__myDashUrl = CONFIG['base_url'] . '/provider/dashboard'; $__myDashLabel = 'Profili im'; }
}
?>
<nav class="navbar navbar-expand-lg helppy-nav">
  <div class="container-fluid helppy-nav-container">

    <!-- Brand -->
    <a class="navbar-brand text-white d-flex align-items-center gap-2"
       href="<?= e(CONFIG['base_url']) ?>/">
      <img src="<?= e(CONFIG['base_path']) ?>/assets/img/logo.svg" alt="Helppy" height="30" class="me-1">
      <span class="fw-bold fs-5 d-none d-lg-inline">Helppy<span style="color:var(--helppy-amber)">.</span>com</span>
    </a>

    <!-- Back button (non-home pages) -->
    <?php if (!$__isHome): ?>
      <button type="button" class="helppy-back-btn" data-helppy-back
              aria-label="Kthehu mbrapa" title="Kthehu mbrapa">
        <i class="bi bi-arrow-left"></i>
        <span class="back-btn-label-desktop">Mbrapa</span>
        <span class="back-btn-label-mobile">Kthehu</span>
      </button>
    <?php endif; ?>

    <!-- Abonohu (always visible for providers/admins) -->
    <?php if (Auth::check() && (Auth::role() === 'provider' || Auth::role() === 'admin')): ?>
      <a class="nav-abonohu-btn" href="<?= e(CONFIG['base_url']) ?>/subscribe">
        <i class="bi bi-stars"></i>
        <span>Abonohu</span>
      </a>
    <?php endif; ?>

    <!-- Mobile-only profile button (sits next to Abonohu, before toggler) -->
    <?php if (Auth::check()): ?>
      <?php $__u = Auth::user(); ?>
      <div class="d-flex d-lg-none nav-mobile-profile dropdown">
        <a class="d-flex align-items-center gap-1 text-white text-decoration-none nav-mobile-profile-toggle"
           href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="profile-avatar profile-avatar-sm"><?= e(mb_strtoupper(mb_substr((string)$__u['name'], 0, 1))) ?></span>
          <span class="nav-mobile-profile-label">Profili</span>
          <i class="bi bi-caret-down-fill" style="font-size:10px;opacity:.7"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
          <li class="profile-dropdown-header">
            <div class="fw-bold"><?= e($__u['name']) ?></div>
            <div class="small text-muted"><?= e($__u['email'] ?? '') ?></div>
            <div class="profile-role-pill"><?= e(Auth::role() ?? '') ?></div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="<?= e($__myDashUrl) ?>">
              <i class="bi bi-person-circle"></i> <?= e($__myDashLabel) ?>
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
      </div>
    <?php endif; ?>

    <!-- Hamburger -->
    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#navmenu" aria-label="Menu">
      <i class="bi bi-list text-white nav-toggler-icon"></i>
    </button>

    <!-- Collapsible menu (overlays on mobile) -->
    <div class="collapse navbar-collapse justify-content-between" id="navmenu">

      <!-- Centre nav links -->
      <ul class="navbar-nav mx-auto align-items-lg-center gap-lg-1">
        <li class="nav-item">
          <a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/search">Shërbimet</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/si-funksionon">Si funksionon</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/rreth-nesh">Rreth nesh</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/kontakt">Kontakt</a>
        </li>
      </ul>

      <!-- Right-side auth/actions (desktop) -->
      <ul class="navbar-nav align-items-lg-center gap-lg-1">

        <?php if (Auth::check()): ?>
          <?php $__u = Auth::user(); ?>

          <li class="nav-item">
            <a class="nav-link text-white nav-link-icon" href="<?= e(CONFIG['base_url']) ?>/chat"
               title="Bisedat" aria-label="Bisedat">
              <i class="bi bi-chat-dots"></i>
              <span>Bisedat</span>
              <span class="nav-badge" data-helppy-badge="chat" hidden>0</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white nav-link-icon" href="<?= e(CONFIG['base_url']) ?>/notifications"
               title="Njoftimet" aria-label="Njoftimet">
              <i class="bi bi-bell"></i>
              <span>Njoftimet</span>
              <span class="nav-badge" data-helppy-badge="notifications" hidden>0</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white nav-link-icon" href="<?= e(CONFIG['base_url']) ?>/bookings">
              <i class="bi bi-calendar-check"></i> <span>Rezervimet</span>
            </a>
          </li>

          <li class="nav-item">
            <button type="button"
                    class="nav-link text-white nav-icon-link theme-toggle-btn"
                    data-theme-toggle title="Ndrysho temë" aria-label="Ndrysho temë">
              <i class="bi bi-moon-stars-fill" data-theme-icon></i>
            </button>
          </li>

          <!-- Desktop-only profile dropdown (inside collapse) -->
          <li class="nav-item dropdown profile-menu d-none d-lg-flex">
            <a class="nav-link text-white d-flex align-items-center gap-2" href="#"
               role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="profile-avatar"><?= e(mb_strtoupper(mb_substr((string)$__u['name'], 0, 1))) ?></span>
              <span><?= e($__u['name']) ?></span>
              <i class="bi bi-caret-down-fill profile-caret"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
              <li class="profile-dropdown-header">
                <div class="fw-bold"><?= e($__u['name']) ?></div>
                <div class="small text-muted text-soft-wrap"><?= e($__u['email'] ?? '') ?></div>
                <div class="profile-role-pill"><?= e(Auth::role() ?? '') ?></div>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="<?= e($__myDashUrl) ?>">
                  <i class="bi bi-person-circle"></i> <?= e($__myDashLabel) ?>
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

          <li class="nav-item">
            <button type="button"
                    class="nav-link text-white nav-icon-link theme-toggle-btn"
                    data-theme-toggle title="Ndrysho temë" aria-label="Ndrysho temë">
              <i class="bi bi-moon-stars-fill" data-theme-icon></i>
            </button>
          </li>
          <li class="nav-item ms-lg-2">
            <a class="nav-register-btn" href="<?= e(CONFIG['base_url']) ?>/register">
              Regjistrohu si mjeshtër
            </a>
          </li>
          <li class="nav-item ms-lg-1">
            <a class="nav-login-btn" href="<?= e(CONFIG['base_url']) ?>/login">
              <i class="bi bi-person"></i> Kyçu
            </a>
          </li>

        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

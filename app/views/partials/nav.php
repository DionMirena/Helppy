<nav class="navbar navbar-expand-lg helppy-nav">
  <div class="container-fluid">
    <a class="navbar-brand text-white d-flex align-items-center" href="<?= e(CONFIG['base_url']) ?>/">
      <img src="<?= e(CONFIG['base_url']) ?>/assets/img/logo.svg" alt="Helppy" height="32" class="me-2">
      <span class="fw-bold">Helppy</span>
    </a>
    <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navmenu">
      <ul class="navbar-nav">
        <?php if (Auth::check()): ?>
          <?php $u = Auth::user(); ?>
          <li class="nav-item"><span class="nav-link text-white-50"><?= e($u['name']) ?></span></li>
          <?php if (Auth::role() === 'admin'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/admin">Admin</a></li>
          <?php elseif (Auth::role() === 'provider'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/provider/dashboard">Profili im</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/client/dashboard">Llogaria ime</a></li>
          <?php endif; ?>
          <li class="nav-item">
            <form method="post" action="<?= e(CONFIG['base_url']) ?>/logout" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
              <button class="btn btn-link nav-link text-white" type="submit">Dilni</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/login">Hyrje</a></li>
          <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/register">Regjistrohu</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

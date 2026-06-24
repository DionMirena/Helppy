<!DOCTYPE html>
<html lang="sq">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Helppy.com') ?></title>
<!-- Set theme BEFORE the stylesheet/body render to avoid a flash of light content. -->
<script>
  (function () {
    try {
      var t = localStorage.getItem('helppy-theme');
      if (t !== 'light' && t !== 'dark') {
        t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }
      document.documentElement.setAttribute('data-theme', t);
    } catch (e) { /* localStorage blocked → ignore */ }
  })();
</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= e(CONFIG['base_url']) ?>/assets/css/style.css?v=18" rel="stylesheet">
</head>
<body>
<?php View::partial('nav'); ?>
<?php View::partial('flash', ['flash' => $__flash ?? []]); ?>
<main>
  <?= $content ?>
</main>
<?php View::partial('footer'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>window.HELPPY_BASE = <?= json_encode(CONFIG['base_url']) ?>;</script>
<script src="<?= e(CONFIG['base_url']) ?>/assets/js/helppy.js?v=7"></script>
</body>
</html>

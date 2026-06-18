<?php if (!empty($flash)): ?>
  <div class="container mt-3">
    <?php foreach ($flash as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show helppy-flash"
           role="alert"
           data-helppy-autohide="3000">
        <?= e($f['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

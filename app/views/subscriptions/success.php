<section class="container py-4">
  <div class="form-card mx-auto text-center" style="max-width: 540px;">
    <?php if ($sub['status'] === 'active'): ?>
      <div style="font-size: 64px; color: var(--helppy-green);"><i class="bi bi-check2-circle"></i></div>
      <h1 class="mt-2">Abonimi u aktivizua!</h1>
      <p class="text-muted">
        Tier <strong><?= e(ucfirst((string)$sub['tier'])) ?></strong>,
        i vlefshëm deri më <strong><?= e(date('d M Y, H:i', strtotime((string)$sub['expires_at']))) ?></strong>.
      </p>
      <a class="btn btn-helppy mt-3" href="<?= e(CONFIG['base_url']) ?>/posts/create">
        <i class="bi bi-plus-lg"></i> Posto ofertën tënde të parë
      </a>
    <?php else: ?>
      <div style="font-size: 64px; color: var(--helppy-muted);"><i class="bi bi-hourglass-split"></i></div>
      <h1 class="mt-2">Pagesa po procesohet</h1>
      <p class="text-muted">
        Po presim konfirmimin nga banka. Aktivizimi do të ndodhë automatikisht sapo pagesa të konfirmohet.
      </p>
      <div class="action-bar justify-content-center mt-3" style="justify-content: center;">
        <a class="btn-ghost" href="<?= e(CONFIG['base_url']) ?>/subscribe">
          <i class="bi bi-arrow-left"></i> Kthehu te abonimet
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

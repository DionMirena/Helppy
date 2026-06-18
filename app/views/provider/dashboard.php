<?php
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : CONFIG['base_url'] . '/assets/img/default-avatar.svg';
$selectedCats = array_column($p['categories'], 'id');
?>
<div class="container py-4">
  <h2>Profili im</h2>
  <p class="text-muted">
    <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$user['id'] ?>" target="_blank">Shiko profilin publik &rarr;</a>
  </p>

  <div class="row mt-4">
    <div class="col-md-4">
      <div class="text-center mb-3">
        <img class="profile-photo mb-2" src="<?= e($photoUrl) ?>" alt="profil">
        <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/photo" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
          <input class="form-control form-control-sm" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
          <button class="btn btn-helppy btn-sm mt-2" type="submit">Ngarko foto</button>
        </form>
      </div>

      <div class="bg-white p-3 rounded">
        <h6>Statistika</h6>
        <p class="mb-1">Vleresimi mesatar: <strong><?= $p['avg_rating'] !== null ? round($p['avg_rating'],1) : '—' ?></strong></p>
        <p class="mb-1">Numri i vleresimeve: <strong><?= (int)$p['review_count'] ?></strong></p>
        <p class="mb-0">Vizita ne profil: <strong><?= (int)$p['views'] ?></strong></p>
      </div>

      <div class="bg-white p-3 rounded mt-3">
        <h6>Abonimi</h6>
        <?php if ($subscription): ?>
          <p class="mb-1">Tier: <strong><?= e(ucfirst((string)$subscription['tier'])) ?></strong></p>
          <p class="mb-1">Skadon: <strong><?= e(date('d M Y', strtotime((string)$subscription['expires_at']))) ?></strong></p>
          <p class="mb-2 small text-muted">
            <?= max(0, (int)floor((strtotime((string)$subscription['expires_at']) - time()) / 86400)) ?> ditë të mbetura
          </p>
          <a class="btn btn-helppy-outline btn-sm w-100" href="<?= e(CONFIG['base_url']) ?>/subscribe">Menaxho</a>
        <?php else: ?>
          <p class="mb-2 small text-muted">Nuk ke abonim aktiv. Aktivizo një tier për të postuar oferta.</p>
          <a class="btn btn-helppy btn-sm w-100" href="<?= e(CONFIG['base_url']) ?>/subscribe">
            <i class="bi bi-credit-card"></i> Abonohu
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-8">
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/edit" class="bg-white p-3 rounded">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">

        <div class="mb-3"><label class="form-label">Emri</label>
          <input class="form-control" name="name" value="<?= e($user['name']) ?>" required></div>

        <div class="mb-3"><label class="form-label">Telefoni</label>
          <input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>

        <div class="mb-3"><label class="form-label">Qyteti</label>
          <select class="form-select" name="city_id">
            <option value="">— Zgjidh —</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$user['city_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3"><label class="form-label">Profesioni</label>
          <input class="form-control" name="profession" value="<?= e($p['profession']) ?>" required></div>

        <?php if (!empty($p['is_company'])): ?>
          <div class="mb-3"><label class="form-label">Emri i kompanise</label>
            <input class="form-control" name="company_name" value="<?= e($p['company_name'] ?? '') ?>"></div>
        <?php endif; ?>

        <div class="mb-3"><label class="form-label">Rreth meje</label>
          <textarea class="form-control" name="bio" rows="4" maxlength="2000" placeholder="Përshkruaj veten — eksperiencë, çfarë të dallon..."><?= e($p['bio'] ?? '') ?></textarea></div>

        <div class="mb-3"><label class="form-label">Aftësitë & Shërbimet</label>
          <textarea class="form-control" name="skills_services" rows="4" maxlength="2000" placeholder="Lista e shërbimeve që ofron, p.sh.:&#10;• Instalim & riparim radiatori&#10;• Çelje bllokimi tubacioni&#10;• Ndërrim bateri/lavaman"><?= e($p['skills_services'] ?? '') ?></textarea></div>

        <div class="mb-3"><label class="form-label">Tarifa standarde (€/orë)</label>
          <input class="form-control" type="number" step="0.01" min="0" max="999999" name="hourly_rate" value="<?= e($p['hourly_rate'] !== null ? (string)$p['hourly_rate'] : '') ?>" placeholder="p.sh. 25.00">
          <small class="text-muted">
            Opsionale. Lëre bosh nëse nuk dëshiron të caktosh çmim — atëherë profili yt do të shfaqë
            <em>“Çmimi sipas marrëveshjes”</em>.
          </small>
        </div>

        <div class="mb-3"><label class="form-label">Kategorite</label>
          <div>
            <?php foreach ($categories as $cat):
              $checked = in_array((int)$cat['id'], $selectedCats, true); ?>
              <label class="me-3">
                <input type="checkbox" name="categories[]" value="<?= (int)$cat['id'] ?>" <?= $checked?'checked':'' ?>>
                <?= e($cat['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <button class="btn btn-helppy" type="submit">Ruaj</button>
      </form>
    </div>
  </div>
</div>

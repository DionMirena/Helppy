<div class="container py-4" style="max-width: 640px;">
  <h2 class="mb-3">Regjistrohu</h2>

  <div class="btn-group mb-3" role="group">
    <input type="radio" class="btn-check" name="role-select" id="r-client"   value="client"   <?= ($old['role'] ?? 'client')==='client'?'checked':''?>>
    <label class="btn btn-outline-success" for="r-client">Klient</label>
    <input type="radio" class="btn-check" name="role-select" id="r-provider" value="provider" <?= ($old['role'] ?? '')==='provider'?'checked':''?>>
    <label class="btn btn-outline-success" for="r-provider">Punues</label>
    <input type="radio" class="btn-check" name="role-select" id="r-company"  value="company"  <?= ($old['role'] ?? '')==='company'?'checked':''?>>
    <label class="btn btn-outline-success" for="r-company">Kompani</label>
  </div>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/register">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <input type="hidden" name="role" id="role-input" value="<?= e($old['role'] ?? 'client') ?>">

    <div class="mb-3"><label class="form-label">Emri</label>
      <input class="form-control" name="name" value="<?= e($old['name'] ?? '') ?>" required></div>

    <div class="mb-3"><label class="form-label">Email</label>
      <input class="form-control" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required></div>

    <div class="mb-3"><label class="form-label">Fjalekalimi</label>
      <input class="form-control" type="password" name="password" minlength="6" required></div>

    <div class="mb-3"><label class="form-label">Telefoni</label>
      <input class="form-control" name="phone" value="<?= e($old['phone'] ?? '') ?>" placeholder="+38344 xxx xxx"></div>

    <div class="mb-3"><label class="form-label">Qyteti</label>
      <select class="form-select" name="city_id">
        <option value="">— Zgjidh —</option>
        <?php foreach ($cities as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (isset($old['city_id']) && (int)$old['city_id']===(int)$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div id="provider-fields" style="display:none">
      <hr>
      <div class="mb-3"><label class="form-label">Profesioni</label>
        <input class="form-control" name="profession" value="<?= e($old['profession'] ?? '') ?>" placeholder="p.sh. Hidraulik, Elektricist"></div>

      <div class="mb-3" id="company-only" style="display:none"><label class="form-label">Emri i kompanise</label>
        <input class="form-control" name="company_name" value="<?= e($old['company_name'] ?? '') ?>"></div>

      <div class="mb-3"><label class="form-label">Kategorite (zgjidhni te pakten nje)</label>
        <div>
          <?php foreach ($categories as $cat):
            $checked = in_array((string)$cat['id'], (array)($old['categories'] ?? []), true); ?>
            <label class="me-3">
              <input type="checkbox" name="categories[]" value="<?= (int)$cat['id'] ?>" <?= $checked?'checked':'' ?>>
              <?= e($cat['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button class="btn btn-helppy w-100" type="submit">Krijo llogari</button>
  </form>

  <p class="text-center mt-3">Keni llogari? <a href="<?= e(CONFIG['base_url']) ?>/login">Hyni ketu</a></p>
</div>

<script>
(function () {
  const roleInput     = document.getElementById('role-input');
  const providerBlock = document.getElementById('provider-fields');
  const companyBlock  = document.getElementById('company-only');
  function apply(role) {
    roleInput.value = role;
    providerBlock.style.display = (role === 'provider' || role === 'company') ? 'block' : 'none';
    companyBlock.style.display  = (role === 'company') ? 'block' : 'none';
  }
  document.querySelectorAll('input[name="role-select"]').forEach(r => {
    r.addEventListener('change', () => apply(r.value));
  });
  apply(roleInput.value);
})();
</script>

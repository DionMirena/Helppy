<?php
declare(strict_types=1);

final class ProviderController extends Controller {
    public function show(array $params = []): void {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->notFound(); return; }

        $provider = Provider::find($id);
        if (!$provider || empty($provider['is_active']) || empty($provider['email_verified'])) { $this->notFound(); return; }

        Provider::incrementViews($id);

        $reviews = Review::forProvider($id);

        // Gallery: portfolio photos this provider uploaded directly to their profile.
        $gallery = ProviderPhoto::forProvider($id, 60);

        $alreadyReviewed = false;
        if (Auth::check() && Auth::role() === 'client') {
            $alreadyReviewed = Review::existsFor($id, (int)Auth::user()['id']);
        }

        $this->render('provider/show', [
            'title'           => $provider['name'],
            'p'               => $provider,
            'reviews'         => $reviews,
            'gallery'         => $gallery,
            'alreadyReviewed' => $alreadyReviewed,
        ]);
    }

    public function dashboard(array $params = []): void {
        Auth::require('provider');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $uid = (int)Auth::user()['id'];
        $provider = Provider::find($uid);
        $this->render('provider/dashboard', [
            'title'        => 'Profili im',
            'p'            => $provider,
            'cities'       => City::all(),
            'categories'   => Category::all(),
            'user'         => Auth::user(),
            'subscription' => Subscription::activeFor($uid),
            'workPhotos'   => ProviderPhoto::forProvider($uid, 60),
        ]);
    }

    public function update(array $params = []): void {
        Auth::require('provider');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $uid = (int)Auth::user()['id'];

        $name           = trim((string)Request::post('name', ''));
        $phone          = trim((string)Request::post('phone', ''));
        $cityId         = Request::post('city_id');
        $cityId         = is_numeric($cityId) ? (int)$cityId : null;
        $profession     = trim((string)Request::post('profession', ''));
        $bio            = trim((string)Request::post('bio', ''));
        $skills         = trim((string)Request::post('skills_services', ''));
        $hourlyRate     = Request::post('hourly_rate');
        $hourlyRate     = ($hourlyRate === '' || $hourlyRate === null || !is_numeric($hourlyRate)) ? null : (float)$hourlyRate;
        $companyName    = trim((string)Request::post('company_name', ''));
        $categoryIds    = array_map('intval', (array)Request::post('categories', []));

        // Optional: provider typed a profession that isn't in the dropdown — create it.
        $newCategoryName = trim((string)Request::post('new_category', ''));
        if ($newCategoryName !== '') {
            $newId = Category::findOrCreateByName($newCategoryName);
            if ($newId > 0 && !in_array($newId, $categoryIds, true)) {
                $categoryIds[] = $newId;
            }
        }

        // Optional pin from the map picker. Empty = clear the pin.
        $latRaw = Request::post('latitude');
        $lngRaw = Request::post('longitude');
        $latitude  = is_numeric($latRaw) ? (float)$latRaw : null;
        $longitude = is_numeric($lngRaw) ? (float)$lngRaw : null;
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $latitude = null;
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $longitude = null;
        if ($latitude === null || $longitude === null) { $latitude = null; $longitude = null; }

        if ($name === '' || $profession === '') {
            $this->flash('danger', 'Emri dhe profesioni jane te detyrueshem.');
            $this->redirect('/provider/dashboard');
        }
        if ($hourlyRate !== null && ($hourlyRate < 0 || $hourlyRate > 999999)) {
            $this->flash('danger', 'Tarifa orare e pavlefshme.');
            $this->redirect('/provider/dashboard');
        }

        $district = User::districtForCity($cityId);
        DB::q('UPDATE users SET name=?, phone=?, city_id=?, district=? WHERE id=?',
              [$name, $phone ?: null, $cityId, $district, $uid]);

        Provider::update($uid, [
            'profession'      => $profession,
            'bio'             => $bio !== '' ? $bio : null,
            'skills_services' => $skills !== '' ? $skills : null,
            'hourly_rate'     => $hourlyRate,
            'company_name'    => $companyName !== '' ? $companyName : null,
            'latitude'        => $latitude,
            'longitude'       => $longitude,
        ]);

        if ($categoryIds) Provider::setCategories($uid, $categoryIds);

        $this->flash('success', 'Profili u perditesua.');
        $this->redirect('/provider/dashboard');
    }

    public function uploadPhoto(array $params = []): void {
        Auth::require('provider');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $uid = (int)Auth::user()['id'];

        $file = Request::file('photo');
        if (!$file) {
            $this->flash('danger', 'Asnje foto e ngarkuar.');
            $this->redirect('/provider/dashboard');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Gabim ne ngarkim (kod ' . (int)$file['error'] . ').');
            $this->redirect('/provider/dashboard');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            $this->flash('danger', 'Foto eshte me e madhe se 2MB.');
            $this->redirect('/provider/dashboard');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            $this->flash('danger', 'Formati i lejuar: JPG, PNG, WEBP.');
            $this->redirect('/provider/dashboard');
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $target  = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $newName;

        if (!is_dir(CONFIG['upload_dir'])) {
            mkdir(CONFIG['upload_dir'], 0775, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $this->flash('danger', 'Foto nuk u ruajt.');
            $this->redirect('/provider/dashboard');
        }

        $cur = DB::q('SELECT photo FROM providers WHERE user_id=?', [$uid])->fetch();
        if (!empty($cur['photo'])) {
            $old = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $cur['photo'];
            if (is_file($old)) @unlink($old);
        }

        Provider::setPhoto($uid, $newName);
        $this->flash('success', 'Foto u ngarkua.');
        $this->redirect('/provider/dashboard');
    }

    /** POST /provider/work-photo — upload a portfolio photo. Requires active subscription. */
    public function uploadWorkPhoto(array $params = []): void {
        Auth::require('provider');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $uid = (int)Auth::user()['id'];

        if (!Subscription::isActive($uid)) {
            $this->flash('info', 'Për të shtuar foto të punës, aktivizo një abonim.');
            $this->redirect('/subscribe');
            return;
        }

        if (ProviderPhoto::count($uid) >= 30) {
            $this->flash('warning', 'Ke arritur maksimumin prej 30 fotosh. Fshi ndonjë para se të shtosh të reja.');
            $this->redirect('/provider/dashboard');
            return;
        }

        $file = Request::file('photo');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Asnjë foto e ngarkuar ose gabim në ngarkim.');
            $this->redirect('/provider/dashboard');
            return;
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            $this->flash('danger', 'Foto është më e madhe se 3MB.');
            $this->redirect('/provider/dashboard');
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            $this->flash('danger', 'Formati i lejuar: JPG, PNG, WEBP.');
            $this->redirect('/provider/dashboard');
            return;
        }

        if (!is_dir(CONFIG['upload_dir'])) {
            mkdir(CONFIG['upload_dir'], 0775, true);
        }

        $newName = 'work_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        $target  = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $newName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $this->flash('danger', 'Foto nuk u ruajt.');
            $this->redirect('/provider/dashboard');
            return;
        }

        $caption = trim((string)Request::post('caption', ''));
        ProviderPhoto::add($uid, $newName, $caption !== '' ? mb_substr($caption, 0, 255) : null);

        $this->flash('success', 'Foto u shtua në galeri.');
        $this->redirect('/provider/dashboard');
    }

    /** POST /provider/work-photo/{id}/delete — owner or admin removes one. */
    public function deleteWorkPhoto(array $params = []): void {
        Auth::require();
        $id   = (int)($params['id'] ?? 0);
        $uid  = (int)Auth::user()['id'];
        $role = Auth::role();

        $filename = $role === 'admin'
            ? ProviderPhoto::adminDelete($id)
            : ProviderPhoto::deleteOne($id, $uid);

        if ($filename === null) {
            $this->flash('danger', 'Foto nuk u gjet ose nuk ke leje ta fshish.');
        } else {
            $path = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $filename;
            if (is_file($path)) @unlink($path);
            $this->flash('success', 'Foto u fshi.');
        }
        $this->redirect($role === 'admin' && Request::post('return') === 'profile'
            ? '/provider/' . (int)Request::post('provider_id')
            : '/provider/dashboard');
    }
}

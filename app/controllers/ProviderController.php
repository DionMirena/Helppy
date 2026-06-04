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

        $alreadyReviewed = false;
        if (Auth::check() && Auth::role() === 'client') {
            $alreadyReviewed = Review::existsFor($id, (int)Auth::user()['id']);
        }

        $this->render('provider/show', [
            'title'           => $provider['name'],
            'p'               => $provider,
            'reviews'         => $reviews,
            'alreadyReviewed' => $alreadyReviewed,
        ]);
    }

    public function dashboard(array $params = []): void {
        Auth::require('provider');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $uid = (int)Auth::user()['id'];
        $provider = Provider::find($uid);
        $this->render('provider/dashboard', [
            'title'      => 'Profili im',
            'p'          => $provider,
            'cities'     => City::all(),
            'categories' => Category::all(),
            'user'       => Auth::user(),
        ]);
    }

    public function update(array $params = []): void {
        Auth::require('provider');
        if (!Verification::isEmailVerified((int)Auth::user()['id'])) { $this->redirect('/verify-email'); }
        $uid = (int)Auth::user()['id'];

        $name        = trim((string)Request::post('name', ''));
        $phone       = trim((string)Request::post('phone', ''));
        $cityId      = Request::post('city_id');
        $cityId      = is_numeric($cityId) ? (int)$cityId : null;
        $profession  = trim((string)Request::post('profession', ''));
        $bio         = trim((string)Request::post('bio', ''));
        $companyName = trim((string)Request::post('company_name', ''));
        $categoryIds = array_map('intval', (array)Request::post('categories', []));

        if ($name === '' || $profession === '') {
            $this->flash('danger', 'Emri dhe profesioni jane te detyrueshem.');
            $this->redirect('/provider/dashboard');
        }

        DB::q('UPDATE users SET name=?, phone=?, city_id=? WHERE id=?',
              [$name, $phone ?: null, $cityId, $uid]);

        Provider::update($uid, [
            'profession'   => $profession,
            'bio'          => $bio !== '' ? $bio : null,
            'company_name' => $companyName !== '' ? $companyName : null,
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
}

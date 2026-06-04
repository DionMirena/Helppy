<?php
declare(strict_types=1);

final class AuthController extends Controller {
    public function loginForm(array $params = []): void {
        if (Auth::check()) $this->redirect('/');
        $this->render('auth/login', ['title' => 'Hyrje']);
    }

    public function login(array $params = []): void {
        $email = trim((string)Request::post('email', ''));
        $pass  = (string)Request::post('password', '');

        $user = $email ? User::findByEmail($email) : null;
        if (!$user || !password_verify($pass, $user['password_hash'])) {
            $this->flash('danger', 'Email ose fjalekalim i pasakte.');
            $this->redirect('/login');
        }
        if (!$user['is_active']) {
            $this->flash('danger', 'Llogaria juaj eshte cdeaktivizuar.');
            $this->redirect('/login');
        }

        Auth::login($user);
        $this->flash('success', 'Mire se erdhet, ' . $user['name'] . '!');

        switch ($user['role']) {
            case 'admin':    $this->redirect('/admin'); break;
            case 'provider': $this->redirect('/provider/dashboard'); break;
            default:         $this->redirect('/');
        }
    }

    public function logout(array $params = []): void {
        Auth::logout();
        session_start();
        $this->flash('info', 'U larguat me sukses.');
        $this->redirect('/');
    }

    public function registerForm(array $params = []): void {
        if (Auth::check()) $this->redirect('/');
        $this->render('auth/register', [
            'title'      => 'Regjistrohu',
            'cities'     => City::all(),
            'categories' => Category::all(),
            'old'        => [],
        ]);
    }

    public function register(array $params = []): void {
        $role        = Request::post('role', 'client');
        $name        = trim((string)Request::post('name', ''));
        $email       = trim((string)Request::post('email', ''));
        $password    = (string)Request::post('password', '');
        $phone       = trim((string)Request::post('phone', ''));
        $cityId      = Request::post('city_id');
        $cityId      = is_numeric($cityId) ? (int)$cityId : null;
        $profession  = trim((string)Request::post('profession', ''));
        $companyName = trim((string)Request::post('company_name', ''));
        $categoryIds = (array)Request::post('categories', []);

        $errors = [];
        if (!in_array($role, ['client','provider','company'], true)) $errors[] = 'Roli i pasakte.';
        if ($name === '')               $errors[] = 'Emri eshte i detyrueshem.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email i pavlefshem.';
        if (strlen($password) < 6)      $errors[] = 'Fjalekalimi duhet te kete te pakten 6 karaktere.';
        if ($email && User::emailExists($email)) $errors[] = 'Ky email eshte i regjistruar tashme.';

        $isProviderRole = ($role === 'provider' || $role === 'company');
        if ($isProviderRole) {
            if ($profession === '') $errors[] = 'Profesioni eshte i detyrueshem.';
            if ($role === 'company' && $companyName === '') $errors[] = 'Emri i kompanise eshte i detyrueshem.';
            if (!$categoryIds) $errors[] = 'Zgjidhni te pakten nje kategori.';
        }

        if ($errors) {
            foreach ($errors as $err) $this->flash('danger', $err);
            $this->render('auth/register', [
                'title'      => 'Regjistrohu',
                'cities'     => City::all(),
                'categories' => Category::all(),
                'old'        => $_POST,
            ]);
            return;
        }

        DB::pdo()->beginTransaction();
        try {
            $dbRole = $isProviderRole ? 'provider' : 'client';
            $uid = User::create($name, $email, password_hash($password, PASSWORD_DEFAULT),
                                $phone ?: null, $dbRole, $cityId);

            if ($isProviderRole) {
                Provider::create(
                    $uid,
                    $profession,
                    $role === 'company',
                    $role === 'company' ? $companyName : null
                );
                Provider::setCategories($uid, array_map('intval', $categoryIds));
            }
            DB::pdo()->commit();
        } catch (Throwable $e) {
            DB::pdo()->rollBack();
            $this->flash('danger', 'Gabim ne regjistrim: ' . $e->getMessage());
            $this->redirect('/register');
        }

        $user = User::find($uid);
        Auth::login($user);
        $this->flash('success', 'Llogaria u krijua. Mire se erdhet!');

        $this->redirect($isProviderRole ? '/provider/dashboard' : '/');
    }
}

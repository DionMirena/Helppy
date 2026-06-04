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

        // Every successful credential check issues a 2FA challenge — no role exemption.
        Verification::generateCodeFor((int)$user['id']);
        try {
            Verification::send((int)$user['id']);
        } catch (Throwable $e) {
            $this->logMailError('login', $e);
            $this->flash('danger', 'Kodi i verifikimit nuk u dergua. Provoni perseri.');
            $this->redirect('/login');
        }
        Auth::setPending((int)$user['id']);
        $this->flash('info', 'Nje kod verifikimi u dergua ne emailin tuaj.');
        $this->redirect('/verify-email');
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

        // Mark new user as unverified, generate code, send email
        DB::q('UPDATE users SET email_verified=0 WHERE id=?', [$uid]);
        Verification::generateCodeFor($uid);
        try {
            Verification::send($uid);
        } catch (Throwable $e) {
            $this->logMailError('signup', $e);
            $this->flash('danger', 'Llogaria u krijua, por emaili nuk u dergua. Provoni te dergoni perseri.');
        }

        $user = User::find($uid);
        Auth::login($user);
        $this->flash('success', 'Llogaria u krijua. Verifikoni emailin per te vazhduar.');
        $this->redirect('/verify-email');
    }

    public function verifyForm(array $params = []): void {
        $uid = Auth::pendingUid() ?? (Auth::check() ? (int)Auth::user()['id'] : null);
        if ($uid === null) { $this->redirect('/login'); }

        if (Auth::check() && Verification::isEmailVerified($uid) && Auth::pendingUid() === null) {
            $this->postLoginRedirect(Auth::role());
            return;
        }

        $email = (string)DB::q('SELECT email FROM users WHERE id=?', [$uid])->fetchColumn();
        $this->render('auth/verify', [
            'title'        => 'Verifiko emailin',
            'masked_email' => self::maskEmail($email),
            'resend_in'    => Verification::secondsUntilResend($uid),
            'mode'         => Auth::pendingUid() !== null ? 'login' : 'signup',
        ]);
    }

    public function verify(array $params = []): void {
        $uid = Auth::pendingUid() ?? (Auth::check() ? (int)Auth::user()['id'] : null);
        if ($uid === null) { $this->redirect('/login'); }

        $code = trim((string)Request::post('code', ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->flash('danger', 'Shkruani nje kod 6-shifror.');
            $this->redirect('/verify-email');
        }

        if (!Verification::verify($uid, $code)) {
            $this->flash('danger', 'Kodi i pavlefshem ose i skaduar.');
            $this->redirect('/verify-email');
        }

        if (Auth::pendingUid() !== null) {
            $user = User::find($uid);
            Auth::clearPending();
            Auth::login($user);
            $this->flash('success', 'Mire se erdhet, ' . $user['name'] . '!');
            $this->postLoginRedirect($user['role']);
        } else {
            DB::q('UPDATE users SET email_verified=1 WHERE id=?', [$uid]);
            $this->flash('success', 'Emaili u verifikua. Mire se erdhet ne Helppy!');
            $this->postLoginRedirect(Auth::role());
        }
    }

    public function resendVerification(array $params = []): void {
        $uid = Auth::pendingUid() ?? (Auth::check() ? (int)Auth::user()['id'] : null);
        if ($uid === null) { $this->redirect('/login'); }

        $wait = Verification::secondsUntilResend($uid);
        if ($wait > 0) {
            $this->flash('danger', "Prisni $wait sekonda perpara se te dergoni perseri.");
            $this->redirect('/verify-email');
        }
        Verification::generateCodeFor($uid);
        try {
            Verification::send($uid);
            $this->flash('info', 'Kodi u dergua perseri.');
        } catch (Throwable $e) {
            $this->logMailError('resend', $e);
            $this->flash('danger', 'Emaili nuk u dergua. Provoni perseri me vone.');
        }
        $this->redirect('/verify-email');
    }

    public function cancelVerification(array $params = []): void {
        Auth::clearPending();
        $this->flash('info', 'Anuluat verifikimin.');
        $this->redirect('/login');
    }

    private static function maskEmail(string $email): string {
        $at = strpos($email, '@');
        if ($at === false || $at === 0) return $email;
        return $email[0] . '***' . substr($email, $at);
    }

    private function postLoginRedirect(?string $role): void {
        switch ($role) {
            case 'admin':    $this->redirect('/admin'); break;
            case 'provider': $this->redirect('/provider/dashboard'); break;
            default:         $this->redirect('/');
        }
    }

    private function logMailError(string $context, Throwable $e): void {
        $dir = APP_ROOT . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        error_log(date('c') . " [$context] " . $e->getMessage() . PHP_EOL, 3, $dir . '/mail.log');
    }
}

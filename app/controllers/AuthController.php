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

        // If the email was never verified (e.g. user registered but didn't
        // finish), bounce them through the verification step. Otherwise log
        // them straight in — no per-login 2FA.
        if (empty($user['email_verified'])) {
            Verification::generateCodeFor((int)$user['id']);
            try {
                Verification::send((int)$user['id']);
            } catch (Throwable $e) {
                $this->logMailError('login', $e);
                $this->flash('danger', 'Kodi i verifikimit nuk u dergua. Provoni perseri.');
                $this->redirect('/login');
            }
            Auth::setPending((int)$user['id']);
            $this->flash('info', 'Llogaria juaj nuk eshte verifikuar ende. Kontrollo email-in per kodin e verifikimit.');
            $this->redirect('/verify-email');
            return;
        }

        Auth::login($user);
        $this->flash('success', 'Mire se erdhet, ' . $user['name'] . '!');
        $this->postLoginRedirect($user['role']);
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

    /* ===========================================================
       PASSWORD RESET (forgot) + PASSWORD CHANGE (logged-in)
       =========================================================== */

    public function forgotForm(array $params = []): void {
        if (Auth::check()) { $this->redirect('/password/change'); return; }
        $this->render('auth/forgot', ['title' => 'Keni harruar passwordin?']);
    }

    public function forgotSend(array $params = []): void {
        $email = trim((string)Request::post('email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('danger', 'Email i pavlefshëm.');
            $this->redirect('/password/forgot');
            return;
        }

        // Always redirect to /password/reset so we don't leak whether the email exists.
        $user = User::findByEmail($email);
        if ($user) {
            // Rate-limit: 60s between sends.
            $last = (string)DB::q('SELECT password_reset_last_sent_at FROM users WHERE id=?', [$user['id']])->fetchColumn();
            if ($last && strtotime($last) > time() - 60) {
                $this->flash('info', 'Kodi u dërgua para pak. Kontrollo email-in.');
                $_SESSION['pw_reset_email'] = $email;
                $this->redirect('/password/reset');
                return;
            }

            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::q(
                'UPDATE users SET password_reset_code = ?,
                                  password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                                  password_reset_last_sent_at = NOW()
                 WHERE id = ?',
                [$code, $user['id']]
            );

            $body = strtr(
                "Pershendetje {NAME},\n\n" .
                "Kodi i ndryshimit te fjalekalimit per Helppy.com:\n\n" .
                "  {CODE}\n\n" .
                "Ky kod vlen 30 minuta. Nese nuk e ke kerkuar ti, injoroje kete email.\n\n" .
                "Ekipi Helppy.com\n",
                ['{NAME}' => (string)$user['name'], '{CODE}' => $code]
            );
            try {
                Mailer::send($email, 'Helppy.com — ndrysho fjalekalimin', $body);
            } catch (Throwable $e) {
                $this->logMailError('pwreset', $e);
            }
        }

        $_SESSION['pw_reset_email'] = $email;
        $this->flash('info', 'Nëse email-i është i regjistruar, kodi u dërgua. Kontrollo kutinë postare.');
        $this->redirect('/password/reset');
    }

    public function resetForm(array $params = []): void {
        $email = (string)($_SESSION['pw_reset_email'] ?? Request::get('email', ''));
        if ($email === '') { $this->redirect('/password/forgot'); return; }
        $this->render('auth/reset', [
            'title'         => 'Vendos passwordin e ri',
            'email'         => $email,
            'masked_email'  => self::maskEmail($email),
        ]);
    }

    public function reset(array $params = []): void {
        $email   = trim((string)Request::post('email', ''));
        $code    = trim((string)Request::post('code', ''));
        $newPass = (string)Request::post('password', '');
        $confirm = (string)Request::post('password_confirm', '');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'Email i pavlefshëm.';
        if (!preg_match('/^\d{6}$/', $code))                   $errors[] = 'Kodi duhet të jetë 6 shifra.';
        if (strlen($newPass) < 8)                              $errors[] = 'Passwordi duhet të jetë të paktën 8 karaktere.';
        if ($newPass !== $confirm)                             $errors[] = 'Passwordet nuk përputhen.';
        if ($errors) {
            $this->flash('danger', implode(' ', $errors));
            $this->redirect('/password/reset');
            return;
        }

        $user = User::findByEmail($email);
        if (!$user
            || empty($user['password_reset_code'])
            || $user['password_reset_code'] !== $code
            || empty($user['password_reset_expires_at'])
            || strtotime((string)$user['password_reset_expires_at']) < time()
        ) {
            $this->flash('danger', 'Kodi i pavlefshëm ose i skaduar.');
            $this->redirect('/password/reset');
            return;
        }

        DB::q(
            'UPDATE users SET password_hash = ?,
                              password_reset_code = NULL,
                              password_reset_expires_at = NULL
             WHERE id = ?',
            [password_hash($newPass, PASSWORD_DEFAULT), (int)$user['id']]
        );
        unset($_SESSION['pw_reset_email']);
        $this->flash('success', 'Passwordi u ndryshua. Tani mund të hysh.');
        $this->redirect('/login');
    }

    public function changeForm(array $params = []): void {
        Auth::require();
        $this->render('auth/change-password', ['title' => 'Ndrysho passwordin']);
    }

    public function change(array $params = []): void {
        Auth::require();
        $uid = (int)Auth::user()['id'];
        $cur     = (string)Request::post('current_password', '');
        $newPass = (string)Request::post('password', '');
        $confirm = (string)Request::post('password_confirm', '');

        $row = DB::q('SELECT password_hash FROM users WHERE id=?', [$uid])->fetch();
        if (!$row || !password_verify($cur, (string)$row['password_hash'])) {
            $this->flash('danger', 'Passwordi aktual i pasaktë.');
            $this->redirect('/password/change');
            return;
        }
        if (strlen($newPass) < 8) {
            $this->flash('danger', 'Passwordi i ri duhet të jetë të paktën 8 karaktere.');
            $this->redirect('/password/change');
            return;
        }
        if ($newPass !== $confirm) {
            $this->flash('danger', 'Passwordet e reja nuk përputhen.');
            $this->redirect('/password/change');
            return;
        }

        DB::q('UPDATE users SET password_hash = ? WHERE id = ?',
              [password_hash($newPass, PASSWORD_DEFAULT), $uid]);
        $this->flash('success', 'Passwordi u ndryshua me sukses.');
        $this->redirect('/password/change');
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

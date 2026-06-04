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

    public function registerForm(array $params = []): void { echo 'TODO regForm'; }
    public function register(array $params = []): void     { echo 'TODO reg'; }
}

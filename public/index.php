<?php
declare(strict_types=1);

session_start();

define('APP_ROOT', dirname(__DIR__));
define('CONFIG', require APP_ROOT . '/config/config.php');

if (CONFIG['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// Tiny autoloader: core, controllers, models
spl_autoload_register(function (string $class): void {
    foreach (['app/core', 'app/controllers', 'app/models'] as $dir) {
        $p = APP_ROOT . "/$dir/$class.php";
        if (is_file($p)) { require $p; return; }
    }
});

$router = new Router();

// PUBLIC
$router->get('/',                          [HomeController::class,    'index']);
$router->get('/search',                    [SearchController::class,  'results']);
$router->get('/provider/dashboard',        [ProviderController::class,'dashboard']);
$router->get('/provider/{id}',             [ProviderController::class,'show']);
$router->get('/login',                     [AuthController::class,    'loginForm']);
$router->post('/login',                    [AuthController::class,    'login']);
$router->get('/register',                  [AuthController::class,    'registerForm']);
$router->post('/register',                 [AuthController::class,    'register']);
$router->post('/logout',                   [AuthController::class,    'logout']);

// CLIENT
$router->get('/client/dashboard',          [ClientController::class,  'dashboard']);
$router->post('/provider/{id}/review',     [ReviewController::class,  'store']);
$router->post('/review/{id}/delete',       [ReviewController::class,  'destroy']);

// PROVIDER
$router->post('/provider/edit',            [ProviderController::class,'update']);
$router->post('/provider/photo',           [ProviderController::class,'uploadPhoto']);

// ADMIN
$router->get('/admin',                     [AdminController::class,   'index']);
$router->get('/admin/providers',           [AdminController::class,   'providers']);
$router->post('/admin/providers/{id}/active',  [AdminController::class,'toggleActive']);
$router->post('/admin/providers/{id}/premium', [AdminController::class,'togglePremium']);
$router->get('/admin/categories',          [AdminController::class,   'categories']);
$router->post('/admin/categories',         [AdminController::class,   'createCategory']);
$router->post('/admin/categories/{id}/delete', [AdminController::class,'deleteCategory']);
$router->post('/admin/reviews/{id}/delete',[AdminController::class,   'deleteReview']);

$url = $_GET['url'] ?? '/';
$router->dispatch(Request::method(), $url);

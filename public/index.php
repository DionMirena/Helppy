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

// Eager-load Helpers (used by controllers as a static utility).
require APP_ROOT . '/app/core/Helpers.php';
require APP_ROOT . '/app/core/Stripe.php';

$router = new Router();

// PUBLIC
$router->get('/',                          [HomeController::class,    'index']);
$router->get('/search',                    [SearchController::class,  'results']);
$router->get('/provider/dashboard',        [ProviderController::class,'dashboard']);
$router->get('/provider/{id}',             [ProviderController::class,'show']);

// POSTS
$router->get('/posts',                     [PostController::class,    'index']);
$router->get('/posts/create',              [PostController::class,    'createForm']);
$router->post('/posts',                    [PostController::class,    'store']);
$router->get('/posts/{id}',                [PostController::class,    'show']);
$router->get('/posts/{id}/edit',           [PostController::class,    'editForm']);
$router->post('/posts/{id}',               [PostController::class,    'update']);
$router->post('/posts/{id}/close',         [PostController::class,    'close']);
$router->post('/posts/{id}/delete',        [PostController::class,    'destroy']);

// BOOKINGS
$router->get('/provider/{id}/book',        [BookingController::class, 'createForm']);
$router->post('/provider/{id}/book',       [BookingController::class, 'store']);
$router->get('/bookings',                  [BookingController::class, 'index']);
$router->get('/bookings/{id}',             [BookingController::class, 'show']);
$router->post('/bookings/{id}/{action}',   [BookingController::class, 'transition']);

// NOTIFICATIONS
$router->get('/notifications',             [NotificationController::class, 'index']);
$router->post('/notifications/read-all',   [NotificationController::class, 'readAll']);
$router->get('/api/notifications/unread.json', [NotificationController::class, 'unreadJson']);

// CHAT
$router->get('/chat',                          [ChatController::class, 'index']);
$router->get('/chat/with/{user_id}',           [ChatController::class, 'start']);
$router->get('/chat/{id}',                     [ChatController::class, 'show']);
$router->post('/chat/{id}/message',            [ChatController::class, 'send']);
$router->get('/api/chat/{id}/messages.json',   [ChatController::class, 'pollMessages']);

// SUBSCRIPTIONS
$router->get('/subscribe',                     [SubscriptionController::class, 'index']);
$router->post('/subscribe/checkout',           [SubscriptionController::class, 'checkout']);
$router->post('/subscribe/bank',               [SubscriptionController::class, 'bank']);
$router->get('/subscribe/bank/{id}',           [SubscriptionController::class, 'bankInstructions']);
$router->get('/subscribe/success',             [SubscriptionController::class, 'success']);
$router->post('/subscribe/cancel-current',     [SubscriptionController::class, 'cancelMine']);
// Stripe webhook — no CSRF (it's signed by Stripe instead).
$router->post('/subscribe/webhook',            [SubscriptionController::class, 'webhook'], false);

$router->get('/admin/subscriptions',                       [AdminController::class, 'subscriptions']);
$router->post('/admin/subscriptions/{id}/activate',        [AdminController::class, 'activateSubscription']);
$router->post('/admin/subscriptions/{id}/cancel',          [AdminController::class, 'cancelSubscription']);

// Full admin: users, photos, bookings, conversations
$router->get('/admin/users',                               [AdminController::class, 'users']);
$router->post('/admin/users/{id}/active',                  [AdminController::class, 'toggleUserActive']);
$router->post('/admin/users/{id}/role',                    [AdminController::class, 'setUserRole']);
$router->post('/admin/users/{id}/delete',                  [AdminController::class, 'deleteUser']);
$router->post('/admin/providers/{id}/photo/delete',        [AdminController::class, 'deleteProviderPhoto']);
$router->post('/admin/post-photos/{photo_id}/delete',      [AdminController::class, 'deletePostPhoto']);
$router->post('/admin/bookings/{id}/delete',               [AdminController::class, 'deleteBooking']);
$router->post('/admin/conversations/{id}/delete',          [AdminController::class, 'deleteConversation']);

$router->get('/login',                     [AuthController::class,    'loginForm']);
$router->post('/login',                    [AuthController::class,    'login']);
$router->get('/register',                  [AuthController::class,    'registerForm']);
$router->post('/register',                 [AuthController::class,    'register']);
$router->post('/logout',                   [AuthController::class,    'logout']);
$router->get('/verify-email',              [AuthController::class,    'verifyForm']);
$router->post('/verify-email',             [AuthController::class,    'verify']);
$router->post('/verify-email/resend',      [AuthController::class,    'resendVerification']);
$router->post('/verify-email/cancel',      [AuthController::class,    'cancelVerification']);

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
$router->get('/admin/posts',               [AdminController::class,   'posts']);
$router->post('/admin/posts/{id}/hide',    [AdminController::class,   'hidePost']);

$url = $_GET['url'] ?? '/';
$router->dispatch(Request::method(), $url);

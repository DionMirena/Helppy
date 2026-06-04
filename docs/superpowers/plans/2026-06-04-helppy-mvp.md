# Helppy.com MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a fully working Kosovo home-services marketplace (Helppy.com) on a local Laragon stack: PHP 8.x + MySQL + Bootstrap 5, with real auth, real DB, search by city+category, reviews, and an admin panel.

**Architecture:** Plain PHP, no framework, no Composer. Front-controller MVC: every request goes through `/public/index.php`, parsed by a tiny `Router`, dispatched to a controller method that returns a `View`. Views are PHP includes wrapped in a single `layout.php`. PDO for DB, sessions + `password_hash` for auth, CSRF tokens on every POST.

**Tech Stack:** PHP 8.x, MySQL, Bootstrap 5 (CDN), vanilla JS, PDO, sessions.

**Spec:** `docs/superpowers/specs/2026-06-04-helppy-design.md`

**Testing approach:** No PHPUnit (no Composer). Each task ends with explicit manual smoke-test steps (URL to open, what to see/click, expected outcome). Engineer must run them before committing.

---

## File Structure (locked)

```
/Helppy.com/
  /public/
    index.php                  front controller
    .htaccess                  rewrite rules
    /assets/css/style.css
    /assets/js/app.js
    /assets/img/logo.svg
    /assets/img/default-avatar.svg
    /uploads/                  provider photos (gitignored, .gitkeep)
  /app/
    /core/
      DB.php
      Router.php
      Request.php
      Auth.php
      View.php
      Controller.php
    /controllers/
      HomeController.php
      AuthController.php
      SearchController.php
      ProviderController.php
      ClientController.php
      ReviewController.php
      AdminController.php
    /models/
      User.php
      Provider.php
      Category.php
      City.php
      Review.php
    /views/
      layout.php
      /home/index.php
      /search/results.php
      /provider/show.php
      /provider/dashboard.php
      /auth/login.php
      /auth/register.php
      /client/dashboard.php
      /admin/index.php
      /admin/providers.php
      /admin/categories.php
      /errors/403.php
      /errors/404.php
      /partials/nav.php
      /partials/footer.php
      /partials/provider-card.php
      /partials/review-card.php
      /partials/flash.php
  /config/
    config.example.php
    config.php                 (gitignored)
  /db/
    schema.sql
    seed.sql
  /index.php                   fallback for installs where Laragon doc root is project root
  README.md
```

---

## Task 1: Project scaffolding

**Files:**
- Create: `public/.htaccess`
- Create: `public/index.php` (stub)
- Create: `public/uploads/.gitkeep` (empty)
- Create: `config/config.example.php`
- Create: `config/config.php` (gitignored)
- Create: `index.php` (root fallback)
- Modify: `.gitignore` (already exists - extend)

- [ ] **Step 1: Create folder structure**

Run:
```bash
mkdir -p public/assets/css public/assets/js public/assets/img public/uploads
mkdir -p app/core app/controllers app/models
mkdir -p app/views/home app/views/search app/views/provider app/views/auth
mkdir -p app/views/client app/views/admin app/views/errors app/views/partials
mkdir -p config db
```

- [ ] **Step 2: Add `.gitkeep` so `/public/uploads/` is tracked but empty**

Create `public/uploads/.gitkeep` (empty file).

- [ ] **Step 3: Write `public/.htaccess`**

```apache
RewriteEngine On
RewriteBase /Helppy.com/public/

# If request is for an existing file or directory, serve directly
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Otherwise route to index.php with the path in ?url=
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

If the user sets a Laragon virtual host pointing at `/public/`, change `RewriteBase /Helppy.com/public/` to `RewriteBase /` and document this in README.

- [ ] **Step 4: Write `config/config.example.php`**

```php
<?php
// Copy to config.php and fill in real values.
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'helppy',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    'base_url'   => 'http://localhost/Helppy.com/public',
    'upload_dir' => __DIR__ . '/../public/uploads',
    'upload_url' => 'http://localhost/Helppy.com/public/uploads',
    'debug'      => true,
];
```

- [ ] **Step 5: Copy to `config/config.php`**

Same content as `config.example.php`. This file is gitignored.

- [ ] **Step 6: Write stub `public/index.php`**

```php
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

echo "Helppy.com bootstrapped. " . date('c');
```

- [ ] **Step 7: Write root `index.php` fallback**

```php
<?php
require __DIR__ . '/public/index.php';
```

- [ ] **Step 8: Verify Laragon serves the page**

Open `http://localhost/Helppy.com/public/` in browser.
Expected: text "Helppy.com bootstrapped. 2026-..." with current ISO timestamp.

Also open `http://localhost/Helppy.com/` (root) — same text.

- [ ] **Step 9: Commit**

```bash
git add public/ config/config.example.php index.php
git commit -m "chore: project scaffolding and bootstrap"
```

Note: `config/config.php` is gitignored — it stays local.

---

## Task 2: Core - DB connection (PDO singleton)

**Files:**
- Create: `app/core/DB.php`

- [ ] **Step 1: Write `app/core/DB.php`**

```php
<?php
declare(strict_types=1);

final class DB {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $c = CONFIG['db'];
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }
}
```

- [ ] **Step 2: Smoke test in `public/index.php` temporarily**

Add this line above the `echo` in `public/index.php`:
```php
require APP_ROOT . '/app/core/DB.php';
```

Then replace the echo with:
```php
try {
    $info = DB::pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "MySQL OK: $info";
} catch (Throwable $e) {
    echo "DB error: " . $e->getMessage();
}
```

Refresh `http://localhost/Helppy.com/public/`. Expected: "MySQL OK: 8.x.x" (or whatever Laragon's MySQL version is).

**If you get "Unknown database 'helppy'":** that's expected — we create it in Task 6. Skip ahead to Task 6, then come back. To make this step pass standalone, run in phpMyAdmin: `CREATE DATABASE helppy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

- [ ] **Step 3: Revert the smoke-test edits to `public/index.php`**

Restore the original `echo "Helppy.com bootstrapped..."`.

- [ ] **Step 4: Commit**

```bash
git add app/core/DB.php
git commit -m "feat: PDO database singleton"
```

---

## Task 3: Core - View renderer

**Files:**
- Create: `app/core/View.php`

- [ ] **Step 1: Write `app/core/View.php`**

```php
<?php
declare(strict_types=1);

final class View {
    /** Render a view inside layout.php. $template like 'home/index'. */
    public static function render(string $template, array $data = [], string $layout = 'layout'): void {
        $viewFile = APP_ROOT . "/app/views/{$template}.php";
        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: $template");
        }
        // Render inner template into $content
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Wrap in layout
        require APP_ROOT . "/app/views/{$layout}.php";
    }

    /** Render a partial directly with shared data (no layout). */
    public static function partial(string $name, array $data = []): void {
        extract($data, EXTR_SKIP);
        require APP_ROOT . "/app/views/partials/{$name}.php";
    }
}

/** Escape for HTML output. Used in every view. */
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
```

- [ ] **Step 2: Commit**

```bash
git add app/core/View.php
git commit -m "feat: view renderer with layout and partial helpers"
```

---

## Task 4: Core - Request + CSRF + Auth

**Files:**
- Create: `app/core/Request.php`
- Create: `app/core/Auth.php`

- [ ] **Step 1: Write `app/core/Request.php`**

```php
<?php
declare(strict_types=1);

final class Request {
    public static function method(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    public static function isPost(): bool {
        return self::method() === 'POST';
    }
    public static function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    public static function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }
    public static function file(string $key): ?array {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE
            ? $_FILES[$key] : null;
    }

    /** Generate (once per session) and return the CSRF token. */
    public static function csrfToken(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Verify the _csrf POST field matches the session token. Dies on mismatch. */
    public static function verifyCsrf(): void {
        $supplied = $_POST['_csrf'] ?? '';
        $expected = $_SESSION['_csrf'] ?? '';
        if (!is_string($supplied) || !is_string($expected) || $expected === '' || !hash_equals($expected, $supplied)) {
            http_response_code(419);
            die('CSRF token mismatch');
        }
    }
}
```

- [ ] **Step 2: Write `app/core/Auth.php`**

```php
<?php
declare(strict_types=1);

final class Auth {
    public static function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['uid']  = (int)$user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool {
        return !empty($_SESSION['uid']);
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        // Lazy-load from DB on first call per request
        static $cached = null;
        if ($cached === null) {
            $st = DB::q('SELECT id,name,email,phone,role,city_id,is_active FROM users WHERE id=?',
                       [$_SESSION['uid']]);
            $cached = $st->fetch() ?: null;
        }
        return $cached;
    }

    public static function role(): ?string {
        return $_SESSION['role'] ?? null;
    }

    /** Require login + optional role. Sends 403/redirect and exits if not allowed. */
    public static function require(?string $role = null): void {
        if (!self::check()) {
            header('Location: ' . CONFIG['base_url'] . '/login');
            exit;
        }
        if ($role !== null && self::role() !== $role) {
            http_response_code(403);
            View::render('errors/403', []);
            exit;
        }
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/core/Request.php app/core/Auth.php
git commit -m "feat: request, csrf, and auth core helpers"
```

---

## Task 5: Core - Controller + Router

**Files:**
- Create: `app/core/Controller.php`
- Create: `app/core/Router.php`

- [ ] **Step 1: Write `app/core/Controller.php`**

```php
<?php
declare(strict_types=1);

abstract class Controller {
    protected function render(string $template, array $data = []): void {
        $data['__flash'] = self::pullFlash();
        View::render($template, $data);
    }

    protected function redirect(string $path): void {
        header('Location: ' . CONFIG['base_url'] . $path);
        exit;
    }

    protected function flash(string $type, string $msg): void {
        $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function pullFlash(): array {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    protected function notFound(): void {
        http_response_code(404);
        View::render('errors/404', []);
        exit;
    }
}
```

- [ ] **Step 2: Write `app/core/Router.php`**

```php
<?php
declare(strict_types=1);

final class Router {
    /** @var array<int, array{method:string,pattern:string,handler:array}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void {
        $this->routes[] = ['method' => 'GET',  'pattern' => $pattern, 'handler' => $handler];
    }
    public function post(string $pattern, array $handler): void {
        $this->routes[] = ['method' => 'POST', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $url): void {
        $url = '/' . trim($url, '/');
        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            $regex = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $r['pattern']);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $url, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                [$ctrl, $action] = $r['handler'];
                if ($method === 'POST') Request::verifyCsrf();
                $obj = new $ctrl();
                $obj->$action($params);
                return;
            }
        }
        http_response_code(404);
        View::render('errors/404', []);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/core/Controller.php app/core/Router.php
git commit -m "feat: router and base controller"
```

---

## Task 6: Database schema + seed

**Files:**
- Create: `db/schema.sql`
- Create: `db/seed.sql`

- [ ] **Step 1: Write `db/schema.sql`**

```sql
-- Helppy.com schema. Run on a fresh `helppy` database.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS provider_categories;
DROP TABLE IF EXISTS providers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS cities;

CREATE TABLE cities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  icon VARCHAR(80) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NULL,
  role ENUM('client','provider','admin') NOT NULL,
  city_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE providers (
  user_id INT PRIMARY KEY,
  profession VARCHAR(120) NOT NULL,
  bio TEXT NULL,
  photo VARCHAR(255) NULL,
  is_company TINYINT(1) NOT NULL DEFAULT 0,
  company_name VARCHAR(160) NULL,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  views INT NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_categories (
  provider_id INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (provider_id, category_id),
  FOREIGN KEY (provider_id) REFERENCES providers(user_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider_id INT NOT NULL,
  client_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_provider_client (provider_id, client_id),
  FOREIGN KEY (provider_id) REFERENCES providers(user_id) ON DELETE CASCADE,
  FOREIGN KEY (client_id)   REFERENCES users(id)         ON DELETE CASCADE,
  CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 2: Write `db/seed.sql`**

Sample passwords used by seed:
- Admin: `admin@helppy.com` / `admin123`
- Sample providers: `provider1@helppy.com` ... `provider6@helppy.com` / `password`
- Sample client: `client@helppy.com` / `password`

Pre-computed bcrypt hashes for these passwords (PASSWORD_DEFAULT, cost 10) — engineer can regenerate via `php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"` if they want fresh hashes:

```sql
SET NAMES utf8mb4;

-- Cities
INSERT INTO cities (name) VALUES
  ('Prishtine'),('Prizren'),('Peje'),('Gjakove'),('Mitrovice'),
  ('Gjilan'),('Ferizaj'),('Vushtrri'),('Suhareke'),('Rahovec'),
  ('Malisheve'),('Drenas'),('Skenderaj'),('Podujeve'),('Lipjan'),
  ('Fushe Kosove'),('Obiliq'),('Kamenice'),('Decan'),('Istog');

-- Categories (icon = bootstrap-icons class)
INSERT INTO categories (name, slug, icon) VALUES
  ('Hidraulike','hidraulike','bi-droplet-half'),
  ('Boje','boje','bi-brush'),
  ('Elektrike','elektrike','bi-lightning-charge'),
  ('Pastrim','pastrim','bi-bucket'),
  ('Stollari','stollari','bi-hammer'),
  ('Murature','murature','bi-bricks'),
  ('Lendine','lendine','bi-tree');

-- Admin (password: admin123)
INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES
  ('Administrator', 'admin@helppy.com',
   '$2y$10$YqVUUuPGv4l7Q0WGwQzC4eY0H8tFqLfX4o7tQfHk2hHsCnxV0Vu7K',
   '+38344000000', 'admin', 1);

-- Sample client (password: password)
INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES
  ('Test Klient', 'client@helppy.com',
   '$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK',
   '+38344111111', 'client', 1);

-- Sample providers (all password: password)
INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES
  ('Arben Krasniqi','provider1@helppy.com','$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK','+38344200001','provider',1),
  ('Bekim Hoxha',   'provider2@helppy.com','$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK','+38344200002','provider',2),
  ('Driton Berisha','provider3@helppy.com','$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK','+38344200003','provider',1),
  ('Egzon Gashi',   'provider4@helppy.com','$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK','+38344200004','provider',3),
  ('Florent Rama',  'provider5@helppy.com','$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK','+38344200005','provider',1),
  ('Granit Lleshi', 'provider6@helppy.com','$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK','+38344200006','provider',4);

-- Provider profiles (user_id assumed sequential starting from 3 for providers)
-- Admin=1, Client=2, then providers 3..8
INSERT INTO providers (user_id, profession, bio, is_company, is_premium) VALUES
  (3, 'Hidraulik', 'Punime profesionale hidraulike, 10+ vite eksperience.', 0, 1),
  (4, 'Elektricist', 'Instalime dhe riparime elektrike per shtepi dhe biznese.', 0, 0),
  (5, 'Bojaxhi', 'Lyerje brendshme dhe te jashtme, cilesi e larte.', 0, 0),
  (6, 'Marangoz', 'Dyer, dritare, mobilje me porosi.', 0, 0),
  (7, 'Murator', 'Ndertim dhe rinovim. Pjese e nje kompanie te vogel.', 1, 1),
  (8, 'Pastrues', 'Pastrim profesional i shtepive dhe zyrave.', 0, 0);

UPDATE providers SET company_name='Lleshi Construction' WHERE user_id=7;

-- Provider -> category mappings
INSERT INTO provider_categories (provider_id, category_id) VALUES
  (3,1),  -- Arben: Hidraulike
  (4,3),  -- Bekim: Elektrike
  (5,2),  -- Driton: Boje
  (6,5),  -- Egzon: Stollari
  (7,6),  -- Florent: Murature
  (7,5),  -- Florent also Stollari
  (8,4);  -- Granit: Pastrim

-- A sample review so the home page has stars
INSERT INTO reviews (provider_id, client_id, rating, comment) VALUES
  (3, 2, 5, 'Punoi shpejt dhe me cilesi. Rekomandoj.'),
  (4, 2, 4, 'Pune e mire, pak vonese.');
```

- [ ] **Step 3: Run the schema and seed**

Open Laragon → MySQL → phpMyAdmin (or run `mysql` directly):

```sql
CREATE DATABASE IF NOT EXISTS helppy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE helppy;
SOURCE C:/laragon/www/Helppy.com/db/schema.sql;
SOURCE C:/laragon/www/Helppy.com/db/seed.sql;
```

(In phpMyAdmin, just paste schema.sql contents, run, then paste seed.sql, run.)

- [ ] **Step 4: Verify the seed worked**

In phpMyAdmin, run:
```sql
SELECT COUNT(*) FROM cities;       -- 20
SELECT COUNT(*) FROM categories;   -- 7
SELECT COUNT(*) FROM users;        -- 8
SELECT COUNT(*) FROM providers;    -- 6
SELECT COUNT(*) FROM reviews;      -- 2
```

If any row counts differ, fix the SQL before continuing.

- [ ] **Step 5: Verify password hashes work**

Important — the hashes above must match the documented passwords. Quick check via Laragon's PHP CLI:

```bash
php -r "var_dump(password_verify('admin123', '$2y$10$YqVUUuPGv4l7Q0WGwQzC4eY0H8tFqLfX4o7tQfHk2hHsCnxV0Vu7K'));"
php -r "var_dump(password_verify('password', '$2y$10$LqaIH9aYxQy1g4XEW6f5oeu1AaO/lU6L3F.lKL.UpwzWcFFzG2.kK'));"
```

Both must print `bool(true)`. If either prints `bool(false)`, regenerate the hash and update seed.sql:

```bash
php -r "echo password_hash('admin123', PASSWORD_DEFAULT) . PHP_EOL;"
php -r "echo password_hash('password', PASSWORD_DEFAULT) . PHP_EOL;"
```

Then UPDATE the affected rows in MySQL (or re-run seed.sql after `DELETE FROM users WHERE id>1;`).

- [ ] **Step 6: Commit**

```bash
git add db/
git commit -m "feat: database schema and seed data"
```

---

## Task 7: Wire up index.php with routes + Home stub

**Files:**
- Modify: `public/index.php`
- Create: `app/controllers/HomeController.php`
- Create: `app/views/errors/404.php`
- Create: `app/views/errors/403.php`

- [ ] **Step 1: Rewrite `public/index.php`**

```php
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
```

- [ ] **Step 2: Write `app/controllers/HomeController.php` stub**

```php
<?php
declare(strict_types=1);

final class HomeController extends Controller {
    public function index(array $params = []): void {
        echo "Home route works.";
    }
}
```

- [ ] **Step 3: Create stub error views**

`app/views/errors/404.php`:
```php
<?php $title = 'Nuk u gjet'; ?>
<div class="container py-5 text-center">
  <h1>404</h1>
  <p>Faqja nuk u gjet.</p>
  <a href="<?= e(CONFIG['base_url']) ?>/" class="btn btn-success">Kthehu ne ballina</a>
</div>
```

`app/views/errors/403.php`:
```php
<?php $title = 'I ndaluar'; ?>
<div class="container py-5 text-center">
  <h1>403</h1>
  <p>Nuk keni leje per kete faqe.</p>
  <a href="<?= e(CONFIG['base_url']) ?>/" class="btn btn-success">Kthehu ne ballina</a>
</div>
```

Both rely on `layout.php` (created in Task 8). For now, since layout doesn't exist, the View renderer would 500. That's fine — we'll see them work after Task 8.

- [ ] **Step 4: Create stub controller files so autoload doesn't error**

Create these files (each one is just an empty class stub — will be filled in later tasks). This prevents autoload errors when the routes reference classes that don't exist yet.

`app/controllers/SearchController.php`:
```php
<?php
final class SearchController extends Controller {
    public function results(array $params = []): void { echo 'TODO'; }
}
```

`app/controllers/ProviderController.php`:
```php
<?php
final class ProviderController extends Controller {
    public function show(array $params = []): void       { echo 'TODO show'; }
    public function dashboard(array $params = []): void  { echo 'TODO dash'; }
    public function update(array $params = []): void     { echo 'TODO update'; }
    public function uploadPhoto(array $params = []): void { echo 'TODO upload'; }
}
```

`app/controllers/AuthController.php`:
```php
<?php
final class AuthController extends Controller {
    public function loginForm(array $params = []): void    { echo 'TODO loginForm'; }
    public function login(array $params = []): void        { echo 'TODO login'; }
    public function registerForm(array $params = []): void { echo 'TODO regForm'; }
    public function register(array $params = []): void     { echo 'TODO reg'; }
    public function logout(array $params = []): void       { echo 'TODO logout'; }
}
```

`app/controllers/ClientController.php`:
```php
<?php
final class ClientController extends Controller {
    public function dashboard(array $params = []): void { echo 'TODO'; }
}
```

`app/controllers/ReviewController.php`:
```php
<?php
final class ReviewController extends Controller {
    public function store(array $params = []): void   { echo 'TODO'; }
    public function destroy(array $params = []): void { echo 'TODO'; }
}
```

`app/controllers/AdminController.php`:
```php
<?php
final class AdminController extends Controller {
    public function index(array $params = []): void          { echo 'TODO'; }
    public function providers(array $params = []): void      { echo 'TODO'; }
    public function toggleActive(array $params = []): void   { echo 'TODO'; }
    public function togglePremium(array $params = []): void  { echo 'TODO'; }
    public function categories(array $params = []): void     { echo 'TODO'; }
    public function createCategory(array $params = []): void { echo 'TODO'; }
    public function deleteCategory(array $params = []): void { echo 'TODO'; }
    public function deleteReview(array $params = []): void   { echo 'TODO'; }
}
```

- [ ] **Step 5: Verify routing works**

Visit each URL in browser:
- `http://localhost/Helppy.com/public/` → "Home route works."
- `http://localhost/Helppy.com/public/login` → "TODO loginForm"
- `http://localhost/Helppy.com/public/provider/3` → "TODO show"
- `http://localhost/Helppy.com/public/provider/dashboard` → "TODO dash"
- `http://localhost/Helppy.com/public/this-does-not-exist` → 404 page (will look ugly since layout missing — that's OK)

- [ ] **Step 6: Commit**

```bash
git add public/index.php app/controllers/ app/views/errors/
git commit -m "feat: front-controller routing + controller stubs"
```

---

## Task 8: Layout + Bootstrap + brand assets + nav

**Files:**
- Create: `app/views/layout.php`
- Create: `app/views/partials/nav.php`
- Create: `app/views/partials/footer.php`
- Create: `app/views/partials/flash.php`
- Create: `public/assets/css/style.css`
- Create: `public/assets/img/logo.svg`
- Create: `public/assets/img/default-avatar.svg`

- [ ] **Step 1: Write `app/views/layout.php`**

```php
<!DOCTYPE html>
<html lang="sq">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Helppy.com') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= e(CONFIG['base_url']) ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php View::partial('nav'); ?>
<?php View::partial('flash', ['flash' => $__flash ?? []]); ?>
<main>
  <?= $content ?>
</main>
<?php View::partial('footer'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

- [ ] **Step 2: Write `app/views/partials/nav.php`**

```php
<nav class="navbar navbar-expand-lg helppy-nav">
  <div class="container-fluid">
    <a class="navbar-brand text-white d-flex align-items-center" href="<?= e(CONFIG['base_url']) ?>/">
      <img src="<?= e(CONFIG['base_url']) ?>/assets/img/logo.svg" alt="Helppy" height="32" class="me-2">
      <span class="fw-bold">Helppy</span>
    </a>
    <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navmenu">
      <ul class="navbar-nav">
        <?php if (Auth::check()): ?>
          <?php $u = Auth::user(); ?>
          <li class="nav-item"><span class="nav-link text-white-50"><?= e($u['name']) ?></span></li>
          <?php if (Auth::role() === 'admin'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/admin">Admin</a></li>
          <?php elseif (Auth::role() === 'provider'): ?>
            <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/provider/dashboard">Profili im</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/client/dashboard">Llogaria ime</a></li>
          <?php endif; ?>
          <li class="nav-item">
            <form method="post" action="<?= e(CONFIG['base_url']) ?>/logout" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
              <button class="btn btn-link nav-link text-white" type="submit">Dilni</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/login">Hyrje</a></li>
          <li class="nav-item"><a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/register">Regjistrohu</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
```

- [ ] **Step 3: Write `app/views/partials/footer.php`**

```php
<footer class="helppy-footer mt-5">
  <div class="container text-center py-4 small text-white-50">
    &copy; <?= date('Y') ?> Helppy.com &middot; Kosove
  </div>
</footer>
```

- [ ] **Step 4: Write `app/views/partials/flash.php`**

```php
<?php if (!empty($flash)): ?>
  <div class="container mt-3">
    <?php foreach ($flash as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
```

- [ ] **Step 5: Write `public/assets/css/style.css`**

```css
:root {
  --helppy-green: #4a6741;
  --helppy-green-dark: #3a5234;
  --helppy-green-light: #6b8a61;
  --helppy-bg: #f3f4f6;
}
body { background: var(--helppy-bg); }
.helppy-nav { background: var(--helppy-green); }
.helppy-footer { background: var(--helppy-green-dark); color: white; }

.btn-helppy { background: var(--helppy-green); color: white; border: none; }
.btn-helppy:hover { background: var(--helppy-green-dark); color: white; }

.category-chip {
  display: inline-block; padding: 8px 16px; border-radius: 20px;
  background: #d1d5db; color: #111; text-decoration: none;
  margin: 4px; font-size: 14px; transition: background .15s;
}
.category-chip:hover { background: #b3b9c2; color: #111; }
.category-chip.active { background: var(--helppy-green); color: white; }

.provider-card {
  background: white; border-radius: 12px; padding: 16px;
  display: flex; gap: 12px; align-items: center; margin-bottom: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.provider-card .avatar { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background: #e5e7eb; }
.provider-card .info { flex: 1; min-width: 0; }
.provider-card .name { font-weight: 600; margin: 0; }
.provider-card .profession { color: #6b7280; font-size: 13px; margin: 0; }
.provider-card .stars { color: #f59e0b; font-size: 14px; }
.provider-card .phone { color: #6b7280; font-size: 13px; }
.provider-card .call-btn { background: var(--helppy-green); color: white; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; white-space: nowrap; }
.provider-card .call-btn:hover { background: var(--helppy-green-dark); color: white; }
.provider-card .premium-badge { background: #f59e0b; color: white; font-size: 10px; padding: 2px 8px; border-radius: 4px; margin-left: 6px; }

.hero { background: var(--helppy-bg); padding: 24px 0; }
.hero h1 { font-weight: 700; }

.profile-photo { width: 160px; height: 160px; border-radius: 50%; object-fit: cover; background: #e5e7eb; }

.review-card { background: white; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
.review-card .stars { color: #f59e0b; }
.review-card .meta { color: #6b7280; font-size: 12px; }
```

- [ ] **Step 6: Write `public/assets/img/logo.svg`**

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
  <rect x="2" y="2" width="60" height="60" rx="14" fill="#4a6741"/>
  <path d="M44 18a8 8 0 0 0-10.7 10.6L18 43.9a3 3 0 1 0 4.2 4.2l15.3-15.3A8 8 0 0 0 48 22a8 8 0 0 0-4-3.7Zm-3 8.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z" fill="#fff"/>
</svg>
```

- [ ] **Step 7: Write `public/assets/img/default-avatar.svg`**

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
  <circle cx="32" cy="32" r="32" fill="#e5e7eb"/>
  <circle cx="32" cy="26" r="11" fill="#9ca3af"/>
  <path d="M12 56a20 20 0 0 1 40 0v8H12z" fill="#9ca3af"/>
</svg>
```

- [ ] **Step 8: Update HomeController stub to use layout**

```php
<?php
declare(strict_types=1);

final class HomeController extends Controller {
    public function index(array $params = []): void {
        $this->render('home/index', ['title' => 'Helppy.com - Punues per shtepi']);
    }
}
```

Create temp `app/views/home/index.php`:
```php
<div class="container py-4">
  <h1>Helppy.com</h1>
  <p>Layout works.</p>
</div>
```

- [ ] **Step 9: Verify layout renders**

Open `http://localhost/Helppy.com/public/`.
Expected:
- Green navbar with Helppy logo and "Hyrje" / "Regjistrohu" links.
- "Helppy.com" heading with "Layout works." text.
- Green footer at bottom.

Open `http://localhost/Helppy.com/public/not-a-page` — should see styled 404 page.

- [ ] **Step 10: Commit**

```bash
git add app/views/ public/assets/ app/controllers/HomeController.php
git commit -m "feat: layout, brand assets, navbar, footer"
```

---

## Task 9: Models (City, Category, Provider, User, Review)

**Files:**
- Create: `app/models/City.php`
- Create: `app/models/Category.php`
- Create: `app/models/Provider.php`
- Create: `app/models/User.php`
- Create: `app/models/Review.php`

- [ ] **Step 1: Write `app/models/City.php`**

```php
<?php
declare(strict_types=1);

final class City {
    public static function all(): array {
        return DB::q('SELECT id, name FROM cities ORDER BY name')->fetchAll();
    }
    public static function find(int $id): ?array {
        $r = DB::q('SELECT id, name FROM cities WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }
}
```

- [ ] **Step 2: Write `app/models/Category.php`**

```php
<?php
declare(strict_types=1);

final class Category {
    public static function all(): array {
        return DB::q('SELECT id, name, slug, icon FROM categories ORDER BY name')->fetchAll();
    }
    public static function find(int $id): ?array {
        $r = DB::q('SELECT id, name, slug, icon FROM categories WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }
    public static function findBySlug(string $slug): ?array {
        $r = DB::q('SELECT id, name, slug, icon FROM categories WHERE slug=?', [$slug])->fetch();
        return $r ?: null;
    }
    public static function create(string $name, string $slug, ?string $icon): int {
        DB::q('INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)', [$name, $slug, $icon]);
        return (int)DB::pdo()->lastInsertId();
    }
    public static function delete(int $id): void {
        DB::q('DELETE FROM categories WHERE id=?', [$id]);
    }
    public static function hasProviders(int $id): bool {
        $r = DB::q('SELECT 1 FROM provider_categories WHERE category_id=? LIMIT 1', [$id])->fetch();
        return (bool)$r;
    }
}
```

- [ ] **Step 3: Write `app/models/Provider.php`**

```php
<?php
declare(strict_types=1);

final class Provider {
    /** Find a provider with joined user + city info. */
    public static function find(int $userId): ?array {
        $sql = "SELECT u.id, u.name, u.email, u.phone, u.is_active, u.created_at,
                       c.name AS city,
                       p.profession, p.bio, p.photo, p.is_company, p.company_name,
                       p.is_premium, p.views
                FROM providers p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN cities c ON c.id = u.city_id
                WHERE p.user_id = ?";
        $r = DB::q($sql, [$userId])->fetch();
        if (!$r) return null;
        $r['categories'] = self::categories($userId);
        $r['avg_rating'] = self::avgRating($userId);
        $r['review_count'] = self::reviewCount($userId);
        return $r;
    }

    /** Search by city_id and category_id (either may be null). */
    public static function search(?int $cityId, ?int $categoryId): array {
        $sql = "SELECT u.id, u.name, u.phone, c.name AS city,
                       p.profession, p.photo, p.is_company, p.company_name,
                       p.is_premium,
                       (SELECT AVG(rating) FROM reviews WHERE provider_id = p.user_id) AS avg_rating,
                       (SELECT COUNT(*)   FROM reviews WHERE provider_id = p.user_id) AS review_count
                FROM providers p
                JOIN users u ON u.id = p.user_id AND u.is_active = 1
                LEFT JOIN cities c ON c.id = u.city_id
                WHERE 1=1 ";
        $args = [];
        if ($cityId !== null)     { $sql .= " AND u.city_id = ?";  $args[] = $cityId; }
        if ($categoryId !== null) { $sql .= " AND EXISTS (SELECT 1 FROM provider_categories pc WHERE pc.provider_id = p.user_id AND pc.category_id = ?)"; $args[] = $categoryId; }
        $sql .= " ORDER BY p.is_premium DESC, u.created_at DESC";
        return DB::q($sql, $args)->fetchAll();
    }

    /** Featured strip for home page. */
    public static function featured(int $limit = 8): array {
        $sql = "SELECT u.id, u.name, u.phone, c.name AS city,
                       p.profession, p.photo, p.is_company, p.is_premium,
                       (SELECT AVG(rating) FROM reviews WHERE provider_id = p.user_id) AS avg_rating
                FROM providers p
                JOIN users u ON u.id = p.user_id AND u.is_active = 1
                LEFT JOIN cities c ON c.id = u.city_id
                ORDER BY p.is_premium DESC, RAND()
                LIMIT $limit";
        return DB::q($sql)->fetchAll();
    }

    public static function categories(int $userId): array {
        return DB::q("SELECT c.id, c.name, c.slug FROM provider_categories pc
                      JOIN categories c ON c.id = pc.category_id
                      WHERE pc.provider_id = ?", [$userId])->fetchAll();
    }

    public static function setCategories(int $userId, array $categoryIds): void {
        DB::q('DELETE FROM provider_categories WHERE provider_id = ?', [$userId]);
        $st = DB::pdo()->prepare('INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)');
        foreach ($categoryIds as $cid) {
            $st->execute([$userId, (int)$cid]);
        }
    }

    public static function avgRating(int $userId): ?float {
        $r = DB::q('SELECT AVG(rating) AS a FROM reviews WHERE provider_id=?', [$userId])->fetch();
        return $r && $r['a'] !== null ? (float)$r['a'] : null;
    }

    public static function reviewCount(int $userId): int {
        return (int)DB::q('SELECT COUNT(*) FROM reviews WHERE provider_id=?', [$userId])->fetchColumn();
    }

    public static function create(int $userId, string $profession, bool $isCompany, ?string $companyName): void {
        DB::q('INSERT INTO providers (user_id, profession, is_company, company_name) VALUES (?, ?, ?, ?)',
              [$userId, $profession, $isCompany ? 1 : 0, $companyName]);
    }

    public static function update(int $userId, array $fields): void {
        $allowed = ['profession','bio','company_name'];
        $sets = []; $args = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) { $sets[] = "$k = ?"; $args[] = $v; }
        }
        if (!$sets) return;
        $args[] = $userId;
        DB::q('UPDATE providers SET ' . implode(', ', $sets) . ' WHERE user_id = ?', $args);
    }

    public static function setPhoto(int $userId, string $filename): void {
        DB::q('UPDATE providers SET photo=? WHERE user_id=?', [$filename, $userId]);
    }

    public static function incrementViews(int $userId): void {
        DB::q('UPDATE providers SET views = views + 1 WHERE user_id = ?', [$userId]);
    }

    public static function togglePremium(int $userId): void {
        DB::q('UPDATE providers SET is_premium = 1 - is_premium WHERE user_id = ?', [$userId]);
    }

    public static function allWithStatus(): array {
        return DB::q("SELECT u.id, u.name, u.email, u.is_active, u.created_at,
                             p.profession, p.is_premium
                      FROM providers p
                      JOIN users u ON u.id = p.user_id
                      ORDER BY u.created_at DESC")->fetchAll();
    }
}
```

- [ ] **Step 4: Write `app/models/User.php`**

```php
<?php
declare(strict_types=1);

final class User {
    public static function find(int $id): ?array {
        $r = DB::q('SELECT * FROM users WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }
    public static function findByEmail(string $email): ?array {
        $r = DB::q('SELECT * FROM users WHERE email=?', [$email])->fetch();
        return $r ?: null;
    }
    public static function emailExists(string $email): bool {
        return (bool)DB::q('SELECT 1 FROM users WHERE email=? LIMIT 1', [$email])->fetch();
    }
    public static function create(string $name, string $email, string $passwordHash, ?string $phone, string $role, ?int $cityId): int {
        DB::q('INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES (?, ?, ?, ?, ?, ?)',
              [$name, $email, $passwordHash, $phone, $role, $cityId]);
        return (int)DB::pdo()->lastInsertId();
    }
    public static function toggleActive(int $id): void {
        DB::q('UPDATE users SET is_active = 1 - is_active WHERE id=?', [$id]);
    }
    public static function counts(): array {
        return [
            'users'     => (int)DB::q('SELECT COUNT(*) FROM users')->fetchColumn(),
            'providers' => (int)DB::q('SELECT COUNT(*) FROM users WHERE role="provider"')->fetchColumn(),
            'clients'   => (int)DB::q('SELECT COUNT(*) FROM users WHERE role="client"')->fetchColumn(),
            'reviews'   => (int)DB::q('SELECT COUNT(*) FROM reviews')->fetchColumn(),
        ];
    }
}
```

- [ ] **Step 5: Write `app/models/Review.php`**

```php
<?php
declare(strict_types=1);

final class Review {
    public static function find(int $id): ?array {
        $r = DB::q('SELECT * FROM reviews WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }

    public static function forProvider(int $providerId): array {
        return DB::q(
            "SELECT r.id, r.rating, r.comment, r.created_at, r.client_id,
                    u.name AS client_name
             FROM reviews r
             JOIN users u ON u.id = r.client_id
             WHERE r.provider_id = ?
             ORDER BY r.created_at DESC",
            [$providerId]
        )->fetchAll();
    }

    public static function byClient(int $clientId): array {
        return DB::q(
            "SELECT r.id, r.rating, r.comment, r.created_at, r.provider_id,
                    u.name AS provider_name
             FROM reviews r
             JOIN users u ON u.id = r.provider_id
             WHERE r.client_id = ?
             ORDER BY r.created_at DESC",
            [$clientId]
        )->fetchAll();
    }

    public static function existsFor(int $providerId, int $clientId): bool {
        return (bool)DB::q('SELECT 1 FROM reviews WHERE provider_id=? AND client_id=? LIMIT 1',
                           [$providerId, $clientId])->fetch();
    }

    public static function create(int $providerId, int $clientId, int $rating, ?string $comment): void {
        DB::q('INSERT INTO reviews (provider_id, client_id, rating, comment) VALUES (?, ?, ?, ?)',
              [$providerId, $clientId, $rating, $comment]);
    }

    public static function delete(int $id): void {
        DB::q('DELETE FROM reviews WHERE id=?', [$id]);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/models/
git commit -m "feat: data models for cities, categories, providers, users, reviews"
```

---

## Task 10: Home page (full implementation)

**Files:**
- Modify: `app/controllers/HomeController.php`
- Modify: `app/views/home/index.php`
- Create: `app/views/partials/provider-card.php`

- [ ] **Step 1: Update `HomeController.php`**

```php
<?php
declare(strict_types=1);

final class HomeController extends Controller {
    public function index(array $params = []): void {
        $this->render('home/index', [
            'title'      => 'Helppy.com',
            'cities'     => City::all(),
            'categories' => Category::all(),
            'featured'   => Provider::featured(8),
        ]);
    }
}
```

- [ ] **Step 2: Write `app/views/partials/provider-card.php`**

```php
<?php
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : CONFIG['base_url'] . '/assets/img/default-avatar.svg';
$avg = isset($p['avg_rating']) && $p['avg_rating'] !== null ? round((float)$p['avg_rating'], 1) : null;
?>
<div class="provider-card">
  <img class="avatar" src="<?= e($photoUrl) ?>" alt="<?= e($p['name']) ?>">
  <div class="info">
    <p class="name">
      <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>" class="text-decoration-none text-dark">
        <?= e($p['name']) ?>
      </a>
      <?php if (!empty($p['is_premium'])): ?><span class="premium-badge">PREMIUM</span><?php endif; ?>
    </p>
    <p class="profession"><?= e($p['profession']) ?><?php if (!empty($p['city'])): ?> &middot; <?= e($p['city']) ?><?php endif; ?></p>
    <p class="stars">
      <?php if ($avg !== null): ?>
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="bi <?= $i <= round($avg) ? 'bi-star-fill' : 'bi-star' ?>"></i>
        <?php endfor; ?>
        <span class="meta ms-1"><?= e((string)$avg) ?></span>
      <?php else: ?>
        <span class="meta">Pa vleresime</span>
      <?php endif; ?>
    </p>
    <?php if (!empty($p['phone'])): ?>
      <p class="phone"><i class="bi bi-telephone"></i> <?= e($p['phone']) ?></p>
    <?php endif; ?>
  </div>
  <?php if (!empty($p['phone'])): ?>
    <a class="call-btn" href="tel:<?= e(preg_replace('/[^0-9+]/','',$p['phone'])) ?>">Telefono Tani</a>
  <?php endif; ?>
</div>
```

- [ ] **Step 3: Write `app/views/home/index.php`**

```php
<section class="hero">
  <div class="container py-4">
    <h1 class="mb-3">Keni nevoje per nje punetor per problemin ne shtepi?</h1>

    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="row g-2 mb-3">
      <div class="col-md-8">
        <select name="city" class="form-select form-select-lg">
          <option value="">Shkruani lokacionin tuaj</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-grid">
        <button class="btn btn-helppy btn-lg" type="submit">
          <i class="bi bi-search"></i> Kerko
        </button>
      </div>
    </form>

    <div class="mb-2">
      <?php foreach ($categories as $cat): ?>
        <a class="category-chip"
           href="<?= e(CONFIG['base_url']) ?>/search?category=<?= (int)$cat['id'] ?>">
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container py-4">
  <h2 class="mb-3">Punonjesit me te afert</h2>
  <div class="row">
    <?php foreach ($featured as $p): ?>
      <div class="col-md-6 col-lg-6">
        <?php View::partial('provider-card', ['p' => $p]); ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$featured): ?>
      <p class="text-muted">Asnje punetor i regjistruar ende.</p>
    <?php endif; ?>
  </div>
</section>
```

- [ ] **Step 4: Verify home page**

Open `http://localhost/Helppy.com/public/`.
Expected:
- Heading "Keni nevoje per nje punetor per problemin ne shtepi?"
- City dropdown with 20 Kosovo cities, "Kerko" button.
- 7 category chips: Hidraulike, Boje, Elektrike, Pastrim, Stollari, Murature, Lendine.
- "Punonjesit me te afert" section showing 6 sample providers (premium first).
- Provider cards show name, profession, stars (with 2 of them having ratings), city, phone, "Telefono Tani" button.

- [ ] **Step 5: Commit**

```bash
git add app/controllers/HomeController.php app/views/home/ app/views/partials/provider-card.php
git commit -m "feat: home page with search, categories, featured providers"
```

---

## Task 11: Search results page

**Files:**
- Modify: `app/controllers/SearchController.php`
- Create: `app/views/search/results.php`

- [ ] **Step 1: Update `SearchController.php`**

```php
<?php
declare(strict_types=1);

final class SearchController extends Controller {
    public function results(array $params = []): void {
        $cityId     = Request::get('city');
        $categoryId = Request::get('category');
        $cityId     = is_numeric($cityId) ? (int)$cityId : null;
        $categoryId = is_numeric($categoryId) ? (int)$categoryId : null;

        $providers = Provider::search($cityId, $categoryId);
        $city      = $cityId     ? City::find($cityId)         : null;
        $category  = $categoryId ? Category::find($categoryId) : null;

        $this->render('search/results', [
            'title'      => 'Rezultatet',
            'providers'  => $providers,
            'city'       => $city,
            'category'   => $category,
            'cities'     => City::all(),
            'categories' => Category::all(),
        ]);
    }
}
```

- [ ] **Step 2: Write `app/views/search/results.php`**

```php
<section class="hero">
  <div class="container py-3">
    <form method="get" action="<?= e(CONFIG['base_url']) ?>/search" class="row g-2">
      <div class="col-md-5">
        <select name="city" class="form-select">
          <option value="">Te gjitha qytetet</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $city && $city['id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <select name="category" class="form-select">
          <option value="">Te gjitha kategorite</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $category && $category['id']==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-helppy" type="submit">Kerko</button>
      </div>
    </form>
  </div>
</section>

<section class="container py-4">
  <h2 class="mb-3">
    <?php if ($category): ?><?= e($category['name']) ?> <?php endif; ?>
    <?php if ($city): ?>ne <?= e($city['name']) ?><?php endif; ?>
    <small class="text-muted">(<?= count($providers) ?>)</small>
  </h2>

  <?php if (!$providers): ?>
    <div class="alert alert-light text-center">
      Asnje punetor i gjetur. Provoni te zgjeroni filterat.
    </div>
  <?php else: ?>
    <div class="row">
      <?php foreach ($providers as $p): ?>
        <div class="col-md-6"><?php View::partial('provider-card', ['p' => $p]); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
```

- [ ] **Step 3: Verify search**

- Open `http://localhost/Helppy.com/public/search?category=1` → see "Hidraulike (1)" and Arben Krasniqi card.
- Open `http://localhost/Helppy.com/public/search?city=1` → see all providers in Prishtine (4 of them).
- Open `http://localhost/Helppy.com/public/search?city=1&category=3` → see Bekim Hoxha? Actually he's in Prizren — should show 0 (or whoever is in Prishtine with category Elektrike). Check the seed mapping.
- Open `http://localhost/Helppy.com/public/search?city=99&category=99` → "Asnje punetor i gjetur".
- Open with no params → all providers.

- [ ] **Step 4: Commit**

```bash
git add app/controllers/SearchController.php app/views/search/
git commit -m "feat: search results with city + category filters"
```

---

## Task 12: Provider profile page (public)

**Files:**
- Modify: `app/controllers/ProviderController.php` (just the `show` method)
- Create: `app/views/provider/show.php`
- Create: `app/views/partials/review-card.php`

- [ ] **Step 1: Update `ProviderController.php` `show` method**

Replace the stub `show` method with:

```php
public function show(array $params = []): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) { $this->notFound(); return; }

    $provider = Provider::find($id);
    if (!$provider || empty($provider['is_active'])) { $this->notFound(); return; }

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
```

Leave the other stub methods as `echo 'TODO ...'` for now — they're filled in later tasks.

- [ ] **Step 2: Write `app/views/partials/review-card.php`**

```php
<div class="review-card">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <strong><?= e($r['client_name']) ?></strong>
      <span class="stars ms-2">
        <?php for ($i=1;$i<=5;$i++): ?>
          <i class="bi <?= $i <= (int)$r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
        <?php endfor; ?>
      </span>
    </div>
    <div class="meta"><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></div>
  </div>
  <?php if (!empty($r['comment'])): ?>
    <p class="mb-1 mt-1"><?= nl2br(e($r['comment'])) ?></p>
  <?php endif; ?>
  <?php if (Auth::check()): ?>
    <?php $uid = (int)Auth::user()['id']; ?>
    <?php if ($uid === (int)$r['client_id']): ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/review/<?= (int)$r['id'] ?>/delete" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button class="btn btn-sm btn-link text-danger p-0" type="submit"
                onclick="return confirm('Fshi vleresimin?');">Fshi</button>
      </form>
    <?php elseif (Auth::role() === 'admin'): ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/reviews/<?= (int)$r['id'] ?>/delete" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button class="btn btn-sm btn-link text-danger p-0" type="submit"
                onclick="return confirm('Fshi vleresimin si admin?');">Fshi (admin)</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
```

- [ ] **Step 3: Write `app/views/provider/show.php`**

```php
<?php
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : CONFIG['base_url'] . '/assets/img/default-avatar.svg';
$avg = $p['avg_rating'] !== null ? round((float)$p['avg_rating'], 1) : null;
?>
<div class="container py-4">
  <div class="row">
    <div class="col-md-4 text-center">
      <img class="profile-photo" src="<?= e($photoUrl) ?>" alt="<?= e($p['name']) ?>">
    </div>
    <div class="col-md-8">
      <h1 class="mb-1">
        <?= e($p['name']) ?>
        <?php if (!empty($p['is_premium'])): ?><span class="premium-badge">PREMIUM</span><?php endif; ?>
      </h1>
      <p class="text-muted mb-2">
        <?= e($p['profession']) ?>
        <?php if (!empty($p['is_company'])): ?>&middot; <i class="bi bi-building"></i> <?= e($p['company_name'] ?? '') ?><?php endif; ?>
        <?php if (!empty($p['city'])): ?>&middot; <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?><?php endif; ?>
      </p>
      <p class="stars mb-2">
        <?php if ($avg !== null): ?>
          <?php for ($i=1;$i<=5;$i++): ?>
            <i class="bi <?= $i <= round($avg) ? 'bi-star-fill' : 'bi-star' ?>"></i>
          <?php endfor; ?>
          <span class="ms-1 text-muted"><?= e((string)$avg) ?> &middot; <?= (int)$p['review_count'] ?> vleresime</span>
        <?php else: ?>
          <span class="text-muted">Pa vleresime</span>
        <?php endif; ?>
      </p>
      <div class="mb-3">
        <?php foreach ($p['categories'] as $cat): ?>
          <span class="category-chip"><?= e($cat['name']) ?></span>
        <?php endforeach; ?>
      </div>
      <?php if (!empty($p['bio'])): ?>
        <p class="mb-3"><?= nl2br(e($p['bio'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($p['phone'])): ?>
        <a class="btn btn-helppy btn-lg" href="tel:<?= e(preg_replace('/[^0-9+]/','',$p['phone'])) ?>">
          <i class="bi bi-telephone-fill"></i> Telefono Tani
        </a>
      <?php endif; ?>
    </div>
  </div>

  <hr class="my-4">

  <h3>Vleresime (<?= count($reviews) ?>)</h3>

  <?php if (Auth::check() && Auth::role() === 'client' && !$alreadyReviewed): ?>
    <form method="post" action="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>/review" class="mb-4 review-card">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
      <div class="mb-2">
        <label class="form-label">Vleresimi</label>
        <select name="rating" class="form-select w-auto d-inline-block">
          <?php for ($i=5;$i>=1;$i--): ?>
            <option value="<?= $i ?>"><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label">Komenti (opsional)</label>
        <textarea name="comment" class="form-control" rows="3" maxlength="2000"></textarea>
      </div>
      <button class="btn btn-helppy" type="submit">Ler vleresim</button>
    </form>
  <?php elseif (Auth::check() && Auth::role() === 'client' && $alreadyReviewed): ?>
    <div class="alert alert-secondary">Keni vleresuar tashme kete punetor.</div>
  <?php elseif (!Auth::check()): ?>
    <div class="alert alert-light">
      <a href="<?= e(CONFIG['base_url']) ?>/login">Hyni</a> ose
      <a href="<?= e(CONFIG['base_url']) ?>/register">regjistrohuni</a> per te lene nje vleresim.
    </div>
  <?php endif; ?>

  <?php foreach ($reviews as $r): ?>
    <?php View::partial('review-card', ['r' => $r]); ?>
  <?php endforeach; ?>
  <?php if (!$reviews): ?>
    <p class="text-muted">Asnje vleresim ende.</p>
  <?php endif; ?>
</div>
```

- [ ] **Step 3.5: Verify the route resolution order is correct**

Open in browser:
- `http://localhost/Helppy.com/public/provider/3` → see Arben Krasniqi's profile (with 1 review).
- `http://localhost/Helppy.com/public/provider/dashboard` → should still show "TODO dash" (route order matters: `dashboard` is registered before `{id}` in Task 7's index.php).
- `http://localhost/Helppy.com/public/provider/9999` → 404 page.

- [ ] **Step 4: Verify view counter increments**

Refresh `http://localhost/Helppy.com/public/provider/3` three times.
In phpMyAdmin: `SELECT views FROM providers WHERE user_id=3;` → should be 3 (or whatever count you refreshed).

- [ ] **Step 5: Commit**

```bash
git add app/controllers/ProviderController.php app/views/provider/show.php app/views/partials/review-card.php
git commit -m "feat: public provider profile page with reviews list and view counter"
```

---

## Task 13: Login / Logout

**Files:**
- Modify: `app/controllers/AuthController.php` (just login + logout methods)
- Create: `app/views/auth/login.php`

- [ ] **Step 1: Replace `loginForm`, `login`, `logout` methods in `AuthController.php`**

Keep the other stubs (`registerForm`, `register`) untouched for now.

```php
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
```

Note: `Auth::logout()` destroys the session — we restart it so the flash message survives the redirect.

- [ ] **Step 2: Write `app/views/auth/login.php`**

```php
<div class="container py-4" style="max-width: 480px;">
  <h2 class="mb-3">Hyrje</h2>
  <form method="post" action="<?= e(CONFIG['base_url']) ?>/login">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="email" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Fjalekalimi</label>
      <input class="form-control" type="password" name="password" required>
    </div>
    <button class="btn btn-helppy w-100" type="submit">Hyr</button>
  </form>
  <p class="text-center mt-3">
    Nuk keni llogari? <a href="<?= e(CONFIG['base_url']) ?>/register">Regjistrohu</a>
  </p>
</div>
```

- [ ] **Step 3: Verify login flow**

- Open `http://localhost/Helppy.com/public/login`.
- Enter `admin@helppy.com` / `admin123` → redirected to `/admin` (still shows "TODO" — that's fine for now). Nav shows "Administrator" + "Admin" + "Dilni".
- Click "Dilni" → redirected to home, flash "U larguat me sukses."
- Try wrong password → flash "Email ose fjalekalim i pasakte." stays on /login.
- Try `client@helppy.com` / `password` → redirected to `/`.
- Try `provider1@helppy.com` / `password` → redirected to `/provider/dashboard` (still "TODO").

- [ ] **Step 4: Commit**

```bash
git add app/controllers/AuthController.php app/views/auth/login.php
git commit -m "feat: login and logout"
```

---

## Task 14: Registration

**Files:**
- Modify: `app/controllers/AuthController.php` (registerForm + register)
- Create: `app/views/auth/register.php`

- [ ] **Step 1: Replace `registerForm` and `register` in `AuthController.php`**

```php
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
```

- [ ] **Step 2: Write `app/views/auth/register.php`**

```php
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
```

- [ ] **Step 3: Verify registration**

- Open `http://localhost/Helppy.com/public/register`.
- Default mode is "Klient". Fill out: Emri "Test Test", Email "newclient@test.com", password "secret1", phone, city. Submit → redirected to home, flash "Llogaria u krijua...". Nav shows "Test Test".
- Logout. Go back to /register. Switch to "Punues". The Profesioni and Kategorite fields appear. Fill in (email "newworker@test.com", password "secret1", profesioni "Bojaxhi", check Boje), submit → redirected to `/provider/dashboard` (still TODO).
- Logout. Try registering with the same email → flash "Ky email eshte i regjistruar tashme."
- Try password "abc" → flash about 6 characters.
- Try Kompani: select "Kompani", fill company name. Submit → registered as provider with is_company=1.

Verify in DB:
```sql
SELECT id, name, email, role FROM users WHERE id > 8;
SELECT user_id, profession, is_company, company_name FROM providers WHERE user_id > 8;
SELECT * FROM provider_categories WHERE provider_id > 8;
```

- [ ] **Step 4: Commit**

```bash
git add app/controllers/AuthController.php app/views/auth/register.php
git commit -m "feat: registration with client/provider/company roles"
```

---

## Task 15: Provider dashboard + edit profile

**Files:**
- Modify: `app/controllers/ProviderController.php` (dashboard + update)
- Create: `app/views/provider/dashboard.php`

- [ ] **Step 1: Replace `dashboard` and `update` methods in `ProviderController.php`**

```php
public function dashboard(array $params = []): void {
    Auth::require('provider');
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
```

- [ ] **Step 2: Write `app/views/provider/dashboard.php`**

```php
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

        <div class="mb-3"><label class="form-label">Bio</label>
          <textarea class="form-control" name="bio" rows="4" maxlength="2000"><?= e($p['bio'] ?? '') ?></textarea></div>

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
```

- [ ] **Step 3: Verify dashboard**

- Login as `provider1@helppy.com` / `password`.
- Redirected to `/provider/dashboard`.
- See profile photo (default avatar), stats card (rating 5.0, 1 review, some views), edit form pre-filled with current values.
- Change bio to "Updated bio test", click Ruaj. Flash "Profili u perditesua." appears.
- Click "Shiko profilin publik" link → opens `/provider/3` in new tab, bio shows updated text.

- [ ] **Step 4: Commit**

```bash
git add app/controllers/ProviderController.php app/views/provider/dashboard.php
git commit -m "feat: provider dashboard and edit profile"
```

---

## Task 16: Provider photo upload

**Files:**
- Modify: `app/controllers/ProviderController.php` (uploadPhoto)

- [ ] **Step 1: Replace `uploadPhoto` method in `ProviderController.php`**

```php
public function uploadPhoto(array $params = []): void {
    Auth::require('provider');
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

    // Delete old photo
    $cur = DB::q('SELECT photo FROM providers WHERE user_id=?', [$uid])->fetch();
    if (!empty($cur['photo'])) {
        $old = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $cur['photo'];
        if (is_file($old)) @unlink($old);
    }

    Provider::setPhoto($uid, $newName);
    $this->flash('success', 'Foto u ngarkua.');
    $this->redirect('/provider/dashboard');
}
```

- [ ] **Step 2: Verify upload**

- Login as `provider1@helppy.com`.
- On dashboard, choose a small JPG/PNG (under 2MB), click "Ngarko foto".
- Dashboard refreshes, profile photo replaced.
- Visit `/provider/3` in another tab → new photo visible.
- Check the file appears in `C:/laragon/www/Helppy.com/public/uploads/` with a random filename.
- Upload a second photo → first one is deleted from uploads folder.
- Try uploading a .txt file → flash "Formati i lejuar: JPG, PNG, WEBP."
- Try a 5MB image → flash "Foto eshte me e madhe se 2MB."

- [ ] **Step 3: Commit**

```bash
git add app/controllers/ProviderController.php
git commit -m "feat: provider photo upload with MIME/size validation"
```

---

## Task 17: Client dashboard

**Files:**
- Modify: `app/controllers/ClientController.php`
- Create: `app/views/client/dashboard.php`

- [ ] **Step 1: Replace `dashboard` in `ClientController.php`**

```php
<?php
declare(strict_types=1);

final class ClientController extends Controller {
    public function dashboard(array $params = []): void {
        Auth::require('client');
        $uid     = (int)Auth::user()['id'];
        $reviews = Review::byClient($uid);
        $this->render('client/dashboard', [
            'title'   => 'Llogaria ime',
            'user'    => Auth::user(),
            'reviews' => $reviews,
        ]);
    }
}
```

- [ ] **Step 2: Write `app/views/client/dashboard.php`**

```php
<div class="container py-4">
  <h2>Llogaria ime</h2>
  <div class="bg-white p-3 rounded mb-4">
    <p class="mb-1"><strong><?= e($user['name']) ?></strong></p>
    <p class="mb-1 text-muted small"><?= e($user['email']) ?></p>
    <?php if (!empty($user['phone'])): ?>
      <p class="mb-0 text-muted small"><?= e($user['phone']) ?></p>
    <?php endif; ?>
  </div>

  <h4>Vleresimet e mia (<?= count($reviews) ?>)</h4>
  <?php if (!$reviews): ?>
    <p class="text-muted">Nuk keni lene asnje vleresim ende.</p>
  <?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <div class="review-card">
      <div class="d-flex justify-content-between">
        <a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$r['provider_id'] ?>"><?= e($r['provider_name']) ?></a>
        <span class="meta"><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></span>
      </div>
      <div class="stars">
        <?php for ($i=1;$i<=5;$i++): ?>
          <i class="bi <?= $i <= (int)$r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
        <?php endfor; ?>
      </div>
      <?php if (!empty($r['comment'])): ?>
        <p class="mb-1"><?= nl2br(e($r['comment'])) ?></p>
      <?php endif; ?>
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/review/<?= (int)$r['id'] ?>/delete" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button class="btn btn-sm btn-link text-danger p-0" type="submit"
                onclick="return confirm('Fshi vleresimin?');">Fshi vleresimin</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
```

- [ ] **Step 3: Verify**

- Login as `client@helppy.com` / `password`.
- Click "Llogaria ime" in nav → see dashboard with 2 reviews (the seeded ones).
- Logout, login as provider → /client/dashboard redirects with 403 (since it requires client role).
- Logout entirely → /client/dashboard redirects to /login.

- [ ] **Step 4: Commit**

```bash
git add app/controllers/ClientController.php app/views/client/dashboard.php
git commit -m "feat: client dashboard with own reviews"
```

---

## Task 18: Submit and delete reviews

**Files:**
- Modify: `app/controllers/ReviewController.php`

- [ ] **Step 1: Replace `ReviewController.php`**

```php
<?php
declare(strict_types=1);

final class ReviewController extends Controller {
    public function store(array $params = []): void {
        Auth::require('client');
        $providerId = (int)($params['id'] ?? 0);
        $clientId   = (int)Auth::user()['id'];
        $rating     = (int)Request::post('rating', 0);
        $comment    = trim((string)Request::post('comment', ''));

        if ($rating < 1 || $rating > 5) {
            $this->flash('danger', 'Vleresimi duhet te jete 1-5.');
            $this->redirect('/provider/' . $providerId);
        }

        $provider = Provider::find($providerId);
        if (!$provider) {
            $this->flash('danger', 'Punetori nuk u gjet.');
            $this->redirect('/');
        }

        if (Review::existsFor($providerId, $clientId)) {
            $this->flash('danger', 'Keni vleresuar tashme kete punetor.');
            $this->redirect('/provider/' . $providerId);
        }

        Review::create($providerId, $clientId, $rating, $comment !== '' ? $comment : null);
        $this->flash('success', 'Faleminderit per vleresimin!');
        $this->redirect('/provider/' . $providerId);
    }

    public function destroy(array $params = []): void {
        Auth::require();           // any logged-in user
        $id = (int)($params['id'] ?? 0);
        $r  = Review::find($id);
        if (!$r) {
            $this->flash('danger', 'Vleresimi nuk u gjet.');
            $this->redirect('/');
        }
        $uid = (int)Auth::user()['id'];
        if ($uid !== (int)$r['client_id'] && Auth::role() !== 'admin') {
            http_response_code(403);
            View::render('errors/403', []);
            exit;
        }
        Review::delete($id);
        $this->flash('info', 'Vleresimi u fshi.');
        // Send admin back to provider; client back to their dashboard.
        if (Auth::role() === 'admin') $this->redirect('/provider/' . (int)$r['provider_id']);
        else                          $this->redirect('/client/dashboard');
    }
}
```

- [ ] **Step 2: Verify**

- Login as a new client (or `client@helppy.com`, who already has reviews — choose a provider they haven't reviewed, e.g. provider 5).
- Open `/provider/5`. The "Ler vleresim" form is shown.
- Submit rating 5 + comment "Test review". Flash "Faleminderit...". Review appears below the form. Form is replaced with "Keni vleresuar tashme..." alert.
- Try posting another review for the same provider (you'd need to bypass UI — skip this). Or just trust the unique constraint.
- Go to /client/dashboard, click "Fshi vleresimin" on the test review → confirm → review gone.

- [ ] **Step 3: Commit**

```bash
git add app/controllers/ReviewController.php
git commit -m "feat: submit and delete reviews with ownership/role guards"
```

---

## Task 19: Admin overview

**Files:**
- Modify: `app/controllers/AdminController.php` (just `index`)
- Create: `app/views/admin/index.php`

- [ ] **Step 1: Update `AdminController.php` `index` method**

```php
public function index(array $params = []): void {
    Auth::require('admin');
    $this->render('admin/index', [
        'title'  => 'Admin',
        'counts' => User::counts(),
    ]);
}
```

(Keep the other methods as stubs.)

- [ ] **Step 2: Write `app/views/admin/index.php`**

```php
<div class="container py-4">
  <h2>Admin Panel</h2>

  <div class="row mt-3">
    <?php
    $cards = [
      ['Perdorues',       $counts['users']],
      ['Punues',          $counts['providers']],
      ['Klient',          $counts['clients']],
      ['Vleresime',       $counts['reviews']],
    ]; ?>
    <?php foreach ($cards as [$label, $n]): ?>
      <div class="col-md-3">
        <div class="bg-white p-3 rounded text-center mb-3">
          <div class="text-muted small"><?= e($label) ?></div>
          <div class="display-6"><?= (int)$n ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-3">
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/providers">Menaxho punetoret</a>
    <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/admin/categories">Menaxho kategorite</a>
  </div>
</div>
```

- [ ] **Step 3: Verify**

- Login as `admin@helppy.com` / `admin123` → redirected to `/admin`.
- See 4 stat cards. Numbers should match seed (8 users, 6 providers, 1+ clients, 2+ reviews — depending on test data added).
- Two buttons visible to manage providers / categories.

- [ ] **Step 4: Commit**

```bash
git add app/controllers/AdminController.php app/views/admin/index.php
git commit -m "feat: admin overview dashboard"
```

---

## Task 20: Admin providers management

**Files:**
- Modify: `app/controllers/AdminController.php` (providers/toggleActive/togglePremium)
- Create: `app/views/admin/providers.php`

- [ ] **Step 1: Replace the three methods in `AdminController.php`**

```php
public function providers(array $params = []): void {
    Auth::require('admin');
    $this->render('admin/providers', [
        'title'     => 'Punetoret',
        'providers' => Provider::allWithStatus(),
    ]);
}

public function toggleActive(array $params = []): void {
    Auth::require('admin');
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) { $this->redirect('/admin/providers'); }
    User::toggleActive($id);
    $this->flash('info', 'Statusi u perditesua.');
    $this->redirect('/admin/providers');
}

public function togglePremium(array $params = []): void {
    Auth::require('admin');
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) { $this->redirect('/admin/providers'); }
    Provider::togglePremium($id);
    $this->flash('info', 'Premium u perditesua.');
    $this->redirect('/admin/providers');
}
```

- [ ] **Step 2: Write `app/views/admin/providers.php`**

```php
<div class="container py-4">
  <h2>Punetoret</h2>
  <table class="table table-striped bg-white">
    <thead>
      <tr>
        <th>ID</th><th>Emri</th><th>Email</th><th>Profesioni</th>
        <th>Aktiv</th><th>Premium</th><th>Veprime</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($providers as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><a href="<?= e(CONFIG['base_url']) ?>/provider/<?= (int)$p['id'] ?>" target="_blank"><?= e($p['name']) ?></a></td>
        <td><?= e($p['email']) ?></td>
        <td><?= e($p['profession']) ?></td>
        <td><?= $p['is_active']  ? '<span class="badge bg-success">Aktiv</span>' : '<span class="badge bg-secondary">Joaktiv</span>' ?></td>
        <td><?= $p['is_premium'] ? '<span class="badge bg-warning text-dark">Premium</span>' : '<span class="text-muted">—</span>' ?></td>
        <td>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/providers/<?= (int)$p['id'] ?>/active" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $p['is_active'] ? 'Cdeaktivizo' : 'Aktivizo' ?></button>
          </form>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/providers/<?= (int)$p['id'] ?>/premium" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-sm btn-outline-warning" type="submit"><?= $p['is_premium'] ? 'Hiq premium' : 'Beje premium' ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 3: Verify**

- Login as admin. Open `/admin/providers`.
- See table with 6 (or more, if you registered test providers earlier) rows.
- Click "Beje premium" on a non-premium row → flash, row badge updates.
- Visit `/` → that provider now appears first in featured strip.
- Click "Cdeaktivizo" → user is_active=0. Search no longer returns them; profile page returns 404.
- Click "Aktivizo" to restore.

- [ ] **Step 4: Commit**

```bash
git add app/controllers/AdminController.php app/views/admin/providers.php
git commit -m "feat: admin manage providers (toggle active/premium)"
```

---

## Task 21: Admin categories management

**Files:**
- Modify: `app/controllers/AdminController.php` (categories/createCategory/deleteCategory)
- Create: `app/views/admin/categories.php`

- [ ] **Step 1: Replace the three methods in `AdminController.php`**

```php
public function categories(array $params = []): void {
    Auth::require('admin');
    $this->render('admin/categories', [
        'title'      => 'Kategorite',
        'categories' => Category::all(),
    ]);
}

public function createCategory(array $params = []): void {
    Auth::require('admin');
    $name = trim((string)Request::post('name', ''));
    $slug = trim((string)Request::post('slug', ''));
    $icon = trim((string)Request::post('icon', '')) ?: null;

    if ($name === '' || $slug === '') {
        $this->flash('danger', 'Emri dhe slug-u jane te detyrueshem.');
        $this->redirect('/admin/categories');
    }
    if (Category::findBySlug($slug)) {
        $this->flash('danger', 'Slug-u ekziston.');
        $this->redirect('/admin/categories');
    }
    Category::create($name, $slug, $icon);
    $this->flash('success', 'Kategoria u shtua.');
    $this->redirect('/admin/categories');
}

public function deleteCategory(array $params = []): void {
    Auth::require('admin');
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) { $this->redirect('/admin/categories'); }
    if (Category::hasProviders($id)) {
        $this->flash('danger', 'Nuk mund ta fshini: ka punetore te lidhur.');
        $this->redirect('/admin/categories');
    }
    Category::delete($id);
    $this->flash('info', 'Kategoria u fshi.');
    $this->redirect('/admin/categories');
}
```

- [ ] **Step 2: Write `app/views/admin/categories.php`**

```php
<div class="container py-4">
  <h2>Kategorite</h2>

  <div class="row">
    <div class="col-md-7">
      <table class="table bg-white">
        <thead><tr><th>ID</th><th>Emri</th><th>Slug</th><th>Ikona</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><?= e($c['name']) ?></td>
            <td><code><?= e($c['slug']) ?></code></td>
            <td><i class="<?= e($c['icon'] ?? '') ?>"></i> <small class="text-muted"><?= e($c['icon'] ?? '') ?></small></td>
            <td>
              <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/categories/<?= (int)$c['id'] ?>/delete">
                <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"
                        onclick="return confirm('Fshi kete kategori?');">Fshi</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="col-md-5">
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/categories" class="bg-white p-3 rounded">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <h5>Shto kategori</h5>
        <div class="mb-2"><label class="form-label">Emri</label>
          <input class="form-control" name="name" required></div>
        <div class="mb-2"><label class="form-label">Slug</label>
          <input class="form-control" name="slug" placeholder="vetem-shkronja-pa-hapesira" required></div>
        <div class="mb-2"><label class="form-label">Ikona (bootstrap-icons)</label>
          <input class="form-control" name="icon" placeholder="bi-wrench"></div>
        <button class="btn btn-helppy" type="submit">Shto</button>
      </form>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Verify**

- Login as admin. Open `/admin/categories`.
- See 7 categories in the table.
- Add a new one: Emri "Kopshtari", Slug "kopshtari", Ikona "bi-flower3". → appears in table, and on home page chips.
- Try to delete it → succeeds (no providers linked).
- Try to delete "Hidraulike" (slug `hidraulike`) → flash "Nuk mund ta fshini: ka punetore te lidhur."
- Add a category with the same slug as an existing one → flash "Slug-u ekziston."

- [ ] **Step 4: Commit**

```bash
git add app/controllers/AdminController.php app/views/admin/categories.php
git commit -m "feat: admin manage categories"
```

---

## Task 22: Admin delete review

**Files:**
- Modify: `app/controllers/AdminController.php` (deleteReview)

- [ ] **Step 1: Replace `deleteReview` in `AdminController.php`**

```php
public function deleteReview(array $params = []): void {
    Auth::require('admin');
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) { $this->redirect('/admin'); }
    $r = Review::find($id);
    if (!$r) { $this->flash('danger', 'Nuk u gjet.'); $this->redirect('/admin'); }
    Review::delete($id);
    $this->flash('info', 'Vleresimi u fshi.');
    $this->redirect('/provider/' . (int)$r['provider_id']);
}
```

- [ ] **Step 2: Verify**

- Login as admin.
- Open `/provider/3`. Below each review there's now a "Fshi (admin)" button (defined in the review-card partial Task 12).
- Click → confirm → review gone, flash "Vleresimi u fshi.", redirected back to the provider page.

- [ ] **Step 3: Commit**

```bash
git add app/controllers/AdminController.php
git commit -m "feat: admin can delete any review"
```

---

## Task 23: README + acceptance walkthrough

**Files:**
- Create: `README.md`

- [ ] **Step 1: Write `README.md`**

```markdown
# Helppy.com

Lokal Kosovo home-services marketplace. PHP 8.x + MySQL + Bootstrap 5, running on Laragon.

## Setup (Laragon)

1. Drop this folder in `C:/laragon/www/Helppy.com/`.
2. Start Laragon (Apache + MySQL).
3. Open phpMyAdmin and create the database + load schema + seed:
   ```sql
   CREATE DATABASE IF NOT EXISTS helppy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE helppy;
   SOURCE C:/laragon/www/Helppy.com/db/schema.sql;
   SOURCE C:/laragon/www/Helppy.com/db/seed.sql;
   ```
   Or open each `.sql` file and paste into phpMyAdmin's SQL tab.
4. Copy `config/config.example.php` to `config/config.php` and update DB credentials if needed (defaults match Laragon out of the box).
5. Open `http://localhost/Helppy.com/public/` (or `http://localhost/Helppy.com/` if your Laragon doc root is the project root).

If you set up a Laragon virtual host (e.g. `helppy.test`) with doc root at `/public/`, also change `RewriteBase` in `public/.htaccess` to `/` and update `base_url` in `config/config.php`.

## Seeded accounts

| Role     | Email                  | Password   |
|----------|------------------------|------------|
| Admin    | admin@helppy.com       | admin123   |
| Client   | client@helppy.com      | password   |
| Provider | provider1@helppy.com   | password   |
| Provider | provider2@helppy.com   | password   |
| ...      | provider3-6@helppy.com | password   |

## Folder structure

```
public/        document root, .htaccess, index.php, assets/, uploads/
app/core/      Router, Controller, View, Request, Auth, DB
app/controllers/, app/models/, app/views/
config/        config.example.php (copy to config.php)
db/            schema.sql, seed.sql
```

## What's in / out

In MVP: auth, provider profiles, search by city + category, reviews, admin panel.
Phase 2 (not built): real-time chat, payment processing, GPS/map sort, email verification, multi-language.

## Spec & plan

- Design spec: `docs/superpowers/specs/2026-06-04-helppy-design.md`
- Implementation plan: `docs/superpowers/plans/2026-06-04-helppy-mvp.md`
```

- [ ] **Step 2: Full end-to-end acceptance walkthrough**

Walk through every acceptance criterion from spec §11:

1. **Schema + seed import**: confirmed (Task 6).
2. **Home page loads with seeded data**: open `http://localhost/Helppy.com/public/` — see logo, search, 7 categories, 6+ providers. ✔
3. **New provider can register, log in, upload photo, edit profile, become searchable**:
   - Log out.
   - Go to `/register`, pick "Punues", create `freshworker@test.com` / `secret1`, profession "Elektricist Test", category Elektrike, city Prishtine.
   - Get redirected to `/provider/dashboard`.
   - Upload a JPG photo.
   - Edit bio.
   - Visit `/search?city=1&category=3` → new worker appears.
4. **Client can register, search, find worker, click Telefono Tani, leave review**:
   - Log out, register a fresh client.
   - Search city=Prishtine, category=Elektrike → find `freshworker`.
   - Open profile, click Telefono Tani (browser shows `tel:` prompt — won't dial on desktop, that's expected).
   - Submit a 5-star review with comment "Test review".
   - Review appears on the profile. Form is hidden.
5. **Admin can log in, manage providers, toggle premium, delete a review, add a category**:
   - Log out, log in as `admin@helppy.com` / `admin123`.
   - Open `/admin` → see counts.
   - `/admin/providers` → toggle `freshworker` to premium. Visit `/` → they appear first in featured.
   - Open the new client's review on `freshworker`'s profile → click "Fshi (admin)" → review gone.
   - `/admin/categories` → add "Kopshtari" → appears as chip on home.

If any of these 5 fails, fix before committing this task.

- [ ] **Step 3: Final commit**

```bash
git add README.md
git commit -m "docs: README with Laragon setup and seeded accounts"
```

---

## Summary

| # | Task | Files touched | Spec section |
|---|------|---------------|--------------|
| 1 | Scaffolding | public/, config/, root index.php | §7 file layout |
| 2 | DB | app/core/DB.php | §3 tech, §9 security (PDO) |
| 3 | View | app/core/View.php | §7 file layout |
| 4 | Request + Auth | app/core/Request.php, Auth.php | §9 CSRF, sessions |
| 5 | Router + Controller | app/core/Router.php, Controller.php | §6, §7 |
| 6 | Schema + seed | db/ | §5 |
| 7 | Front-controller wiring | public/index.php, controller stubs | §6 routes |
| 8 | Layout + brand | app/views/layout.php, nav/footer/flash, css, svg | §4 |
| 9 | Models | app/models/ | §5 |
| 10 | Home | HomeController, home/index.php, provider-card | §8.1 |
| 11 | Search | SearchController, search/results | §8.1 |
| 12 | Provider profile | ProviderController@show, show.php, review-card | §8.2 |
| 13 | Login/logout | AuthController, auth/login.php | §8.3 (login part) |
| 14 | Registration | AuthController, auth/register.php | §8.3 |
| 15 | Provider dashboard | ProviderController dash/update, dashboard.php | §8.4 |
| 16 | Photo upload | ProviderController@uploadPhoto | §9 file uploads |
| 17 | Client dashboard | ClientController, client/dashboard.php | §8.5 |
| 18 | Reviews | ReviewController | §8.2 review flow |
| 19 | Admin overview | AdminController@index, admin/index.php | §8.6 |
| 20 | Admin providers | AdminController providers/toggle*, admin/providers.php | §8.6 |
| 21 | Admin categories | AdminController categories/create/delete, admin/categories.php | §8.6 |
| 22 | Admin delete review | AdminController@deleteReview | §8.6 |
| 23 | README + acceptance | README.md | §11 acceptance |

Every spec section maps to at least one task. No placeholders. Type names (`Provider::find`, `Category::create`, etc.) are consistent across tasks.

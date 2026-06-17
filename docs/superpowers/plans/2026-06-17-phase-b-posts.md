# Helppy.com — Phase B (Posts) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a two-sided post system to Helppy.com — provider offers (paid services to advertise) and client requests (jobs to be done) — with a shared feed, per-post photo upload, contact via `tel:` and `mailto:`, owner edit/close/delete, and admin hide.

**Architecture:** Single `posts` table with a `type` discriminator (`offer` / `request`); a separate `post_photos` table for multi-photo support; one `PostController` covering CRUD; views live in `app/views/posts/`. Follows the existing static-method-on-model + procedural-PHP-controller pattern.

**Tech Stack:** PHP 8.x, MySQL, Bootstrap 5 (CDN), Bootstrap Icons, PDO, sessions. No Composer, no npm.

**Spec:** `docs/superpowers/specs/2026-06-17-phase-b-posts-design.md`

**Testing approach:** No PHPUnit. Each task ends with explicit manual smoke-test steps (URL to open, what to click, expected outcome).

---

## File Structure (locked)

```
db/
  migrations/
    003_posts.sql                    NEW – schema for posts + post_photos
app/
  models/
    Post.php                         NEW – Post::find / feed / create / update / close / hide / delete
    PostPhoto.php                    NEW – PostPhoto::forPost / add / removeOne / removeAll
  controllers/
    PostController.php               NEW – index / createForm / store / show / editForm / update / close / destroy
    AdminController.php              MOD – add posts() + hidePost()
  views/
    posts/
      index.php                      NEW – feed with filters
      show.php                       NEW – detail with photo carousel + contact buttons
      create.php                     NEW – create form (offer or request based on role)
      edit.php                       NEW – edit form
    partials/
      post-card.php                  NEW – card used in feed
      time-ago.php                   NEW – helper partial for "ka X minuta/ore/dite"
      nav.php                        MOD – add "Postimet" link
    admin/
      posts.php                      NEW – admin posts table
public/
  index.php                          MOD – register 10 new routes
  assets/css/style.css               MOD – add post badge / urgency styles
```

---

## Task 1 — Schema migration

**Files:**
- Create: `db/migrations/003_posts.sql`

- [ ] **Step 1: Write migration file**

Create `db/migrations/003_posts.sql`:

```sql
-- Phase B: posts and post_photos.
-- Run once on the existing helppy database.
SET NAMES utf8mb4;

CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('offer','request') NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  category_id INT NOT NULL,
  city_id INT NOT NULL,

  -- Offer-only (NULL for requests)
  price_from DECIMAL(10,2) NULL,
  price_to DECIMAL(10,2) NULL,
  working_hours VARCHAR(120) NULL,
  contact_preferences VARCHAR(200) NULL,

  -- Request-only (NULL for offers)
  budget_from DECIMAL(10,2) NULL,
  budget_to DECIMAL(10,2) NULL,
  deadline DATE NULL,
  urgency ENUM('low','normal','high') NULL,

  -- Shared
  status ENUM('active','closed','hidden') NOT NULL DEFAULT 'active',
  views INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  FOREIGN KEY (city_id)     REFERENCES cities(id)     ON DELETE RESTRICT,

  INDEX idx_feed (type, status, created_at DESC),
  INDEX idx_category (category_id),
  INDEX idx_city (city_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  sort_order TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  INDEX idx_post (post_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Apply migration**

Run from PowerShell:

```
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy --default-character-set=utf8mb4 < "C:/laragon/www/Helppy.com/db/migrations/003_posts.sql"
```

Expected: no output (success). If you see an error about a missing referenced table, confirm `users`, `categories`, `cities` already exist.

- [ ] **Step 3: Smoke test — verify tables**

Run:

```
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SHOW TABLES LIKE 'post%'; DESCRIBE posts; DESCRIBE post_photos;"
```

Expected: both `posts` and `post_photos` are listed; column lists match the schema above.

- [ ] **Step 4: Commit**

```
git add db/migrations/003_posts.sql
git commit -m "feat(posts): schema for posts + post_photos"
```

---

## Task 2 — Models: Post and PostPhoto

**Files:**
- Create: `app/models/Post.php`
- Create: `app/models/PostPhoto.php`

- [ ] **Step 1: Write `app/models/PostPhoto.php`**

```php
<?php
declare(strict_types=1);

final class PostPhoto {
    /** All photos for a post, ordered. */
    public static function forPost(int $postId): array {
        return DB::q(
            'SELECT id, post_id, filename, sort_order FROM post_photos WHERE post_id = ? ORDER BY sort_order ASC, id ASC',
            [$postId]
        )->fetchAll();
    }

    /** First photo for a post, or null. Used for card thumbnails. */
    public static function firstForPost(int $postId): ?array {
        $r = DB::q(
            'SELECT id, filename FROM post_photos WHERE post_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1',
            [$postId]
        )->fetch();
        return $r ?: null;
    }

    /** Insert one photo row. Returns the new id. */
    public static function add(int $postId, string $filename, int $sortOrder): int {
        DB::q(
            'INSERT INTO post_photos (post_id, filename, sort_order) VALUES (?, ?, ?)',
            [$postId, $filename, $sortOrder]
        );
        return (int)DB::pdo()->lastInsertId();
    }

    /** Delete one photo row by id; returns the filename so the caller can unlink. */
    public static function removeOne(int $photoId, int $postId): ?string {
        $r = DB::q('SELECT filename FROM post_photos WHERE id = ? AND post_id = ?', [$photoId, $postId])->fetch();
        if (!$r) return null;
        DB::q('DELETE FROM post_photos WHERE id = ?', [$photoId]);
        return $r['filename'];
    }

    /** Delete all photo rows for a post; returns the list of filenames so the caller can unlink. */
    public static function removeAllForPost(int $postId): array {
        $names = array_column(
            DB::q('SELECT filename FROM post_photos WHERE post_id = ?', [$postId])->fetchAll(),
            'filename'
        );
        DB::q('DELETE FROM post_photos WHERE post_id = ?', [$postId]);
        return $names;
    }
}
```

- [ ] **Step 2: Write `app/models/Post.php`**

```php
<?php
declare(strict_types=1);

final class Post {
    /** Find one post with joined user, category, city. Returns null if not found. */
    public static function find(int $id): ?array {
        $sql = "SELECT p.*, u.name AS author_name, u.email AS author_email, u.phone AS author_phone, u.role AS author_role,
                       cat.name AS category_name, cat.icon AS category_icon,
                       ct.name AS city_name
                FROM posts p
                JOIN users u        ON u.id = p.user_id
                JOIN categories cat ON cat.id = p.category_id
                JOIN cities ct      ON ct.id = p.city_id
                WHERE p.id = ?";
        $r = DB::q($sql, [$id])->fetch();
        return $r ?: null;
    }

    /**
     * Feed query with optional filters.
     * $filters keys: type (offer|request), category_id (int), city_id (int), include_hidden (bool, admin only).
     * Returns rows with first-photo filename joined as `photo` (nullable).
     */
    public static function feed(array $filters = [], int $limit = 60): array {
        $limit = max(1, min(200, $limit));

        $sql = "SELECT p.id, p.type, p.title, p.category_id, p.city_id, p.status, p.created_at,
                       p.price_from, p.price_to, p.budget_from, p.budget_to, p.urgency, p.deadline,
                       u.name AS author_name, u.role AS author_role,
                       cat.name AS category_name, cat.icon AS category_icon,
                       ct.name AS city_name,
                       (SELECT filename FROM post_photos ph WHERE ph.post_id = p.id ORDER BY ph.sort_order, ph.id LIMIT 1) AS photo
                FROM posts p
                JOIN users u        ON u.id = p.user_id
                JOIN categories cat ON cat.id = p.category_id
                JOIN cities ct      ON ct.id = p.city_id
                WHERE 1=1 ";
        $args = [];

        if (empty($filters['include_hidden'])) {
            $sql .= " AND p.status = 'active' ";
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['offer','request'], true)) {
            $sql .= " AND p.type = ? ";
            $args[] = $filters['type'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ? ";
            $args[] = (int)$filters['category_id'];
        }
        if (!empty($filters['city_id'])) {
            $sql .= " AND p.city_id = ? ";
            $args[] = (int)$filters['city_id'];
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT $limit";

        return DB::q($sql, $args)->fetchAll();
    }

    /** All posts for the admin table (active + closed + hidden). */
    public static function allForAdmin(int $limit = 200): array {
        $limit = max(1, min(500, $limit));
        $sql = "SELECT p.id, p.type, p.title, p.status, p.created_at,
                       u.name AS author_name, u.role AS author_role,
                       cat.name AS category_name, ct.name AS city_name
                FROM posts p
                JOIN users u        ON u.id = p.user_id
                JOIN categories cat ON cat.id = p.category_id
                JOIN cities ct      ON ct.id = p.city_id
                ORDER BY p.created_at DESC
                LIMIT $limit";
        return DB::q($sql)->fetchAll();
    }

    /** All active posts authored by one user. */
    public static function forAuthor(int $userId): array {
        $sql = "SELECT id, type, title, status, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC";
        return DB::q($sql, [$userId])->fetchAll();
    }

    /** Insert a post. $data is pre-validated. Returns the new id. */
    public static function create(int $userId, string $type, array $data): int {
        $sql = "INSERT INTO posts
                  (user_id, type, title, description, category_id, city_id,
                   price_from, price_to, working_hours, contact_preferences,
                   budget_from, budget_to, deadline, urgency)
                VALUES (?,?,?,?,?,?, ?,?,?,?, ?,?,?,?)";
        DB::q($sql, [
            $userId,
            $type,
            $data['title'],
            $data['description'],
            $data['category_id'],
            $data['city_id'],
            $data['price_from']          ?? null,
            $data['price_to']            ?? null,
            $data['working_hours']       ?? null,
            $data['contact_preferences'] ?? null,
            $data['budget_from']         ?? null,
            $data['budget_to']           ?? null,
            $data['deadline']            ?? null,
            $data['urgency']             ?? null,
        ]);
        return (int)DB::pdo()->lastInsertId();
    }

    /** Update an existing post. Only fields present in $data are touched. */
    public static function update(int $id, array $data): void {
        $allowed = ['title','description','category_id','city_id',
                    'price_from','price_to','working_hours','contact_preferences',
                    'budget_from','budget_to','deadline','urgency'];
        $sets = []; $args = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) { $sets[] = "$k = ?"; $args[] = $v; }
        }
        if (!$sets) return;
        $args[] = $id;
        DB::q('UPDATE posts SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
    }

    public static function close(int $id): void {
        DB::q("UPDATE posts SET status = 'closed' WHERE id = ?", [$id]);
    }

    public static function hide(int $id): void {
        DB::q("UPDATE posts SET status = 'hidden' WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void {
        DB::q('DELETE FROM posts WHERE id = ?', [$id]);
    }

    public static function incrementViews(int $id): void {
        DB::q('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);
    }

    /** True if the post exists and belongs to $userId. */
    public static function ownedBy(int $id, int $userId): bool {
        $r = DB::q('SELECT 1 FROM posts WHERE id = ? AND user_id = ?', [$id, $userId])->fetch();
        return (bool)$r;
    }
}
```

- [ ] **Step 3: Smoke test — autoloader picks up the new classes**

Run from PowerShell:

```
curl -sk -o NUL -w "HTTP %{http_code}\n" https://helppy.com.loc/
```

Expected: `HTTP 200`. (The autoloader in `public/index.php` scans `app/models`, so the new files load on demand without code changes.)

Then verify the model loads without parse errors:

```
"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "C:/laragon/www/Helppy.com/app/models/Post.php"
"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -l "C:/laragon/www/Helppy.com/app/models/PostPhoto.php"
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```
git add app/models/Post.php app/models/PostPhoto.php
git commit -m "feat(posts): Post and PostPhoto models"
```

---

## Task 3 — PostController shell, feed route, nav link, empty feed view

**Files:**
- Create: `app/controllers/PostController.php` (only `index()` for now)
- Create: `app/views/posts/index.php`
- Create: `app/views/partials/post-card.php` (renders a single card; used by feed)
- Modify: `public/index.php` (register `GET /posts`)
- Modify: `app/views/partials/nav.php` (add "Postimet" link)

- [ ] **Step 1: Write `app/controllers/PostController.php`** (skeleton with index only)

```php
<?php
declare(strict_types=1);

final class PostController extends Controller {
    public function index(array $params = []): void {
        $filters = [
            'type'        => Request::get('type'),
            'category_id' => Request::get('category') !== null ? (int)Request::get('category') : null,
            'city_id'     => Request::get('city') !== null ? (int)Request::get('city') : null,
        ];

        $posts = Post::feed($filters, 60);

        $this->render('posts/index', [
            'title'      => 'Postimet',
            'posts'      => $posts,
            'filters'    => $filters,
            'categories' => Category::all(),
            'cities'     => City::all(),
        ]);
    }
}
```

- [ ] **Step 2: Write `app/views/partials/post-card.php`**

```php
<?php
/** @var array $p Joined post row from Post::feed() */
$photoUrl = !empty($p['photo'])
    ? CONFIG['upload_url'] . '/' . rawurlencode($p['photo'])
    : null;

$typeLabel = $p['type'] === 'offer' ? 'Ofertë' : 'Kërkesë';
$typeClass = $p['type'] === 'offer' ? 'post-badge-offer' : 'post-badge-request';

$priceLabel = '';
if ($p['type'] === 'offer' && ($p['price_from'] !== null || $p['price_to'] !== null)) {
    $from = $p['price_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['price_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = trim($from . ($from && $to ? ' – ' : '') . $to);
} elseif ($p['type'] === 'request' && ($p['budget_from'] !== null || $p['budget_to'] !== null)) {
    $from = $p['budget_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['budget_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = 'Buxhet: ' . trim($from . ($from && $to ? ' – ' : '') . $to);
}
?>
<a class="post-card" href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>">
  <div class="post-card-photo">
    <?php if ($photoUrl): ?>
      <img src="<?= e($photoUrl) ?>" alt="<?= e($p['title']) ?>">
    <?php else: ?>
      <div class="post-card-placeholder">
        <i class="bi <?= e($p['category_icon'] ?: 'bi-image') ?>"></i>
      </div>
    <?php endif; ?>
    <span class="post-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
  </div>
  <div class="post-card-body">
    <h3 class="post-card-title"><?= e($p['title']) ?></h3>
    <p class="post-card-meta">
      <?= e($p['category_name']) ?> &middot; <?= e($p['city_name']) ?>
    </p>
    <?php if ($priceLabel): ?>
      <p class="post-card-price"><?= e($priceLabel) ?></p>
    <?php endif; ?>
    <p class="post-card-author">
      <i class="bi bi-person"></i> <?= e($p['author_name']) ?>
    </p>
  </div>
</a>
```

- [ ] **Step 3: Write `app/views/posts/index.php`** (feed; filters added in Task 9, just renders cards for now)

```php
<section class="container py-4">
  <h1 class="section-title mb-3">Postimet</h1>

  <?php if (Auth::check() && (Auth::role() === 'provider' || Auth::role() === 'client')): ?>
    <p class="mb-3">
      <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/posts/create">
        <i class="bi bi-plus-lg"></i> Posto
      </a>
    </p>
  <?php endif; ?>

  <?php if (!$posts): ?>
    <div class="empty-state">
      <i class="bi bi-postcard"></i>
      <p>Asnjë postim ende. Bëhu i pari!</p>
      <?php if (!Auth::check()): ?>
        <p><a href="<?= e(CONFIG['base_url']) ?>/register">Regjistrohu</a> ose <a href="<?= e(CONFIG['base_url']) ?>/login">hyr</a> për të postuar.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($posts as $p): ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <?php View::partial('post-card', ['p' => $p]); ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
```

- [ ] **Step 4: Add post-card CSS to `public/assets/css/style.css`**

Append to the end of `public/assets/css/style.css`:

```css
/* ===========================================================
   Posts (Phase B)
   =========================================================== */

.post-card {
  display: flex;
  flex-direction: column;
  background: var(--helppy-card);
  border-radius: var(--helppy-radius);
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  box-shadow: var(--helppy-shadow);
  transition: box-shadow .15s, transform .1s;
  height: 100%;
}
.post-card:hover { box-shadow: var(--helppy-shadow-lg); transform: translateY(-1px); color: inherit; }

.post-card-photo {
  position: relative;
  aspect-ratio: 16 / 10;
  background: #f6f7f5;
  overflow: hidden;
}
.post-card-photo img {
  width: 100%; height: 100%; object-fit: cover; display: block;
}
.post-card-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  color: var(--helppy-green-light);
  font-size: 56px;
  background: linear-gradient(135deg, var(--helppy-green-bg), #fff);
}

.post-badge {
  position: absolute;
  top: 10px; right: 10px;
  padding: 4px 10px;
  border-radius: var(--helppy-radius-pill);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .3px;
  color: #fff;
  text-transform: uppercase;
}
.post-badge-offer   { background: var(--helppy-green); }
.post-badge-request { background: #d97706; }

.post-card-body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
.post-card-title { font-size: 15px; font-weight: 700; margin: 0; line-height: 1.3;
                   display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.post-card-meta  { font-size: 12px; color: var(--helppy-muted); margin: 0; }
.post-card-price { font-size: 14px; font-weight: 600; color: var(--helppy-green-dark); margin: 4px 0 0; }
.post-card-author { font-size: 12px; color: var(--helppy-muted); margin: auto 0 0; }

.empty-state {
  text-align: center;
  padding: 48px 20px;
  background: #fff;
  border-radius: var(--helppy-radius);
  box-shadow: var(--helppy-shadow);
}
.empty-state i { font-size: 56px; color: var(--helppy-green-light); display: block; margin-bottom: 12px; }
.empty-state p { color: var(--helppy-muted); margin: 0; }
.empty-state p + p { margin-top: 10px; }
```

- [ ] **Step 5: Register `GET /posts` route in `public/index.php`**

Find the `// PUBLIC` section in `public/index.php`. Below the existing public routes, add:

```php
// POSTS
$router->get('/posts',                     [PostController::class,    'index']);
```

Place it after the existing `/provider/{id}` route block, before the `// CLIENT` section.

- [ ] **Step 6: Add "Postimet" link to `app/views/partials/nav.php`**

In `app/views/partials/nav.php`, inside `<ul class="navbar-nav">`, add this as the FIRST `<li>` (before any `Auth::check()` branches):

```php
<li class="nav-item">
  <a class="nav-link text-white" href="<?= e(CONFIG['base_url']) ?>/posts">Postimet</a>
</li>
```

- [ ] **Step 7: Smoke test**

Run:

```
curl -sk -o NUL -w "Feed: %{http_code} (%{size_download}b)\n" https://helppy.com.loc/posts
```

Expected: `Feed: 200 (some bytes)`.

Open `https://helppy.com.loc/posts` in a browser. Expected:
- Nav shows "Postimet".
- Page heading "Postimet" visible.
- Empty state shows "Asnjë postim ende. Bëhu i pari!" with a postcard icon.
- If you're logged in as a client or provider, a green "Posto" button is visible above the empty state.

- [ ] **Step 8: Commit**

```
git add public/index.php app/controllers/PostController.php app/views/posts/index.php app/views/partials/post-card.php app/views/partials/nav.php public/assets/css/style.css
git commit -m "feat(posts): feed route, empty view, nav link, card styles"
```

---

## Task 4 — Create form: GET, POST handler, photo upload, validation

**Files:**
- Modify: `app/controllers/PostController.php` (add `createForm` + `store`)
- Create: `app/views/posts/create.php`
- Modify: `public/index.php` (register `GET` + `POST /posts/create` and `POST /posts`)

- [ ] **Step 1: Add `createForm()` to `PostController`**

In `app/controllers/PostController.php`, inside the class, after `index()`:

```php
    public function createForm(array $params = []): void {
        Auth::require();
        $role = Auth::role();
        if ($role !== 'client' && $role !== 'provider' && $role !== 'admin') {
            http_response_code(403);
            View::render('errors/403', []);
            return;
        }

        // Type derives from role. Admin can post either; default to 'offer'.
        $type = $role === 'client' ? 'request' : 'offer';
        if ($role === 'admin' && in_array(Request::get('type'), ['offer','request'], true)) {
            $type = Request::get('type');
        }

        $this->render('posts/create', [
            'title'      => $type === 'offer' ? 'Posto ofertën tënde' : 'Posto kërkesën tënde',
            'type'       => $type,
            'categories' => Category::all(),
            'cities'     => City::all(),
            'old'        => [], // empty on first render; refilled on validation failure
            'errors'     => [],
        ]);
    }
```

- [ ] **Step 2: Add `store()` to `PostController`**

In `app/controllers/PostController.php`, after `createForm()`:

```php
    public function store(array $params = []): void {
        Auth::require();
        $role = Auth::role();
        $uid  = (int)Auth::user()['id'];

        // Type derives from role (admin chooses via hidden field).
        $type = $role === 'client' ? 'request' : 'offer';
        if ($role === 'admin' && in_array(Request::post('type'), ['offer','request'], true)) {
            $type = Request::post('type');
        } elseif ($role !== 'client' && $role !== 'provider' && $role !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        // Collect raw input
        $title       = trim((string)Request::post('title', ''));
        $description = trim((string)Request::post('description', ''));
        $categoryId  = (int)Request::post('category_id', 0);
        $cityId      = (int)Request::post('city_id', 0);

        $priceFrom = self::numOrNull(Request::post('price_from'));
        $priceTo   = self::numOrNull(Request::post('price_to'));
        $workingHours       = trim((string)Request::post('working_hours', ''));
        $contactPreferences = trim((string)Request::post('contact_preferences', ''));

        $budgetFrom = self::numOrNull(Request::post('budget_from'));
        $budgetTo   = self::numOrNull(Request::post('budget_to'));
        $deadline   = trim((string)Request::post('deadline', ''));
        $urgency    = trim((string)Request::post('urgency', ''));

        // Validate
        $errors = [];
        if (mb_strlen($title) < 4 || mb_strlen($title) > 160)            $errors['title']       = 'Titulli duhet të jetë 4-160 karaktere.';
        if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) $errors['description'] = 'Përshkrimi duhet të jetë 20-5000 karaktere.';
        if ($categoryId <= 0 || !Category::find($categoryId))            $errors['category_id'] = 'Zgjidh një kategori.';
        if ($cityId <= 0     || !City::find($cityId))                    $errors['city_id']     = 'Zgjidh një qytet.';

        if ($priceFrom !== null && ($priceFrom < 0 || $priceFrom > 1000000)) $errors['price_from'] = 'Çmim i pavlefshëm.';
        if ($priceTo   !== null && ($priceTo   < 0 || $priceTo   > 1000000)) $errors['price_to']   = 'Çmim i pavlefshëm.';
        if ($priceFrom !== null && $priceTo !== null && $priceFrom > $priceTo) $errors['price_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';

        if ($budgetFrom !== null && ($budgetFrom < 0 || $budgetFrom > 1000000)) $errors['budget_from'] = 'Buxhet i pavlefshëm.';
        if ($budgetTo   !== null && ($budgetTo   < 0 || $budgetTo   > 1000000)) $errors['budget_to']   = 'Buxhet i pavlefshëm.';
        if ($budgetFrom !== null && $budgetTo !== null && $budgetFrom > $budgetTo) $errors['budget_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';

        if (mb_strlen($workingHours)       > 120) $errors['working_hours']       = 'Orari deri 120 karaktere.';
        if (mb_strlen($contactPreferences) > 200) $errors['contact_preferences'] = 'Preferencat e kontaktit deri 200 karaktere.';

        if ($type === 'request') {
            if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                $errors['deadline'] = 'Datë e pavlefshme.';
            } elseif ($deadline !== '' && $deadline < date('Y-m-d')) {
                $errors['deadline'] = 'Afati duhet të jetë sot ose më vonë.';
            }
            if ($urgency !== '' && !in_array($urgency, ['low','normal','high'], true)) {
                $errors['urgency'] = 'Urgjenca e pavlefshme.';
            }
        }

        // Photo validation (no save yet)
        $files = self::collectUploadedPhotos('photos');
        if (count($files) > 5) {
            $errors['photos'] = 'Maksimumi 5 foto për postim.';
        }
        foreach ($files as $idx => $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) { $errors['photos'] = 'Gabim në ngarkim të fotos.'; break; }
            if ($f['size'] > 5 * 1024 * 1024) { $errors['photos'] = 'Çdo foto duhet të jetë nën 5MB.'; break; }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
                $errors['photos'] = 'Formati i lejuar: JPG, PNG, WEBP.';
                break;
            }
        }

        if ($errors) {
            $this->render('posts/create', [
                'title'      => $type === 'offer' ? 'Posto ofertën tënde' : 'Posto kërkesën tënde',
                'type'       => $type,
                'categories' => Category::all(),
                'cities'     => City::all(),
                'old'        => $_POST,
                'errors'     => $errors,
            ]);
            return;
        }

        // Build clean data
        $data = [
            'title'         => $title,
            'description'   => $description,
            'category_id'   => $categoryId,
            'city_id'       => $cityId,
        ];
        if ($type === 'offer') {
            $data['price_from']          = $priceFrom;
            $data['price_to']            = $priceTo;
            $data['working_hours']       = $workingHours !== '' ? $workingHours : null;
            $data['contact_preferences'] = $contactPreferences !== '' ? $contactPreferences : null;
        } else {
            $data['budget_from'] = $budgetFrom;
            $data['budget_to']   = $budgetTo;
            $data['deadline']    = $deadline !== '' ? $deadline : null;
            $data['urgency']     = $urgency  !== '' ? $urgency  : null;
        }

        // Insert post + photos. Roll back files on DB failure.
        try {
            DB::pdo()->beginTransaction();
            $postId = Post::create($uid, $type, $data);

            $savedFilenames = [];
            foreach ($files as $idx => $f) {
                $ext  = self::extForMime((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']));
                $name = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $name;
                if (!is_dir(CONFIG['upload_dir'])) mkdir(CONFIG['upload_dir'], 0775, true);
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    throw new RuntimeException('Photo move failed');
                }
                $savedFilenames[] = $name;
                PostPhoto::add($postId, $name, $idx);
            }
            DB::pdo()->commit();
        } catch (Throwable $ex) {
            DB::pdo()->rollBack();
            foreach ($savedFilenames ?? [] as $n) {
                @unlink(CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $n);
            }
            $this->flash('danger', 'Gabim teknik gjatë ruajtjes së postimit. Provo përsëri.');
            $this->redirect('/posts/create');
            return;
        }

        $this->flash('success', 'Postimi u publikua.');
        $this->redirect('/posts/' . $postId);
    }

    /** Convert a stringy number to float or null. Empty => null. */
    private static function numOrNull($v): ?float {
        if ($v === null || $v === '' || $v === false) return null;
        if (!is_numeric($v)) return null;
        return (float)$v;
    }

    /** Normalize $_FILES['photos'] (multi-upload) to a list of single-file arrays. */
    private static function collectUploadedPhotos(string $key): array {
        if (empty($_FILES[$key]) || !is_array($_FILES[$key]['name'])) return [];
        $out = [];
        foreach ($_FILES[$key]['name'] as $i => $name) {
            if ($_FILES[$key]['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $out[] = [
                'name'     => $name,
                'type'     => $_FILES[$key]['type'][$i],
                'tmp_name' => $_FILES[$key]['tmp_name'][$i],
                'error'    => $_FILES[$key]['error'][$i],
                'size'     => $_FILES[$key]['size'][$i],
            ];
        }
        return $out;
    }

    private static function extForMime(string $mime): string {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'jpg';
    }
```

- [ ] **Step 3: Write `app/views/posts/create.php`**

```php
<section class="container py-4">
  <div class="form-card mx-auto" style="max-width: 720px;">
    <h1 class="section-title"><?= e($title) ?></h1>
    <p class="text-muted small mb-3">
      <?php if ($type === 'offer'): ?>
        Shfaq shërbimin tënd në feed-in publik. Klientët mund të të kontaktojnë drejtpërdrejt.
      <?php else: ?>
        Përshkruaj punën që ke nevojë. Punëtorët dhe kompanitë do ta shohin postimin tënd.
      <?php endif; ?>
    </p>

    <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
      <?php if (Auth::role() === 'admin'): ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Titulli</label>
        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['title'] ?? '') ?>" required maxlength="160">
        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Përshkrimi</label>
        <textarea name="description" rows="5" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                  required maxlength="5000"><?= e($old['description'] ?? '') ?></textarea>
        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?= e($errors['description']) ?></div><?php endif; ?>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">Kategoria</label>
          <select name="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" required>
            <option value="">— Zgjidh —</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($old['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= e($errors['category_id']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Qyteti</label>
          <select name="city_id" class="form-select <?= isset($errors['city_id']) ? 'is-invalid' : '' ?>" required>
            <option value="">— Zgjidh —</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($old['city_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['city_id'])): ?><div class="invalid-feedback"><?= e($errors['city_id']) ?></div><?php endif; ?>
        </div>
      </div>

      <?php if ($type === 'offer'): ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Çmim nga (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="price_from" class="form-control <?= isset($errors['price_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['price_from'] ?? '') ?>">
            <?php if (isset($errors['price_from'])): ?><div class="invalid-feedback"><?= e($errors['price_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="price_to" class="form-control <?= isset($errors['price_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['price_to'] ?? '') ?>">
            <?php if (isset($errors['price_to'])): ?><div class="invalid-feedback"><?= e($errors['price_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Orari i punës <span class="text-muted small">opsional</span></label>
          <input type="text" name="working_hours" class="form-control <?= isset($errors['working_hours']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['working_hours'] ?? '') ?>" maxlength="120"
                 placeholder="p.sh. Hënë–Shtunë 08:00–18:00">
          <?php if (isset($errors['working_hours'])): ?><div class="invalid-feedback"><?= e($errors['working_hours']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Preferencat e kontaktit <span class="text-muted small">opsional</span></label>
          <input type="text" name="contact_preferences" class="form-control <?= isset($errors['contact_preferences']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['contact_preferences'] ?? '') ?>" maxlength="200"
                 placeholder="p.sh. Vetëm WhatsApp pas orës 20:00">
          <?php if (isset($errors['contact_preferences'])): ?><div class="invalid-feedback"><?= e($errors['contact_preferences']) ?></div><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Buxheti nga (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="budget_from" class="form-control <?= isset($errors['budget_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['budget_from'] ?? '') ?>">
            <?php if (isset($errors['budget_from'])): ?><div class="invalid-feedback"><?= e($errors['budget_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€) <span class="text-muted small">opsional</span></label>
            <input type="number" step="0.01" min="0" name="budget_to" class="form-control <?= isset($errors['budget_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['budget_to'] ?? '') ?>">
            <?php if (isset($errors['budget_to'])): ?><div class="invalid-feedback"><?= e($errors['budget_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Afati <span class="text-muted small">opsional</span></label>
            <input type="date" name="deadline" class="form-control <?= isset($errors['deadline']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['deadline'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
            <?php if (isset($errors['deadline'])): ?><div class="invalid-feedback"><?= e($errors['deadline']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Urgjenca</label>
            <select name="urgency" class="form-select <?= isset($errors['urgency']) ? 'is-invalid' : '' ?>">
              <option value="">— Pa urgjencë —</option>
              <?php foreach (['low' => 'I ulët', 'normal' => 'Normal', 'high' => 'I lartë'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($old['urgency'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['urgency'])): ?><div class="invalid-feedback"><?= e($errors['urgency']) ?></div><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Foto <span class="text-muted small">deri 5, JPG/PNG/WEBP, ≤5MB secila</span></label>
        <input type="file" name="photos[]" class="form-control <?= isset($errors['photos']) ? 'is-invalid' : '' ?>"
               multiple accept="image/jpeg,image/png,image/webp">
        <?php if (isset($errors['photos'])): ?><div class="invalid-feedback"><?= e($errors['photos']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-helppy btn-lg" type="submit">
        <i class="bi bi-send"></i> Posto
      </button>
      <a href="<?= e(CONFIG['base_url']) ?>/posts" class="btn btn-link">Anulo</a>
    </form>
  </div>
</section>
```

- [ ] **Step 4: Register routes in `public/index.php`**

Just below the `$router->get('/posts', ...)` line you added in Task 3:

```php
$router->get('/posts/create',              [PostController::class,    'createForm']);
$router->post('/posts',                    [PostController::class,    'store']);
```

- [ ] **Step 5: Smoke test — create form renders**

```
curl -sk -o NUL -w "Form (logged out): %{http_code}\n" https://helppy.com.loc/posts/create
```

Expected: `302` (redirect to login) because `Auth::require()` triggers.

Then in browser:
1. Log in as `provider1@helppy.com` / `password`.
2. Open `/posts/create`. Expected: form heading "Posto ofertën tënde", offer-specific fields visible (price range, working hours, contact preferences). No `deadline` / `urgency` fields.
3. Log in as `client@helppy.com` / `password`.
4. Open `/posts/create`. Expected: heading "Posto kërkesën tënde", request-specific fields visible (budget, deadline, urgency). No `price_*` / `working_hours` fields.

- [ ] **Step 6: Smoke test — create a post**

As `client@helppy.com`:
1. Open `/posts/create`.
2. Fill: title="Tubat e ujit po pikojnë", description="Nën lavaman, urgjent, banja e dytë.", category=Hidraulike, city=Prishtine, urgency="I lartë".
3. Submit.
4. Expected: redirect to `/posts/{id}` (returns 200; detail view added in Task 5, so for now expect a "View not found: posts/show" exception — that's fine; the redirect URL itself proves the insert worked).
5. Verify in DB:
   ```
   "C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT id, type, title, status FROM posts ORDER BY id DESC LIMIT 1;"
   ```
   Expected: one row with `type=request`, the title you typed, `status=active`.

As `provider1@helppy.com`:
6. Open `/posts/create`. Fill in an offer with one or two photos. Submit.
7. Expected: post inserted; verify with the same SQL. Also check `post_photos`:
   ```
   "C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT id, post_id, filename, sort_order FROM post_photos ORDER BY id DESC LIMIT 5;"
   ```
   Expected: rows with `post_id` matching your new offer, filenames like `<32-hex>.jpg`. Check `public/uploads/` — the files exist.

- [ ] **Step 7: Smoke test — validation failure**

As any logged-in user:
1. Open `/posts/create`.
2. Submit with empty title, 5-character description, no category, no city.
3. Expected: form re-renders. Red `is-invalid` borders + Albanian error messages on each field. Old values preserved in the description field.

- [ ] **Step 8: Commit**

```
git add app/controllers/PostController.php app/views/posts/create.php public/index.php
git commit -m "feat(posts): create form, validation, multi-photo upload"
```

---

## Task 5 — Detail page

**Files:**
- Modify: `app/controllers/PostController.php` (add `show()`)
- Create: `app/views/posts/show.php`
- Modify: `public/index.php` (register `GET /posts/{id}`)
- Modify: `public/assets/css/style.css` (carousel + urgency badge styles)

- [ ] **Step 1: Add `show()` to `PostController`**

In `app/controllers/PostController.php`, after `store()`:

```php
    public function show(array $params = []): void {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) { $this->notFound(); return; }

        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }

        $viewerIsOwner = Auth::check() && (int)Auth::user()['id'] === (int)$post['user_id'];
        $viewerIsAdmin = Auth::role() === 'admin';

        // Hidden posts: visible to owner + admin only.
        if ($post['status'] === 'hidden' && !$viewerIsOwner && !$viewerIsAdmin) {
            $this->notFound();
            return;
        }

        Post::incrementViews($id);
        $photos = PostPhoto::forPost($id);

        $this->render('posts/show', [
            'title'         => $post['title'],
            'p'             => $post,
            'photos'        => $photos,
            'viewerIsOwner' => $viewerIsOwner,
            'viewerIsAdmin' => $viewerIsAdmin,
        ]);
    }
```

- [ ] **Step 2: Write `app/views/posts/show.php`**

```php
<?php
$typeLabel = $p['type'] === 'offer' ? 'Ofertë' : 'Kërkesë';
$typeClass = $p['type'] === 'offer' ? 'post-badge-offer' : 'post-badge-request';

$priceLabel = '';
if ($p['type'] === 'offer' && ($p['price_from'] !== null || $p['price_to'] !== null)) {
    $from = $p['price_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['price_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['price_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = trim($from . ($from && $to ? ' – ' : '') . $to);
} elseif ($p['type'] === 'request' && ($p['budget_from'] !== null || $p['budget_to'] !== null)) {
    $from = $p['budget_from'] !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_from'], 2, '.', ''), '0'), '.') : '';
    $to   = $p['budget_to']   !== null ? '€' . rtrim(rtrim(number_format((float)$p['budget_to'],   2, '.', ''), '0'), '.') : '';
    $priceLabel = 'Buxhet: ' . trim($from . ($from && $to ? ' – ' : '') . $to);
}

$urgencyLabels = ['low' => 'I ulët', 'normal' => 'Normal', 'high' => 'I lartë'];
$phoneRaw = !empty($p['author_phone']) ? preg_replace('/[^0-9+]/', '', $p['author_phone']) : '';
?>
<section class="container py-4">
  <?php if ($p['status'] === 'hidden'): ?>
    <div class="alert alert-warning">
      <i class="bi bi-eye-slash"></i> Ky postim është i fshehur nga administratori. Vetëm ti dhe administratori e shihni.
    </div>
  <?php elseif ($p['status'] === 'closed'): ?>
    <div class="alert alert-secondary">
      <i class="bi bi-check2-circle"></i> Ky postim është i mbyllur.
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <?php if ($photos): ?>
        <div id="postCarousel" class="carousel slide post-carousel mb-3" data-bs-ride="carousel">
          <div class="carousel-inner">
            <?php foreach ($photos as $i => $ph): ?>
              <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <img src="<?= e(CONFIG['upload_url'] . '/' . rawurlencode($ph['filename'])) ?>" alt="<?= e($p['title']) ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (count($photos) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#postCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#postCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon"></span>
            </button>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="profile-card">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="post-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
          <?php if ($p['type'] === 'request' && !empty($p['urgency'])): ?>
            <span class="urgency-badge urgency-<?= e($p['urgency']) ?>">
              <i class="bi bi-exclamation-triangle"></i> <?= e($urgencyLabels[$p['urgency']]) ?>
            </span>
          <?php endif; ?>
        </div>
        <h1 class="mb-1"><?= e($p['title']) ?></h1>
        <p class="text-muted small mb-3">
          <i class="bi <?= e($p['category_icon'] ?: 'bi-tag') ?>"></i> <?= e($p['category_name']) ?>
          &middot; <i class="bi bi-geo-alt"></i> <?= e($p['city_name']) ?>
          &middot; <i class="bi bi-person"></i> <?= e($p['author_name']) ?>
          &middot; <?= e(date('d M Y', strtotime((string)$p['created_at']))) ?>
        </p>

        <div class="mb-3"><?= nl2br(e($p['description'])) ?></div>

        <?php if ($p['type'] === 'offer'): ?>
          <ul class="list-unstyled small">
            <?php if ($priceLabel): ?><li><strong>Çmimi:</strong> <?= e($priceLabel) ?></li><?php endif; ?>
            <?php if (!empty($p['working_hours'])): ?><li><strong>Orari:</strong> <?= e($p['working_hours']) ?></li><?php endif; ?>
            <?php if (!empty($p['contact_preferences'])): ?><li><strong>Kontakti:</strong> <?= e($p['contact_preferences']) ?></li><?php endif; ?>
          </ul>
        <?php else: ?>
          <ul class="list-unstyled small">
            <?php if ($priceLabel): ?><li><?= e($priceLabel) ?></li><?php endif; ?>
            <?php if (!empty($p['deadline'])): ?>
              <li><strong>Afati:</strong> <?= e(date('d M Y', strtotime((string)$p['deadline']))) ?></li>
            <?php endif; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="profile-card mb-3">
        <p class="small text-muted mb-1">Kontakto</p>
        <p class="mb-2"><strong><?= e($p['author_name']) ?></strong></p>
        <?php if (!empty($p['author_phone'])): ?>
          <a class="btn btn-helppy w-100 mb-2" href="tel:<?= e($phoneRaw) ?>">
            <i class="bi bi-telephone-fill"></i> Telefono Tani
          </a>
        <?php endif; ?>
        <?php if (!empty($p['author_email'])): ?>
          <a class="btn btn-helppy-outline w-100" href="mailto:<?= e($p['author_email']) ?>">
            <i class="bi bi-envelope"></i> Email
          </a>
        <?php endif; ?>
      </div>

      <?php if ($viewerIsOwner || $viewerIsAdmin): ?>
        <div class="profile-card">
          <p class="small text-muted mb-2">Veprime</p>
          <?php if ($viewerIsOwner): ?>
            <a class="btn btn-link w-100 text-start" href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/edit">
              <i class="bi bi-pencil"></i> Modifiko
            </a>
            <?php if ($p['status'] === 'active'): ?>
              <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/close" class="d-inline w-100">
                <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                <button class="btn btn-link w-100 text-start" type="submit">
                  <i class="bi bi-check2-circle"></i> Mbyll postimin
                </button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($viewerIsAdmin && $p['status'] !== 'hidden'): ?>
            <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/posts/<?= (int)$p['id'] ?>/hide" class="d-inline w-100">
              <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
              <button class="btn btn-link w-100 text-start" type="submit">
                <i class="bi bi-eye-slash"></i> Fsheh
              </button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/delete" class="d-inline w-100"
                onsubmit="return confirm('Të fshihet ky postim përgjithmonë?');">
            <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
            <button class="btn btn-link text-danger w-100 text-start" type="submit">
              <i class="bi bi-trash"></i> Fshi
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 3: Append carousel + urgency badge styles to `public/assets/css/style.css`**

```css
.post-carousel { background: #000; border-radius: var(--helppy-radius); overflow: hidden; }
.post-carousel .carousel-item img { width: 100%; height: clamp(260px, 50vw, 460px); object-fit: contain; background: #000; display: block; }

.urgency-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 9px; border-radius: var(--helppy-radius-pill);
  font-size: 11px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
}
.urgency-low    { background: #e0e7ff; color: #3730a3; }
.urgency-normal { background: #fef3c7; color: #92400e; }
.urgency-high   { background: #fee2e2; color: #991b1b; }
```

- [ ] **Step 4: Register `GET /posts/{id}` in `public/index.php`**

Add to the POSTS block:

```php
$router->get('/posts/{id}',                [PostController::class,    'show']);
```

Make sure it's AFTER `/posts/create` (literal segments match before `{id}`).

- [ ] **Step 5: Smoke test**

Open the post you created in Task 4 step 6 (`https://helppy.com.loc/posts/{id}`). Expected:
- Heading shows the title.
- Type badge "Ofertë" or "Kërkesë" colored accordingly.
- If photos were uploaded → carousel renders with controls when ≥ 2 photos.
- Description visible.
- Right column shows author name + green "Telefono Tani" + outlined "Email".
- If viewing as the post's author → "Veprime" panel with Modifiko / Mbyll / Fshi.
- If viewing as admin (`admin@helppy.com`) → also see "Fsheh".

Open a non-existent post id (e.g. `/posts/99999`). Expected: 404 page renders.

- [ ] **Step 6: Commit**

```
git add app/controllers/PostController.php app/views/posts/show.php public/index.php public/assets/css/style.css
git commit -m "feat(posts): detail page with photo carousel and contact actions"
```

---

## Task 6 — Edit form, update handler, photo add/remove

**Files:**
- Modify: `app/controllers/PostController.php` (add `editForm`, `update`)
- Create: `app/views/posts/edit.php`
- Modify: `public/index.php` (register `GET /posts/{id}/edit` + `POST /posts/{id}`)

- [ ] **Step 1: Add `editForm()` to `PostController`**

In `app/controllers/PostController.php`, after `show()`:

```php
    public function editForm(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }

        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        $this->render('posts/edit', [
            'title'      => 'Modifiko postimin',
            'p'          => $post,
            'photos'     => PostPhoto::forPost($id),
            'categories' => Category::all(),
            'cities'     => City::all(),
            'old'        => $post, // pre-fill from current row
            'errors'     => [],
        ]);
    }
```

- [ ] **Step 2: Add `update()` to `PostController`**

In `app/controllers/PostController.php`, after `editForm()`:

```php
    public function update(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }

        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        $type = $post['type']; // type cannot change after creation

        // Collect raw input (mirrors store(); same validation, no type switch)
        $title       = trim((string)Request::post('title', ''));
        $description = trim((string)Request::post('description', ''));
        $categoryId  = (int)Request::post('category_id', 0);
        $cityId      = (int)Request::post('city_id', 0);
        $priceFrom   = self::numOrNull(Request::post('price_from'));
        $priceTo     = self::numOrNull(Request::post('price_to'));
        $workingHours       = trim((string)Request::post('working_hours', ''));
        $contactPreferences = trim((string)Request::post('contact_preferences', ''));
        $budgetFrom  = self::numOrNull(Request::post('budget_from'));
        $budgetTo    = self::numOrNull(Request::post('budget_to'));
        $deadline    = trim((string)Request::post('deadline', ''));
        $urgency     = trim((string)Request::post('urgency', ''));

        $errors = [];
        if (mb_strlen($title) < 4 || mb_strlen($title) > 160)            $errors['title'] = 'Titulli duhet të jetë 4-160 karaktere.';
        if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) $errors['description'] = 'Përshkrimi duhet të jetë 20-5000 karaktere.';
        if ($categoryId <= 0 || !Category::find($categoryId))            $errors['category_id'] = 'Zgjidh një kategori.';
        if ($cityId <= 0     || !City::find($cityId))                    $errors['city_id'] = 'Zgjidh një qytet.';
        if ($priceFrom !== null && ($priceFrom < 0 || $priceFrom > 1000000)) $errors['price_from'] = 'Çmim i pavlefshëm.';
        if ($priceTo   !== null && ($priceTo   < 0 || $priceTo   > 1000000)) $errors['price_to']   = 'Çmim i pavlefshëm.';
        if ($priceFrom !== null && $priceTo !== null && $priceFrom > $priceTo) $errors['price_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';
        if ($budgetFrom !== null && ($budgetFrom < 0 || $budgetFrom > 1000000)) $errors['budget_from'] = 'Buxhet i pavlefshëm.';
        if ($budgetTo   !== null && ($budgetTo   < 0 || $budgetTo   > 1000000)) $errors['budget_to']   = 'Buxhet i pavlefshëm.';
        if ($budgetFrom !== null && $budgetTo !== null && $budgetFrom > $budgetTo) $errors['budget_to'] = 'Maksimumi duhet të jetë ≥ minimumi.';
        if (mb_strlen($workingHours)       > 120) $errors['working_hours']       = 'Orari deri 120 karaktere.';
        if (mb_strlen($contactPreferences) > 200) $errors['contact_preferences'] = 'Preferencat deri 200 karaktere.';
        if ($type === 'request') {
            if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) $errors['deadline'] = 'Datë e pavlefshme.';
            if ($urgency !== '' && !in_array($urgency, ['low','normal','high'], true)) $errors['urgency'] = 'Urgjenca e pavlefshme.';
        }

        // Handle photo deletions (checkbox array: delete_photo[]=id)
        $deleteIds = array_map('intval', (array)Request::post('delete_photo', []));

        // Handle new photo uploads
        $files = self::collectUploadedPhotos('photos');
        $existingCount = count(PostPhoto::forPost($id)) - count($deleteIds);
        if ($existingCount + count($files) > 5) {
            $errors['photos'] = 'Maksimumi 5 foto për postim.';
        }
        foreach ($files as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) { $errors['photos'] = 'Gabim në ngarkim të fotos.'; break; }
            if ($f['size'] > 5 * 1024 * 1024) { $errors['photos'] = 'Çdo foto duhet të jetë nën 5MB.'; break; }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { $errors['photos'] = 'Formati i lejuar: JPG, PNG, WEBP.'; break; }
        }

        if ($errors) {
            $this->render('posts/edit', [
                'title'      => 'Modifiko postimin',
                'p'          => $post,
                'photos'     => PostPhoto::forPost($id),
                'categories' => Category::all(),
                'cities'     => City::all(),
                'old'        => array_merge($post, $_POST),
                'errors'     => $errors,
            ]);
            return;
        }

        // Build data
        $data = [
            'title'         => $title,
            'description'   => $description,
            'category_id'   => $categoryId,
            'city_id'       => $cityId,
        ];
        if ($type === 'offer') {
            $data['price_from']          = $priceFrom;
            $data['price_to']            = $priceTo;
            $data['working_hours']       = $workingHours !== '' ? $workingHours : null;
            $data['contact_preferences'] = $contactPreferences !== '' ? $contactPreferences : null;
        } else {
            $data['budget_from'] = $budgetFrom;
            $data['budget_to']   = $budgetTo;
            $data['deadline']    = $deadline !== '' ? $deadline : null;
            $data['urgency']     = $urgency  !== '' ? $urgency  : null;
        }

        // Apply changes
        try {
            DB::pdo()->beginTransaction();
            Post::update($id, $data);

            // Delete chosen photos
            foreach ($deleteIds as $pid) {
                $fn = PostPhoto::removeOne($pid, $id);
                if ($fn) {
                    $p = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $fn;
                    if (is_file($p)) @unlink($p);
                }
            }

            // Add new photos (continue sort_order numbering)
            $existing = PostPhoto::forPost($id);
            $nextOrder = $existing ? (max(array_column($existing, 'sort_order')) + 1) : 0;
            $savedFilenames = [];
            foreach ($files as $f) {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                $ext  = self::extForMime($mime);
                $name = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $name;
                if (!is_dir(CONFIG['upload_dir'])) mkdir(CONFIG['upload_dir'], 0775, true);
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    throw new RuntimeException('Photo move failed');
                }
                $savedFilenames[] = $name;
                PostPhoto::add($id, $name, $nextOrder++);
            }
            DB::pdo()->commit();
        } catch (Throwable $ex) {
            DB::pdo()->rollBack();
            foreach ($savedFilenames ?? [] as $n) @unlink(CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $n);
            $this->flash('danger', 'Gabim teknik gjatë ruajtjes së ndryshimeve.');
            $this->redirect('/posts/' . $id . '/edit');
            return;
        }

        $this->flash('success', 'Postimi u përditësua.');
        $this->redirect('/posts/' . $id);
    }
```

- [ ] **Step 3: Write `app/views/posts/edit.php`**

```php
<section class="container py-4">
  <div class="form-card mx-auto" style="max-width: 720px;">
    <h1 class="section-title">Modifiko postimin</h1>

    <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">

      <div class="mb-3">
        <label class="form-label">Titulli</label>
        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
               value="<?= e((string)($old['title'] ?? '')) ?>" required maxlength="160">
        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Përshkrimi</label>
        <textarea name="description" rows="5" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" required maxlength="5000"><?= e((string)($old['description'] ?? '')) ?></textarea>
        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?= e($errors['description']) ?></div><?php endif; ?>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">Kategoria</label>
          <select name="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" required>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($old['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= e($errors['category_id']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Qyteti</label>
          <select name="city_id" class="form-select <?= isset($errors['city_id']) ? 'is-invalid' : '' ?>" required>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($old['city_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['city_id'])): ?><div class="invalid-feedback"><?= e($errors['city_id']) ?></div><?php endif; ?>
        </div>
      </div>

      <?php if ($p['type'] === 'offer'): ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Çmim nga (€)</label>
            <input type="number" step="0.01" min="0" name="price_from" class="form-control <?= isset($errors['price_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['price_from'] ?? '')) ?>">
            <?php if (isset($errors['price_from'])): ?><div class="invalid-feedback"><?= e($errors['price_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€)</label>
            <input type="number" step="0.01" min="0" name="price_to" class="form-control <?= isset($errors['price_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['price_to'] ?? '')) ?>">
            <?php if (isset($errors['price_to'])): ?><div class="invalid-feedback"><?= e($errors['price_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Orari i punës</label>
          <input type="text" name="working_hours" class="form-control <?= isset($errors['working_hours']) ? 'is-invalid' : '' ?>"
                 value="<?= e((string)($old['working_hours'] ?? '')) ?>" maxlength="120">
          <?php if (isset($errors['working_hours'])): ?><div class="invalid-feedback"><?= e($errors['working_hours']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Preferencat e kontaktit</label>
          <input type="text" name="contact_preferences" class="form-control <?= isset($errors['contact_preferences']) ? 'is-invalid' : '' ?>"
                 value="<?= e((string)($old['contact_preferences'] ?? '')) ?>" maxlength="200">
          <?php if (isset($errors['contact_preferences'])): ?><div class="invalid-feedback"><?= e($errors['contact_preferences']) ?></div><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Buxheti nga (€)</label>
            <input type="number" step="0.01" min="0" name="budget_from" class="form-control <?= isset($errors['budget_from']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['budget_from'] ?? '')) ?>">
            <?php if (isset($errors['budget_from'])): ?><div class="invalid-feedback"><?= e($errors['budget_from']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Deri (€)</label>
            <input type="number" step="0.01" min="0" name="budget_to" class="form-control <?= isset($errors['budget_to']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['budget_to'] ?? '')) ?>">
            <?php if (isset($errors['budget_to'])): ?><div class="invalid-feedback"><?= e($errors['budget_to']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Afati</label>
            <input type="date" name="deadline" class="form-control <?= isset($errors['deadline']) ? 'is-invalid' : '' ?>"
                   value="<?= e((string)($old['deadline'] ?? '')) ?>" min="<?= date('Y-m-d') ?>">
            <?php if (isset($errors['deadline'])): ?><div class="invalid-feedback"><?= e($errors['deadline']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Urgjenca</label>
            <select name="urgency" class="form-select <?= isset($errors['urgency']) ? 'is-invalid' : '' ?>">
              <option value="">— Pa urgjencë —</option>
              <?php foreach (['low' => 'I ulët', 'normal' => 'Normal', 'high' => 'I lartë'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($old['urgency'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['urgency'])): ?><div class="invalid-feedback"><?= e($errors['urgency']) ?></div><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($photos): ?>
        <div class="mb-3">
          <label class="form-label">Foto ekzistuese</label>
          <div class="row g-2">
            <?php foreach ($photos as $ph): ?>
              <div class="col-4 col-md-3">
                <div class="position-relative">
                  <img src="<?= e(CONFIG['upload_url'] . '/' . rawurlencode($ph['filename'])) ?>" class="img-fluid rounded" alt="">
                  <label class="form-check position-absolute" style="top:6px; left:6px; background:#fff; padding:2px 6px; border-radius:6px;">
                    <input type="checkbox" class="form-check-input" name="delete_photo[]" value="<?= (int)$ph['id'] ?>">
                    <span class="form-check-label small">Fshi</span>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Shto foto të reja <span class="text-muted small">(deri 5 gjithsej)</span></label>
        <input type="file" name="photos[]" class="form-control <?= isset($errors['photos']) ? 'is-invalid' : '' ?>"
               multiple accept="image/jpeg,image/png,image/webp">
        <?php if (isset($errors['photos'])): ?><div class="invalid-feedback"><?= e($errors['photos']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-helppy btn-lg" type="submit">Ruaj ndryshimet</button>
      <a href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>" class="btn btn-link">Anulo</a>
    </form>
  </div>
</section>
```

- [ ] **Step 4: Register routes**

In `public/index.php`, in the POSTS block, add:

```php
$router->get('/posts/{id}/edit',           [PostController::class,    'editForm']);
$router->post('/posts/{id}',               [PostController::class,    'update']);
```

- [ ] **Step 5: Smoke test**

As the user who created a post:
1. Open `/posts/{id}` → click "Modifiko" → form pre-fills with current values.
2. Change title and description, optionally check "Fshi" on a photo, upload a new one, submit.
3. Expected: redirect to `/posts/{id}` with success flash. Title/description updated. The checked photo's file is deleted from `public/uploads/` and removed from `post_photos`. New uploaded photo appears in the carousel.

As a different non-admin user (e.g. log in as `client@helppy.com` and try to edit a provider's post):
4. Open the edit URL directly. Expected: 403 page.

- [ ] **Step 6: Commit**

```
git add app/controllers/PostController.php app/views/posts/edit.php public/index.php
git commit -m "feat(posts): edit form, update handler, photo add/remove"
```

---

## Task 7 — Close, delete, admin hide

**Files:**
- Modify: `app/controllers/PostController.php` (add `close`, `destroy`)
- Modify: `app/controllers/AdminController.php` (add `hidePost`)
- Modify: `public/index.php` (register the 3 POST routes)

- [ ] **Step 1: Add `close()` + `destroy()` to `PostController`**

In `app/controllers/PostController.php`, after `update()`:

```php
    public function close(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }
        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }
        Post::close($id);
        $this->flash('success', 'Postimi u mbyll.');
        $this->redirect('/posts/' . $id);
    }

    public function destroy(array $params = []): void {
        Auth::require();
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }
        $uid = (int)Auth::user()['id'];
        if ((int)$post['user_id'] !== $uid && Auth::role() !== 'admin') {
            http_response_code(403); View::render('errors/403', []); return;
        }

        // Remove photo files from disk
        $filenames = PostPhoto::removeAllForPost($id);
        foreach ($filenames as $fn) {
            $p = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $fn;
            if (is_file($p)) @unlink($p);
        }
        Post::delete($id);

        $this->flash('success', 'Postimi u fshi.');
        $this->redirect('/posts');
    }
```

- [ ] **Step 2: Add `hidePost()` to `AdminController`**

Find `AdminController.php`. Inside the class, after the last method:

```php
    public function hidePost(array $params = []): void {
        Auth::require('admin');
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0 || !Post::find($id)) { $this->notFound(); return; }
        Post::hide($id);
        $this->flash('success', 'Postimi u fshi nga publiku.');
        $this->redirect('/admin/posts');
    }
```

- [ ] **Step 3: Register routes in `public/index.php`**

Add to the POSTS block:

```php
$router->post('/posts/{id}/close',         [PostController::class,    'close']);
$router->post('/posts/{id}/delete',        [PostController::class,    'destroy']);
```

In the ADMIN block, add:

```php
$router->post('/admin/posts/{id}/hide',    [AdminController::class,   'hidePost']);
```

- [ ] **Step 4: Smoke test**

As the post owner:
1. Open `/posts/{id}` → click "Mbyll postimin" → redirect back, "u mbyll" flash. The detail page now shows the "Ky postim është i mbyllur" alert.
2. Open `/posts` → the closed post is NOT in the feed (Post::feed filters by `status='active'`).
3. Open `/posts/{id}` directly → still visible (closed posts are visible at the detail URL, just hidden from feed).
4. Click "Fshi" → confirm dialog → after confirmation redirected to `/posts` with "u fshi" flash. The post and its photos are gone from disk and DB.

As admin (`admin@helppy.com`):
5. View any active post. Click "Fsheh". Redirected to `/admin/posts` (page made in Task 8 — for now you'll see a 404 if Task 8 isn't done; in that case verify via DB:
   ```
   "C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT id, status FROM posts ORDER BY id DESC LIMIT 5;"
   ```
   Expected: target post has `status=hidden`.

- [ ] **Step 5: Commit**

```
git add app/controllers/PostController.php app/controllers/AdminController.php public/index.php
git commit -m "feat(posts): close, delete, admin hide"
```

---

## Task 8 — Admin posts page

**Files:**
- Modify: `app/controllers/AdminController.php` (add `posts()`)
- Create: `app/views/admin/posts.php`
- Modify: `public/index.php` (register `GET /admin/posts`)
- Modify: `app/views/admin/index.php` (add a "Postimet" link)

- [ ] **Step 1: Add `posts()` to `AdminController`**

In `app/controllers/AdminController.php`, after the last method:

```php
    public function posts(array $params = []): void {
        Auth::require('admin');
        $posts = Post::allForAdmin(200);
        $this->render('admin/posts', [
            'title' => 'Postimet — Admin',
            'posts' => $posts,
        ]);
    }
```

- [ ] **Step 2: Write `app/views/admin/posts.php`**

```php
<section class="container py-4">
  <h1 class="section-title">Postimet (<?= count($posts) ?>)</h1>

  <?php if (!$posts): ?>
    <p class="text-muted">Asnjë postim.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Titulli</th>
            <th>Tipi</th>
            <th>Autori</th>
            <th>Kategori / Qytet</th>
            <th>Data</th>
            <th>Statusi</th>
            <th>Veprime</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($posts as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td>
                <a href="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>">
                  <?= e($p['title']) ?>
                </a>
              </td>
              <td>
                <span class="post-badge <?= $p['type'] === 'offer' ? 'post-badge-offer' : 'post-badge-request' ?>" style="position: static;">
                  <?= $p['type'] === 'offer' ? 'Ofertë' : 'Kërkesë' ?>
                </span>
              </td>
              <td><?= e($p['author_name']) ?> <small class="text-muted">(<?= e($p['author_role']) ?>)</small></td>
              <td><small><?= e($p['category_name']) ?> &middot; <?= e($p['city_name']) ?></small></td>
              <td><small><?= e(date('d M Y', strtotime((string)$p['created_at']))) ?></small></td>
              <td>
                <?php if ($p['status'] === 'active'):  ?><span class="badge text-bg-success">aktiv</span>
                <?php elseif ($p['status'] === 'closed'): ?><span class="badge text-bg-secondary">mbyllur</span>
                <?php else: ?><span class="badge text-bg-warning">fshehur</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['status'] !== 'hidden'): ?>
                  <form method="post" action="<?= e(CONFIG['base_url']) ?>/admin/posts/<?= (int)$p['id'] ?>/hide" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                    <button class="btn btn-sm btn-outline-warning" type="submit">Fsheh</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= e(CONFIG['base_url']) ?>/posts/<?= (int)$p['id'] ?>/delete" class="d-inline"
                      onsubmit="return confirm('Fshi postimin përgjithmonë?');">
                  <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Fshi</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
```

- [ ] **Step 3: Register route in `public/index.php`**

In the ADMIN block, add:

```php
$router->get('/admin/posts',               [AdminController::class,   'posts']);
```

- [ ] **Step 4: Add link in `app/views/admin/index.php`**

Open `app/views/admin/index.php`. Find the existing admin nav cards/links. Add a card/link to `/admin/posts`:

```php
<a class="btn btn-helppy-outline" href="<?= e(CONFIG['base_url']) ?>/admin/posts">
  <i class="bi bi-postcard"></i> Postimet
</a>
```

(Place it next to the existing buttons for Providers / Categories. If the file's structure differs, adapt — the goal is one visible link to the new page.)

- [ ] **Step 5: Smoke test**

As `admin@helppy.com`:
1. Open `/admin`. Click "Postimet". Expected: table of all posts (active + closed + hidden), with badges showing status. Hidden posts have a yellow `fshehur` badge.
2. Click "Fsheh" on an active post. Expected: redirect back, status becomes `fshehur`.
3. Click "Fshi" on any post → confirm → row disappears, photos deleted from disk.

- [ ] **Step 6: Commit**

```
git add app/controllers/AdminController.php app/views/admin/posts.php public/index.php app/views/admin/index.php
git commit -m "feat(posts): admin posts list + hide action"
```

---

## Task 9 — Feed filters and category chip strip

**Files:**
- Modify: `app/views/posts/index.php` (add filter form + chip strip)

The controller already reads `type`, `category`, `city` query params from Task 3; the model already supports them. This task is purely the view.

- [ ] **Step 1: Update `app/views/posts/index.php`**

Replace the file entirely with:

```php
<?php
$activeType = $filters['type'] ?? null;
$activeCategory = $filters['category_id'] ?? null;
$activeCity = $filters['city_id'] ?? null;
$qsBase = function(array $override) use ($activeType, $activeCategory, $activeCity) {
    $qs = [];
    $type     = $override['type']     ?? $activeType;
    $category = $override['category'] ?? $activeCategory;
    $city     = $override['city']     ?? $activeCity;
    if ($type)     $qs['type'] = $type;
    if ($category) $qs['category'] = (int)$category;
    if ($city)     $qs['city'] = (int)$city;
    return $qs ? '?' . http_build_query($qs) : '';
};
?>
<section class="container py-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h1 class="section-title mb-0">Postimet</h1>
    <?php if (Auth::check() && (Auth::role() === 'provider' || Auth::role() === 'client' || Auth::role() === 'admin')): ?>
      <a class="btn btn-helppy" href="<?= e(CONFIG['base_url']) ?>/posts/create">
        <i class="bi bi-plus-lg"></i> Posto
      </a>
    <?php endif; ?>
  </div>

  <!-- Type tabs -->
  <div class="post-type-tabs mb-3">
    <a class="post-type-tab <?= !$activeType ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['type' => false]) ?>">Të gjitha</a>
    <a class="post-type-tab <?= $activeType === 'offer' ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['type' => 'offer']) ?>">Ofertat</a>
    <a class="post-type-tab <?= $activeType === 'request' ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['type' => 'request']) ?>">Kërkesat</a>
  </div>

  <!-- City + category dropdowns -->
  <form method="get" action="<?= e(CONFIG['base_url']) ?>/posts" class="helppy-search mb-3">
    <?php if ($activeType): ?><input type="hidden" name="type" value="<?= e($activeType) ?>"><?php endif; ?>
    <span class="location-icon"><i class="bi bi-geo-alt-fill"></i></span>
    <select name="city" class="form-select" aria-label="Qyteti">
      <option value="">Të gjitha qytetet</option>
      <?php foreach ($cities as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $activeCity == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="helppy-search-divider d-none d-sm-block"></div>
    <select name="category" class="form-select" aria-label="Kategoria">
      <option value="">Të gjitha kategoritë</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= $activeCategory == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-helppy" type="submit">
      <i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Kërko</span>
    </button>
  </form>

  <!-- Category chip strip -->
  <div class="category-chips mb-3">
    <a class="category-chip <?= !$activeCategory ? 'is-active' : '' ?>"
       href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['category' => false]) ?>">
      Të gjitha
    </a>
    <?php foreach ($categories as $cat): ?>
      <a class="category-chip <?= $activeCategory == $cat['id'] ? 'is-active' : '' ?>"
         href="<?= e(CONFIG['base_url']) ?>/posts<?= $qsBase(['category' => $cat['id']]) ?>">
        <?php if (!empty($cat['icon'])): ?><i class="bi <?= e($cat['icon']) ?>"></i><?php endif; ?>
        <?= e($cat['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$posts): ?>
    <div class="empty-state">
      <i class="bi bi-postcard"></i>
      <p>Asnjë postim nuk përputhet me filtrat.</p>
      <p><a href="<?= e(CONFIG['base_url']) ?>/posts">Hiq filtrat</a></p>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($posts as $p): ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <?php View::partial('post-card', ['p' => $p]); ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
```

- [ ] **Step 2: Append type-tab styles to `public/assets/css/style.css`**

```css
.post-type-tabs {
  display: inline-flex;
  background: #fff;
  padding: 4px;
  border-radius: var(--helppy-radius-pill);
  box-shadow: var(--helppy-shadow);
  gap: 2px;
}
.post-type-tab {
  padding: 8px 18px;
  border-radius: var(--helppy-radius-pill);
  font-weight: 600;
  font-size: 14px;
  color: var(--helppy-text);
  text-decoration: none;
  transition: background .15s, color .15s;
}
.post-type-tab:hover { color: var(--helppy-green-dark); }
.post-type-tab.is-active { background: var(--helppy-green); color: #fff; }
```

- [ ] **Step 3: Smoke test**

1. Open `/posts`. Verify type tabs render, all three look right (default tab = "Të gjitha" active).
2. Click "Ofertat" → URL becomes `/posts?type=offer`, only offer cards show.
3. Click a category chip → URL becomes `/posts?type=offer&category=X`, both filters active, fewer cards.
4. Submit the city dropdown → URL adds `city=Y`, results narrow further.
5. Use "Të gjitha" chip → category param removed, type still active.

Verify in the URL bar that combining type + city + category produces a clean URL like `/posts?type=offer&category=1&city=1`.

- [ ] **Step 4: Commit**

```
git add app/views/posts/index.php public/assets/css/style.css
git commit -m "feat(posts): type tabs, city/category filters, chip strip"
```

---

## Task 10 — Time-ago helper, final polish, end-to-end smoke

**Files:**
- Modify: `app/core/View.php` (add `timeAgoSq()` helper)
- Modify: `app/views/partials/post-card.php` (show time-ago)
- Modify: `app/views/posts/show.php` (show time-ago in meta line)

- [ ] **Step 1: Add `timeAgoSq()` helper to `app/core/View.php`**

Below the `e()` function at the bottom of `app/core/View.php`, add:

```php
/** Albanian relative time helper: "tani", "para X minutash/orësh/ditësh". */
function timeAgoSq(string $dt): string {
    $t = strtotime($dt);
    if ($t === false) return '';
    $diff = time() - $t;
    if ($diff < 60)         return 'tani';
    if ($diff < 3600)       { $n = (int)floor($diff / 60);    return "para $n " . ($n === 1 ? 'minute' : 'minutash'); }
    if ($diff < 86400)      { $n = (int)floor($diff / 3600);  return "para $n " . ($n === 1 ? 'ore'    : 'orësh'); }
    if ($diff < 30 * 86400) { $n = (int)floor($diff / 86400); return "para $n " . ($n === 1 ? 'dite'   : 'ditësh'); }
    return date('d M Y', $t);
}
```

- [ ] **Step 2: Update `app/views/partials/post-card.php`**

Replace the `<p class="post-card-author">` line with:

```php
    <p class="post-card-author">
      <i class="bi bi-person"></i> <?= e($p['author_name']) ?>
      <span class="text-muted">&middot; <?= e(timeAgoSq((string)$p['created_at'])) ?></span>
    </p>
```

- [ ] **Step 3: Update `app/views/posts/show.php`**

In the meta line, change:

```php
&middot; <?= e(date('d M Y', strtotime((string)$p['created_at']))) ?>
```

to:

```php
&middot; <?= e(timeAgoSq((string)$p['created_at'])) ?>
```

- [ ] **Step 4: End-to-end smoke test**

Open `https://helppy.com.loc/posts` and walk through:

| Test | Steps | Expected |
|---|---|---|
| Empty feed | New DB, no posts | Empty state renders |
| Logged-in nav | Log in as any role | "Postimet" link visible |
| Create offer  | Log in as provider, create with 2 photos | Redirects to detail; carousel works |
| Create request | Log in as client, create with deadline + urgency | Detail shows urgency badge red/yellow |
| Filter type   | Click Ofertat tab | Only offers in feed |
| Filter chip   | Click a category chip | Only that category visible |
| Filter city   | Submit city dropdown | URL adds city= and narrows |
| Edit          | Owner clicks Modifiko | Form pre-fills; can change + delete + add photos |
| Close         | Owner clicks Mbyll | Post leaves feed; detail shows "mbyllur" alert |
| Delete        | Owner clicks Fshi → OK | Files + rows gone |
| Admin hide    | Admin clicks Fsheh on detail | Status=hidden; only owner + admin see |
| 404           | Visit /posts/99999 | 404 page |
| Validation    | Submit empty title in create form | Red borders + Albanian errors |

Run:

```
curl -sk -o NUL -w "/posts: %{http_code}\n" https://helppy.com.loc/posts
curl -sk -o NUL -w "/posts/create: %{http_code} (302 if logged out, 200 if in)\n" https://helppy.com.loc/posts/create
```

Check Apache error log for any new entries during the smoke test:

```
tail -50 C:/laragon/bin/apache/httpd-2.4.62-240904-win64-VS17/logs/error.log
```

Expected: no new `[core:error]` or `[php:error]` lines from your activity.

- [ ] **Step 5: Commit**

```
git add app/core/View.php app/views/partials/post-card.php app/views/posts/show.php
git commit -m "feat(posts): Albanian time-ago helper in feed and detail"
```

---

## Done — Phase B Complete

The post system is live: feed, create, detail, edit, close, delete, admin hide. Ready for Phase C (subscription gating on `offer` creation).

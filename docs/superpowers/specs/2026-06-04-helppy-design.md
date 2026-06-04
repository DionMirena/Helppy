# Helppy.com — MVP Design

**Date:** 2026-06-04
**Author:** Dion (with Claude)
**Status:** Approved, ready for implementation planning

## 1. Overview

Helppy.com is a Kosovo-focused web platform that connects residents who need home repair services (plumbing, paint, electrical, cleaning, carpentry, masonry, lawn) with individual workers and companies who provide those services.

A client opens the site, picks their city and a service category, sees a list of providers in that city, opens a provider's profile, and either taps a `tel:` link to call them or (after logging in) leaves a review.

This document defines the **MVP** — the first complete, working version that ships with a real database, real authentication, and a real admin panel. Several features described in the original project document are explicitly deferred to Phase 2 (see §10).

## 2. Goals & non-goals

### Goals

- A clickable, working website served from a local Laragon stack.
- Real MySQL database; no mocked data in the running app.
- Three user roles with distinct dashboards: **client**, **provider**, **admin**.
- Self-serve provider registration with no manual approval step.
- Location-based search constrained to Kosovo cities (predefined list).
- One-photo provider profiles with default-avatar fallback.
- Review system gated to registered clients (one review per client per provider).
- Admin can manage providers, categories, reviews, and toggle a "premium" flag.

### Non-goals (Phase 2)

- Real-time chat
- Payment gateway / recurring billing
- GPS / map-based geolocation
- Email verification, password reset, SMS
- Provider portfolio galleries
- Multi-language support

## 3. Tech stack

- **Server:** PHP 8.x running under Laragon (Apache).
- **Database:** MySQL (Laragon default).
- **Frontend:** Bootstrap 5 (CDN), vanilla JavaScript, no build step.
- **DB access:** PDO with prepared statements only.
- **Auth:** PHP sessions, `password_hash()` / `password_verify()`.
- **Templating:** plain PHP includes via a tiny `View` helper.

No Composer, no npm, no framework — the goal is "open Laragon, drop the folder in, run two SQL files, it works."

## 4. UI / brand

- **Primary green:** `#4a6741` (header, primary buttons, active category chip).
- **Logo:** wrench icon + "Helppy" wordmark, white-on-green.
- **Layout:** mobile-first responsive (Bootstrap 5 grid).
- **Patterns:** rounded category chips, card-list provider results, prominent "Telefono Tani" green button.
- **Language:** Albanian only (Kosovo Albanian).

## 5. Database schema

```sql
-- cities (seeded)
CREATE TABLE cities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
);
-- Seed: Prishtine, Prizren, Peje, Gjakove, Mitrovice, Gjilan, Ferizaj,
--       Vushtrri, Suhareke, Rahovec, Malisheve, Drenas, Skenderaj,
--       Podujeve, Lipjan, Fushe Kosove, Obiliq, Kamenice, Decan, Istog

-- categories (seeded)
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  icon VARCHAR(80) NULL  -- bootstrap-icons class name
);
-- Seed: Hidraulike, Boje, Elektrike, Pastrim, Stollari, Murature, Lendine

-- users (clients, providers, admins all live here)
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
);

-- providers (1-to-1 extension of users where role='provider')
CREATE TABLE providers (
  user_id INT PRIMARY KEY,
  profession VARCHAR(120) NOT NULL,
  bio TEXT NULL,
  photo VARCHAR(255) NULL,         -- filename in /public/uploads/
  is_company TINYINT(1) NOT NULL DEFAULT 0,
  company_name VARCHAR(160) NULL,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  views INT NOT NULL DEFAULT 0,    -- incremented on GET /provider/{id}
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- provider_categories (many-to-many)
CREATE TABLE provider_categories (
  provider_id INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (provider_id, category_id),
  FOREIGN KEY (provider_id) REFERENCES providers(user_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- reviews
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider_id INT NOT NULL,
  client_id INT NOT NULL,
  rating TINYINT NOT NULL,         -- 1..5
  comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_provider_client (provider_id, client_id),
  FOREIGN KEY (provider_id) REFERENCES providers(user_id) ON DELETE CASCADE,
  FOREIGN KEY (client_id)   REFERENCES users(id)        ON DELETE CASCADE,
  CHECK (rating BETWEEN 1 AND 5)
);
```

The seeded admin (in `seed.sql`) has email `admin@helppy.com` and a known password (documented in the seed file comment, to be changed in production).

## 6. Routes

All routes go through `/public/index.php`. The `.htaccess` rewrites every non-existing file to `index.php?url=...`. The Router class parses `url` and dispatches to a controller method.

```
PUBLIC
  GET  /                              HomeController@index
  GET  /search                        SearchController@results   ?city=&category=
  GET  /provider/{id}                 ProviderController@show
  GET  /register                      AuthController@registerForm
  POST /register                      AuthController@register
  GET  /login                         AuthController@loginForm
  POST /login                         AuthController@login
  POST /logout                        AuthController@logout

CLIENT (requires role=client)
  GET  /client/dashboard              ClientController@dashboard
  POST /provider/{id}/review          ReviewController@store

PROVIDER (requires role=provider)
  GET  /provider/dashboard            ProviderController@dashboard
  POST /provider/edit                 ProviderController@update
  POST /provider/photo                ProviderController@uploadPhoto
  POST /provider/categories           ProviderController@updateCategories

ADMIN (requires role=admin)
  GET  /admin                         AdminController@index
  GET  /admin/providers               AdminController@providers
  POST /admin/providers/{id}/active   AdminController@toggleActive
  POST /admin/providers/{id}/premium  AdminController@togglePremium
  GET  /admin/categories              AdminController@categories
  POST /admin/categories              AdminController@createCategory
  POST /admin/categories/{id}/delete  AdminController@deleteCategory
  POST /admin/reviews/{id}/delete     AdminController@deleteReview
```

URL collision note: `/provider/{id}` (public show) and `/provider/dashboard` (provider's own page) — the router resolves "dashboard" as a literal segment before treating it as an id.

## 7. File layout

```
/Helppy.com/
  /public/                       <- Laragon document root SHOULD point here
    index.php                    front controller
    .htaccess                    rewrite rules
    /assets/css/style.css
    /assets/js/app.js
    /assets/img/logo.svg
    /assets/img/default-avatar.svg
    /uploads/                    provider photos (gitignored, .gitkeep'd)
  /app/
    /core/
      Router.php                 parses URL, dispatches
      Request.php                wraps $_GET/$_POST/$_FILES + CSRF helper
      Controller.php             base class with render(), redirect(), csrf()
      Auth.php                   login(), logout(), user(), require(role)
      View.php                   render('home/index', $data) helper
      DB.php                     PDO singleton
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
      layout.php                 master wrapper (nav + footer)
      /home/        index.php
      /search/      results.php
      /provider/    show.php, dashboard.php, edit.php
      /auth/        login.php, register.php
      /client/      dashboard.php
      /admin/       index.php, providers.php, categories.php
      /partials/    nav.php, footer.php, provider-card.php, review-card.php, flash.php
  /config/
    config.php                   DB creds, BASE_URL, UPLOAD_DIR
  /db/
    schema.sql                   CREATE TABLE statements above
    seed.sql                     cities, categories, admin, 6 sample providers
  /docs/superpowers/specs/2026-06-04-helppy-design.md   (this file)
  README.md                      setup steps for Laragon + phpMyAdmin
  .gitignore                     /public/uploads/*, /config/config.php (use .example)
```

If the user cannot point Laragon's doc root at `/public/`, a fallback `/index.php` at project root will include `/public/index.php` so `http://localhost/Helppy.com` keeps working. This is set up out of the box.

## 8. Key flows

### 8.1 Home + search
Home shows: (a) a city dropdown ("Shkruani lokacionin tuaj"), (b) the 7 category chips (Hidraulike, Boje, Elektrike, Pastrim, Stollari, Murature, Lendine), and (c) a "Punonjësit më të afërt" featured strip of up to 8 providers ordered by `is_premium DESC, RAND()`. Clicking a category chip submits a GET to `/search?city=X&category=Y` which renders a card list. If no city is selected, search returns providers in all cities for that category. Empty result page shows "Asnjë punëtor i gjetur" with a CTA to broaden filters.

### 8.2 Provider profile (`/provider/{id}`)
Big circular photo (or default avatar), name, profession, company badge if `is_company`, city, average rating + count, list of categories as chips, bio, prominent green "Telefono Tani" button that is `<a href="tel:...">`, then reviews list ordered newest-first. If the viewer is a logged-in client who hasn't already reviewed this provider, a "Lër vlerësim" form appears below.

### 8.3 Registration
Single `/register` form with a radio at the top: **Klient** / **Punues** / **Kompani**. Common fields: name, email, password, phone, city. If Punues or Kompani: also profession + which categories (multi-select). On submit:
- Insert into `users` with the chosen role.
- If provider: insert into `providers` (with `is_company` = 1 if "Kompani") and into `provider_categories`.
- Auto-login the user.
- Redirect: clients → `/`, providers → `/provider/dashboard` to upload photo and finish bio.

### 8.4 Provider dashboard
Shows current profile preview + an edit form (profession, bio, city, categories, phone), separate photo upload form, and a stats strip (average rating, review count, profile views — `views` is incremented on every public profile load).

### 8.5 Client dashboard
Shows account info + the reviews the client has left (each editable/deletable by the same client).

### 8.6 Admin
- `/admin` — counts: total users, providers, clients, reviews.
- `/admin/providers` — table with toggle buttons for `is_active` and `is_premium`.
- `/admin/categories` — list + add form + delete button (categories with assigned providers cannot be deleted; show error).
- `/admin/reviews` is implicitly handled by a delete button visible to admin on any review.

## 9. Security & quality

- **Passwords:** `password_hash(PASSWORD_DEFAULT)` on registration, `password_verify()` on login.
- **SQL:** every query is a prepared statement via PDO. No string concatenation for SQL.
- **XSS:** `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')` wrapped in `e()` helper, used on every echoed user-supplied string.
- **CSRF:** session-stored token, hidden `_csrf` field on every POST form, validated in `Request::verifyCsrf()` before controllers run for POSTs.
- **File uploads:**
  - Whitelist MIME types: `image/jpeg`, `image/png`, `image/webp`.
  - Whitelist extensions: `.jpg`, `.jpeg`, `.png`, `.webp`.
  - Max size: 2 MB.
  - Stored with `bin2hex(random_bytes(16))` filename + extension.
  - Old photo deleted on replace.
- **Auth gates:** `Auth::require('client')`, `Auth::require('provider')`, `Auth::require('admin')` called at the top of protected controller methods; redirect to `/login` if unauthenticated, 403 page if wrong role.
- **Sessions:** `session_regenerate_id(true)` after login. `session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax'])`.
- **Errors:** display errors OFF in `config.php` example for production; logged to a file.

## 10. Out of scope (explicitly Phase 2)

- Real-time chat between client and provider — would require WebSockets or long polling + message table + notifications.
- Payment processing for premium tier — admin manually toggles `is_premium` for now.
- Map / GPS sorting — city is the only location dimension.
- Email verification, password reset, SMS notifications.
- Provider portfolio gallery (multiple photos).
- Multi-language (English/Serbian).

These are listed here so we don't accidentally scope-creep into them during implementation.

## 11. Acceptance criteria

The MVP is "done" when a user can:

1. Run `db/schema.sql` then `db/seed.sql` in phpMyAdmin and have a working database with cities, categories, an admin, and sample providers.
2. Open `http://localhost/Helppy.com` (or whichever host Laragon assigns) and see the Albanian home page with logo, search, categories, and featured providers from the seed.
3. Register a new provider account, log in, upload a photo, edit bio + categories, and have that provider immediately searchable.
4. Register a new client account, search by city + category, find the provider above, click "Telefono Tani" (opens dialer on mobile), and submit a 1-5 star review with comment.
5. Log in as `admin@helppy.com`, see all providers, toggle one as premium and verify they now appear first in search and on the home featured strip, delete a review, add a new category, and have it appear in the home page chips.

If any of those five flows breaks, the MVP is not done.

## 12. Open items (decide during implementation)

- Bootstrap 5 via CDN vs vendored locally. Default: CDN for speed of setup; swap to local if offline development is a concern.
- Exact set of seeded Kosovo cities — list in §5 is a draft, can be tweaked without affecting design.

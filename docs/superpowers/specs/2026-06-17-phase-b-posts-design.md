# Helppy.com — Phase B: Posts (Offers + Requests)

**Date:** 2026-06-17
**Author:** Dion (with Claude)
**Status:** Approved, ready for implementation planning
**Depends on:** MVP (2026-06-04-helppy-design.md)
**Phase order:** A (visual redesign) → **B (posts)** → C (payments) → D (bookings)

## 1. Overview

Phase B adds a two-sided post system to Helppy.com:

- **Provider offers** — a punëtor or kompani publishes a service offering ("I do hidraulike in Prishtinë, €20–40/hour, here are photos of past work"). Clients browse and call.
- **Client requests** — a client publishes a job they need done ("Tubat e ujit po pikojnë në banjë, urgjent, Prishtinë"). Providers browse and call.

Both kinds of posts share a feed at `/posts` with filters. Each post has a detail page with photos, full description, and a `tel:` "Telefono Tani" button plus a `mailto:` link to the author. No in-app chat, no contact form.

This phase ships posts **without monetization gating** — every provider can post freely. Phase C will add the subscription gate.

## 2. Goals & non-goals

### Goals

- One post feed with type filter (Të gjitha / Ofertat / Kërkesat), category filter, city filter.
- Provider can create offer posts. Client can create request posts. One role per post type — no cross-posting.
- Multi-photo upload per post (≤ 5).
- Owner can edit, close (mark complete), or delete their own post. Admin can hide any post.
- Auto-publish on create. Admin has a list of recent posts and can hide problem ones.
- Mobile-first UI consistent with the existing Helppy visual style.

### Non-goals (later phases / out of scope)

- Subscription gating on provider offers → Phase C.
- Bookings/calendar reservation from a post → Phase D.
- In-app messaging, ratings/reviews on posts, paid post boosting, comments on posts.
- Email notifications when a post matches a saved category.
- Cross-city posts (each post belongs to exactly one city).

## 3. Tech stack

No changes from the MVP stack:

- PHP 8.x under Laragon Apache.
- MySQL (Laragon default).
- Bootstrap 5 via CDN.
- PDO prepared statements; same `DB::q()` helper.
- Session auth; same `Auth::check()`, `Auth::role()`.
- File uploads with `bin2hex(random_bytes(16))` filename, stored under `public/uploads/`.

## 4. Database schema

Two new tables. All charsets `utf8mb4` / collation `utf8mb4_unicode_ci`, engine InnoDB.

```sql
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

Migration file: `db/migrations/003_posts.sql`. Safe to re-run (`CREATE TABLE IF NOT EXISTS` not used because we want failures to be loud; idempotency handled by the migration runner / manual check).

## 5. Routes

Added to `public/index.php`:

| Method | Path | Controller::action | Auth |
|---|---|---|---|
| GET  | `/posts`                          | `PostController::index`   | public |
| GET  | `/posts/create`                   | `PostController::createForm` | client or provider |
| POST | `/posts`                          | `PostController::store`   | client or provider |
| GET  | `/posts/{id}`                     | `PostController::show`    | public |
| GET  | `/posts/{id}/edit`                | `PostController::editForm`| owner |
| POST | `/posts/{id}`                     | `PostController::update`  | owner |
| POST | `/posts/{id}/close`               | `PostController::close`   | owner |
| POST | `/posts/{id}/delete`              | `PostController::destroy` | owner or admin |
| POST | `/admin/posts/{id}/hide`          | `AdminController::hidePost` | admin |
| GET  | `/admin/posts`                    | `AdminController::posts`  | admin |

`{id}` must match `\d+`. The router already supports `{param}` placeholders.

## 6. Authorization rules

| Action | Guest | Client | Provider | Admin |
|---|:---:|:---:|:---:|:---:|
| Browse feed / view post | ✓ | ✓ | ✓ | ✓ |
| Create `request` post | — | ✓ | — | ✓ |
| Create `offer` post   | — | — | ✓ | ✓ |
| Edit / close own post | — | ✓ | ✓ | ✓ |
| Delete own post       | — | ✓ | ✓ | ✓ |
| Delete any post       | — | — | — | ✓ |
| Hide any post         | — | — | — | ✓ |

Role-to-type mapping is enforced server-side in `PostController::store` and `update` — the client picks nothing; the type is derived from `Auth::role()`. Admin acting on their own behalf can post either type (rare; mainly for seed/demo data).

Hidden posts are invisible to everyone except admin and the owner.

## 7. Photo upload

- Max **5 photos per post**, validated server-side.
- Allowed MIME types: `image/jpeg`, `image/png`, `image/webp`.
- Max file size: **5 MB per photo**.
- Filename: `bin2hex(random_bytes(16))` + extension matching MIME (`.jpg`, `.png`, `.webp`).
- Saved under `public/uploads/`.
- Records inserted into `post_photos` with `sort_order` matching upload order (0 = first photo, shown as thumbnail).
- Edit form supports adding new photos and deleting existing ones. Deleted photo files are removed from disk on success.
- If MIME / size validation fails, none of the photos are saved and the form is re-rendered with an error.

No image resizing in this phase — original files served as-is. Large file sizes will be addressed only if a real-world client problem appears.

## 8. UI & views

### New nav link
`Postimet` — added between the brand and the auth links, visible to everyone.

### Feed page (`/posts`)
- Top: filter bar — type pill toggle (Të gjitha / Ofertat / Kërkesat), city dropdown, category dropdown, `Kërko` button. Reuses the `helppy-search` component pattern.
- Below: category chip row (same component as homepage).
- Then: card grid (2 cols ≥ sm). Empty state: "Asnjë postim ende. Bëhu i pari!" with a `/posts/create` link if logged in.
- Sort: `created_at DESC`. No pagination in v1 — show last 60 posts. (Pagination added when the feed grows beyond that.)

### Post card
- First photo (or category-icon placeholder) on top.
- Type badge in the top-right corner (`Ofertë` green, `Kërkesë` orange).
- Title (2 lines, ellipsis).
- Category · city.
- Price/budget range if set (`€20 – €40` or `Buxhet: €100 – €200`).
- Posted-by name + "ka 2 ditë" (time-ago helper).
- Whole card is a link to `/posts/{id}`.

### Post detail (`/posts/{id}`)
- Photo carousel (Bootstrap, with thumbnail strip).
- Title + type badge.
- Category · city · posted-by + profile link · time-ago.
- Full description (`nl2br`).
- Type-specific block:
  - Offer → working hours, contact preferences, price range.
  - Request → deadline (with `bi-calendar`), urgency badge, budget range.
- Sticky action bar at the bottom (mobile) or right column (desktop): `tel:` button + `mailto:` link.
- Owner actions (if logged in as owner): Edit / Close / Delete.
- Admin actions (if logged in as admin): Hide / Delete.

### Create form (`/posts/create`)
- Type is implicit from `Auth::role()`. Heading: "Posto ofertën tënde" or "Posto kërkesën tënde".
- Common fields first: title, description, category, city.
- Then type-specific block below.
- Photo upload: drop area + thumbnails preview (vanilla JS, no library). Up to 5.
- Submit button: `Posto`. On success → redirect to `/posts/{id}` with flash `Postimi u publikua.`.

### Admin posts page (`/admin/posts`)
- Table: ID, title, type, author (with role), category/city, created, status, actions (Hide / Delete).

## 9. Models

New `Post.php` and `PostPhoto.php` in `app/models/`. Mirrors the existing static-method style (`Post::find`, `Post::feed($filters, $limit)`, `Post::create($data, $photos)`, `Post::update`, `Post::close`, `Post::hide`, `Post::delete`, `Post::ownedBy($postId, $userId)`).

`Post::feed` is the only complex query — joins `posts` with `categories`, `cities`, `users`, plus a left-join to the first photo per post for the card thumbnail. WHERE clauses build dynamically from `$filters` (type, category, city).

## 10. Validation & error handling

- Title: 4–160 chars.
- Description: 20–5000 chars.
- Category, city: must reference existing rows.
- Price/budget: optional; if provided, `from ≤ to`, both non-negative, ≤ 1,000,000.
- Working hours: free-form, ≤ 120 chars.
- Deadline: ≥ today.
- Urgency: must be one of the enum values.
- Photos: see §7.
- All inputs run through `e()` on output. All inserts use prepared statements (existing `DB::q` pattern).

Validation failures re-render the form with field-level error messages and the user's input preserved. No client-side JS validation in v1 — server-side only, errors render as Bootstrap `is-invalid` + `invalid-feedback`.

## 11. Out of scope (explicit non-goals)

- **Subscription gating** on offer creation — Phase C.
- **Booking** widget on a post — Phase D.
- **Email notifications** on new matching posts or replies.
- **Saved searches** / favorites.
- **Reporting** posts as a user — admin only sees and acts.
- **Pagination** beyond a 60-post LIMIT.
- **Image resizing / thumbnails.**
- **Search by free text** — only by type/category/city in v1.

## 12. Risks & open questions

- **Storage growth**: every post can have 5 × 5MB = 25MB of photos. Phase C should bring an admin storage-cleanup view or per-post photo limit tightening if growth is an issue.
- **Photo orphans**: if a transaction partially fails during create, photo files on disk may exist without DB rows. Mitigation: do photo uploads + DB inserts in the same `try { … } catch { … rollback + unlink }` block.
- **Time-ago in Albanian**: needs a small helper for "ka X minuta/orë/ditë". Implementation detail, not architectural.
- **Owner of a hidden post viewing it**: open — should they see a "hidden by admin" banner, or 404? Picked: banner + read-only view.

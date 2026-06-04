# Helppy.com

Local Kosovo home-services marketplace. PHP 8.x + MySQL + Bootstrap 5, running on Laragon.

Clients search by city + category, view provider profiles, leave reviews, and tap a `Telefono Tani` button to call. Providers self-register (as individual or company), upload a profile photo, list categories they serve. An admin can manage providers (toggle active/premium), categories, and moderate reviews.

UI is in Albanian (Kosovo).

## Setup (Laragon)

1. Drop this folder in `C:\laragon\www\Helppy.com\`.
2. Start Laragon (Apache + MySQL).
3. Open phpMyAdmin and create the database + load schema + seed. From the SQL tab:
   ```sql
   CREATE DATABASE IF NOT EXISTS helppy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE helppy;
   ```
   Then paste the contents of `db/schema.sql` and run, then `db/seed.sql` and run.

   Or from the command line:
   ```bash
   "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -uroot -e "CREATE DATABASE IF NOT EXISTS helppy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -uroot helppy < db\schema.sql
   "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -uroot helppy < db\seed.sql
   ```
4. Copy `config/config.example.php` to `config/config.php` (already done if you cloned this repo with the example committed — `config.php` itself is gitignored). Defaults match Laragon's out-of-the-box MySQL setup (`root`, no password).
5. Open `http://localhost/Helppy.com/public/` in your browser.

If you set up a Laragon virtual host (e.g. `helppy.test`) pointing at the `/public/` folder, also:
- Change `RewriteBase` in `public/.htaccess` to `/`.
- Update `base_url` and `upload_url` in `config/config.php` to your vhost URL (e.g. `http://helppy.test`).

## Seeded accounts

| Role     | Email                       | Password   |
|----------|-----------------------------|------------|
| Admin    | `admin@helppy.com`          | `admin123` |
| Client   | `client@helppy.com`         | `password` |
| Provider | `provider1@helppy.com` ... `provider6@helppy.com` | `password` |

Six sample providers are seeded across multiple cities and categories, including two with `is_premium = 1` so the home page featured strip has something to rank.

## Email verification setup (REQUIRED for login)

Every login and registration sends a 6-digit code to the user's email. Without working Gmail SMTP credentials, **no one can log in** — including admin. Set this up before opening the site.

### Generate a Gmail App Password

1. Open https://myaccount.google.com/security
2. Turn on "2-Step Verification" if it isn't already on.
3. Click "App passwords" (search if you can't find it). Name it "Helppy", app type "Mail", device "Other (Windows Computer)".
4. Copy the 16-character password Google shows you. You will not see it again.

### Configure Helppy

Edit `config/config.php`:

```php
'mailer' => [
    'host'     => 'smtp.gmail.com',
    'port'     => 587,
    'username' => 'youremail@gmail.com',          // your Gmail
    'password' => 'xxxxxxxxxxxxxxxx',             // the 16-char App Password
    'from'     => 'Helppy.com <youremail@gmail.com>',
    'reply_to' => '',
    'timeout'  => 10,
],
```

The `from` address must use the same Gmail you authenticate with — otherwise Gmail rejects the message.

### Update the seeded admin email

The seeded `admin@helppy.com` cannot receive emails. Before logging in as admin:

```sql
UPDATE users SET email = 'youremail@gmail.com' WHERE id = 1;
```

After that, log in normally — a 2FA code arrives at your Gmail every login.

### Recovery if you lock yourself out

If Gmail breaks or the App Password is revoked:
- Non-admin user: edit their `users.email` in phpMyAdmin to a working Gmail.
- Admin: same — edit `users.email` to a Gmail you currently control.

## Folder structure

```
public/        document root — .htaccess, index.php (front controller), assets/, uploads/
app/core/      Router, Controller, View, Request, Auth, DB (all PDO + prepared statements)
app/controllers/, app/models/, app/views/
config/        config.example.php (copy to config.php)
db/            schema.sql, seed.sql
docs/superpowers/specs/   the design spec
docs/superpowers/plans/   the implementation plan
```

## What's in / out

**In MVP:** Authentication (3 roles: client / provider / admin), provider profiles (with categories, photo upload, bio, "Telefono Tani" `tel:` button), city + category search, reviews + 5-star ratings, admin panel (manage providers, categories, reviews), CSRF protection, PDO prepared statements, file upload validation (MIME + size).

**Phase 2 (not built):** Real-time chat between client and provider, payment gateway / recurring billing for premium tier (admin manually toggles `is_premium` for now), GPS/map-based sorting, email verification, multi-language.

## Spec & plan

- Design spec: `docs/superpowers/specs/2026-06-04-helppy-design.md`
- Implementation plan: `docs/superpowers/plans/2026-06-04-helppy-mvp.md`

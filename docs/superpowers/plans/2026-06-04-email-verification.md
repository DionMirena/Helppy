# Email-Based 2FA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add email-based two-factor authentication for every user — clients, providers, companies, and admin — triggered at registration and on every login. 6-digit code sent via Gmail SMTP. Pure PHP, no Composer.

**Architecture:** A small SMTP client (`Mailer.php`) handles transport. A `Verification` helper owns all DB column writes for the code lifecycle. The registration flow auto-logs the user in and redirects to `/verify-email` (signup verification). The login flow does NOT auto-login — it stores the user id as `$_SESSION['pending_2fa_uid']`, emails a fresh code, and redirects to `/verify-email`; only after the code is accepted does the real `Auth::login()` fire. All authenticated routes gain a verification guard so users with `email_verified=0` cannot reach role pages by URL-typing.

**Tech Stack:** PHP 8.x, MySQL, PDO, sessions, Gmail SMTP over STARTTLS port 587. No external libraries.

**Spec:** `docs/superpowers/specs/2026-06-04-email-verification-design.md`

---

## Critical prerequisite

**Before Task 1**, the engineer must:

1. Generate a Gmail App Password (Google Account → Security → 2-Step Verification → App passwords → name "Helppy"). Save the 16-character password.
2. Decide which Gmail address will be the "from" address for the site (can be the same as the app password).
3. Update the seeded `admin@helppy.com` row to point at a real Gmail before Task 8 runs, otherwise admin login will break (admin can't receive verification codes).

```sql
-- Run BEFORE Task 8 (login 2FA goes live):
UPDATE users SET email = '<your-real-gmail>@gmail.com' WHERE id = 1;
```

If you skip this and migrate to 2FA-on-login, you will be locked out of admin until you edit `users.email` in phpMyAdmin.

---

## File Structure (locked)

```
NEW   app/core/Mailer.php                       SMTP client (~150 LOC)
NEW   app/core/Verification.php                 code lifecycle helper
NEW   app/views/auth/verify.php                 6-digit code form
NEW   db/migrations/2026-06-04-email-verification.sql
NEW   storage/.gitkeep                          mail.log destination
MOD   app/core/Auth.php                         pendingUid / setPending / clearPending
MOD   app/controllers/AuthController.php        4 new methods + register/login changes
MOD   app/controllers/ProviderController.php    verification guard + 404-on-unverified
MOD   app/controllers/ClientController.php      verification guard
MOD   app/controllers/ReviewController.php      verification guards
MOD   app/controllers/AdminController.php       verification guards (8 methods)
MOD   app/models/Provider.php                   filter on email_verified
MOD   app/views/admin/providers.php             "Email i verifikuar" column
MOD   config/config.example.php                 mailer block
MOD   config/config.php                         mailer block
MOD   db/schema.sql                             5 new columns
MOD   public/index.php                          4 new routes
MOD   README.md                                 App Password setup
MOD   .gitignore                                storage/*.log
```

**Testing approach:** No PHPUnit (no Composer). Each task ends with explicit manual smoke-test commands (curl + PHP built-in server + MySQL inspection). The engineer must run them and see expected output before committing.

---

## Task 1: Mailer config + storage dir + Mailer.php

**Goal:** Send a real email via PHP CLI before touching the database or any routes. Validates SMTP credentials in isolation.

**Files:**
- Create: `storage/.gitkeep`
- Create: `app/core/Mailer.php`
- Modify: `config/config.example.php`
- Modify: `config/config.php`
- Modify: `.gitignore`

- [ ] **Step 1: Add `mailer` block to both config files**

Append to `config/config.example.php` (before the closing `];`):

```php
    'mailer' => [
        'host'     => 'smtp.gmail.com',
        'port'     => 587,
        'username' => '',          // YOUR Gmail address
        'password' => '',          // App Password (16 chars, NOT your real password)
        'from'     => 'Helppy.com <noreply@helppy.com>',
        'reply_to' => '',
        'timeout'  => 10,
    ],
```

Same block to `config/config.php` — except fill in `username` with your Gmail and `password` with your App Password. Also set `from` to use your Gmail (e.g. `'Helppy.com <yourname@gmail.com>'`) so Gmail doesn't reject the mail because the From doesn't match.

- [ ] **Step 2: Create storage directory**

Run:
```bash
mkdir -p storage
touch storage/.gitkeep
```

- [ ] **Step 3: Update .gitignore**

Append to `.gitignore`:

```
storage/*.log
```

- [ ] **Step 4: Write `app/core/Mailer.php`**

```php
<?php
declare(strict_types=1);

final class Mailer {
    /** Send a plain-text email via SMTP. Throws RuntimeException on any failure. */
    public static function send(string $to, string $subject, string $bodyText): bool {
        $cfg = CONFIG['mailer'];
        if (($cfg['username'] ?? '') === '' || ($cfg['password'] ?? '') === '') {
            throw new RuntimeException('Mailer not configured (mailer.username/password empty in config.php)');
        }

        $sock = @stream_socket_client(
            "tcp://{$cfg['host']}:{$cfg['port']}",
            $errno, $errstr,
            (float)($cfg['timeout'] ?? 10),
            STREAM_CLIENT_CONNECT
        );
        if (!$sock) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($sock, (int)($cfg['timeout'] ?? 10));

        try {
            self::readResponse($sock, 220);
            self::cmd($sock, "EHLO helppy.com", 250);

            // STARTTLS for port 587
            if ((int)$cfg['port'] === 587) {
                self::cmd($sock, "STARTTLS", 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException("STARTTLS handshake failed");
                }
                self::cmd($sock, "EHLO helppy.com", 250);
            }

            // AUTH LOGIN
            self::cmd($sock, "AUTH LOGIN", 334);
            self::cmd($sock, base64_encode($cfg['username']), 334);
            self::cmd($sock, base64_encode($cfg['password']), 235);

            // Envelope
            $fromAddr = self::extractAddr($cfg['from']);
            self::cmd($sock, "MAIL FROM:<$fromAddr>", 250);
            self::cmd($sock, "RCPT TO:<$to>", [250, 251]);
            self::cmd($sock, "DATA", 354);

            // Headers + body
            $headers = [
                'From: ' . $cfg['from'],
                'To: ' . $to,
                'Subject: ' . self::encodeHeader($subject),
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(8)) . '@helppy.com>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            if (!empty($cfg['reply_to'])) $headers[] = 'Reply-To: ' . $cfg['reply_to'];

            // Dot-stuff body (escape lines starting with ".")
            $body = preg_replace('/^\./m', '..', $bodyText);

            fwrite($sock, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            self::readResponse($sock, 250);

            self::cmd($sock, "QUIT", 221);
            return true;
        } finally {
            @fclose($sock);
        }
    }

    /** Send a command and require a status code (or list of acceptable codes). */
    private static function cmd($sock, string $line, $expected): string {
        fwrite($sock, $line . "\r\n");
        return self::readResponse($sock, $expected);
    }

    /** Read one SMTP response (may be multi-line). Throws unless status matches $expected. */
    private static function readResponse($sock, $expected): string {
        $resp = '';
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) throw new RuntimeException("SMTP read failed");
            $resp .= $line;
            // Multi-line: "250-..." continues, "250 ..." ends
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $code = (int)substr($resp, 0, 3);
        $ok = is_array($expected) ? in_array($code, $expected, true) : $code === (int)$expected;
        if (!$ok) {
            throw new RuntimeException("SMTP unexpected response: " . trim($resp));
        }
        return $resp;
    }

    /** Extract email address from "Name <addr@x.com>" or just "addr@x.com". */
    private static function extractAddr(string $field): string {
        if (preg_match('/<([^>]+)>/', $field, $m)) return $m[1];
        return trim($field);
    }

    /** RFC 2047 Q-encode header value if it contains non-ASCII. */
    private static function encodeHeader(string $s): string {
        if (preg_match('/[\x80-\xff]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }
}
```

- [ ] **Step 5: Send a real test email via PHP CLI**

Replace `YOUR_INBOX@gmail.com` with a real Gmail address you can check (your own).

```bash
cd 'C:/laragon/www/Helppy.com'
php -r "
define('APP_ROOT', __DIR__);
define('CONFIG', require 'config/config.php');
require 'app/core/Mailer.php';
try {
    Mailer::send('YOUR_INBOX@gmail.com', 'Helppy SMTP test', 'If you see this, SMTP works.');
    echo \"SENT\n\";
} catch (Throwable \$e) {
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
}
"
```

Expected:
- Prints `SENT` to the console.
- Within ~10 seconds, a real email arrives in YOUR_INBOX with subject "Helppy SMTP test" and body "If you see this, SMTP works."

**If you see "SMTP unexpected response: 535 ... Authentication failed":** The App Password is wrong or 2-Step Verification isn't on for your Gmail. Generate a new one and update `config/config.php`.

**If you see "SMTP connect failed":** Network/firewall problem. Check that port 587 isn't blocked.

**Do not proceed until this step prints `SENT` and the email arrives.** All later tasks depend on working SMTP.

- [ ] **Step 6: Commit**

```bash
git add config/config.example.php .gitignore storage/.gitkeep app/core/Mailer.php
git commit -m "feat: SMTP mailer with Gmail STARTTLS support"
```

Note: `config/config.php` is gitignored — your actual credentials stay local.

---

## Task 2: DB migration + schema.sql update

**Files:**
- Create: `db/migrations/2026-06-04-email-verification.sql`
- Modify: `db/schema.sql`

- [ ] **Step 1: Write migration file**

`db/migrations/2026-06-04-email-verification.sql`:

```sql
-- Add email verification columns to users.
-- Default email_verified=1 so any rows inserted via SQL (admin maintenance, seed)
-- are trusted. The registration flow explicitly sets =0 for new users.
ALTER TABLE users
  ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN verification_code CHAR(6) NULL,
  ADD COLUMN verification_expires_at DATETIME NULL,
  ADD COLUMN verification_attempts TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN verification_last_sent_at DATETIME NULL;

-- Make existing seeded users explicitly verified.
UPDATE users SET email_verified = 1;
```

- [ ] **Step 2: Apply migration**

```bash
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy < db/migrations/2026-06-04-email-verification.sql
```

- [ ] **Step 3: Verify schema**

```bash
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "DESCRIBE users;"
```

Expected: the 5 new columns appear with these types — `email_verified tinyint(1) DEFAULT '1'`, `verification_code char(6) DEFAULT NULL`, `verification_expires_at datetime DEFAULT NULL`, `verification_attempts tinyint DEFAULT '0'`, `verification_last_sent_at datetime DEFAULT NULL`.

```bash
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT id, name, email_verified FROM users;"
```

Expected: all 8 rows show `email_verified = 1`.

- [ ] **Step 4: Update `db/schema.sql` so fresh installs include these columns**

In `db/schema.sql`, find the `CREATE TABLE users` block and modify it to add the columns. Replace the entire `CREATE TABLE users (...)` block with:

```sql
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
  email_verified TINYINT(1) NOT NULL DEFAULT 1,
  verification_code CHAR(6) NULL,
  verification_expires_at DATETIME NULL,
  verification_attempts TINYINT NOT NULL DEFAULT 0,
  verification_last_sent_at DATETIME NULL,
  FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 5: Commit**

```bash
git add db/migrations/ db/schema.sql
git commit -m "feat: add email verification columns to users"
```

---

## Task 3: Verification helper

**Files:**
- Create: `app/core/Verification.php`

- [ ] **Step 1: Write the helper**

```php
<?php
declare(strict_types=1);

final class Verification {
    /** Generates a fresh 6-digit code, resets attempts to 0, sets expires_at=NOW+15min, last_sent_at=NOW. Returns the code. */
    public static function generateCodeFor(int $userId): string {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::q(
            'UPDATE users SET verification_code=?, verification_expires_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), verification_attempts=0, verification_last_sent_at=NOW() WHERE id=?',
            [$code, $userId]
        );
        return $code;
    }

    /** Sends the current verification code via Mailer. Rethrows on transport failure. */
    public static function send(int $userId): void {
        $row = DB::q('SELECT name, email, verification_code FROM users WHERE id=?', [$userId])->fetch();
        if (!$row || $row['verification_code'] === null) {
            throw new RuntimeException("No active code for user $userId");
        }
        $subject = 'Helppy.com — kodi i verifikimit';
        $body = strtr(
            "Pershendetje {NAME},\n\n" .
            "Kodi juaj i verifikimit per Helppy.com eshte:\n\n" .
            "  {CODE}\n\n" .
            "Ky kod vlen 15 minuta. Nese nuk e keni kerkuar ju, injorojeni kete email.\n\n" .
            "Faleminderit,\n" .
            "Ekipi Helppy.com\n",
            ['{NAME}' => $row['name'], '{CODE}' => $row['verification_code']]
        );
        Mailer::send($row['email'], $subject, $body);
    }

    /** Compares supplied code with stored. Returns true on success (and clears code columns).
     *  On failure: increments attempts; if >=5, nulls the code (forces resend). */
    public static function verify(int $userId, string $code): bool {
        $row = DB::q(
            'SELECT verification_code, verification_expires_at, verification_attempts FROM users WHERE id=?',
            [$userId]
        )->fetch();
        if (!$row || $row['verification_code'] === null) return false;
        if (strtotime($row['verification_expires_at']) < time()) return false;

        if (hash_equals((string)$row['verification_code'], $code)) {
            DB::q(
                'UPDATE users SET verification_code=NULL, verification_expires_at=NULL, verification_attempts=0, verification_last_sent_at=NULL WHERE id=?',
                [$userId]
            );
            return true;
        }

        $newAttempts = (int)$row['verification_attempts'] + 1;
        if ($newAttempts >= 5) {
            DB::q('UPDATE users SET verification_code=NULL, verification_attempts=? WHERE id=?', [$newAttempts, $userId]);
        } else {
            DB::q('UPDATE users SET verification_attempts=? WHERE id=?', [$newAttempts, $userId]);
        }
        return false;
    }

    /** TRUE if no last_sent or last_sent is older than 60s. */
    public static function canResend(int $userId): bool {
        return self::secondsUntilResend($userId) === 0;
    }

    /** Seconds remaining in the 60s resend cooldown. 0 means ready. */
    public static function secondsUntilResend(int $userId): int {
        $sent = DB::q('SELECT verification_last_sent_at FROM users WHERE id=?', [$userId])->fetchColumn();
        if (!$sent) return 0;
        $elapsed = time() - strtotime((string)$sent);
        return $elapsed >= 60 ? 0 : 60 - $elapsed;
    }

    public static function isEmailVerified(int $userId): bool {
        return (int)DB::q('SELECT email_verified FROM users WHERE id=?', [$userId])->fetchColumn() === 1;
    }
}
```

- [ ] **Step 2: Smoke-test the helper via PHP CLI**

```bash
cd 'C:/laragon/www/Helppy.com'
php -r "
session_start();
define('APP_ROOT', __DIR__);
define('CONFIG', require 'config/config.php');
spl_autoload_register(function(\$c){ foreach(['app/core','app/controllers','app/models'] as \$d){ \$p=APP_ROOT.\"/\$d/\$c.php\"; if(is_file(\$p)){ require \$p; return; } } });

// Generate a code for user 1 (admin)
\$code = Verification::generateCodeFor(1);
echo \"Generated code: \$code\n\";

// Verify isEmailVerified
echo 'Admin verified (should be 1): ' . (Verification::isEmailVerified(1) ? '1' : '0') . PHP_EOL;

// Try wrong code
echo 'Wrong code accepted (should be empty): ' . (Verification::verify(1, '000000') ? 'TRUE' : '') . PHP_EOL;

// Try right code
echo 'Right code accepted (should be TRUE): ' . (Verification::verify(1, \$code) ? 'TRUE' : 'FALSE') . PHP_EOL;

// Cooldown
\$code = Verification::generateCodeFor(1);
echo 'Seconds until resend (just generated, should be ~60): ' . Verification::secondsUntilResend(1) . PHP_EOL;
echo 'canResend (should be empty=false): ' . (Verification::canResend(1) ? 'TRUE' : '') . PHP_EOL;

// Clean up
DB::q('UPDATE users SET verification_code=NULL, verification_expires_at=NULL, verification_attempts=0, verification_last_sent_at=NULL WHERE id=1');
echo \"Cleanup done.\n\";
"
```

Expected output:
```
Generated code: 123456    (some 6-digit number)
Admin verified (should be 1): 1
Wrong code accepted (should be empty):
Right code accepted (should be TRUE): TRUE
Seconds until resend (just generated, should be ~60): 60
canResend (should be empty=false):
Cleanup done.
```

If anything is off, fix Verification.php before continuing.

- [ ] **Step 3: Commit**

```bash
git add app/core/Verification.php
git commit -m "feat: verification code lifecycle helper"
```

---

## Task 4: Auth.php — pending-2FA session helpers

**Files:**
- Modify: `app/core/Auth.php`

- [ ] **Step 1: Add three methods to `Auth` class**

Open `app/core/Auth.php` and add these methods inside the `final class Auth { ... }` block (after `role()` and before `require()`):

```php
public static function pendingUid(): ?int {
    return isset($_SESSION['pending_2fa_uid']) ? (int)$_SESSION['pending_2fa_uid'] : null;
}

public static function setPending(int $uid): void {
    session_regenerate_id(true);
    $_SESSION['pending_2fa_uid'] = $uid;
}

public static function clearPending(): void {
    unset($_SESSION['pending_2fa_uid']);
}
```

- [ ] **Step 2: Lint**

```bash
php -l app/core/Auth.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/core/Auth.php
git commit -m "feat: Auth pendingUid/setPending/clearPending helpers"
```

---

## Task 5: Routes + verify view + AuthController stubs

**Goal:** Get the `/verify-email` page rendering with stubbed controller methods. This lets us iterate on the view in isolation before wiring real logic.

**Files:**
- Modify: `public/index.php`
- Modify: `app/controllers/AuthController.php`
- Create: `app/views/auth/verify.php`

- [ ] **Step 1: Add 4 routes to `public/index.php`**

Find the existing `PUBLIC` route block (around the `AuthController` registrations) and add these 4 routes immediately after the `POST /logout` line:

```php
$router->get('/verify-email',          [AuthController::class, 'verifyForm']);
$router->post('/verify-email',         [AuthController::class, 'verify']);
$router->post('/verify-email/resend',  [AuthController::class, 'resendVerification']);
$router->post('/verify-email/cancel',  [AuthController::class, 'cancelVerification']);
```

- [ ] **Step 2: Add stub methods to `AuthController`**

In `app/controllers/AuthController.php`, add these four methods inside the class (after the existing `register` method):

```php
public function verifyForm(array $params = []): void {
    $this->render('auth/verify', [
        'title'        => 'Verifiko emailin',
        'masked_email' => 'd***@example.com',
        'resend_in'    => 0,
        'mode'         => 'signup',
    ]);
}
public function verify(array $params = []): void               { echo 'TODO verify'; }
public function resendVerification(array $params = []): void   { echo 'TODO resend'; }
public function cancelVerification(array $params = []): void   { echo 'TODO cancel'; }
```

- [ ] **Step 3: Write `app/views/auth/verify.php`**

```php
<div class="container py-4" style="max-width: 480px;">
  <h2 class="mb-2">Verifiko emailin</h2>
  <p class="text-muted">Nje kod 6-shifror u dergua ne <strong><?= e($masked_email) ?></strong></p>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/verify-email" class="bg-white p-3 rounded mb-3">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <div class="mb-3">
      <label class="form-label">Kodi i verifikimit</label>
      <input class="form-control form-control-lg text-center"
             name="code"
             inputmode="numeric"
             pattern="[0-9]{6}"
             maxlength="6"
             autocomplete="one-time-code"
             autofocus
             required
             style="letter-spacing: 8px; font-size: 24px;">
    </div>
    <button class="btn btn-helppy w-100" type="submit">Verifiko</button>
  </form>

  <form method="post" action="<?= e(CONFIG['base_url']) ?>/verify-email/resend" class="mb-3">
    <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
    <?php if ($resend_in > 0): ?>
      <button class="btn btn-outline-secondary w-100" type="submit" disabled>
        Prisni <?= (int)$resend_in ?>s perpara se te dergoni perseri
      </button>
    <?php else: ?>
      <button class="btn btn-outline-secondary w-100" type="submit">Dergo perseri kodin</button>
    <?php endif; ?>
  </form>

  <?php if ($mode === 'login'): ?>
    <p class="text-center small">
      Nuk je ti?
      <form method="post" action="<?= e(CONFIG['base_url']) ?>/verify-email/cancel" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= e(Request::csrfToken()) ?>">
        <button type="submit" class="btn btn-link p-0 small">Anulo</button>
      </form>
    </p>
  <?php endif; ?>
</div>
```

- [ ] **Step 4: Verify the page renders**

```bash
cd 'C:/laragon/www/Helppy.com'
php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2
curl -s "http://127.0.0.1:8765/?url=/verify-email" | grep -oE 'Verifiko emailin|d\*\*\*@example.com|name="code"' | sort -u
kill %1 2>/dev/null
wait 2>/dev/null
```

Expected output:
```
Verifiko emailin
d***@example.com
name="code"
```

- [ ] **Step 5: Commit**

```bash
git add public/index.php app/controllers/AuthController.php app/views/auth/verify.php
git commit -m "feat: verify-email routes and stub view"
```

---

## Task 6: AuthController verify methods

**Goal:** Implement all four verify-related methods. After this task, the page still isn't reachable through real signup or login flows — those tasks come next. But we can manually test by inserting a session pending state.

**Files:**
- Modify: `app/controllers/AuthController.php`

- [ ] **Step 1: Replace the four stub methods with real implementations**

In `app/controllers/AuthController.php`, replace the four stub methods from Task 5 with these implementations, AND add the two private helpers (`maskEmail` and `postLoginRedirect`):

```php
public function verifyForm(array $params = []): void {
    $uid = Auth::pendingUid() ?? (Auth::check() ? (int)Auth::user()['id'] : null);
    if ($uid === null) { $this->redirect('/login'); }

    // Signup-verifier who is already verified and no pending login: forward home.
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
        // Login-time 2FA: complete the login now.
        $user = User::find($uid);
        Auth::clearPending();
        Auth::login($user);
        $this->flash('success', 'Mire se erdhet, ' . $user['name'] . '!');
        $this->postLoginRedirect($user['role']);
    } else {
        // Signup verification: mark verified, stay logged in.
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

/** "d***@kore.co" — keeps first char of local part, masks the rest, keeps domain. */
private static function maskEmail(string $email): string {
    $at = strpos($email, '@');
    if ($at === false || $at === 0) return $email;
    return $email[0] . '***' . substr($email, $at);
}

/** Role-based post-login destination. */
private function postLoginRedirect(?string $role): void {
    switch ($role) {
        case 'admin':    $this->redirect('/admin'); break;
        case 'provider': $this->redirect('/provider/dashboard'); break;
        default:         $this->redirect('/');
    }
}

/** Log mail errors to storage/mail.log, creating the directory if missing. */
private function logMailError(string $context, Throwable $e): void {
    $dir = APP_ROOT . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    error_log(date('c') . " [$context] " . $e->getMessage() . PHP_EOL, 3, $dir . '/mail.log');
}
```

- [ ] **Step 2: Lint**

```bash
php -l app/controllers/AuthController.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/controllers/AuthController.php
git commit -m "feat: verify-email controller methods (verify, resend, cancel)"
```

---

## Task 7: Wire registration through 2FA

**Goal:** New registrations now create users with `email_verified=0`, generate a code, send the email, auto-login, and redirect to `/verify-email`. Clients, providers, and companies all go through this.

**Files:**
- Modify: `app/controllers/AuthController.php`

- [ ] **Step 1: Update `register()` method**

Find the existing `register()` method in `AuthController.php`. Locate the block that ends with:

```php
$user = User::find($uid);
Auth::login($user);
$this->flash('success', 'Llogaria u krijua. Mire se erdhet!');

$this->redirect($isProviderRole ? '/provider/dashboard' : '/');
```

Replace those five lines with:

```php
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
```

- [ ] **Step 2: Smoke-test signup verification end-to-end**

You need a real Gmail inbox you can check.

```bash
cd 'C:/laragon/www/Helppy.com'
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "DELETE FROM users WHERE email='signup-test@gmail.com';" 2>/dev/null

php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2

COOKIE="C:/Users/admin/AppData/Local/Temp/cookie.txt"
rm -f "$COOKIE"
FORM=$(curl -sc "$COOKIE" "http://127.0.0.1:8765/?url=/register")
CSRF=$(echo "$FORM" | grep -oE '[a-f0-9]{64}' | head -1)

# Register as a client
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w "HTTP %{http_code} -> %{redirect_url}\n" \
  -d "_csrf=$CSRF&role=client&name=Signup Test&email=YOUR_REAL_GMAIL@gmail.com&password=secret1&phone=%2B38344999000&city_id=1" \
  "http://127.0.0.1:8765/?url=/register"

# Should redirect to /verify-email
echo "User state:"
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT id, email_verified, verification_code IS NOT NULL AS has_code FROM users WHERE email='YOUR_REAL_GMAIL@gmail.com';"

# Visit /verify-email - should render the verify page (user is logged in)
echo "Verify page reachable:"
curl -sb "$COOKIE" -c "$COOKIE" "http://127.0.0.1:8765/?url=/verify-email" | grep -c 'Verifiko emailin'

kill %1 2>/dev/null
wait 2>/dev/null
```

Replace `YOUR_REAL_GMAIL@gmail.com` with your actual Gmail address before running.

Expected:
- `HTTP 302 -> .../verify-email`
- DB: `email_verified=0`, `has_code=1`
- Verify page reachable: `1`
- Within ~10 seconds, a real email arrives at YOUR_REAL_GMAIL with the 6-digit code.

- [ ] **Step 3: Now verify the code manually**

Look at the code in the email. Then submit it:

```bash
cd 'C:/laragon/www/Helppy.com'
php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2
COOKIE="C:/Users/admin/AppData/Local/Temp/cookie.txt"

# Re-login isn't needed — we kept the cookie
PAGE=$(curl -sb "$COOKIE" -c "$COOKIE" "http://127.0.0.1:8765/?url=/verify-email")
CSRF=$(echo "$PAGE" | grep -oE '[a-f0-9]{64}' | head -1)

# Replace YOUR_CODE_HERE with the 6-digit code from the email
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w "HTTP %{http_code} -> %{redirect_url}\n" \
  -d "_csrf=$CSRF&code=YOUR_CODE_HERE" \
  "http://127.0.0.1:8765/?url=/verify-email"

# Verify email_verified=1 now
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT email_verified, verification_code FROM users WHERE email='YOUR_REAL_GMAIL@gmail.com';"

kill %1 2>/dev/null
wait 2>/dev/null
```

Expected:
- `HTTP 302 -> .../` (client redirect destination)
- DB: `email_verified=1`, `verification_code=NULL`

- [ ] **Step 4: Clean up test user**

```bash
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "DELETE FROM users WHERE email='YOUR_REAL_GMAIL@gmail.com';"
```

- [ ] **Step 5: Commit**

```bash
git add app/controllers/AuthController.php
git commit -m "feat: signup creates unverified user and redirects to verify-email"
```

---

## Task 8: Wire login through 2FA (THE breaking change)

**Goal:** Every successful login now stops at `/verify-email` and requires the user to enter a fresh code emailed to them. After this task, **no one can log in without a working mailer**.

**Critical:** Before running step 2, update the admin email so you can receive admin codes:

```sql
UPDATE users SET email = 'YOUR_REAL_GMAIL@gmail.com' WHERE id = 1;
```

**Files:**
- Modify: `app/controllers/AuthController.php`

- [ ] **Step 1: Update admin email**

```bash
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "UPDATE users SET email='YOUR_REAL_GMAIL@gmail.com' WHERE id=1;"
```

- [ ] **Step 2: Update `login()` method**

Find the existing `login()` method. Locate the block that ends with:

```php
Auth::login($user);
$this->flash('success', 'Mire se erdhet, ' . $user['name'] . '!');

switch ($user['role']) {
    case 'admin':    $this->redirect('/admin'); break;
    case 'provider': $this->redirect('/provider/dashboard'); break;
    default:         $this->redirect('/');
}
```

Replace those lines with:

```php
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
```

- [ ] **Step 3: Smoke-test admin login end-to-end**

```bash
cd 'C:/laragon/www/Helppy.com'
php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2

COOKIE="C:/Users/admin/AppData/Local/Temp/cookie.txt"
rm -f "$COOKIE"

# Get CSRF
FORM=$(curl -sc "$COOKIE" "http://127.0.0.1:8765/?url=/login")
CSRF=$(echo "$FORM" | grep -oE '[a-f0-9]{64}' | head -1)

# Login as admin
echo "Step A: submit credentials"
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w "  HTTP %{http_code} -> %{redirect_url}\n" \
  -d "_csrf=$CSRF&email=YOUR_REAL_GMAIL@gmail.com&password=admin123" \
  "http://127.0.0.1:8765/?url=/login"

# Should redirect to /verify-email
echo "Step B: pending state"
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT id, verification_code IS NOT NULL AS has_code, verification_expires_at FROM users WHERE id=1;"

# Should be able to load verify page
echo "Step C: verify page reachable"
curl -sb "$COOKIE" -c "$COOKIE" "http://127.0.0.1:8765/?url=/verify-email" | grep -c 'Verifiko emailin'

kill %1 2>/dev/null
wait 2>/dev/null
```

Expected:
- HTTP 302 → /verify-email
- DB: `has_code=1`, `verification_expires_at` ~ 15 min from now
- Verify page: `1`
- Email arrives at YOUR_REAL_GMAIL with a fresh 6-digit code (different from the one in Task 7 if you ran that).

- [ ] **Step 4: Complete the 2FA challenge**

Look at the email, get the code, submit it.

```bash
cd 'C:/laragon/www/Helppy.com'
php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2
COOKIE="C:/Users/admin/AppData/Local/Temp/cookie.txt"

PAGE=$(curl -sb "$COOKIE" -c "$COOKIE" "http://127.0.0.1:8765/?url=/verify-email")
CSRF=$(echo "$PAGE" | grep -oE '[a-f0-9]{64}' | head -1)

# Replace YOUR_CODE_HERE with the code from the email
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w "HTTP %{http_code} -> %{redirect_url}\n" \
  -d "_csrf=$CSRF&code=YOUR_CODE_HERE" \
  "http://127.0.0.1:8765/?url=/verify-email"

# Should redirect to /admin. Verify admin index renders.
echo "Admin reachable: $(curl -sb "$COOKIE" -c "$COOKIE" "http://127.0.0.1:8765/?url=/admin" | grep -c 'Admin Panel')"

kill %1 2>/dev/null
wait 2>/dev/null
```

Expected:
- HTTP 302 → /admin
- "Admin reachable: 1"

- [ ] **Step 5: Test the cancel flow**

```bash
cd 'C:/laragon/www/Helppy.com'
php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2
COOKIE="C:/Users/admin/AppData/Local/Temp/cookie.txt"
rm -f "$COOKIE"

FORM=$(curl -sc "$COOKIE" "http://127.0.0.1:8765/?url=/login")
CSRF=$(echo "$FORM" | grep -oE '[a-f0-9]{64}' | head -1)
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null \
  -d "_csrf=$CSRF&email=YOUR_REAL_GMAIL@gmail.com&password=admin123" \
  "http://127.0.0.1:8765/?url=/login"

# Now at /verify-email with pending uid set
PAGE=$(curl -sb "$COOKIE" -c "$COOKIE" "http://127.0.0.1:8765/?url=/verify-email")
CSRF=$(echo "$PAGE" | grep -oE '[a-f0-9]{64}' | head -1)

# Cancel
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w "Cancel: HTTP %{http_code} -> %{redirect_url}\n" \
  -d "_csrf=$CSRF" \
  "http://127.0.0.1:8765/?url=/verify-email/cancel"

# Should redirect to /login. Visit /admin - should redirect to /login (not logged in).
echo "After cancel, /admin: HTTP $(curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code} -> %{redirect_url}' 'http://127.0.0.1:8765/?url=/admin')"

kill %1 2>/dev/null
wait 2>/dev/null
```

Expected:
- Cancel: HTTP 302 → /login
- After cancel, /admin: HTTP 302 → /login (Auth::check() returned false because we cleared pending)

- [ ] **Step 6: Commit**

```bash
git add app/controllers/AuthController.php
git commit -m "feat: login requires 2FA code for all users"
```

---

## Task 9: Verification guards on protected pages

**Goal:** A signup-verifying user (logged in, `email_verified=0`) cannot reach role-restricted pages by URL-typing — they get bounced to `/verify-email`.

**Files:**
- Modify: `app/controllers/ProviderController.php`
- Modify: `app/controllers/ClientController.php`
- Modify: `app/controllers/ReviewController.php`
- Modify: `app/controllers/AdminController.php`

- [ ] **Step 1: Add the guard to all protected controller methods**

The guard pattern is:

```php
if (!Verification::isEmailVerified((int)Auth::user()['id'])) {
    $this->redirect('/verify-email');
}
```

Add this line **immediately after** each existing `Auth::require(...)` call in the following methods:

`ProviderController.php`:
- `dashboard()`
- `update()`
- `uploadPhoto()`

`ClientController.php`:
- `dashboard()`

`ReviewController.php`:
- `store()`
- `destroy()` — note: `destroy()` uses `Auth::require()` with no role; guard goes immediately after that line.

`AdminController.php` — ALL EIGHT methods:
- `index()`
- `providers()`
- `toggleActive()`
- `togglePremium()`
- `categories()`
- `createCategory()`
- `deleteCategory()`
- `deleteReview()`

`ProviderController.php::show()` does NOT need this guard (it's public — but it gets a different change in Task 10).

- [ ] **Step 2: Smoke-test the guard**

```bash
cd 'C:/laragon/www/Helppy.com'
php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2
COOKIE="C:/Users/admin/AppData/Local/Temp/cookie.txt"
rm -f "$COOKIE"

# Register a fresh client (becomes email_verified=0, logged in)
FORM=$(curl -sc "$COOKIE" "http://127.0.0.1:8765/?url=/register")
CSRF=$(echo "$FORM" | grep -oE '[a-f0-9]{64}' | head -1)
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null \
  -d "_csrf=$CSRF&role=client&name=Guard Test&email=guardtest@helppy.local&password=secret1&city_id=1" \
  "http://127.0.0.1:8765/?url=/register"

# Try to access /client/dashboard while unverified
echo "Unverified -> /client/dashboard:"
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w "  HTTP %{http_code} -> %{redirect_url}\n" \
  "http://127.0.0.1:8765/?url=/client/dashboard"

# Manually set email_verified=1 in DB to simulate completed verification
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "UPDATE users SET email_verified=1 WHERE email='guardtest@helppy.local';"

# Now /client/dashboard should work
echo "Verified -> /client/dashboard:"
curl -sb "$COOKIE" -c "$COOKIE" -o /dev/null -w "  HTTP %{http_code}\n" \
  "http://127.0.0.1:8765/?url=/client/dashboard"

# Clean up
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "DELETE FROM users WHERE email='guardtest@helppy.local';"

kill %1 2>/dev/null
wait 2>/dev/null
```

Expected:
- Unverified -> /client/dashboard: HTTP 302 → /verify-email
- Verified -> /client/dashboard: HTTP 200

- [ ] **Step 3: Commit**

```bash
git add app/controllers/
git commit -m "feat: bounce unverified users to /verify-email on protected pages"
```

---

## Task 10: Filter unverified providers from public listings

**Goal:** Until a provider completes signup verification, they don't appear in `/search`, in the home featured strip, or on the admin providers list as "active". Their `/provider/{id}` page returns 404.

**Files:**
- Modify: `app/models/Provider.php`
- Modify: `app/controllers/ProviderController.php` (just `show`)
- Modify: `app/views/admin/providers.php`

- [ ] **Step 1: Update `Provider::search()`**

In `app/models/Provider.php`, find the `search()` method. Replace the JOIN clause:

```php
JOIN users u ON u.id = p.user_id AND u.is_active = 1
```

with:

```php
JOIN users u ON u.id = p.user_id AND u.is_active = 1 AND u.email_verified = 1
```

- [ ] **Step 2: Update `Provider::featured()`**

Same edit in `featured()`:

```php
JOIN users u ON u.id = p.user_id AND u.is_active = 1 AND u.email_verified = 1
```

- [ ] **Step 3: Update `Provider::find()`**

In `find()`, add `u.email_verified` to the SELECT list. Change:

```sql
SELECT u.id, u.name, u.email, u.phone, u.is_active, u.created_at,
```

to:

```sql
SELECT u.id, u.name, u.email, u.phone, u.is_active, u.email_verified, u.created_at,
```

- [ ] **Step 4: Update `Provider::allWithStatus()`**

In `allWithStatus()`, add `u.email_verified` to the SELECT list. Change:

```sql
SELECT u.id, u.name, u.email, u.is_active, u.created_at,
```

to:

```sql
SELECT u.id, u.name, u.email, u.is_active, u.email_verified, u.created_at,
```

- [ ] **Step 5: Update `ProviderController::show()`**

In `app/controllers/ProviderController.php`, find `show()`. Change:

```php
if (!$provider || empty($provider['is_active'])) { $this->notFound(); return; }
```

to:

```php
if (!$provider || empty($provider['is_active']) || empty($provider['email_verified'])) { $this->notFound(); return; }
```

- [ ] **Step 6: Update `app/views/admin/providers.php` — add a verification column**

Find the `<thead>` block:

```html
<thead>
  <tr>
    <th>ID</th><th>Emri</th><th>Email</th><th>Profesioni</th>
    <th>Aktiv</th><th>Premium</th><th>Veprime</th>
  </tr>
</thead>
```

Replace with:

```html
<thead>
  <tr>
    <th>ID</th><th>Emri</th><th>Email</th><th>Profesioni</th>
    <th>Email i verifikuar</th><th>Aktiv</th><th>Premium</th><th>Veprime</th>
  </tr>
</thead>
```

And in the `<tbody>` foreach row, add the new column AFTER the `<td><?= e($p['profession']) ?></td>` and BEFORE the `<td><?= $p['is_active'] ? ...`:

```html
<td><?= $p['email_verified'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?></td>
```

- [ ] **Step 7: Smoke-test unverified visibility**

```bash
cd 'C:/laragon/www/Helppy.com'
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "UPDATE users SET email_verified=0 WHERE id=3;"  # Arben

php -S 127.0.0.1:8765 -t public public/index.php > /dev/null 2>&1 &
sleep 2

echo "Search city=1 cat=1 (Hidraulike in Prishtine), expect 0 (Arben hidden):"
curl -s "http://127.0.0.1:8765/?url=/search&city=1&category=1" | grep -c "Arben"

echo "Home featured, expect Arben not in list:"
curl -s "http://127.0.0.1:8765/?url=/" | grep -c "Arben"

echo "/provider/3 (Arben), expect 404:"
curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8765/?url=/provider/3"

# Restore
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "UPDATE users SET email_verified=1 WHERE id=3;"
echo "After restore, /provider/3 expect 200:"
curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8765/?url=/provider/3"

kill %1 2>/dev/null
wait 2>/dev/null
```

Expected:
- Search: 0 (Arben hidden when unverified)
- Home featured: 0
- /provider/3 unverified: 404
- /provider/3 restored: 200

- [ ] **Step 8: Smoke-test admin verification column**

Log in as admin (via cookie + 2FA flow if you remember the code from Task 8, otherwise re-run a quick login). Visit `/admin/providers`. The new column should render — all 6 providers currently show a green checkmark.

To verify manually via curl, you can just check the HTML:

```bash
cd 'C:/laragon/www/Helppy.com'
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "UPDATE users SET email_verified=0 WHERE id=4;"  # Bekim unverified

# (skip admin login flow for this smoke test - just check the SQL change is reflected)
# Direct query mirror:
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "SELECT u.name, u.email_verified FROM providers p JOIN users u ON u.id=p.user_id ORDER BY u.created_at DESC;"

# Restore
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "UPDATE users SET email_verified=1 WHERE id=4;"
```

Expected: One provider shows `email_verified=0` (Bekim), the rest are `=1`.

- [ ] **Step 9: Commit**

```bash
git add app/models/Provider.php app/controllers/ProviderController.php app/views/admin/providers.php
git commit -m "feat: hide unverified providers from search and public profile"
```

---

## Task 11: README App Password setup

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add a new section to README.md**

Insert this section AFTER the "Seeded accounts" section and BEFORE "Folder structure":

```markdown
## Email verification setup (REQUIRED for login)

Every login and registration sends a 6-digit code to the user's email. Without working Gmail SMTP credentials, no one can log in. Set this up before importing the seed data, or you will lock yourself out.

### Generate a Gmail App Password

1. Open https://myaccount.google.com/security
2. Turn on "2-Step Verification" if it isn't already on.
3. Click "App passwords" (search if you can't find it). Name it "Helppy" and pick app type "Mail" and device "Other (Windows Computer)".
4. Copy the 16-character password Google shows you. You will not see it again.

### Configure Helppy

Edit `config/config.php` and set:

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

The `from` must use the same Gmail you authenticate with — otherwise Gmail rejects the message.

### Change the seeded admin's email

The seeded `admin@helppy.com` cannot receive emails. Before logging in as admin:

```sql
UPDATE users SET email = 'youremail@gmail.com' WHERE id = 1;
```

After that, log in normally — a 2FA code arrives at your Gmail every login.

### If you lose access

If Gmail breaks or you lose the App Password:
- For any non-admin user: their account is unrecoverable from the UI. Edit `users.email` in phpMyAdmin to a working address.
- For admin: same — edit `users.email` directly to point at a Gmail you control.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: Gmail App Password and 2FA setup instructions"
```

---

## Task 12: Final acceptance walkthrough

**Goal:** Run all 15 acceptance criteria from spec §12 end-to-end. Document any deviation.

**Prerequisite:** Your mailer credentials in `config/config.php` are working (Task 1 passed). The admin email in DB is your real Gmail (set in Task 8).

- [ ] **Step 1: Reset DB to clean state**

```bash
cd 'C:/laragon/www/Helppy.com'
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy < db/schema.sql
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy < db/seed.sql
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "UPDATE users SET email='YOUR_REAL_GMAIL@gmail.com' WHERE id=1;"
```

(Schema.sql already includes the new columns from Task 2 step 4, so this works cleanly.)

- [ ] **Step 2: Run the acceptance walkthrough**

The walkthrough is most easily done in a real browser because you need to read codes from Gmail and type them into the verify page. Walk through each numbered criterion from the spec §12. For each one, note whether it passes.

A reasonable order to cover most criteria with two test accounts (admin + one fresh provider):

1. **Criterion 13 (existing data + 2FA):** Open `/login`, enter `YOUR_REAL_GMAIL@gmail.com` / `admin123`. You should be redirected to `/verify-email`. Wait for the code in your Gmail. Enter it → /admin loads.
2. **Criterion 15 (anonymous browse):** Open `/` and `/search` in incognito. Listings visible, no 2FA prompt.
3. **Criterion 3 (provider signup):** Logout. Go to `/register`, select "Punues", use a SECOND real Gmail address you control (or a Gmail alias like `yourname+test@gmail.com`). Fill out profession + category. Submit → `/verify-email`. Wait for code, enter → `/provider/dashboard`. Visit `/search?category=<their cat>` → they appear.
4. **Criterion 4 (unverified hidden):** Register a THIRD provider via incognito but DO NOT enter the code. Open another incognito tab and visit `/search` and `/provider/<their id>` — they should NOT appear / 404.
5. **Criterion 6 (provider login 2FA):** Logout the second provider. Login → /verify-email. Confirm the code in this email is DIFFERENT from the signup code. Enter → /provider/dashboard.
6. **Criterion 8 (cancel):** Login again → /verify-email. Click "Anulo" → bounced to /login. Try to visit /provider/dashboard → redirected to /login.
7. **Criterion 9–11 (resend + attempts):** Login again. On /verify-email, click "Dergo perseri" within 60s → see disabled button with countdown. Wait, click → new code arrives. Enter wrong code 5 times → flash on 5th invalid, then try resend.
8. **Criterion 12 (mailer failure):** Edit `config/config.php`, set `mailer.password = ''`. Try to register a new client → user created, flash "Llogaria u krijua, por emaili nuk u dergua." Check `storage/mail.log` exists and has an entry. Restore the password, hit resend on /verify-email → email arrives.
9. **Criterion 1 + 2 (client signup):** Register a new client with another real Gmail. /verify-email → enter code → /. Open a provider page in the same session, submit a review → accepted.
10. **Criterion 14 (client login 2FA):** Logout the client. Login → /verify-email → fresh code → /.

- [ ] **Step 3: Document the walkthrough result**

Create `docs/superpowers/plans/2026-06-04-email-verification-RESULT.md`:

```markdown
# 2FA Acceptance Walkthrough Result

Date: <YYYY-MM-DD>
Tester: <name>

| # | Criterion (one-line summary) | Pass/Fail | Notes |
|---|------------------------------|-----------|-------|
| 1 | New client gets code at signup, code arrives | | |
| 2 | Verify code → redirect to /, can submit reviews | | |
| 3 | New provider signup → code → visible in search | | |
| 4 | Unverified provider hidden from search and 404 | | |
| 5 | Verified client login → fresh code → / | | |
| 6 | Verified provider login → fresh code → dashboard | | |
| 7 | Admin login → fresh code → /admin | | |
| 8 | Cancel mid-2FA → /login, session anonymous | | |
| 9 | Resend within 60s shows countdown | | |
| 10 | Resend after 60s arrives, old code invalid | | |
| 11 | 5 wrong attempts → code nullified | | |
| 12 | Mailer.username='' → graceful failure | | |
| 13 | Existing seeded users still log in (with 2FA) | | |
| 14 | Clients log in via 2FA | | |
| 15 | Anonymous browsing unaffected | | |

## Deviations / known issues

(none)
```

- [ ] **Step 4: Clean up test users**

```bash
"C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" -uroot helppy -e "DELETE FROM users WHERE id > 8;"
```

- [ ] **Step 5: Final commit**

```bash
git add docs/superpowers/plans/2026-06-04-email-verification-RESULT.md
git commit -m "test: 2FA acceptance walkthrough results"
```

---

## Summary

| # | Task | Files touched | Spec section |
|---|------|---------------|--------------|
| 1 | Mailer + config + storage | Mailer.php, config.example.php, config.php, .gitignore, storage/ | §5, §6.1 |
| 2 | DB migration + schema | migrations/, schema.sql | §4 |
| 3 | Verification helper | Verification.php | §6.2 |
| 4 | Auth pending-2FA helpers | Auth.php | §6.4 |
| 5 | Routes + verify view + stubs | index.php, AuthController stubs, verify.php | §6.3, §7 |
| 6 | AuthController verify methods | AuthController.php | §8.3 |
| 7 | Wire register → 2FA | AuthController.php | §8.1 |
| 8 | Wire login → 2FA (breaking) | AuthController.php | §8.2 |
| 9 | Verification guards | Provider/Client/Review/AdminController.php | §8.4 |
| 10 | Search/profile filter + admin column | Provider.php, ProviderController.php, admin/providers.php | §9 |
| 11 | README App Password setup | README.md | §5 |
| 12 | Final acceptance walkthrough | RESULT.md | §12 |

Every spec section maps to at least one task. No placeholders. Method signatures (`Verification::generateCodeFor`, `::send`, `::verify`, `::canResend`, `::secondsUntilResend`, `::isEmailVerified`, `Auth::pendingUid`, `::setPending`, `::clearPending`) are consistent between tasks. The breaking change (login 2FA) is isolated to Task 8 with explicit prerequisites and a tested rollback path (edit `users.email` in phpMyAdmin).

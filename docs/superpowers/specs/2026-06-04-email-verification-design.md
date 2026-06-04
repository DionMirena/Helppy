# Email-Based 2FA for All Users — Design

**Date:** 2026-06-04
**Author:** Dion (with Claude)
**Status:** Approved, ready for implementation planning
**Supersedes:** moves the "Email verification" Phase 2 item out of `2026-06-04-helppy-design.md` §10.

## 1. Overview

**Every user — client, provider, company, admin — must enter a one-time 6-digit code emailed to them at registration AND on every login.** Without entering the current code, they cannot complete authentication. For providers/companies, the additional effect is that their public profile is hidden from search until they have completed signup verification at least once.

This is two distinct mechanisms sharing the same email + UI + DB columns:

| Mechanism | When | Persistent effect |
|-----------|------|-------------------|
| **Signup verification** | Once, immediately after `POST /register` | Sets `email_verified = 1`. For providers, this also unlocks their visibility in search and on `/provider/{id}`. |
| **Login 2FA challenge** | Every successful email+password match | None — purely a session-time gate. No DB flag changes on success. |

The verification page (`/verify-email`) and the SMTP plumbing are shared. The four code-related columns on `users` get re-populated on every relevant event.

## 2. Goals & non-goals

### Goals

- Real email is delivered from Gmail SMTP to **every user** on every register and every login.
- Authentication cannot complete without entering the current code.
- A provider's profile is hidden from search/public view until signup verification completes at least once.
- One Gmail address can only be used by one account (already enforced by the existing `users.email` UNIQUE constraint).
- Pure PHP, no Composer.
- A user who loses or mistypes the code can request a fresh one (rate-limited, 60s).

### Non-goals (explicitly out of scope)

- "Remember this device for 30 days" option — every login gets a fresh code, no exceptions.
- SMS / phone verification. The user has acknowledged email 2FA does NOT prove ownership of the phone number field; it only proves control of the Gmail address.
- Magic-link verification — UI uses a 6-digit numeric code only.
- Backup codes / recovery codes / SMS fallback.
- Forgot-password flow.
- Authenticator-app TOTP.
- Re-verifying email when a user edits their email (the email field is not editable in the MVP).

## 3. Decisions locked

| Decision | Value |
|----------|-------|
| Triggers | (a) `POST /register` for every role; (b) `POST /login` for every role |
| Affects | All roles: `client`, `provider`, `admin`. Companies are providers with `is_company = 1`. |
| Code format | 6 digits, generated via `random_int(0, 999999)` then zero-padded |
| Expiry | 15 minutes from issue |
| Attempts before invalidate | 5 wrong submissions for the current code |
| Resend cooldown | 60 seconds since last send |
| SMTP source | Gmail with an App Password, configured in `config/config.php` |
| Pre-2FA session state | `$_SESSION['pending_2fa_uid']` only; `Auth::check()` returns FALSE until 2FA passes |
| Post-2FA redirect | role-based: `admin` → `/admin`, `provider` → `/provider/dashboard`, `client` → `/` |

**Recovery if Gmail is broken:** Admin can flip `email_verified=1` AND null out the code columns directly in phpMyAdmin to bypass signup verification. Login-time 2FA cannot be bypassed without first toggling the user's role to one that doesn't exist (effectively locking them out) — by design, there is no "skip 2FA" admin UI. If the admin themselves is locked out of Gmail, they must edit `users.email` in phpMyAdmin to point to a working Gmail before attempting login again.

## 4. Database schema delta

Migration file: `db/migrations/2026-06-04-email-verification.sql`

```sql
ALTER TABLE users
  ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN verification_code CHAR(6) NULL,
  ADD COLUMN verification_expires_at DATETIME NULL,
  ADD COLUMN verification_attempts TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN verification_last_sent_at DATETIME NULL;

-- Existing seeded rows keep working; mark them verified explicitly.
UPDATE users SET email_verified = 1;
```

`db/schema.sql` is also updated so a fresh install includes these columns from the start. `db/seed.sql` is unchanged (defaults to verified).

`email_verified` defaults to `1` so any user inserted directly via SQL (admin maintenance) is trusted. The signup path explicitly inserts users with `email_verified = 0`. The login 2FA challenge does NOT read or write `email_verified` — it only uses the four code/expiry/attempts/last_sent columns.

**Important:** seeded users continue to work because the migration's final UPDATE sets them all to verified. Their first login after the migration WILL still trigger 2FA — because 2FA at login is independent of `email_verified`.

## 5. Configuration delta

`config/config.example.php` and `config/config.php` gain:

```php
'mailer' => [
    'host'     => 'smtp.gmail.com',
    'port'     => 587,
    'username' => '',          // YOUR Gmail address — fill in
    'password' => '',          // App Password (16 chars, NOT your real Gmail password)
    'from'     => 'Helppy.com <noreply@helppy.com>',
    'reply_to' => '',          // optional
    'timeout'  => 10,          // seconds for SMTP connect / read
],
```

If `username` or `password` is empty when a code attempt is made, controllers log to `storage/mail.log` (directory auto-created via `mkdir(..., 0775, true)` if missing) and flash a generic error to the user. The code is still generated and stored in DB — so a registration attempt creates the user row regardless of mail success. The user can hit resend later once mailer is configured.

README gets a new section explaining how to generate the App Password (Google Account → Security → 2-Step Verification → App passwords → Mail / Windows Computer).

## 6. New components

### 6.1 `app/core/Mailer.php`

Tiny SMTP client. Public surface:

```php
final class Mailer {
    /** Throws RuntimeException on transport failure. Returns true on accepted send. */
    public static function send(string $to, string $subject, string $bodyText): bool;
}
```

Implementation outline:
- `stream_socket_client("tcp://{host}:{port}", ..., timeout)`.
- Read greeting, send `EHLO helppy.com`, parse capabilities.
- If `STARTTLS` advertised and port is 587: `STARTTLS`, then `stream_socket_enable_crypto(..., STREAM_CRYPTO_METHOD_TLS_CLIENT)`, then re-`EHLO`.
- `AUTH LOGIN`, send base64 username, base64 password.
- `MAIL FROM:<from>`, `RCPT TO:<to>`, `DATA`.
- Headers: `From`, `To`, `Subject`, `MIME-Version: 1.0`, `Content-Type: text/plain; charset=utf-8`, `Date`, `Message-ID`.
- Body, then `\r\n.\r\n` terminator.
- `QUIT`.

All read/write through small `readLine()` and `command()` helpers that throw on unexpected status codes (`4xx`/`5xx`).

If `CONFIG['mailer']['username'] === ''`: throw `RuntimeException("Mailer not configured")` immediately, do not open a socket. Caller logs + flashes.

Roughly 150 lines. Text-plain only — no HTML, no attachments.

### 6.2 `app/core/Verification.php`

Domain helper. Encapsulates all column writes so controllers never touch the columns directly.

```php
final class Verification {
    /** Generates a fresh code, sets expiry=NOW+15m, last_sent=NOW, attempts=0. Returns the code. */
    public static function generateCodeFor(int $userId): string;

    /** Reads user name + email + current code from DB, calls Mailer with the rendered template (§10). Rethrows on transport failure. */
    public static function send(int $userId): void;

    /**
     * Compares supplied code with stored verification_code using hash_equals.
     * On match: clears all four code columns, returns true.
     *   (Caller is responsible for setting email_verified=1 if this is a signup verification.)
     * On mismatch: increments verification_attempts. If attempts >= 5, sets verification_code = NULL
     *   (forces resend). Returns false.
     * On expired code: returns false (no attempt increment).
     */
    public static function verify(int $userId, string $code): bool;

    /** TRUE if verification_last_sent_at IS NULL or older than 60s. */
    public static function canResend(int $userId): bool;

    /** 0 if can resend now, else remaining seconds. Used to render "wait Xs" message. */
    public static function secondsUntilResend(int $userId): int;

    /** TRUE if user has completed at least one signup verification. */
    public static function isEmailVerified(int $userId): bool;
}
```

### 6.3 `app/views/auth/verify.php`

Single page used for both signup verification AND per-login 2FA challenge. Identical UI either way.

Renders:
- Headline: "Verifiko emailin tend"
- Subtext masked email (e.g. `d***@gmail.com`) via `maskEmail()` helper (§8.3).
- Numeric input (`inputmode="numeric"`, `pattern="[0-9]{6}"`, `maxlength="6"`, autofocus).
- "Verifiko" submit button.
- Below: "Dergo perseri kodin" form (POST to `/verify-email/resend`). If cooldown > 0, button is disabled and text shows "Prisni Xs perpara se te dergoni perseri".
- Bottom: small text "Nuk je ti? <a>Anulo</a>" — cancels the pending 2FA session (login flow). Hidden during signup verification because the user is fully logged in and should use the normal Logout button instead.

### 6.4 `app/core/Auth.php` additions

Auth helper gains:

```php
public static function pendingUid(): ?int;     // returns $_SESSION['pending_2fa_uid'] or null
public static function setPending(int $uid): void;  // session_regenerate_id + set the key
public static function clearPending(): void;   // unset the key only
```

`Auth::check()` continues to look at `$_SESSION['uid']` (only set AFTER 2FA passes). `Auth::pendingUid()` is the new way to identify a user mid-2FA.

## 7. Routes added

```
GET  /verify-email           AuthController@verifyForm
POST /verify-email           AuthController@verify
POST /verify-email/resend    AuthController@resendVerification
POST /verify-email/cancel    AuthController@cancelVerification
```

Access rules:
- `/verify-email` (GET) requires EITHER `Auth::check()` (post-signup, user is logged in but `email_verified=0`) OR `Auth::pendingUid()` (mid-login 2FA). If neither: redirect to `/login`.
- `/verify-email` (POST) same access rule. The controller figures out which flow this is by looking at session state.
- `/verify-email/cancel` clears `pending_2fa_uid` and redirects to `/login` with flash "Anuluat verifikimin." This route is ONLY usable for the login flow (post-signup, the user is fully logged in via `Auth::check()` and there is no pending state to cancel — they should use Logout).

## 8. Controller changes

### 8.1 `AuthController::register` (signup-verification flow, ALL roles)

The role-based branching is removed for signup verification. Every newly-created user gets `email_verified=0` and is sent to `/verify-email`.

```
// existing validation + transactional insert of user (+ provider/categories if applicable)...
// AFTER the transaction commits:
DB::q('UPDATE users SET email_verified=0 WHERE id=?', [$uid]);

Verification::generateCodeFor($uid);
try { Verification::send($uid); }
catch (Throwable $e) {
    error_log("[Mailer signup] " . $e->getMessage(), 3, APP_ROOT . '/storage/mail.log');
    $this->flash('danger', 'Llogaria u krijua, por emaili nuk u dergua. Provoni te dergoni perseri.');
}
Auth::login($user);              // user IS logged in, but email_verified=0
$this->redirect('/verify-email');
```

We keep `User::create()`'s signature unchanged and do the explicit `UPDATE … SET email_verified=0` after creation. This avoids editing the seed file and keeps `User::create` reusable for any future admin tool that bypasses verification.

### 8.2 `AuthController::login` (per-login 2FA challenge, ALL roles)

```
$user = User::findByEmail($email);
if (!$user || !password_verify($pass, $user['password_hash'])) {
    flash danger; redirect /login;
}
if (!$user['is_active']) {
    flash danger; redirect /login;
}

// EVERY successful credential match issues a 2FA challenge.
Verification::generateCodeFor((int)$user['id']);
try { Verification::send((int)$user['id']); }
catch (Throwable $e) {
    error_log("[Mailer login] " . $e->getMessage(), 3, APP_ROOT . '/storage/mail.log');
    $this->flash('danger', 'Kodi i verifikimit nuk u dergua. Provoni perseri.');
    $this->redirect('/login');
}
Auth::setPending((int)$user['id']);   // does NOT call Auth::login yet
$this->flash('info', 'Nje kod verifikimi u dergua ne emailin tuaj.');
$this->redirect('/verify-email');
```

There is NO direct path to `/admin`, `/`, or `/provider/dashboard` from `POST /login`. Every successful credential check ends at `/verify-email`.

### 8.3 New `AuthController` methods

```php
public function verifyForm(array $params = []): void {
    $uid = Auth::pendingUid() ?? (Auth::check() ? (int)Auth::user()['id'] : null);
    if ($uid === null) { $this->redirect('/login'); }

    // signup-verifier who is already verified: forward to their role's home.
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

    // SUCCESS: which flow?
    if (Auth::pendingUid() !== null) {
        // login-time 2FA: complete the login now.
        $user = User::find($uid);
        Auth::clearPending();
        Auth::login($user);
        $this->flash('success', 'Mire se erdhet, ' . $user['name'] . '!');
        $this->postLoginRedirect($user['role']);
    } else {
        // signup verification: mark verified, user stays logged in.
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
    try { Verification::send($uid); $this->flash('info', 'Kodi u dergua perseri.'); }
    catch (Throwable $e) {
        error_log("[Mailer resend] " . $e->getMessage(), 3, APP_ROOT . '/storage/mail.log');
        $this->flash('danger', 'Emaili nuk u dergua. Provoni perseri me vone.');
    }
    $this->redirect('/verify-email');
}

public function cancelVerification(array $params = []): void {
    Auth::clearPending();
    $this->flash('info', 'Anuluat verifikimin.');
    $this->redirect('/login');
}

/** "d***@kore.co" — keeps first char of local part, masks the rest with `***`, keeps domain. */
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
```

### 8.4 Route gates on existing authenticated pages

All authenticated controllers gain a check at the top, after `Auth::require(...)`:

```php
Auth::require('provider'); // or 'client' or 'admin'
if (!Verification::isEmailVerified((int)Auth::user()['id'])) {
    $this->redirect('/verify-email');
}
```

Touched methods:
- `ProviderController::dashboard / update / uploadPhoto`
- `ClientController::dashboard`
- `ReviewController::store / destroy`
- All eight `AdminController::*` methods

This handles the corner case where a signup-verifying user with `email_verified=0` types an authenticated URL into the browser bar to skip the verify step. They get bounced back to `/verify-email`.

Login-time 2FA does NOT need this guard, because login-flow users never reach `Auth::require()` in the first place — `Auth::check()` is false during pending 2FA.

## 9. Search / profile changes

Same as before — only providers who have completed signup verification (`email_verified = 1`) are visible in search:

- `Provider::search()`, `Provider::featured()`: JOIN clause adds `AND u.email_verified = 1`.
- `Provider::find()`: SELECT list gains `u.email_verified`.
- `Provider::allWithStatus()` (admin table): SELECT list gains `u.email_verified` so admin can see verification status as a column.
- `ProviderController::show()`: returns 404 if `email_verified = 0`.

Clients have no public profile, so `email_verified` for clients only affects whether they can submit a review (because `ReviewController::store` is gated by the §8.4 guard).

## 10. Email content (Albanian, plain text)

```
Subject: Helppy.com — kodi i verifikimit

Pershendetje {NAME},

Kodi juaj i verifikimit per Helppy.com eshte:

  {CODE}

Ky kod vlen 15 minuta. Nese nuk e keni kerkuar ju, injorojeni kete email.

Faleminderit,
Ekipi Helppy.com
```

The `{NAME}` and `{CODE}` placeholders are filled by `Verification::send` (simple `strtr`, no templating engine). Same body for signup and login 2FA — the user doesn't need to know the difference.

## 11. Security considerations

- **Constant-time compare:** `hash_equals($expected, $supplied)` not `==`.
- **Session fixation:** `Auth::setPending()` calls `session_regenerate_id(true)` before setting the key. `Auth::login()` already does this.
- **Replay:** Each successful verify clears `verification_code`. The same code cannot be reused even within the 15-minute window.
- **Brute force:** 5 attempts → code invalidated. 60s resend cooldown caps attacker throughput to ~5 codes/minute per account, on a 6-digit space → ~200,000 attempts on average per code → effectively unbruteforceable.
- **Email enumeration:** Login behavior is identical for unknown email and wrong password (same flash). Already in place — no change.
- **Code in URL:** Never. The code only enters via POST body.
- **Lockout / DoS:** No formal account lockout. A malicious party with a target's email+password could spam the target with 2FA emails (one per cooldown). Acceptable for MVP — Gmail enforces its own per-recipient throttling.
- **Admin lockout:** If admin loses Gmail, they must update `users.email` in phpMyAdmin to a working address. There is intentionally no admin-skips-2FA shortcut.

## 12. Acceptance criteria

The feature is "done" when:

### Signup verification

1. Register as a new **client** with a real Gmail address. Redirected to `/verify-email`. An email arrives within ~10 seconds with a 6-digit code addressed by name.
2. Enter that code → redirected to `/`. The client can now submit a review.
3. Register as a new **provider** with a real Gmail. Redirected to `/verify-email`, email arrives. Enter the code → redirected to `/provider/dashboard`. Their profile is now visible in `/search` and at `/provider/{id}`.
4. Register a third provider but do NOT verify. While logged in (`email_verified=0`), try to GET `/provider/dashboard` directly → bounced to `/verify-email`. Profile is hidden from search and `/provider/{id}` returns 404.

### Login-time 2FA — every role

5. Log out the verified client from step 2. Log in again → bounced to `/verify-email` with a fresh code email. Enter code → redirected to `/`.
6. Log out the verified provider from step 3. Log in → bounced to `/verify-email`, code emailed. Enter → redirected to `/provider/dashboard`.
7. Log in as the seeded admin (`admin@helppy.com`). After password check → bounced to `/verify-email`, code emailed. Enter code → redirected to `/admin`.
8. Cancel mid-flow: log in, get to `/verify-email`, click "Anulo" → flash "Anuluat verifikimin.", redirected to `/login`. Session is back to anonymous (`Auth::check()` false, `Auth::pendingUid()` null).

### Resend + attempts

9. On `/verify-email`, click "Dergo perseri" within 60s → flash "Prisni Xs perpara se te dergoni perseri" (X is the real remaining seconds).
10. Wait 60s+, click resend → new email arrives, previous code invalidated immediately.
11. Enter wrong code 5 times → 5th flash says invalid, `verification_code` becomes NULL in DB. Pressing "Dergo perseri" after the cooldown produces a new working code.

### Mailer failure path

12. Set `mailer.username = ''` in `config/config.php`. Register a new client → user row IS created, redirect to /verify-email, flash "Llogaria u krijua, por emaili nuk u dergua." `storage/mail.log` contains an entry.
13. Restore mailer credentials, hit resend on /verify-email → email arrives, normal flow resumes.

### Existing data (post-migration)

14. After running the migration, seeded users (admin, client, provider1–6) have `email_verified=1`. They can log in — but each login still triggers a fresh 2FA code via email. Without correctly configured mailer creds, they CANNOT log in.

### Browse unauthenticated

15. Anonymous visitor browsing `/`, `/search`, `/provider/{id}` is unaffected — no 2FA. Verified providers are visible.

If any of 1–15 fails, the feature is not done.

## 13. Files touched

```
NEW   app/core/Mailer.php
NEW   app/core/Verification.php
NEW   app/views/auth/verify.php
NEW   db/migrations/2026-06-04-email-verification.sql
NEW   storage/.gitkeep                                  (log directory)
MOD   db/schema.sql                                    (add 5 columns)
MOD   config/config.example.php                        (add mailer block)
MOD   config/config.php                                (add mailer block, blank creds)
MOD   public/index.php                                 (4 new routes)
MOD   app/core/Auth.php                                (pendingUid / setPending / clearPending)
MOD   app/controllers/AuthController.php               (4 new methods + register + login changes)
MOD   app/controllers/ProviderController.php           (3 method verification guards + show 404 unverified)
MOD   app/controllers/ClientController.php             (dashboard verification guard)
MOD   app/controllers/ReviewController.php             (store/destroy verification guards)
MOD   app/controllers/AdminController.php              (8 method verification guards)
MOD   app/models/Provider.php                          (verification check in search/featured/find/allWithStatus)
MOD   app/views/admin/providers.php                    (verification column)
MOD   README.md                                        (App Password setup section)
MOD   .gitignore                                       (storage/*.log)
```

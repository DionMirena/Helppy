# Email-Based 2FA for Providers and Companies — Design

**Date:** 2026-06-04
**Author:** Dion (with Claude)
**Status:** Approved, ready for implementation planning
**Supersedes:** moves the "Email verification" Phase 2 item out of `2026-06-04-helppy-design.md` §10.

## 1. Overview

Providers and companies must enter a one-time 6-digit code emailed to them **at registration AND on every login**. Without entering the current code, they cannot reach `/provider/dashboard`, and their public profile is hidden from search until they have completed verification at least once.

Clients are unaffected — they continue to register and log in instantly with no email step.

This is two distinct mechanisms sharing the same email + UI:

| Mechanism | When | Persistent effect |
|-----------|------|-------------------|
| **Signup verification** | Once, immediately after `POST /register` | Sets `email_verified = 1` so the profile becomes visible in search |
| **Login 2FA challenge** | Every successful email+password match for a provider | None — purely a session-time check |

The verification page (`/verify-email`) and the SMTP plumbing are shared. The code columns on `users` get re-populated on every relevant event.

## 2. Goals & non-goals

### Goals

- Real email is delivered from Gmail SMTP to the provider on every register and every login.
- The provider cannot reach any provider-only page (`/provider/dashboard`, `/provider/edit`, `/provider/photo`) without entering the current code.
- Their profile is hidden from search and 404s on public access until signup verification completes (at least once).
- Pure PHP, no Composer.
- A provider who loses or mistypes the code can request a fresh one (rate-limited, 60s).

### Non-goals (explicitly out of scope)

- 2FA for clients (instant login as today).
- 2FA for admin (instant login as today).
- "Remember this device for 30 days" option — every login gets a fresh code.
- SMS / phone verification.
- Magic-link verification — UI uses a 6-digit numeric code only.
- Backup codes / recovery codes.
- Forgot-password flow.
- Authenticator-app TOTP.
- Re-verifying email when a provider edits their email (the email field is not editable in the MVP).

## 3. Decisions locked

| Decision | Value |
|----------|-------|
| Triggers | (a) `POST /register` for `provider` role; (b) `POST /login` for any user with `role = 'provider'` |
| Affects | `role = 'provider'` only. Companies are providers with `is_company = 1`. |
| Code format | 6 digits, generated via `random_int(0, 999999)` then zero-padded |
| Expiry | 15 minutes from issue |
| Attempts before invalidate | 5 wrong submissions for the current code |
| Resend cooldown | 60 seconds since last send |
| SMTP source | Gmail with an App Password, configured in `config/config.php` |
| Pre-2FA session state | `$_SESSION['pending_2fa_uid']` only; `Auth::check()` returns FALSE until 2FA passes |
| Verified user UX | Cannot reach dashboard until code accepted; nav shows "Verifiko emailin" link |

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

`email_verified` defaults to `1` so any user inserted directly via SQL (admin maintenance) is trusted. The signup path explicitly inserts providers with `email_verified = 0`. The login 2FA challenge does NOT read or write `email_verified` — it only uses the four code/expiry/attempts/last_sent columns.

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
- Subtext masked email (e.g. `d***@gmail.com`).
- Numeric input (`inputmode="numeric"`, `pattern="[0-9]{6}"`, `maxlength="6"`, autofocus).
- "Verifiko" submit button.
- Below: "Dergo perseri kodin" form (POST to `/verify-email/resend`). If cooldown > 0, button is disabled and text shows "Prisni Xs perpara se te dergoni perseri".
- Bottom: small text "Nuk je ti? <a href='/verify-email/cancel'>Anulo</a>" — cancels the pending 2FA session (see §7).

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
- `/verify-email/cancel` simply clears `pending_2fa_uid` (and does NOT log out a signup-verifying user — see §8.3) and redirects to `/login` with flash "Anuluat verifikimin."

## 8. Controller changes

### 8.1 `AuthController::register` (signup-verification flow)

```
// existing validation + transactional insert of user/provider/categories...
// for $isProviderRole branch:
if ($isProviderRole) {
    // user row created with email_verified=0 (default 1 overridden in INSERT)
    Verification::generateCodeFor($uid);
    try { Verification::send($uid); }
    catch (Throwable $e) {
        error_log("[Mailer signup] " . $e->getMessage(), 3, APP_ROOT . '/storage/mail.log');
        $this->flash('danger', 'Llogaria u krijua, por emaili nuk u dergua. Provoni te dergoni perseri.');
    }
    Auth::login($user);              // user IS logged in, but email_verified=0
    $this->redirect('/verify-email');
}
// clients unchanged — log in and redirect to /
```

Implementation: keep `User::create()`'s public signature unchanged, then immediately after creating the provider row, run `DB::q('UPDATE users SET email_verified=0 WHERE id=?', [$uid])` for provider/company roles. This avoids editing the seed file and keeps `User::create` reusable.

### 8.2 `AuthController::login` (per-login 2FA challenge)

```
$user = User::findByEmail($email);
if (!$user || !password_verify($pass, $user['password_hash'])) {
    flash danger; redirect /login;
}
if (!$user['is_active']) {
    flash danger; redirect /login;
}

// Branch on role:
if ($user['role'] === 'provider') {
    // Issue a fresh 2FA code regardless of email_verified flag.
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
    return;
}

// admin or client: existing behaviour - full login immediately
Auth::login($user);
// existing role-based redirect ($user['role'] === 'admin' -> /admin, else /)
```

### 8.3 New `AuthController` methods

```php
public function verifyForm(array $params = []): void {
    $uid = Auth::pendingUid() ?? (Auth::check() ? (int)Auth::user()['id'] : null);
    if ($uid === null) { $this->redirect('/login'); }
    // if the logged-in user is already verified AND there's no pending challenge, send them on.
    if (Auth::check() && Verification::isEmailVerified($uid) && Auth::pendingUid() === null) {
        $this->redirect('/provider/dashboard');
    }
    $email = (string)DB::q('SELECT email FROM users WHERE id=?', [$uid])->fetchColumn();
    $this->render('auth/verify', [
        'title'           => 'Verifiko emailin',
        'masked_email'    => self::maskEmail($email),
        'resend_in'       => Verification::secondsUntilResend($uid),
        'mode'            => Auth::pendingUid() !== null ? 'login' : 'signup',
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

    $ok = Verification::verify($uid, $code);
    if (!$ok) {
        $this->flash('danger', 'Kodi i pavlefshem ose i skaduar.');
        $this->redirect('/verify-email');
    }

    // SUCCESS: figure out which flow we just completed.
    if (Auth::pendingUid() !== null) {
        // login-time 2FA: do the actual login now
        $user = User::find($uid);
        Auth::clearPending();
        Auth::login($user);
        $this->flash('success', 'Mire se erdhet, ' . $user['name'] . '!');
        $this->redirect('/provider/dashboard');
    } else {
        // signup verification: mark verified, stay logged in
        DB::q('UPDATE users SET email_verified=1 WHERE id=?', [$uid]);
        $this->flash('success', 'Emaili u verifikua. Mire se erdhet ne Helppy!');
        $this->redirect('/provider/dashboard');
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

/** "d***@kore.co" — keeps first char of local part, masks the rest with `***`, keeps domain. */
private static function maskEmail(string $email): string {
    $at = strpos($email, '@');
    if ($at === false || $at === 0) return $email;
    return $email[0] . '***' . substr($email, $at);
}

public function cancelVerification(array $params = []): void {
    // Only cancels a login-time challenge. Signup-time verification cannot be "cancelled"
    // because the user is already logged in — they would just /logout instead.
    Auth::clearPending();
    $this->flash('info', 'Anuluat verifikimin.');
    $this->redirect('/login');
}
```

### 8.4 Route gates on existing provider pages

`ProviderController::dashboard / update / uploadPhoto` already call `Auth::require('provider')`. They get an additional guard at the start:

```php
Auth::require('provider');
if (!Verification::isEmailVerified((int)Auth::user()['id'])) {
    $this->redirect('/verify-email');
}
```

(This handles the case where a signup-verifying user types `/provider/dashboard` into the URL bar to skip the verify step. They get bounced back.)

`ClientController::dashboard`, `ReviewController::store/destroy` are unaffected — clients are not gated by 2FA.

## 9. Search / profile changes

These are the same as in the prior spec — only providers who have completed signup verification at least once (`email_verified = 1`) appear in search.

`Provider::search()`, `Provider::featured()`: JOIN clause adds `AND u.email_verified = 1`.
`Provider::find()`: SELECT list gains `u.email_verified`.
`Provider::allWithStatus()` (admin table): SELECT list gains `u.email_verified` so the admin can see verification status as a column.
`ProviderController::show()`: returns 404 if `email_verified = 0`.

Note: 2FA challenges on login do NOT affect search. A verified provider is visible even while sitting at the `/verify-email` 2FA page for their own session.

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

The `{NAME}` and `{CODE}` placeholders are filled by `Verification::send` (simple `strtr`, no templating engine).

The same email body is used for signup AND login 2FA — the user doesn't need to know the difference.

## 11. Security considerations

- **Constant-time compare:** `hash_equals($expected, $supplied)` not `==`.
- **Session fixation:** `Auth::setPending()` calls `session_regenerate_id(true)` before setting the key. `Auth::login()` already does this.
- **Replay:** Each successful verify clears `verification_code`. The same code cannot be reused.
- **Brute force:** 5 attempts → code invalidated. 60s resend cooldown caps attacker throughput to ~5 codes/minute per account, on a 6-digit space → 200,000 attempts on average per code → effectively unbruteforceable in any practical window.
- **Email enumeration:** Login behavior is identical for unknown email and wrong password (same flash). No change.
- **Code in URL:** Never. The code only enters the system via POST body.
- **Lockout / DoS:** No formal lockout. A malicious party with a target's email+password could spam them with 2FA emails (one per cooldown). Acceptable for MVP — Gmail has its own per-recipient throttling.
- **Unverified profile leak:** `Provider::find()` is used by both the public profile show controller AND the dashboard. The 404-on-unverified rule applies to `show` only. The dashboard renders unverified users' own data so they can see what's pending — but only after they pass 2FA OR are in signup-verify mode.

## 12. Acceptance criteria

The feature is "done" when:

### Signup verification

1. Register as a new provider with a real Gmail address. Redirected to `/verify-email`. An email arrives at that Gmail within ~10 seconds with a 6-digit code addressed by name.
2. Enter that code on `/verify-email` → redirected to `/provider/dashboard`. The provider profile is now visible at `/search?city=X&category=Y` and at `/provider/{id}`.
3. Register a second provider but do NOT verify. While logged in (with `email_verified=0`), trying to GET `/provider/dashboard` directly → bounced to `/verify-email`. The profile is NOT visible publicly (search excludes them, `/provider/{id}` returns 404).

### Login-time 2FA

4. Log out the verified provider from step 2. Go to `/login`, enter their email + password. Submit. NOT redirected to `/provider/dashboard` — redirected to `/verify-email`. `Auth::check()` returns false at this point; `Auth::pendingUid()` returns their id. An email arrives with a fresh 6-digit code that is DIFFERENT from any previous one.
5. Enter the new code → redirected to `/provider/dashboard`. Full login completes; nav bar shows their name.
6. Log out. Log back in. Receive a brand-new code via email. Old codes (including the signup code from step 1) no longer work even if they hadn't expired.
7. Mid-login 2FA, click "Anulo" → redirected to `/login` with flash "Anuluat verifikimin." Session no longer holds `pending_2fa_uid`.

### Resend + attempts

8. On `/verify-email`, click "Dergo perseri" within 60s → flash "Prisni Xs perpara se te dergoni perseri." (X is the actual remaining seconds.)
9. Wait 60s+, click resend → new email arrives, previous code invalidated.
10. Enter wrong code 5 times → flash on 5th says invalid, `verification_code` becomes NULL in DB. Pressing "Dergo perseri" works (now past cooldown after 60s).

### Mailer failure path

11. Set `mailer.username = ''` in `config/config.php`. Register a new provider → user row is created (DB visible), redirect to /verify-email, flash "Llogaria u krijua, por emaili nuk u dergua." `storage/mail.log` contains an entry.
12. Restore mailer credentials, hit resend on /verify-email → email arrives, normal flow resumes.

### Existing data

13. Existing seeded users (admin, client, provider1–6) continue to work as before. Client + admin login is instant (no email). Provider1 login now requires 2FA (new emails sent each time), but their `email_verified=1` means they remain searchable and their profiles return 200 publicly.

### Clients unaffected

14. Register a new client → instant login (no email step). Log out and back in → instant login.

If any of 1–14 fails, the feature is not done.

## 13. Files touched

```
NEW   app/core/Mailer.php
NEW   app/core/Verification.php
NEW   app/views/auth/verify.php
NEW   db/migrations/2026-06-04-email-verification.sql
NEW   storage/                                         (gitignored - log file destination, .gitkeep'd)
MOD   db/schema.sql                                    (add 5 columns)
MOD   config/config.example.php                        (add mailer block)
MOD   config/config.php                                (add mailer block, blank creds)
MOD   public/index.php                                 (4 new routes)
MOD   app/core/Auth.php                                (pendingUid / setPending / clearPending)
MOD   app/controllers/AuthController.php               (4 new methods + register + login changes)
MOD   app/controllers/ProviderController.php           (dashboard/update/uploadPhoto verification guard; show 404 on unverified)
MOD   app/models/Provider.php                          (verification check in search/featured/find/allWithStatus)
MOD   app/views/admin/providers.php                    (verification column)
MOD   README.md                                        (App Password setup section)
MOD   .gitignore                                       (storage/*.log)
```

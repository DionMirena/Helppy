# Email Verification for Providers and Companies — Design

**Date:** 2026-06-04
**Author:** Dion (with Claude)
**Status:** Approved, ready for implementation planning
**Supersedes:** moves the "Email verification" Phase 2 item out of `2026-06-04-helppy-design.md` §10.

## 1. Overview

Providers and companies must verify their email address before their account becomes usable. After they submit the registration form, the system emails a 6-digit code to the address they provided. They cannot log in or appear in search until they enter the code.

Clients are unaffected — they continue to register and log in immediately.

This document defines the addition. The base MVP spec (`2026-06-04-helppy-design.md`) remains the canonical reference for everything else.

## 2. Goals & non-goals

### Goals

- Real email is delivered from Gmail via SMTP to the provider's inbox.
- The provider cannot use the platform until verified.
- The mechanism is implementable in plain PHP with no Composer dependency.
- A provider who loses/mistypes the code can request a fresh one (rate-limited).

### Non-goals (explicitly out of scope)

- Verifying clients' emails.
- SMS / phone verification.
- Re-verifying when a provider edits their email address (the email field is not editable today — out of scope until it is).
- A "manually mark verified" admin UI. If unblocking is needed, admin can set `users.email_verified = 1` directly in phpMyAdmin.
- Forgot password / password reset.
- Magic-link verification (UI uses a 6-digit code only).

## 3. Decisions locked

| Decision | Value |
|----------|-------|
| Trigger | Once, at registration |
| Affects | role IN ('provider') only (companies are providers with `is_company=1`) |
| Code format | 6 digits, generated via `random_int(0, 999999)` then zero-padded |
| Expiry | 15 minutes from issue |
| Attempts before invalidate | 5 wrong submissions |
| Resend cooldown | 60 seconds since last send |
| SMTP source | Gmail with an App Password, configured in `config/config.php` |
| Unverified UX | Redirected to `/verify-email`, profile hidden from search, profile 404s publicly |

## 4. Database schema delta

Migration file: `db/migrations/2026-06-04-email-verification.sql`

```sql
ALTER TABLE users
  ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN verification_code CHAR(6) NULL,
  ADD COLUMN verification_expires_at DATETIME NULL,
  ADD COLUMN verification_attempts TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN verification_last_sent_at DATETIME NULL;

-- Existing seeded rows are already trusted; mark them verified explicitly.
UPDATE users SET email_verified = 1;
```

The base `db/schema.sql` is also updated so a fresh install includes these columns from the start. `db/seed.sql` is unchanged (defaults to verified).

Defaults: `email_verified` defaults to `1`. Registration code *explicitly* sets it to `0` for new providers. This means any future user inserted via SQL/admin is treated as verified — desirable; the verification gate only triggers from the registration flow.

## 5. Configuration delta

`config/config.example.php` and `config/config.php` gain:

```php
'mailer' => [
    'host'     => 'smtp.gmail.com',
    'port'     => 587,
    'username' => '',          // YOUR Gmail address — fill in
    'password' => '',          // App Password (16 chars, NOT your Gmail password)
    'from'     => 'Helppy.com <noreply@helppy.com>',
    'reply_to' => '',          // optional
    'timeout'  => 10,          // seconds for SMTP connect / read
],
```

If `username` or `password` is empty when a verification email is attempted, the controller logs an error to `storage/mail.log` (directory is auto-created with `mkdir(..., 0775, true)` if missing) and shows the user a generic "Email could not be sent, contact support" message. The account stays unverified — the user can try resend.

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
- `stream_socket_client("tcp://{host}:{port}", ..., timeout)`
- Read greeting, send `EHLO helppy.com`, parse capabilities.
- If `tls` configured and `STARTTLS` advertised: `STARTTLS`, then `stream_socket_enable_crypto(..., STREAM_CRYPTO_METHOD_TLS_CLIENT)`, then re-`EHLO`.
- `AUTH LOGIN`, send base64 username, base64 password.
- `MAIL FROM:<from>`, `RCPT TO:<to>`, `DATA`.
- Headers: `From`, `To`, `Subject`, `MIME-Version: 1.0`, `Content-Type: text/plain; charset=utf-8`, `Date`, `Message-ID`.
- Body, then `.\r\n` terminator.
- `QUIT`.

All read/write through small `readLine()` and `command()` helpers that throw on unexpected status codes (`4xx`/`5xx`).

If `CONFIG['mailer']['username'] === ''`: don't attempt to send; throw immediately and let caller log + flash. (No silent success.)

This is roughly 150 lines. We will not use this for HTML email or attachments — text/plain only.

### 6.2 `app/core/Verification.php`

Domain helper:

```php
final class Verification {
    public static function generateCodeFor(int $userId): string;   // sets code, expiry, last_sent_at, attempts=0; returns the code
    public static function send(int $userId): void;                // reads user name + email + current code from DB, calls Mailer; rethrows on error
    public static function verify(int $userId, string $code): bool;// returns true on success; increments attempts on fail; invalidates code on 5th fail
    public static function canResend(int $userId): bool;           // true if verification_last_sent_at IS NULL or older than 60s
    public static function secondsUntilResend(int $userId): int;   // 0 if can resend now, else remaining seconds
    public static function isVerified(int $userId): bool;
}
```

Encapsulates all column writes — controllers never touch the columns directly. `send()` is responsible for filling the `{NAME}` and `{CODE}` placeholders in the email template (§10).

### 6.3 `app/views/auth/verify.php`

Page rendered when the user is logged in but unverified. Contains:
- The masked email they registered with: e.g. `d***@gmail.com`.
- 6-digit code input (numeric, autofocus).
- "Verifiko" submit button.
- "Dergo perseri kodin" form (POST to `/verify-email/resend`).
- Logout link (so they can switch accounts).

## 7. Routes added

```
GET  /verify-email          AuthController@verifyForm
POST /verify-email          AuthController@verify
POST /verify-email/resend   AuthController@resendVerification
```

All three require the user to be logged in (`Auth::require()` with no role). The form/verify methods short-circuit to `/provider/dashboard` if the user is already verified.

## 8. Controller changes

### 8.1 `AuthController::register`

Currently auto-logs in everyone. Change for `provider`/`company` only:

```
// After the existing transaction creates user + provider + categories:
if ($isProviderRole) {
    Verification::generateCodeFor($uid);   // also resets attempts, sets last_sent_at
    try { Verification::send($uid); }
    catch (Throwable $e) {
        error_log("[Mailer] " . $e->getMessage(), 3, APP_ROOT . '/storage/mail.log');
        $this->flash('danger', 'Llogaria u krijua, por emaili nuk u dergua. Provoni te dergoni perseri.');
    }
    Auth::login($user);
    $this->redirect('/verify-email');
}
// Clients unchanged — log in and redirect to /
```

### 8.2 `AuthController::login`

Add post-login branch:

```
if ($user['role'] === 'provider' && (int)$user['email_verified'] === 0) {
    $this->redirect('/verify-email');     // not /provider/dashboard
}
```

### 8.3 New methods on `AuthController`

```php
public function verifyForm(array $params = []): void;        // render auth/verify
public function verify(array $params = []): void;            // POST handler
public function resendVerification(array $params = []): void;// POST handler
```

`verify` increments attempt counter on failure. On 5th failure: null out the code so user is forced to resend. On success: set `email_verified = 1`, clear code columns, flash success, redirect to `/provider/dashboard`.

`resendVerification` checks `canResend`; if too soon, flash "Prisni X sekonda perpara se te dergoni perseri." If allowed, regenerate code + send + redirect back to `/verify-email`.

## 9. Search / profile changes

Two methods on `Provider.php` need a verification check added:

`Provider::search()` and `Provider::featured()` JOIN clause changes from:
```sql
JOIN users u ON u.id = p.user_id AND u.is_active = 1
```
to:
```sql
JOIN users u ON u.id = p.user_id AND u.is_active = 1 AND u.email_verified = 1
```

`ProviderController::show()` adds after the existing `is_active` check:
```php
if (empty($provider['is_active']) || empty($provider['email_verified'])) { $this->notFound(); return; }
```

`Provider::find()` already SELECTs from `users` — extend the SELECT list to include `u.email_verified` so the controller has access to it.

`Provider::allWithStatus()` (used by `/admin/providers`) also adds `u.email_verified` to the SELECT so admin can see verification status. The admin table view gets a new column "Email i verifikuar" showing a checkmark or X.

## 10. Email content

Albanian, plain text (no HTML for v1):

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

## 11. Security considerations

- **Constant-time compare:** `hash_equals($expected, $supplied)` not `==`.
- **Timing oracle on resend:** Don't reveal "this email is verified" or "no such user" — the resend route works only for the logged-in user, so this is naturally limited.
- **Brute force:** 5 attempts → code invalidated. Cooldown of 60s on resend caps how fast attackers can cycle codes (worst case ~5 codes per minute of valid attempts).
- **Email enumeration:** Already mitigated by the existing duplicate-email flash. No change.
- **Code in URL:** No magic link. Code stays in the form body, not query string, not logs.
- **Email storage:** Plaintext code in DB is acceptable here because (a) it's short-lived, (b) database access already implies full account compromise. No need to hash a 6-digit code.

## 12. Acceptance criteria

The feature is "done" when a user can:

1. Register as a provider with a real Gmail address. The form returns a redirect to `/verify-email`. An email arrives at that Gmail within ~10 seconds containing a 6-digit code and the user's name.
2. Enter that code on `/verify-email`. The page redirects to `/provider/dashboard`. The provider profile is now searchable via `/search?city=X&category=Y`.
3. Register a second provider but do NOT verify. Log out, log back in as that provider. The login redirect lands them on `/verify-email`, not `/provider/dashboard`. Their profile is NOT visible at `/provider/{id}` (404).
4. On the verify page, click "Dergo perseri" within 60 seconds → see flash "Prisni X sekonda perpara se te dergoni perseri." After 60s, click again → a new email arrives with a new code. The first code no longer works.
5. Enter wrong code 5 times → flash "Kodi u shenuar te pavlefshem, dergoni perseri." Code is cleared (`verification_code` is NULL in DB). User clicks resend → new code → succeeds.
6. Set `mailer.username = ''` in config → register a provider → see "Llogaria u krijua, por emaili nuk u dergua." flash. The user is created with `email_verified = 0` and a code is generated. Resend with proper config works.
7. Existing seeded providers (provider1-6) continue to log in and appear in search exactly as before. The migration must mark them verified.
8. Clients continue to register and log in instantly with no email step.

If any criterion fails, the feature is not done.

## 13. Files touched

```
NEW   app/core/Mailer.php
NEW   app/core/Verification.php
NEW   app/views/auth/verify.php
NEW   db/migrations/2026-06-04-email-verification.sql
MOD   db/schema.sql                                    (add columns)
MOD   config/config.example.php                        (add mailer block)
MOD   config/config.php                                (add mailer block, blank creds)
MOD   public/index.php                                 (3 new routes)
MOD   app/controllers/AuthController.php               (3 new methods, register+login changes)
MOD   app/models/Provider.php                          (verification check in search/featured/find/allWithStatus)
MOD   app/controllers/ProviderController.php           (show: 404 if unverified)
MOD   app/views/admin/providers.php                    (verification column)
MOD   README.md                                        (App Password setup section)
NEW   storage/                                         (gitignored - log file destination)
MOD   .gitignore                                       (add storage/)
```

-- Presence tracking: last activity timestamp per user. Updated on every
-- authenticated request via Presence::touch(). "Online" = last_seen within
-- the threshold defined in app/core/Presence.php.
SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN last_seen_at TIMESTAMP NULL AFTER district,
  ADD INDEX idx_last_seen (last_seen_at);

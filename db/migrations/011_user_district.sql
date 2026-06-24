-- District lives on the user row so search/queries don't have to join through
-- Geography in PHP every time. Backfilled from the user's city via the same
-- name→district map defined in app/core/Geography.php.
SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN district VARCHAR(40) NULL AFTER city_id,
  ADD INDEX idx_district (district);

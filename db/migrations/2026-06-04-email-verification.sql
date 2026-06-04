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

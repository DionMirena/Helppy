-- Phase: password reset/change.
-- Adds two columns to users for the forgot-password flow.
SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN password_reset_code CHAR(6) NULL AFTER verification_last_sent_at,
  ADD COLUMN password_reset_expires_at DATETIME NULL AFTER password_reset_code,
  ADD COLUMN password_reset_last_sent_at DATETIME NULL AFTER password_reset_expires_at;

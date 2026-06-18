-- Phase C+: track which bank a manual-transfer subscription was paid to.
SET NAMES utf8mb4;

ALTER TABLE subscriptions
  ADD COLUMN bank_chosen VARCHAR(40) NULL AFTER bank_reference;

-- Phase: add per-plan period support so subscriptions can have different durations.
-- Existing rows default to the old 30-day standard/premium plans.
SET NAMES utf8mb4;

ALTER TABLE subscriptions
  ADD COLUMN plan VARCHAR(40) NOT NULL DEFAULT '' AFTER tier,
  ADD COLUMN period_days INT NOT NULL DEFAULT 30 AFTER plan;

-- Backfill the plan key for existing rows from their tier.
UPDATE subscriptions SET plan = CONCAT(tier, '_30') WHERE plan = '';

-- Phase C: provider subscriptions.
-- Run once on the existing helppy database.
SET NAMES utf8mb4;

CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider_id INT NOT NULL,
  tier ENUM('standard','premium') NOT NULL,
  status ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('stripe','bank') NOT NULL,
  amount_eur DECIMAL(8,2) NOT NULL,
  bank_reference VARCHAR(40) NULL,           -- unique code printed on the bank-transfer page
  stripe_session_id VARCHAR(255) NULL,       -- Stripe Checkout session id
  stripe_payment_intent VARCHAR(255) NULL,   -- Stripe PaymentIntent id (on success)
  activated_at  TIMESTAMP NULL,
  expires_at    TIMESTAMP NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_active   (provider_id, status, expires_at),
  INDEX idx_pending  (status, payment_method, created_at),
  UNIQUE KEY uniq_bankref (bank_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

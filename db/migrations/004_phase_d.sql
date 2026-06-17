-- Phase D: provider profile fields + bookings + notifications + chat.
-- Run once on the existing helppy database.
SET NAMES utf8mb4;

-- Provider profile additions (additive; nullable for backward compat)
ALTER TABLE providers
  ADD COLUMN skills_services TEXT NULL AFTER bio,
  ADD COLUMN hourly_rate     DECIMAL(8,2) NULL AFTER skills_services;

-- Bookings: client requests a time slot from a provider; provider accepts/rejects/completes
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id   INT NOT NULL,
  provider_id INT NOT NULL,
  scheduled_at   DATETIME NOT NULL,
  duration_hours DECIMAL(4,2) NULL,
  notes TEXT NULL,
  status ENUM('pending','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_client   (client_id,   created_at DESC),
  INDEX idx_provider (provider_id, status, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications: simple per-user notification stream
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type    VARCHAR(50) NOT NULL,
  title   VARCHAR(200) NOT NULL,
  body    TEXT NULL,
  link    VARCHAR(255) NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id, read_at, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1-on-1 conversations: user_a_id is ALWAYS the smaller user id, user_b_id the larger.
-- Enforced in app code; the UNIQUE makes the (a,b) pair unique.
CREATE TABLE conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_a_id INT NOT NULL,
  user_b_id INT NOT NULL,
  last_message_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pair (user_a_id, user_b_id),
  FOREIGN KEY (user_a_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (user_b_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_last_msg (last_message_at DESC),
  CHECK (user_a_id < user_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  body TEXT NOT NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id)       REFERENCES users(id)         ON DELETE CASCADE,
  INDEX idx_convo (conversation_id, id),
  INDEX idx_unread (conversation_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

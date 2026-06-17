-- Phase B: posts and post_photos.
-- Run once on the existing helppy database.
SET NAMES utf8mb4;

CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('offer','request') NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  category_id INT NOT NULL,
  city_id INT NOT NULL,

  -- Offer-only (NULL for requests)
  price_from DECIMAL(10,2) NULL,
  price_to DECIMAL(10,2) NULL,
  working_hours VARCHAR(120) NULL,
  contact_preferences VARCHAR(200) NULL,

  -- Request-only (NULL for offers)
  budget_from DECIMAL(10,2) NULL,
  budget_to DECIMAL(10,2) NULL,
  deadline DATE NULL,
  urgency ENUM('low','normal','high') NULL,

  -- Shared
  status ENUM('active','closed','hidden') NOT NULL DEFAULT 'active',
  views INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  FOREIGN KEY (city_id)     REFERENCES cities(id)     ON DELETE RESTRICT,

  INDEX idx_feed (type, status, created_at DESC),
  INDEX idx_category (category_id),
  INDEX idx_city (city_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  sort_order TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  INDEX idx_post (post_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

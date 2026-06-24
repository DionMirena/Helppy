-- Work-portfolio photos uploaded directly to a provider's profile.
-- Separate from post_photos (which belong to a specific post).
-- Gated by active subscription at upload time.

CREATE TABLE IF NOT EXISTS provider_photos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_id INT          NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  caption     VARCHAR(255) NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_provider (provider_id, sort_order, id),
  CONSTRAINT fk_provider_photos_user
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

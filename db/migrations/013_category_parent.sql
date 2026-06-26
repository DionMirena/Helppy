-- Hierarchical categories: each row can point at a parent (NULL = top-level).
-- The home page shows top-level chips; clicking one drills into its children.
SET NAMES utf8mb4;

ALTER TABLE categories
  ADD COLUMN parent_id INT NULL AFTER id,
  ADD INDEX idx_parent (parent_id),
  ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE;

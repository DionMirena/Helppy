-- Provider exact location: latitude + longitude (decimal degrees).
-- The picker on the dashboard drops a pin; clients open the pin in Google Maps.
SET NAMES utf8mb4;

ALTER TABLE providers
  ADD COLUMN latitude  DECIMAL(10,7) NULL AFTER company_name,
  ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;

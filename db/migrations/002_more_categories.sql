-- Adds ~25 more home-service categories with Bootstrap icons.
-- Safe to re-run: uses INSERT IGNORE on the unique slug.
SET NAMES utf8mb4;

INSERT IGNORE INTO categories (name, slug, icon) VALUES
  ('Marangoz',          'marangoz',          'bi-tree-fill'),
  ('Klimatizim',        'klimatizim',        'bi-snow'),
  ('Ngrohje',           'ngrohje',           'bi-thermometer-high'),
  ('Pllaka',            'pllaka',            'bi-grid-3x3-gap-fill'),
  ('Suvatim',           'suvatim',           'bi-square-fill'),
  ('Dyer e Dritare',    'dyer-dritare',      'bi-door-open-fill'),
  ('Xhama',             'xhama',             'bi-window'),
  ('Bravari',           'bravari',           'bi-key-fill'),
  ('Çatitari',          'catitari',          'bi-house-fill'),
  ('Hidroizolim',       'hidroizolim',       'bi-droplet-fill'),
  ('Termoizolim',       'termoizolim',       'bi-snow2'),
  ('Gips Karton',       'gips-karton',       'bi-layers'),
  ('Parket e Dysheme',  'parket-dysheme',    'bi-grid'),
  ('Kopshtari',         'kopshtari',         'bi-flower1'),
  ('Pastrim Oxhaku',    'pastrim-oxhaku',    'bi-fire'),
  ('Dezinfektim',       'dezinfektim',       'bi-bug-fill'),
  ('Pajisje Elektroshtepiake', 'pajisje-elektroshtepiake', 'bi-plug-fill'),
  ('Internet e Rrjet',  'internet-rrjet',    'bi-wifi'),
  ('Riparim Kompjuteri','kompjuter',         'bi-pc-display'),
  ('Antena e Satelit',  'antena-satelit',    'bi-broadcast-pin'),
  ('Alarm e Kamera',    'alarm-kamera',      'bi-camera-video-fill'),
  ('Levizje e Transport','levizje-transport','bi-truck'),
  ('Solar',             'solar',             'bi-sun-fill'),
  ('Saldim',            'saldim',            'bi-wrench-adjustable'),
  ('Pastrim Tepihësh',  'pastrim-tepiheve',  'bi-stars'),
  ('Demolim',           'demolim',           'bi-cone-striped'),
  ('Sanitar',           'sanitar',           'bi-droplet');

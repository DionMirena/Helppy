-- Seed data for Helppy.com.
-- Passwords:
--   admin@helppy.com           -> admin123
--   client@helppy.com          -> password
--   provider1..6@helppy.com    -> password
SET NAMES utf8mb4;

-- Cities (Kosovo municipalities)
INSERT INTO cities (name) VALUES
  ('Prishtine'),('Prizren'),('Peje'),('Gjakove'),('Mitrovice'),
  ('Gjilan'),('Ferizaj'),('Vushtrri'),('Suhareke'),('Rahovec'),
  ('Malisheve'),('Drenas'),('Skenderaj'),('Podujeve'),('Lipjan'),
  ('Fushe Kosove'),('Obiliq'),('Kamenice'),('Decan'),('Istog');

-- Categories (icon = bootstrap-icons class)
INSERT INTO categories (name, slug, icon) VALUES
  ('Hidraulike','hidraulike','bi-droplet-half'),
  ('Boje','boje','bi-brush'),
  ('Elektrike','elektrike','bi-lightning-charge'),
  ('Pastrim','pastrim','bi-bucket'),
  ('Stollari','stollari','bi-hammer'),
  ('Murature','murature','bi-bricks'),
  ('Lendine','lendine','bi-tree');

-- Admin (password: admin123)
INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES
  ('Administrator', 'admin@helppy.com',
   '$2y$10$NHZ38zQKBerQ4rvu95ssJe.BgtI/3JcNjjVO2ySIM8Gd4MxwGmBw.',
   '+38344000000', 'admin', 1);

-- Sample client (password: password)
INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES
  ('Test Klient', 'client@helppy.com',
   '$2y$10$VRRIyBDq35Gl5jByQL8h0uFeVjG2rn//kpG5m6o4dQhGiVOuGJ4k6',
   '+38344111111', 'client', 1);

-- Sample providers (all password: password)
INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES
  ('Arben Krasniqi','provider1@helppy.com','$2y$10$VRRIyBDq35Gl5jByQL8h0uFeVjG2rn//kpG5m6o4dQhGiVOuGJ4k6','+38344200001','provider',1),
  ('Bekim Hoxha',   'provider2@helppy.com','$2y$10$VRRIyBDq35Gl5jByQL8h0uFeVjG2rn//kpG5m6o4dQhGiVOuGJ4k6','+38344200002','provider',2),
  ('Driton Berisha','provider3@helppy.com','$2y$10$VRRIyBDq35Gl5jByQL8h0uFeVjG2rn//kpG5m6o4dQhGiVOuGJ4k6','+38344200003','provider',1),
  ('Egzon Gashi',   'provider4@helppy.com','$2y$10$VRRIyBDq35Gl5jByQL8h0uFeVjG2rn//kpG5m6o4dQhGiVOuGJ4k6','+38344200004','provider',3),
  ('Florent Rama',  'provider5@helppy.com','$2y$10$VRRIyBDq35Gl5jByQL8h0uFeVjG2rn//kpG5m6o4dQhGiVOuGJ4k6','+38344200005','provider',1),
  ('Granit Lleshi', 'provider6@helppy.com','$2y$10$VRRIyBDq35Gl5jByQL8h0uFeVjG2rn//kpG5m6o4dQhGiVOuGJ4k6','+38344200006','provider',4);

-- Provider profiles
INSERT INTO providers (user_id, profession, bio, is_company, is_premium) VALUES
  (3, 'Hidraulik',   'Punime profesionale hidraulike, 10+ vite eksperience.', 0, 1),
  (4, 'Elektricist', 'Instalime dhe riparime elektrike per shtepi dhe biznese.', 0, 0),
  (5, 'Bojaxhi',     'Lyerje brendshme dhe te jashtme, cilesi e larte.', 0, 0),
  (6, 'Marangoz',    'Dyer, dritare, mobilje me porosi.', 0, 0),
  (7, 'Murator',     'Ndertim dhe rinovim. Pjese e nje kompanie te vogel.', 1, 1),
  (8, 'Pastrues',    'Pastrim profesional i shtepive dhe zyrave.', 0, 0);

UPDATE providers SET company_name='Lleshi Construction' WHERE user_id=7;

-- Provider -> category mappings
INSERT INTO provider_categories (provider_id, category_id) VALUES
  (3,1),  -- Arben: Hidraulike
  (4,3),  -- Bekim: Elektrike
  (5,2),  -- Driton: Boje
  (6,5),  -- Egzon: Stollari
  (7,6),  -- Florent: Murature
  (7,5),  -- Florent also Stollari
  (8,4);  -- Granit: Pastrim

-- Sample reviews so the home page has some stars
INSERT INTO reviews (provider_id, client_id, rating, comment) VALUES
  (3, 2, 5, 'Punoi shpejt dhe me cilesi. Rekomandoj.'),
  (4, 2, 4, 'Pune e mire, pak vonese.');

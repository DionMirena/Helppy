<?php
declare(strict_types=1);

// Seeder: 20 providers with GPS coordinates spread across Kosovo cities.
// Run once: php db/seed_providers_with_map.php
// Password for all seeded accounts: 'password'

$cfg = require __DIR__ . '/../config/config.php';
$pdo = new PDO(
    "mysql:host={$cfg['db']['host']};port={$cfg['db']['port']};dbname={$cfg['db']['name']};charset=utf8mb4",
    $cfg['db']['user'],
    $cfg['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Fetch existing city IDs keyed by lowercase name so we can look them up.
$cityRows = $pdo->query("SELECT id, name FROM cities")->fetchAll(PDO::FETCH_ASSOC);
$cityByName = [];
foreach ($cityRows as $c) {
    $cityByName[mb_strtolower($c['name'])] = (int)$c['id'];
}

$categoryRows = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$catByName = [];
foreach ($categoryRows as $c) {
    $catByName[mb_strtolower($c['name'])] = (int)$c['id'];
}

// Pick the first available category ID for a given name (partial match).
function catId(array $catByName, string ...$candidates): ?int {
    foreach ($candidates as $try) {
        foreach ($catByName as $name => $id) {
            if (mb_strpos($name, mb_strtolower($try)) !== false) return $id;
        }
    }
    return empty($catByName) ? null : reset($catByName);
}

// Kosovo city centres + small random offset so pins don't all stack.
function jitter(float $base, float $range = 0.015): float {
    return round($base + (mt_rand(-1000, 1000) / 1000) * $range, 7);
}

$pwHash = password_hash('password', PASSWORD_DEFAULT);

// [name, email_slug, phone, role, city_name, lat_base, lng_base,
//  profession, bio_short, hourly, is_company, company_name, is_premium, categories...]
$providers = [
    // ── Pristina ────────────────────────────────────────────────
    ['Arben Krasniqi',  'arben.krasniqi',  '+38344 111 222', 'provider', 'prishtinë', 42.6629, 21.1655,
     'Hidraulik',       'Specialist për instalime hidraulike dhe çelje bllokimesh.',
     18.00, false, null, true,  ['hidraulikë', 'instalime']],

    ['Besa Gashi',      'besa.gashi',      '+38344 222 333', 'provider', 'prishtinë', 42.6629, 21.1655,
     'Pastruese',       'Pastrim i thellë shtëpish dhe zyrash. Disponibël fundjavë.',
     12.00, false, null, false, ['pastrim']],

    ['Elektro Berisha SH.P.K.', 'elektro.berisha', '+38344 333 444', 'provider', 'prishtinë', 42.6629, 21.1655,
     'Elektricist',     'Instalime elektrike, panele dhe sistemim kabllosh.',
     25.00, true,  'Elektro Berisha SH.P.K.', true,  ['elektricist', 'ndriçim']],

    ['Driton Hoxha',    'driton.hoxha',    '+38344 444 555', 'provider', 'prishtinë', 42.6720, 21.1580,
     'Bojaxhi',         'Bojatisje interiori dhe eksteriori. Punë cilësore me garanci.',
     15.00, false, null, false, ['bojatisje', 'riparim']],

    ['Lirie Rexhepi',   'lirie.rexhepi',   '+38345 555 666', 'provider', 'prishtinë', 42.6550, 21.1730,
     'Kujdestare',      'Kujdes për fëmijë dhe të moshuar. Referencë të disponueshme.',
      0.00, false, null, false, ['kujdes', 'familje']],

    // ── Prizren ─────────────────────────────────────────────────
    ['Granit Shala',    'granit.shala',    '+38344 666 777', 'provider', 'prizren', 42.2139, 20.7419,
     'Murues',          'Muraturë, suvatim dhe ndërtim. 12 vjet eksperiencë.',
     20.00, false, null, true,  ['muraturë', 'ndërtim']],

    ['Ndertimi Gashi Group', 'ndertimi.gashi', '+38344 777 888', 'provider', 'prizren', 42.2139, 20.7419,
     'Kompani ndërtimi', 'Ndërtim nga themeli, renovim dhe shtesa ndërtesash.',
     45.00, true,  'Ndertimi Gashi Group', true,  ['ndërtim', 'riparim']],

    ['Fatmire Bytyqi',  'fatmire.bytyqi',  '+38345 888 999', 'provider', 'prizren', 42.2200, 20.7500,
     'Rrobaqepëse',     'Qepje dhe alterime rrobash sipas masës. Dorëzim i shpejtë.',
      8.00, false, null, false, ['rrobaqepësi', 'mode']],

    // ── Peja / Pejë ─────────────────────────────────────────────
    ['Valon Demaj',     'valon.demaj',     '+38344 101 202', 'provider', 'pejë', 42.6597, 20.2883,
     'Marangoz',        'Mobilje sipas porosisë, riparim dyersh dhe dritaresh.',
     22.00, false, null, false, ['marangoz', 'mobilje']],

    ['Klima Peja SH.P.K.', 'klima.peja',  '+38344 202 303', 'provider', 'pejë', 42.6597, 20.2883,
     'Teknician klimash', 'Instalim, servisim dhe riparim klimash të të gjitha markave.',
     30.00, true,  'Klima Peja SH.P.K.', false, ['klima', 'instalime']],

    ['Mentor Avdiu',    'mentor.avdiu',    '+38344 303 404', 'provider', 'pejë', 42.6680, 20.2950,
     'Hidraulik',       'Ndërrrim baterishë, tubacione, instalim lavamaniesh.',
     16.00, false, null, false, ['hidraulikë']],

    // ── Gjilan ──────────────────────────────────────────────────
    ['Egzon Selimi',    'egzon.selimi',    '+38344 404 505', 'provider', 'gjilan', 42.4634, 21.4693,
     'Elektricist',     'Instalime elektrike shtëpiake dhe industriale.',
     20.00, false, null, true,  ['elektricist']],

    ['Servisi Hasani LLC', 'servisi.hasani', '+38344 505 606', 'provider', 'gjilan', 42.4634, 21.4693,
     'Servis pajisjesh', 'Riparim lavastovilje, lavatriçesh, frigoriferësh dhe sobash.',
     28.00, true,  'Servisi Hasani LLC', false, ['riparim', 'elektroshtëpiake']],

    // ── Mitrovica ───────────────────────────────────────────────
    ['Jeton Morina',    'jeton.morina',    '+38344 606 707', 'provider', 'mitrovicë', 42.8914, 20.8659,
     'Bojaxhi',         'Bojatisje e brendshme dhe e jashtme. Çmim të arsyeshëm.',
     14.00, false, null, false, ['bojatisje']],

    ['Pllaka Kelmendi Pro', 'pllaka.kelmendi', '+38344 707 808', 'provider', 'mitrovicë', 42.8914, 20.8659,
     'Pllakues',        'Vendosje pllakash, guri natyror dhe mozaiku. Çdo formë e madhësi.',
     22.00, true,  'Pllaka Kelmendi Pro', true,  ['pllakues', 'ndërtim']],

    // ── Ferizaj ─────────────────────────────────────────────────
    ['Rinor Bajrami',   'rinor.bajrami',   '+38344 808 909', 'provider', 'ferizaj', 42.3703, 21.1553,
     'Hidraulik & Ngrohje', 'Instalim kaldajash, ngrohje qendrore dhe sisteme diellore.',
     25.00, false, null, false, ['hidraulikë', 'ngrohje']],

    ['Blerta Osmani',   'blerta.osmani',   '+38345 909 010', 'provider', 'ferizaj', 42.3750, 21.1600,
     'Pastruese',       'Pastrim pas ndërtimit dhe mirëmbajtje të rregullt.',
     10.00, false, null, false, ['pastrim']],

    // ── Gjakova ─────────────────────────────────────────────────
    ['Termo Kastrati SH.P.K.', 'termo.kastrati', '+38344 010 111', 'provider', 'gjakovë', 42.3797, 20.4311,
     'Termo-izolim',    'Izolim fasade, çatie dhe nënkatin. Kursim energjie garantohet.',
     35.00, true,  'Termo Kastrati SH.P.K.', true,  ['izolim', 'ndërtim']],

    ['Naim Dervishaj',  'naim.dervishaj',  '+38344 111 213', 'provider', 'gjakovë', 42.3797, 20.4311,
     'Instalues dyersh', 'Montim dyersh PVC/alumin dhe xhamash termopan.',
     20.00, false, null, false, ['dyert', 'instalime']],

    // ── Vushtrri ────────────────────────────────────────────────
    ['Burim Halilaj',   'burim.halilaj',   '+38344 213 314', 'provider', 'vushtrri', 42.8231, 21.0994,
     'Elektricist',     'Tabllo elektrike, alarm, kamera sigurie dhe ndriçim LED.',
     18.00, false, null, false, ['elektricist', 'kamera sigurie']],
];

$inserted = 0;
$skipped  = 0;

$pdo->beginTransaction();
try {
    foreach ($providers as $row) {
        [
            $name, $emailSlug, $phone, $role, $cityName,
            $latBase, $lngBase,
            $profession, $bio, $hourly, $isCompany, $companyName, $isPremium,
            $catNames
        ] = $row;

        $email = 'map.' . $emailSlug . '@helppy.test';

        // Skip duplicates
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) { $skipped++; continue; }

        // Resolve city ID (partial match, case-insensitive)
        $cityId = null;
        foreach ($cityByName as $n => $id) {
            if (mb_strpos(mb_strtolower($cityName), mb_substr($n, 0, 5)) !== false ||
                mb_strpos($n, mb_strtolower(mb_substr($cityName, 0, 5))) !== false) {
                $cityId = $id;
                break;
            }
        }
        if ($cityId === null && !empty($cityByName)) {
            $cityId = reset($cityByName); // fallback to first city
        }

        // Insert user
        $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role, city_id, is_active, email_verified)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)"
        )->execute([$name, $email, $pwHash, $phone, $role, $cityId]);
        $uid = (int)$pdo->lastInsertId();

        // GPS pin with small random jitter so markers don't stack
        $lat = jitter($latBase);
        $lng = jitter($lngBase);

        // Insert provider
        $pdo->prepare(
            "INSERT INTO providers (user_id, profession, bio, hourly_rate, is_company, company_name, latitude, longitude, is_premium)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $uid, $profession, $bio,
            $hourly > 0 ? $hourly : null,
            $isCompany ? 1 : 0,
            $companyName,
            $lat, $lng,
            $isPremium ? 1 : 0,
        ]);

        // Categories (best-effort match)
        foreach ($catNames as $catSearch) {
            $cid = catId($catByName, $catSearch);
            if ($cid === null) continue;
            try {
                $pdo->prepare(
                    "INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)"
                )->execute([$uid, $cid]);
            } catch (PDOException $e) { /* ignore dup */ }
        }

        $inserted++;
        echo "  + {$name} ({$profession}) @ {$lat}, {$lng}\n";
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Seeder failed: " . $e->getMessage() . "\n");
    exit(2);
}

echo "\nDone. Inserted {$inserted} providers with GPS pins, skipped {$skipped} duplicates.\n";
echo "All accounts use password: 'password'  |  email suffix: @helppy.test\n";

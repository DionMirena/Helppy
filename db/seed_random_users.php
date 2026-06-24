<?php
declare(strict_types=1);

// One-off random-data seeder: 15 clients + 15 individual providers + 10
// companies, randomly assigned to existing cities and categories. About 1
// in 4 users gets a NULL phone so the "Ska numër" UI state has data to show.
// Re-runnable — checks unique emails so duplicates aren't created.

$cfg = require __DIR__ . '/../config/config.php';
$pdo = new PDO(
    "mysql:host={$cfg['db']['host']};port={$cfg['db']['port']};dbname={$cfg['db']['name']};charset=utf8mb4",
    $cfg['db']['user'],
    $cfg['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$firstM = ['Arben','Bekim','Driton','Egzon','Florent','Granit','Ilir','Jeton','Kreshnik','Liridon','Mentor','Naim','Petrit','Rinor','Shpend','Valon','Yll','Zeqir','Burim','Genti'];
$firstF = ['Albulena','Besa','Drita','Egzona','Fatlinda','Granita','Ilirjana','Jehona','Kaltrina','Lirie','Mimoza','Naime','Petrita','Rina','Shpresa','Valbona','Yllka','Zana','Blerta','Genta'];
$last   = ['Krasniqi','Hoxha','Berisha','Gashi','Rexhepi','Shala','Bytyqi','Demaj','Mehmetaj','Avdiu','Selimi','Hasani','Morina','Kelmendi','Bajrami','Dervishaj','Halilaj','Osmani','Kastrati','Beqiri'];

// Kosovo-style company name shapes.
$companyHead = ['Ndertimi','Servisi','Elektro','Hidro','Bojaxhi','Marangoz','Pllaka','Klima','Stolari','Murature','Termo'];
$companyTail = ['SH.P.K.','LLC','Group','Plus','Pro','Kosova','RKS','Co.'];

// Pick available cities and categories.
$cities     = array_column($pdo->query("SELECT id FROM cities")->fetchAll(PDO::FETCH_ASSOC), 'id');
$categories = array_column($pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
if (!$cities || !$categories) {
    fwrite(STDERR, "Cities/categories empty — seed the base data first.\n");
    exit(1);
}
$categoryIds = array_keys($categories);

$pwHash = password_hash('password', PASSWORD_DEFAULT);

function pick(array $a) { return $a[array_rand($a)]; }
function maybePhone(): ?string {
    if (random_int(1, 4) === 1) return null;             // ~25% no phone
    return '+38344 ' . random_int(100, 999) . ' ' . random_int(100, 999);
}

$insertedUsers   = 0;
$insertedClients = 0;
$insertedProv    = 0;
$insertedComp    = 0;

$pdo->beginTransaction();
try {

    // ---- 15 clients ----
    for ($i = 0; $i < 15; $i++) {
        $first = pick(random_int(0, 1) ? $firstM : $firstF);
        $name  = $first . ' ' . pick($last);
        $email = 'seed.client.' . strtolower($first) . '.' . random_int(1000, 9999) . '@helppy.test';
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) continue;

        $st = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role, city_id, is_active, email_verified)
             VALUES (?, ?, ?, ?, 'client', ?, 1, 1)"
        );
        $st->execute([$name, $email, $pwHash, maybePhone(), pick($cities)]);
        $insertedUsers++;
        $insertedClients++;
    }

    // ---- 15 individual providers (is_company = 0) ----
    for ($i = 0; $i < 15; $i++) {
        $first = pick(random_int(0, 1) ? $firstM : $firstF);
        $name  = $first . ' ' . pick($last);
        $email = 'seed.provider.' . strtolower($first) . '.' . random_int(1000, 9999) . '@helppy.test';
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) continue;

        $st = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role, city_id, is_active, email_verified)
             VALUES (?, ?, ?, ?, 'provider', ?, 1, 1)"
        );
        $st->execute([$name, $email, $pwHash, maybePhone(), pick($cities)]);
        $uid = (int)$pdo->lastInsertId();
        $insertedUsers++;
        $insertedProv++;

        $catId      = pick($categoryIds);
        $profession = $categories[$catId];
        $hourly     = random_int(0, 1) ? null : random_int(8, 35) + (random_int(0, 1) ? 0 : 0.5);
        $premium    = (random_int(1, 5) === 1) ? 1 : 0;        // ~20% premium

        $pdo->prepare(
            "INSERT INTO providers (user_id, profession, hourly_rate, is_company, is_premium)
             VALUES (?, ?, ?, 0, ?)"
        )->execute([$uid, $profession, $hourly, $premium]);

        // Map to 1–2 categories
        $extra = (random_int(0, 1) === 1)
            ? array_diff([pick($categoryIds)], [$catId])
            : [];
        $pdo->prepare("INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)")
            ->execute([$uid, $catId]);
        foreach ($extra as $cid) {
            try {
                $pdo->prepare("INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)")
                    ->execute([$uid, $cid]);
            } catch (PDOException $e) { /* dup ignore */ }
        }
    }

    // ---- 10 companies (is_company = 1) ----
    for ($i = 0; $i < 10; $i++) {
        $headWord = pick($companyHead);
        $surname  = pick($last);
        $tail     = pick($companyTail);
        $company  = $headWord . ' ' . $surname . ' ' . $tail;
        $contact  = pick($firstM) . ' ' . pick($last);
        $email    = 'seed.company.' . strtolower($headWord) . '.' . random_int(1000, 9999) . '@helppy.test';
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) continue;

        $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role, city_id, is_active, email_verified)
             VALUES (?, ?, ?, ?, 'provider', ?, 1, 1)"
        )->execute([$contact, $email, $pwHash, maybePhone(), pick($cities)]);
        $uid = (int)$pdo->lastInsertId();
        $insertedUsers++;
        $insertedComp++;

        $catId      = pick($categoryIds);
        $profession = $categories[$catId];
        $premium    = (random_int(1, 3) === 1) ? 1 : 0;        // companies are premium more often

        $pdo->prepare(
            "INSERT INTO providers (user_id, profession, hourly_rate, is_company, company_name, is_premium)
             VALUES (?, ?, ?, 1, ?, ?)"
        )->execute([$uid, $profession, random_int(15, 60), $company, $premium]);

        // 2–3 categories for companies
        $extra = array_slice(array_unique([$catId, pick($categoryIds), pick($categoryIds)]), 0, 3);
        foreach ($extra as $cid) {
            try {
                $pdo->prepare("INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)")
                    ->execute([$uid, $cid]);
            } catch (PDOException $e) { /* dup ignore */ }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Seeder failed: " . $e->getMessage() . "\n");
    exit(2);
}

echo "Inserted $insertedUsers users — $insertedClients clients, $insertedProv individual providers, $insertedComp companies.\n";
echo "All seed users use password: 'password' and have @helppy.test email suffix.\n";

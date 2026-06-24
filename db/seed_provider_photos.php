<?php
declare(strict_types=1);

// One-off seeder: generates colorful 600x600 JPGs and assigns them as
// "work photos" for one or two providers, so the gallery feature has
// something to render out of the box. Idempotent — bails out if rows exist.

require __DIR__ . '/../config/config.php';
$cfg = require __DIR__ . '/../config/config.php';

$pdo = new PDO(
    "mysql:host={$cfg['db']['host']};port={$cfg['db']['port']};dbname={$cfg['db']['name']};charset=utf8mb4",
    $cfg['db']['user'],
    $cfg['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$uploadDir = $cfg['upload_dir'];
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// Pick a random provider (active + verified, role=provider).
$row = $pdo->query(
    "SELECT u.id, u.name FROM users u
     JOIN providers p ON p.user_id = u.id
     WHERE u.is_active = 1 AND u.email_verified = 1
     ORDER BY RAND() LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    fwrite(STDERR, "No eligible provider found.\n");
    exit(1);
}
$providerId   = (int)$row['id'];
$providerName = (string)$row['name'];

// Bail if this provider already has work photos.
$existing = (int)$pdo->prepare("SELECT COUNT(*) FROM provider_photos WHERE provider_id = ?")
    ->execute([$providerId]) ?: 0;
$st = $pdo->prepare("SELECT COUNT(*) FROM provider_photos WHERE provider_id = ?");
$st->execute([$providerId]);
$existing = (int)$st->fetchColumn();
if ($existing > 0) {
    fwrite(STDOUT, "Provider $providerName (id $providerId) already has $existing work photos — skipping.\n");
    exit(0);
}

// Captions to make the gallery feel real (Albanian).
$captions = [
    'Banjë e rinovuar — Prishtinë',
    'Tubacion i ndërruar pas dëmtimit',
    'Sistemim ujësjellësi për kuzhinë të re',
    'Boja e dhomës pas finalizimit',
    'Riparim çatie pas shiut',
    'Çelje bllokimi tubacioni — punë urgjente',
    'Montim radiatori — apartament',
    'Punë në murature — fasadë e jashtme',
    'Instalim lavaman dhe baterie',
    'Mirëmbajtje sezonale — pranverë',
];
shuffle($captions);

// Palette of green-tinted "work site" colors.
$palettes = [
    [ [90, 122, 79],   [239, 246, 232] ],   // green / cream
    [ [44, 95, 124],   [216, 235, 240] ],   // blue / pale blue
    [ [161, 86, 32],   [253, 232, 215] ],   // brown / sand
    [ [69, 96, 64],    [220, 240, 200] ],   // forest / mint
    [ [102, 76, 156],  [232, 225, 248] ],   // purple / lavender
    [ [194, 124, 35],  [255, 240, 219] ],   // amber / cream
    [ [50, 60, 80],    [220, 226, 234] ],   // slate / fog
];

$count = 6;
$inserted = 0;

for ($i = 1; $i <= $count; $i++) {
    $W = 600; $H = 600;
    $img = imagecreatetruecolor($W, $H);

    [$fg, $bg] = $palettes[array_rand($palettes)];
    $bgColor = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
    $fgColor = imagecolorallocate($img, $fg[0], $fg[1], $fg[2]);
    $accent  = imagecolorallocate($img,
        max(0, min(255, $fg[0] + 40)),
        max(0, min(255, $fg[1] + 40)),
        max(0, min(255, $fg[2] + 40))
    );

    imagefill($img, 0, 0, $bgColor);

    // A few random rectangles to look like "site materials".
    for ($r = 0; $r < 6; $r++) {
        $x1 = random_int(0, $W);
        $y1 = random_int(0, $H);
        $x2 = $x1 + random_int(40, 220);
        $y2 = $y1 + random_int(40, 220);
        imagefilledrectangle($img, $x1, $y1, $x2, $y2,
            $r % 2 === 0 ? $fgColor : $accent);
    }

    // Diagonal stripe across the middle, mimicking a tool line.
    imagesetthickness($img, 8);
    imageline($img, 0, $H/2, $W, $H/2 - 60, $fgColor);
    imageline($img, 0, $H/2 + 60, $W, $H/2 - 120, $accent);

    // Label
    $label = 'Punë #' . $i;
    $textColor = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 20, $H - 80, 220, $H - 20, $fgColor);
    imagestring($img, 5, 32, $H - 60, $label, $textColor);

    $name = 'work_seed_' . bin2hex(random_bytes(8)) . '.jpg';
    $path = $uploadDir . DIRECTORY_SEPARATOR . $name;
    imagejpeg($img, $path, 86);
    imagedestroy($img);

    $caption = $captions[$i - 1] ?? null;
    $ins = $pdo->prepare(
        "INSERT INTO provider_photos (provider_id, filename, caption, sort_order)
         VALUES (?, ?, ?, ?)"
    );
    $ins->execute([$providerId, $name, $caption, $i]);
    $inserted++;
}

echo "Inserted $inserted work photos for provider $providerName (id $providerId).\n";

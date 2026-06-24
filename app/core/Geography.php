<?php
declare(strict_types=1);

/**
 * Kosovo districts → cities map. Used to "broaden" a search to neighboring
 * municipalities when the picked city has no matching providers.
 *
 * Districts are matched against the cities.name column (stored without
 * Albanian diacritics in this DB — Prishtine, Pejë written as "Peje", etc.).
 * If you add a new city, drop it into the right district bucket below.
 */
final class Geography {

    private const DISTRICTS = [
        'Prishtinë' => ['Prishtine', 'Fushe Kosove', 'Lipjan', 'Obiliq', 'Podujeve', 'Drenas'],
        'Mitrovicë' => ['Mitrovice', 'Vushtrri', 'Skenderaj'],
        'Pejë'      => ['Peje', 'Istog', 'Decan'],
        'Prizren'   => ['Prizren', 'Suhareke', 'Malisheve'],
        'Gjakovë'   => ['Gjakove', 'Rahovec'],
        'Ferizaj'   => ['Ferizaj'],
        'Gjilan'    => ['Gjilan', 'Kamenice'],
    ];

    /** Return the district name for a given city name, or null. */
    public static function districtOfCityName(string $cityName): ?string {
        foreach (self::DISTRICTS as $district => $cities) {
            foreach ($cities as $c) {
                if (strcasecmp($c, $cityName) === 0) return $district;
            }
        }
        return null;
    }

    /** City names in the same district as the given city name (excluding it). */
    public static function siblingCityNames(string $cityName): array {
        $district = self::districtOfCityName($cityName);
        if ($district === null) return [];
        $out = [];
        foreach (self::DISTRICTS[$district] as $c) {
            if (strcasecmp($c, $cityName) !== 0) $out[] = $c;
        }
        return $out;
    }

    /**
     * City IDs in the same district as the given city ID (excluding it).
     * Returns [] if the city isn't in our district map or no siblings exist.
     */
    public static function siblingCityIds(int $cityId): array {
        $row = DB::q('SELECT name FROM cities WHERE id = ?', [$cityId])->fetch();
        if (!$row) return [];
        $siblings = self::siblingCityNames((string)$row['name']);
        if (!$siblings) return [];

        $placeholders = implode(',', array_fill(0, count($siblings), '?'));
        $rows = DB::q("SELECT id FROM cities WHERE name IN ($placeholders)", $siblings)->fetchAll();
        return array_map(static fn($r) => (int)$r['id'], $rows);
    }
}

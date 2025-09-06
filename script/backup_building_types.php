<?php
// scripts/backup_building_types.php
// Esegue un CSV dei building_types in storage/backups/building_types, con retention.

declare(strict_types=1);

chdir(dirname(__DIR__)); // vai nella root del progetto (public_html)
require_once 'config.php';
require_once 'includes/autoload.php';

use Ironhaven\Core\Database;

const BACKUP_DIR      = 'storage/backups/building_types';
const RETENTION_DAYS  = 30;   // quanti giorni tenere i backup
const COMPRESS_GZIP   = true; // true => genera .csv.gz

// 1) Assicura cartella
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0775, true);
}

// 2) Estrai i dati
$db   = Ironhaven\Core\Database::getInstance();
$rows = $db->fetchAll("SELECT * FROM building_types ORDER BY name ASC");

// 3) Scrivi CSV
$ts        = date('Ymd_His');
$baseName  = BACKUP_DIR . "/building_types_{$ts}.csv";
$tmp       = $baseName . '.tmp';
$out       = fopen($tmp, 'w');

// intestazioni compatibili con l’import
$headers = [
    'slug','name','description','level_required',
    'water_production','food_production','wood_production','stone_production','iron_production','gold_production',
    'capacity_increase','capacity_resource',
    'water_cost','food_cost','wood_cost','stone_cost','iron_cost','gold_cost',
    'upgrade_cost_multiplier','production_multiplier','capacity_multiplier','time_multiplier',
    'build_time_minutes','max_level','image_url','xp_per_building'
];
fputcsv($out, $headers);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['slug'] ?? '', $r['name'] ?? '', $r['description'] ?? '', $r['level_required'] ?? 1,
        $r['water_production'] ?? 0, $r['food_production'] ?? 0, $r['wood_production'] ?? 0, $r['stone_production'] ?? 0, $r['iron_production'] ?? 0, $r['gold_production'] ?? 0,
        $r['capacity_increase'] ?? 0, $r['capacity_resource'] ?? 'all',
        $r['water_cost'] ?? 0, $r['food_cost'] ?? 0, $r['wood_cost'] ?? 0, $r['stone_cost'] ?? 0, $r['iron_cost'] ?? 0, $r['gold_cost'] ?? 0,
        $r['upgrade_cost_multiplier'] ?? 1.5, $r['production_multiplier'] ?? 1.2,
        $r['capacity_multiplier'] ?? 1.2, $r['time_multiplier'] ?? 1.0,
        $r['build_time_minutes'] ?? 5, $r['max_level'] ?? 10, $r['image_url'] ?? '', $r['xp_per_building'] ?? 10,
    ]);
}
fclose($out);

// 4) Rinomina atomica e comprimi se serve
rename($tmp, $baseName);

if (COMPRESS_GZIP) {
    $gz = gzopen($baseName . '.gz', 'wb9');
    gzwrite($gz, file_get_contents($baseName));
    gzclose($gz);
    @unlink($baseName); // tieni solo .gz
}

// 5) Retention: cancella backup troppo vecchi
$cutoff = time() - (RETENTION_DAYS * 86400);
foreach (glob(BACKUP_DIR . '/building_types_*.csv*') as $f) {
    if (filemtime($f) < $cutoff) @unlink($f);
}

echo "[OK] backup completato\n";

<?php
// pages/admin_building_types.php
require_once 'includes/autoload.php';
require_once 'config.php';

use Ironhaven\Core\Auth;
use Ironhaven\Core\Database;

$auth = Auth::getInstance();
$current = $auth->getCurrentUser();
if (!$auth->isLoggedIn() || !$current || empty($current['is_admin'])) {
    header('Location: index.php');
    exit();
}

/**
 * CSS/JS della pagina
 * - riuso lo stile tech-tree per le classi .tt-*
 * - NON setto $pageScript per evitare 404 se non esiste un JS dedicato
 */
$pageStylesheet = 'tech-tree';
// $pageStylesheet = 'admin-building-types'; // <-- se crei un CSS tuo che @import tech-tree.css
// (non definire $pageScript se non hai assets/js/admin_building_types.js)

include_once 'templates/header.php';

/* ----------------------- helpers ----------------------- */
function slugify($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

function detectDelimiter($line) {
    $c = substr_count($line, ';');
    $k = substr_count($line, ',');
    return ($c > $k) ? ';' : ',';
}

/* ----------------------- import CSV ----------------------- */
$db = Database::getInstance();
$msg = null; $err = null; $preview = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
    $tmp = $_FILES['csv']['tmp_name'];
    $raw = file_get_contents($tmp);

    if ($raw === false) {
        $err = 'Impossibile leggere il file.';
    } else {
        // elimina BOM se presente
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $lines = preg_split("/\r\n|\n|\r/", $raw);
        if (!$lines || !isset($lines[0])) {
            $err = 'File vuoto o non valido.';
        } else {
            $del = detectDelimiter($lines[0]);

            if (($fh = fopen($tmp, 'r')) === false) {
                $err = 'Errore apertura file.';
            } else {
                $headers = fgetcsv($fh, 0, $del);
                $map = [];
                foreach ($headers as $i => $h) {
                    $map[strtolower(trim($h))] = $i;
                }

                $requiredAny = ['name', 'building_type_id', 'slug'];
                if (!array_intersect($requiredAny, array_keys($map))) {
                    $err = 'Il CSV deve contenere almeno una tra le colonne: name, building_type_id o slug.';
                } else {
                    while (($row = fgetcsv($fh, 0, $del)) !== false) {
                        // salta righe completamente vuote
                        if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) continue;

                        $get = function($key, $def = null) use ($map, $row) {
                            $k = strtolower($key);
                            return isset($map[$k]) ? trim((string)$row[$map[$k]]) : $def;
                        };

                        $name = $get('name', $get('building_type_id', ''));
                        $slug = $get('slug') ?: slugify($name);
                        if ($slug === '') continue;

                        $data = [
                            'slug' => $slug,
                            'name' => $name,
                            'description' => $get('description',''),

                            'level_required' => (int)($get('level_required',1)),

                            'water_production' => (int)($get('water_production',0)),
                            'food_production'  => (int)($get('food_production',0)),
                            'wood_production'  => (int)($get('wood_production',0)),
                            'stone_production' => (int)($get('stone_production',0)),
                            'iron_production'  => (int)($get('iron_production',0)),
                            'gold_production'  => (int)($get('gold_production',0)),

                            'capacity_increase' => (int)($get('capacity_increase',0)),
                            'capacity_resource' => $get('capacity_resource','all'),

                            'water_cost' => (int)($get('water_cost',0)),
                            'food_cost'  => (int)($get('food_cost',0)),
                            'wood_cost'  => (int)($get('wood_cost',0)),
                            'stone_cost' => (int)($get('stone_cost',0)),
                            'iron_cost'  => (int)($get('iron_cost',0)),
                            'gold_cost'  => (int)($get('gold_cost',0)),

                            'upgrade_cost_multiplier' => (float)($get('upgrade_cost_multiplier',1.5)),
                            'production_multiplier'   => (float)($get('production_multiplier',1.2)),
                            'capacity_multiplier'     => (float)($get('capacity_multiplier',1.2)),
                            'time_multiplier'         => (float)($get('time_multiplier',1.0)),

                            'build_time_minutes' => (int)($get('build_time_minutes',5)),
                            'max_level' => (int)($get('max_level',10)),
                            'image_url' => $get('image_url',''),
                            'xp_per_building' => (int)($get('xp_per_building', $get('XP_PER_BUILDING',10))),
                        ];

                        $sql = "INSERT INTO building_types
                        (slug,name,description,level_required,
                         water_production,food_production,wood_production,stone_production,iron_production,gold_production,
                         capacity_increase,capacity_resource,
                         water_cost,food_cost,wood_cost,stone_cost,iron_cost,gold_cost,
                         upgrade_cost_multiplier,production_multiplier,capacity_multiplier,time_multiplier,
                         build_time_minutes,max_level,image_url,xp_per_building)
                        VALUES
                        (:slug,:name,:description,:level_required,
                         :water_production,:food_production,:wood_production,:stone_production,:iron_production,:gold_production,
                         :capacity_increase,:capacity_resource,
                         :water_cost,:food_cost,:wood_cost,:stone_cost,:iron_cost,:gold_cost,
                         :upgrade_cost_multiplier,:production_multiplier,:capacity_multiplier,:time_multiplier,
                         :build_time_minutes,:max_level,:image_url,:xp_per_building)
                        ON DUPLICATE KEY UPDATE
                         name=VALUES(name), description=VALUES(description), level_required=VALUES(level_required),
                         water_production=VALUES(water_production), food_production=VALUES(food_production),
                         wood_production=VALUES(wood_production), stone_production=VALUES(stone_production),
                         iron_production=VALUES(iron_production), gold_production=VALUES(gold_production),
                         capacity_increase=VALUES(capacity_increase), capacity_resource=VALUES(capacity_resource),
                         water_cost=VALUES(water_cost), food_cost=VALUES(food_cost), wood_cost=VALUES(wood_cost),
                         stone_cost=VALUES(stone_cost), iron_cost=VALUES(iron_cost), gold_cost=VALUES(gold_cost),
                         upgrade_cost_multiplier=VALUES(upgrade_cost_multiplier),
                         production_multiplier=VALUES(production_multiplier),
                         capacity_multiplier=VALUES(capacity_multiplier),
                         time_multiplier=VALUES(time_multiplier),
                         build_time_minutes=VALUES(build_time_minutes),
                         max_level=VALUES(max_level),
                         image_url=VALUES(image_url),
                         xp_per_building=VALUES(xp_per_building)";

                        try {
                            $stmt = $db->getConnection()->prepare($sql);
                            $stmt->execute($data);
                            $preview[] = $data; // solo per anteprima
                        } catch (\Throwable $e) {
                            $err = 'Errore import: '.$e->getMessage();
                            break;
                        }
                    }
                    if (!$err) $msg = 'Import completato.';
                }
                fclose($fh);
            }
        }
    }
}
?>
<div class="tech-container">
  <h1>Import tipi di edificio (CSV)</h1>

  <?php if ($msg): ?>
    <div class="tt-note" style="color:#0a7d39;"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="tt-note" style="color:#b00020;"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin:12px 0;">
    <input type="file" name="csv" accept=".csv" required>
    <button class="tt-btn" type="submit">Importa</button>
    <small style="color:#666">Separatore automatico (`,` o `;`). Intestazioni case-insensitive.</small>
  </form>

  <?php if (!empty($preview)): ?>
    <details open>
      <summary>Anteprima ultime righe importate</summary>
      <div style="max-height:300px; overflow:auto; border:1px solid #eee; padding:8px; background:#fff;">
        <table class="tt-table" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th>slug</th><th>name</th><th>prod L1</th><th>costi L1</th><th>build L1 (min)</th><th>max L</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach (array_slice($preview, -10) as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['slug']) ?></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td>
                Wtr: <?= (int)$r['water_production'] ?>,
                Food: <?= (int)$r['food_production'] ?>,
                Wood: <?= (int)$r['wood_production'] ?>,
                Stone: <?= (int)$r['stone_production'] ?>,
                Iron: <?= (int)$r['iron_production'] ?>,
                Gold: <?= (int)$r['gold_production'] ?>
              </td>
              <td>
                Wtr: <?= (int)$r['water_cost'] ?>,
                Food: <?= (int)$r['food_cost'] ?>,
                Wood: <?= (int)$r['wood_cost'] ?>,
                Stone: <?= (int)$r['stone_cost'] ?>,
                Iron: <?= (int)$r['iron_cost'] ?>,
                Gold: <?= (int)$r['gold_cost'] ?>
              </td>
              <td><?= (int)$r['build_time_minutes'] ?></td>
              <td><?= (int)$r['max_level'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php endif; ?>
</div>
<?php include_once 'templates/footer.php'; ?>

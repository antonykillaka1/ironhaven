<?php
// api.php - VERSIONE PULITA E PRODUZIONE-READY

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

session_start();
ob_start();

require_once 'config.php';
require_once 'includes/autoload.php';

use Ironhaven\Core\Auth;
use Ironhaven\Core\Database;
use Ironhaven\Core\SettlementManager;
use Ironhaven\Core\ResourceManager;
use Ironhaven\Core\PopulationManager;
use Ironhaven\Missions\MissionManager;

header('Content-Type: application/json');

/* ----------------- Helpers ----------------- */
function apiError($message, $code = 400) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}
function apiOk(array $payload, int $code = 200) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($payload);
    exit;
}
function verifyAdmin() {
    global $auth;
    $player = $auth->getCurrentUser();
    if (!$player || empty($player['is_admin'])) {
        apiError('Accesso non autorizzato', 403);
    }
}

/* ----------------- Boot ----------------- */
$auth = Auth::getInstance();
$db   = Database::getInstance();

$publicActions = ['login', 'register'];
$action = $_GET['action'] ?? '';

if (!$auth->isLoggedIn() && !in_array($action, $publicActions, true)) {
    apiError('Non autenticato', 401);
}

/* Aggiornamenti automatici per utenti loggati */
if ($auth->isLoggedIn()) {
    $playerId = $auth->getUserId();
    $settlementManager = new SettlementManager();
    $settlement = $settlementManager->getPlayerSettlement($playerId);
    if ($settlement) {
        (new ResourceManager())->updateResources($settlement['id']);
        $settlementManager->checkConstructionStatus();
    }
}

/* ----------------- Router ----------------- */
switch ($action) {

    /* ============ AUTH ============ */
    case 'login': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        if ($auth->login($username, $password)) {
            apiOk(['success' => true, 'user' => $auth->getCurrentUser()]);
        }
        apiError('Credenziali non valide');
    }

    case 'register': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $username = $d['username'] ?? '';
        $email    = $d['email'] ?? '';
        $password = $d['password'] ?? '';
        if (!$username || !$email || !$password) apiError('Dati mancanti');
        if ($auth->register($username, $email, $password)) {
            apiOk(['success' => true, 'user' => $auth->getCurrentUser()]);
        }
        apiError('Registrazione fallita');
    }

    case 'logout': {
        $auth->logout();
        apiOk(['success' => true]);
    }

    /* ============ SETTLEMENT ============ */
    case 'create_settlement': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = $d['name'] ?? '';
        $x    = $d['x'] ?? 0;
        $y    = $d['y'] ?? 0;
        if (!$name) apiError('Nome insediamento mancante');

        $playerId = $auth->getUserId() ?: apiError('Autenticazione fallita', 401);

        $exists = $db->fetch("SELECT id FROM settlements WHERE player_id = ?", [$playerId]);
        if ($exists) apiError('Hai già un insediamento');

        $settlementId = $db->insert('settlements', [
            'player_id' => $playerId,
            'name' => $name,
            'coordinates_x' => $x,
            'coordinates_y' => $y,
            'founded_at' => date('Y-m-d H:i:s')
        ]);
        if (!$settlementId) apiError('Creazione insediamento fallita');

        $db->insert('resources', [
            'settlement_id' => $settlementId,
            'wood' => 200, 'stone' => 200, 'food' => 200, 'water' => 200,
            'last_update' => date('Y-m-d H:i:s')
        ]);
        (new PopulationManager())->updatePopulation($settlementId);
        apiOk(['success' => true, 'settlement_id' => $settlementId]);
    }

    case 'get_settlement': {
        $playerId = $auth->getUserId();
        $sm  = new SettlementManager();
        $pm  = new PopulationManager();
        $set = $sm->getPlayerSettlement($playerId);
        if (!$set) apiError('Insediamento non trovato');
        $res = $db->fetch("SELECT * FROM resources WHERE settlement_id = ?", [$set['id']]);
        $bld = $sm->getBuildings($set['id']);
        $pop = $pm->getPopulation($set['id']);
        apiOk(['success' => true, 'settlement' => $set, 'resources' => $res, 'buildings' => $bld, 'population' => $pop]);
    }

    case 'construct_building': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $type = $d['type'] ?? '';
        $x = $d['x'] ?? null; $y = $d['y'] ?? null;
        if (!$type) apiError('Tipo edificio mancante');

        $playerId = $auth->getUserId();
        $sm = new SettlementManager();
        $set = $sm->getPlayerSettlement($playerId) ?: apiError('Insediamento non trovato');
        $id = $sm->constructBuilding($set['id'], $type, $x, $y);
        if (!$id) apiError('Costruzione edificio fallita');
        apiOk(['success' => true, 'building_id' => $id]);
    }

    case 'check_construction_status': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $playerId = $auth->getUserId();
        $sm = new SettlementManager();
        $set = $sm->getPlayerSettlement($playerId) ?: apiError('Insediamento non trovato');
        $completed = $sm->checkConstructionStatus();
        apiOk(['success' => true, 'completed_buildings' => $completed]);
    }

    case 'collect_all_resources': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $playerId = $auth->getUserId();
        $sm = new SettlementManager();
        $rm = new ResourceManager();
        $set = $sm->getPlayerSettlement($playerId) ?: apiError('Insediamento non trovato');

        $resources = $db->fetch("SELECT * FROM resources WHERE settlement_id = ?", [$set['id']]);
        if (!$resources) apiError('Risorse non trovate');

        $productionData = $rm->calculateProduction($set['id']);
        $production = is_array($productionData['production']) ? $productionData['production'] : [];

        $last = new DateTime($resources['last_update']);
        $now  = new DateTime();
        $diff = $last->diff($now);
        $hours = min(12, $diff->h + $diff->days * 24 + $diff->i / 60);

        $collected = [];
        $update = ['last_update' => $now->format('Y-m-d H:i:s')];

        foreach ($production as $res => $perHour) {
            if (array_key_exists($res, $resources)) {
                $add = $perHour * $hours;
                $update[$res] = $resources[$res] + $add;
                $collected[$res] = (int)round($add);
            }
        }

        if (count($update) <= 1) {
            apiOk(['success' => true, 'collected' => [], 'hours_collected' => $hours, 'message' => 'Nessuna risorsa da raccogliere']);
        }

        $db->update('resources', $update, 'settlement_id = ?', [$set['id']]);
        apiOk(['success' => true, 'collected' => $collected, 'hours_collected' => $hours, 'resources' => $update]);
    }

    /* ============ MISSIONI ============ */
    case 'available_missions': {
        $playerId = $auth->getUserId();
        $mm = new MissionManager();
        apiOk(['success' => true, 'missions' => $mm->getAvailableMissions($playerId)]);
    }

    case 'start_mission': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $missionId = (int)($d['mission_id'] ?? 0);
        if ($missionId <= 0) apiError('ID missione mancante');
        $res = (new MissionManager())->startMission($auth->getUserId(), $missionId);
        if (!$res) apiError('Avvio missione fallito');
        apiOk(['success' => true, 'player_mission_id' => $res]);
    }

    case 'check_mission_status': {
        $id = (int)($_GET['player_mission_id'] ?? 0);
        if ($id <= 0) apiError('ID missione giocatore mancante');
        $status = (new MissionManager())->checkMissionStatus($id);
        apiOk(['success' => true, 'status' => $status]);
    }

    case 'claim_mission_rewards': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int)($d['player_mission_id'] ?? 0);
        if ($id <= 0) apiError('ID missione giocatore mancante');
        $ok = (new MissionManager())->claimRewards($id);
        if (!$ok) apiError('Riscossione ricompense fallita');
        apiOk(['success' => true]);
    }

    /* ============ PROFILO ============ */
    case 'update_profile': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = $d['email'] ?? '';
        $cur   = $d['current_password'] ?? '';
        $new   = $d['new_password'] ?? '';

        if (!$email) apiError('Email mancante');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) apiError('Email non valida');

        $playerId = $auth->getUserId();
        $player = $db->fetch("SELECT * FROM players WHERE id = ?", [$playerId]) ?: apiError('Giocatore non trovato');

        $exists = $db->fetch("SELECT id FROM players WHERE email = ? AND id != ?", [$email, $playerId]);
        if ($exists) apiError('Email già in uso');

        $upd = ['email' => $email];

        if ($new) {
            if (strlen($new) < 6) apiError('La password deve essere di almeno 6 caratteri');
            if (!$cur) apiError('Password attuale mancante');
            if (!password_verify($cur, $player['password'])) apiError('Password attuale non corretta');
            $upd['password'] = password_hash($new, PASSWORD_DEFAULT);
        }

        $db->update('players', $upd, 'id = ?', [$playerId]);
        apiOk(['success' => true]);
    }

    /* ============ ITEMS ============ */
    case 'equip_item': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $itemId = (int)($d['item_id'] ?? 0);
        if ($itemId <= 0) apiError('ID oggetto mancante');

        $playerId = $auth->getUserId();
        $item = $db->fetch("SELECT * FROM special_items WHERE id = ? AND player_id = ?", [$itemId, $playerId]);
        if (!$item) apiError('Oggetto non trovato');

        $db->update('special_items', ['is_equipped' => 0], 'player_id = ? AND type = ? AND id != ?', [$playerId, $item['type'], $itemId]);
        $db->update('special_items', ['is_equipped' => 1], 'id = ?', [$itemId]);
        apiOk(['success' => true]);
    }

    /* ============ NOTIFICHE ============ */
    case 'check_notifications': {
        try {
            $nm = new \Ironhaven\Core\NotificationManager();
            $noti = $nm->getUnreadNotifications();
            apiOk(['success' => true, 'notifications' => $noti ?? []]);
        } catch (\Throwable $e) {
            apiOk(['success' => true, 'notifications' => []]);
        }
    }

    case 'mark_notifications_read': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        try {
            (new \Ironhaven\Core\NotificationManager())->markAllAsRead();
            apiOk(['success' => true]);
        } catch (\Throwable $e) {
            apiOk(['success' => true]);
        }
    }

    /* ============ ADMIN DEBUG ============ */
    case 'admin_population_health': {
        if (!DEBUG_MODE) apiError('Endpoint non disponibile', 404);
        verifyAdmin();
        $pm = new PopulationManager();
        $results = $pm->verifyAllSettlements();
        apiOk(['success' => true, 'results' => $results]);
    }

    /* ============ RISORSE (widget header) ============ */
    case 'get_resources': {
        $playerId = $auth->getUserId();
        $sm = new SettlementManager();
        $set = $sm->getPlayerSettlement($playerId) ?: apiError('Insediamento non trovato');
        $rm = new ResourceManager();

        $amounts = $db->fetch("SELECT * FROM resources WHERE settlement_id = ?", [$set['id']]);
        $rates   = $rm->calculateProduction($set['id'])['production'];

        $out = [];
        foreach (['wood','stone','food','water','iron','gold'] as $res) {
            $out[$res] = [
                'amount'   => isset($amounts[$res]) ? (int)$amounts[$res] : 0,
                'per_hour' => isset($rates[$res]) ? (int)$rates[$res] : 0,
            ];
        }
        apiOk(['success' => true, 'resources' => $out]);
    }

    /* ============ COSTRUZIONI: annulla / demolisci ============ */
    case 'cancel_construction': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $buildingId = (int)($d['building_id'] ?? 0);
        if ($buildingId <= 0) apiError('ID edificio mancante o non valido');

        $playerId = $auth->getUserId();
        $sm = new SettlementManager();
        $set = $sm->getPlayerSettlement($playerId) ?: apiError('Insediamento non trovato');

        $building = $db->fetch("SELECT * FROM buildings WHERE id = ? AND settlement_id = ?", [$buildingId, $set['id']]);
        if (!$building) apiError('Edificio non trovato');

        if (empty($building['construction_ends']) || strtotime($building['construction_ends']) <= time()) {
            apiError('L\'edificio non è annullabile (non in costruzione)');
        }

        $stmt = $db->query(
            "DELETE FROM buildings WHERE id = ? AND settlement_id = ? AND construction_ends > NOW()",
            [$buildingId, $set['id']]
        );
        $deleted = $stmt ? $stmt->rowCount() : 0;
        if ($deleted > 0) apiOk(['success' => true]);
        apiError('Annullamento costruzione fallito');
    }

    case 'demolish_building': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $buildingId = (int)($d['building_id'] ?? 0);
        if ($buildingId <= 0) apiError('ID edificio mancante o non valido');

        $playerId = $auth->getUserId();
        $sm = new SettlementManager();
        $set = $sm->getPlayerSettlement($playerId) ?: apiError('Insediamento non trovato');

        $building = $db->fetch("SELECT * FROM buildings WHERE id = ? AND settlement_id = ?", [$buildingId, $set['id']]);
        if (!$building) apiError('Edificio non trovato');

        if (!empty($building['construction_ends']) && strtotime($building['construction_ends']) > time()) {
            apiError('L\'edificio è in costruzione. Usa "Annulla" per interrompere.');
        }

        $stmt = $db->query("DELETE FROM buildings WHERE id = ? AND settlement_id = ?", [$buildingId, $set['id']]);
        $deleted = $stmt ? $stmt->rowCount() : 0;

        if ($deleted > 0) {
            (new PopulationManager())->updatePopulation($set['id']);
            apiOk(['success' => true]);
        }
        apiError('Demolizione fallita');
    }

    /* ============ DETTAGLI EDIFICIO (singolo posseduto) ============ */
    case 'building_details': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $buildingId = (int)($d['building_id'] ?? 0);
        if ($buildingId <= 0) apiError('ID edificio mancante', 400);

        $playerId = $auth->getUserId();
        $building = $db->fetch(
            "SELECT b.* FROM buildings b
             JOIN settlements s ON s.id = b.settlement_id
             WHERE b.id = ? AND s.player_id = ?",
            [$buildingId, $playerId]
        );
        if (!$building) apiError('Edificio non trovato', 404);

        $type = $building['type'];
        $className = str_replace('_', '', ucwords($type, '_'));
        $full = 'Ironhaven\\Buildings\\' . $className;

        $details = [
            'type' => $type,
            'level' => (int)$building['level'],
            'production' => [],
            'next_level_production' => [],
            'capacity' => null,
            'upgrade_costs' => null,
            'build_time_sec' => null
        ];

        if (class_exists($full)) {
            $inst = new $full($building);
            if (method_exists($inst, 'getProduction')) {
                $details['production'] = (array)$inst->getProduction((int)$building['level']);
                $details['next_level_production'] = (array)$inst->getProduction((int)$building['level'] + 1);
            }
            foreach (['getCapacity','getPopulation','getStorageCapacity'] as $m) {
                if (method_exists($inst, $m)) { $details['capacity'] = $inst->$m((int)$building['level']); break; }
            }
            foreach (['getUpgradeCost','getUpgradeCosts','getConstructionCost','getConstructionCosts'] as $m) {
                if (method_exists($inst, $m)) { $details['upgrade_costs'] = $inst->$m((int)$building['level'] + 1); break; }
            }
            foreach (['getConstructionTime','getBuildTime'] as $m) {
                if (method_exists($inst, $m)) { $details['build_time_sec'] = (int)$inst->$m((int)$building['level'] + 1); break; }
            }
        }

        apiOk(['success' => true, 'details' => $details]);
    }

    /* ============ CATALOGO (calcolato) ============ */
    case 'buildings_catalog': {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') apiError('Metodo non consentito', 405);

        try {
            $types = $db->fetchAll("SELECT * FROM building_types ORDER BY name ASC");
        } catch (\Throwable $e) {
            apiError('Tabella building_types non trovata. Importa i dati prima.', 500);
        }

        $geo = function($base, $mult, $level) {
            $base = (float)$base; $mult = (float)$mult; $level = (int)$level;
            return $level <= 1 ? $base : $base * pow($mult, $level - 1);
        };

        $out = [];
        foreach ($types as $t) {
            $max = max(1, (int)$t['max_level']);
            $entry = ['type'=>$t['slug'],'display'=>$t['name'],'max_level'=>$max,'levels'=>[]];

            $cm=(float)($t['upgrade_cost_multiplier'] ?? 1.5);
            $pm=(float)($t['production_multiplier'] ?? 1.2);
            $capm=(float)($t['capacity_multiplier'] ?? 1.2);
            $tm=(float)($t['time_multiplier'] ?? 1.0);
            $base_time_min = (int)($t['build_time_minutes'] ?? 5);

            $cumSec = 0;
            for ($lvl=1; $lvl<=$max; $lvl++) {
                $costs = [
                    'water'=>(int)round($geo($t['water_cost'],$cm,$lvl)),
                    'food' =>(int)round($geo($t['food_cost'],$cm,$lvl)),
                    'wood' =>(int)round($geo($t['wood_cost'],$cm,$lvl)),
                    'stone'=>(int)round($geo($t['stone_cost'],$cm,$lvl)),
                    'iron' =>(int)round($geo($t['iron_cost'],$cm,$lvl)),
                    'gold' =>(int)round($geo($t['gold_cost'],$cm,$lvl)),
                ];
                $prod = [
                    'water'=>(int)round($geo($t['water_production'],$pm,$lvl)),
                    'food' =>(int)round($geo($t['food_production'],$pm,$lvl)),
                    'wood' =>(int)round($geo($t['wood_production'],$pm,$lvl)),
                    'stone'=>(int)round($geo($t['stone_production'],$pm,$lvl)),
                    'iron' =>(int)round($geo($t['iron_production'],$pm,$lvl)),
                    'gold' =>(int)round($geo($t['gold_production'],$pm,$lvl)),
                ];
                $capacity = (int)round($geo($t['capacity_increase'],$capm,$lvl));
                $time_sec = (int)round($geo($base_time_min,$tm,$lvl))*60;
                $cumSec += $time_sec;

                $entry['levels'][] = [
                    'level'=>$lvl,
                    'costs'=>array_filter($costs, fn($v)=>$v>0),
                    'time_sec'=>$time_sec,
                    'time_cum_sec'=>$cumSec,
                    'production'=>array_filter($prod, fn($v)=>$v>0),
                    'capacity'=>$capacity
                ];
            }
            $out[] = $entry;
        }

        apiOk(['success' => true, 'buildings' => $out]);
    }

    /* ============ TECH-TREE CRUD LITE ============ */
    case 'get_building_types': {
        $rows = $db->fetchAll("SELECT 
            id, slug, name, description, max_level, image_url, level_required,
            water_production, food_production, wood_production, stone_production, iron_production, gold_production,
            capacity_increase, capacity_resource,
            water_cost, food_cost, wood_cost, stone_cost, iron_cost, gold_cost,
            upgrade_cost_multiplier, production_multiplier, capacity_multiplier, time_multiplier,
            build_time_minutes
        FROM building_types
        ORDER BY name ASC");

        // >>> CHIAVE ALLINEATA AL FRONT-END <<<
        apiOk(['success' => true, 'types' => $rows]);
    }

    case 'get_building_type': {
        $slug = $_GET['slug'] ?? '';
        $id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$slug && !$id) apiError('Parametro mancante (slug o id).', 400);

        $row = $slug
            ? $db->fetch("SELECT * FROM building_types WHERE slug = ?", [$slug])
            : $db->fetch("SELECT * FROM building_types WHERE id = ?", [$id]);

        if (!$row) apiError('Struttura non trovata', 404);

        // >>> CHIAVE ALLINEATA AL FRONT-END <<<
        apiOk(['success' => true, 'type' => $row]);
    }

    case 'export_building_types_csv': {
        verifyAdmin();
        $rows = $db->fetchAll("SELECT * FROM building_types ORDER BY name ASC");
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=building_types.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'slug','name','description','level_required',
            'water_production','food_production','wood_production','stone_production','iron_production','gold_production',
            'capacity_increase','capacity_resource',
            'water_cost','food_cost','wood_cost','stone_cost','iron_cost','gold_cost',
            'upgrade_cost_multiplier','production_multiplier','capacity_multiplier','time_multiplier',
            'build_time_minutes','max_level','image_url','xp_per_building'
        ]);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['slug'],$r['name'],$r['description'],$r['level_required'],
                $r['water_production'],$r['food_production'],$r['wood_production'],$r['stone_production'],$r['iron_production'],$r['gold_production'],
                $r['capacity_increase'],$r['capacity_resource'],
                $r['water_cost'],$r['food_cost'],$r['wood_cost'],$r['stone_cost'],$r['iron_cost'],$r['gold_cost'],
                $r['upgrade_cost_multiplier'],$r['production_multiplier'],$r['capacity_multiplier'],$r['time_multiplier'],
                $r['build_time_minutes'],$r['max_level'],$r['image_url'],$r['xp_per_building']
            ]);
        }
        fclose($out);
        exit;
    }

    case 'export_building_type_csv': {
        verifyAdmin();
        $slug = $_GET['slug'] ?? '';
        $id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$slug && !$id) apiError('Parametro mancante (slug o id).', 400);

        $r = $slug
            ? $db->fetch("SELECT * FROM building_types WHERE slug = ?", [$slug])
            : $db->fetch("SELECT * FROM building_types WHERE id = ?", [$id]);

        if (!$r) apiError('Struttura non trovata', 404);

        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=building_type_'.$r['slug'].'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'slug','name','description','level_required',
            'water_production','food_production','wood_production','stone_production','iron_production','gold_production',
            'capacity_increase','capacity_resource',
            'water_cost','food_cost','wood_cost','stone_cost','iron_cost','gold_cost',
            'upgrade_cost_multiplier','production_multiplier','capacity_multiplier','time_multiplier',
            'build_time_minutes','max_level','image_url','xp_per_building'
        ]);
        fputcsv($out, [
            $r['slug'],$r['name'],$r['description'],$r['level_required'],
            $r['water_production'],$r['food_production'],$r['wood_production'],$r['stone_production'],$r['iron_production'],$r['gold_production'],
            $r['capacity_increase'],$r['capacity_resource'],
            $r['water_cost'],$r['food_cost'],$r['wood_cost'],$r['stone_cost'],$r['iron_cost'],$r['gold_cost'],
            $r['upgrade_cost_multiplier'],$r['production_multiplier'],$r['capacity_multiplier'],$r['time_multiplier'],
            $r['build_time_minutes'],$r['max_level'],$r['image_url'],$r['xp_per_building']
        ]);
        fclose($out);
        exit;
    }

    case 'update_building_type': {
        verifyAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $d = json_decode(file_get_contents('php://input'), true) ?: [];
        $slug = $d['slug'] ?? '';
        $id   = isset($d['id']) ? (int)$d['id'] : 0;
        if (!$slug && !$id) apiError('Parametro mancante (slug o id).', 400);

        $fields = [
            'description','max_level','upgrade_cost_multiplier','production_multiplier',
            'capacity_multiplier','time_multiplier','image_url'
        ];
        $updates = [];
        $params  = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $d)) { $updates[] = "$f = ?"; $params[] = $d[$f]; }
        }
        if (!$updates) apiError('Nessun campo da aggiornare.', 400);

        if ($slug) { $where = 'slug = ?'; $params[] = $slug; }
        else       { $where = 'id = ?';   $params[] = $id;   }

        $sql = "UPDATE building_types SET ".implode(', ', $updates)." WHERE $where";
        $db->query($sql, $params);
        apiOk(['success' => true]);
    }

    /* >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
       NUOVO: DUPLICA STRUTTURA (solo admin)
       >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> */
    case 'duplicate_building_type': {
        verifyAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);

        $payload   = json_decode(file_get_contents('php://input'), true) ?: [];
        $sourceSlug = $payload['source_slug'] ?? '';
        $newName    = trim($payload['new_name'] ?? '');
        $newSlug    = trim($payload['new_slug'] ?? '');

        if ($sourceSlug === '' || $newName === '' || $newSlug === '') {
            apiError('Parametri mancanti', 400);
        }
        if (!preg_match('/^[a-z0-9\-]+$/', $newSlug)) {
            apiError('Slug non valido (solo minuscole, numeri e trattini).', 400);
        }

        // sorgente esiste?
        $src = $db->fetch("SELECT * FROM building_types WHERE slug = ?", [$sourceSlug]);
        if (!$src) apiError('Struttura di origine non trovata', 404);

        // slug nuovo libero?
        $exists = $db->fetch("SELECT id FROM building_types WHERE slug = ?", [$newSlug]);
        if ($exists) apiError('Slug già in uso', 409);

        // campi da copiare
        $copyFields = [
            'description','level_required',
            'water_production','food_production','wood_production','stone_production','iron_production','gold_production',
            'capacity_increase','capacity_resource',
            'water_cost','food_cost','wood_cost','stone_cost','iron_cost','gold_cost',
            'upgrade_cost_multiplier','production_multiplier','capacity_multiplier','time_multiplier',
            'build_time_minutes','max_level','image_url','xp_per_building'
        ];

        $row = [
            'slug' => $newSlug,
            'name' => $newName,
        ];
        foreach ($copyFields as $f) {
            $row[$f] = $src[$f] ?? null;
        }

        try {
            $newId = $db->insert('building_types', $row);
            apiOk(['success' => true, 'id' => $newId]);
        } catch (\Throwable $e) {
            apiError('Errore durante la duplicazione: '.$e->getMessage(), 500);
        }
    }

    /* ============ DEFAULT ============ */
    default:
        apiError('Azione non riconosciuta', 404);
}

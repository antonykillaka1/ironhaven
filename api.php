<?php
// api.php - VERSIONE PULITA E PRODUZIONE-READY
// Configurazione di base per la gestione degli errori
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

/**
 * Gestione errori API - Invia risposta di errore JSON
 */
function apiError($message, $code = 400) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}

/**
 * Verifica che l'utente sia admin
 */
function verifyAdmin() {
    global $auth;
    $player = $auth->getCurrentUser();
    if (!$player || !$player['is_admin']) {
        apiError('Accesso non autorizzato', 403);
    }
}

// Initialize core services
$auth = Auth::getInstance();
$db = Database::getInstance();

// Public actions that don't require authentication
$publicActions = ['login', 'register'];
$action = $_GET['action'] ?? '';

if (!$auth->isLoggedIn() && !in_array($action, $publicActions)) {
    apiError('Non autenticato', 401);
}

// Auto-update resources and check constructions for authenticated users
if ($auth->isLoggedIn()) {
    $playerId = $auth->getUserId();
    $settlementManager = new SettlementManager();
    $settlement = $settlementManager->getPlayerSettlement($playerId);
    
    if ($settlement) {
        // Update resources
        $resourceManager = new ResourceManager();
        $resourceManager->updateResources($settlement['id']);
        
        // Check completed constructions
        $settlementManager->checkConstructionStatus();
    }
}

// Main API router
switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if ($auth->login($username, $password)) {
            echo json_encode(['success' => true, 'user' => $auth->getCurrentUser()]);
        } else {
            apiError('Credenziali non valide');
        }
        break;
        
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($username) || empty($email) || empty($password)) {
            apiError('Dati mancanti');
        }
        
        if ($auth->register($username, $email, $password)) {
            echo json_encode(['success' => true, 'user' => $auth->getCurrentUser()]);
        } else {
            apiError('Registrazione fallita');
        }
        break;
        
    case 'logout':
        $auth->logout();
        echo json_encode(['success' => true]);
        break;
        
    case 'create_settlement':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        $x = $data['x'] ?? 0;
        $y = $data['y'] ?? 0;
        
        if (empty($name)) {
            apiError('Nome insediamento mancante');
        }
        
        $playerId = $auth->getUserId();
        if (!$playerId) {
            apiError("Autenticazione fallita: ID giocatore mancante", 401);
        }
        
        // Check if user already has a settlement
        $existingSettlement = $db->fetch(
            "SELECT id FROM settlements WHERE player_id = ?",
            [$playerId]
        );
        
        if ($existingSettlement) {
            apiError('Hai già un insediamento');
        }
        
        // Create settlement
        $settlementId = $db->insert('settlements', [
            'player_id' => $playerId,
            'name' => $name,
            'coordinates_x' => $x,
            'coordinates_y' => $y,
            'founded_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($settlementId) {
            // Initialize resources
            $db->insert('resources', [
                'settlement_id' => $settlementId,
                'wood' => 200,
                'stone' => 200,
                'food' => 200,
                'water' => 200,
                'last_update' => date('Y-m-d H:i:s')
            ]);
            
            // Initialize population using PopulationManager
            $populationManager = new PopulationManager();
            $populationManager->updatePopulation($settlementId);
            
            echo json_encode(['success' => true, 'settlement_id' => $settlementId]);
        } else {
            apiError('Creazione insediamento fallita');
        }
        break;
        
    case 'get_settlement':
        $playerId = $auth->getUserId();
        $settlementManager = new SettlementManager();
        $populationManager = new PopulationManager();
        $settlement = $settlementManager->getPlayerSettlement($playerId);
        
        if ($settlement) {
            $resources = $db->fetch(
                "SELECT * FROM resources WHERE settlement_id = ?",
                [$settlement['id']]
            );
            
            $buildings = $settlementManager->getBuildings($settlement['id']);
            $population = $populationManager->getPopulation($settlement['id']);
            
            echo json_encode([
                'success' => true,
                'settlement' => $settlement,
                'resources' => $resources,
                'buildings' => $buildings,
                'population' => $population
            ]);
        } else {
            apiError('Insediamento non trovato');
        }
        break;
        
    case 'construct_building':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? '';
        $x = $data['x'] ?? null;
        $y = $data['y'] ?? null;
        
        if (empty($type)) {
            apiError('Tipo edificio mancante');
        }
        
        $playerId = $auth->getUserId();
        $settlementManager = new SettlementManager();
        $settlement = $settlementManager->getPlayerSettlement($playerId);
        
        if (!$settlement) {
            apiError('Insediamento non trovato');
        }
        
        $buildingId = $settlementManager->constructBuilding($settlement['id'], $type, $x, $y);
        
        if ($buildingId) {
            echo json_encode(['success' => true, 'building_id' => $buildingId]);
        } else {
            apiError('Costruzione edificio fallita');
        }
        break;
        
    case 'check_construction_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $playerId = $auth->getUserId();
        $settlementManager = new SettlementManager();
        $settlement = $settlementManager->getPlayerSettlement($playerId);
        
        if (!$settlement) {
            apiError('Insediamento non trovato');
        }
        
        $completedBuildings = $settlementManager->checkConstructionStatus();
        
        echo json_encode([
            'success' => true, 
            'completed_buildings' => $completedBuildings
        ]);
        break;
        
    case 'collect_all_resources':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $playerId = $auth->getUserId();
        $settlementManager = new SettlementManager();
        $resourceManager = new ResourceManager();
        $settlement = $settlementManager->getPlayerSettlement($playerId);
        
        if (!$settlement) {
            apiError('Insediamento non trovato');
        }
        
        // Get current resources
        $resources = $db->fetch(
            "SELECT * FROM resources WHERE settlement_id = ?",
            [$settlement['id']]
        );
        
        if (!$resources) {
            apiError('Risorse non trovate');
        }
        
        // Calculate production
        $productionData = $resourceManager->calculateProduction($settlement['id']);
        $production = is_array($productionData['production']) ? $productionData['production'] : [];
        
        // Calculate time passed since last update
        $lastUpdate = new \DateTime($resources['last_update']);
        $now = new \DateTime();
        $interval = $lastUpdate->diff($now);
        $hoursPassed = $interval->h + ($interval->days * 24) + ($interval->i / 60);
        
        // Limit to maximum 12 hours
        $hoursPassed = min(12, $hoursPassed);
        
        $collected = [];
        $updateData = [];
        $updateData['last_update'] = $now->format('Y-m-d H:i:s');
        
        foreach ($production as $resource => $amountPerHour) {
            if (isset($resources[$resource])) {
                $amountToAdd = $amountPerHour * $hoursPassed;
                $updateData[$resource] = $resources[$resource] + $amountToAdd;
                $collected[$resource] = round($amountToAdd);
            }
        }
        
        if (count($updateData) <= 1) {
            echo json_encode([
                'success' => true,
                'collected' => [],
                'hours_collected' => $hoursPassed,
                'message' => 'Nessuna risorsa da raccogliere'
            ]);
            break;
        }
        
        $result = $db->update(
            'resources',
            $updateData,
            'settlement_id = ?',
            [$settlement['id']]
        );
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'collected' => $collected,
                'hours_collected' => $hoursPassed,
                'resources' => $updateData
            ]);
        } else {
            apiError('Raccolta risorse fallita');
        }
        break;
        
    case 'available_missions':
        $playerId = $auth->getUserId();
        $missionManager = new MissionManager();
        $missions = $missionManager->getAvailableMissions($playerId);
        
        echo json_encode(['success' => true, 'missions' => $missions]);
        break;
        
    case 'start_mission':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $missionId = $data['mission_id'] ?? 0;
        
        if (empty($missionId)) {
            apiError('ID missione mancante');
        }
        
        $playerId = $auth->getUserId();
        $missionManager = new MissionManager();
        $result = $missionManager->startMission($playerId, $missionId);
        
        if ($result) {
            echo json_encode(['success' => true, 'player_mission_id' => $result]);
        } else {
            apiError('Avvio missione fallito');
        }
        break;
        
    case 'check_mission_status':
        $playerMissionId = $_GET['player_mission_id'] ?? 0;
        
        if (empty($playerMissionId)) {
            apiError('ID missione giocatore mancante');
        }
        
        $missionManager = new MissionManager();
        $status = $missionManager->checkMissionStatus($playerMissionId);
        
        echo json_encode(['success' => true, 'status' => $status]);
        break;
        
    case 'claim_mission_rewards':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $playerMissionId = $data['player_mission_id'] ?? 0;
        
        if (empty($playerMissionId)) {
            apiError('ID missione giocatore mancante');
        }
        
        $missionManager = new MissionManager();
        $result = $missionManager->claimRewards($playerMissionId);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            apiError('Riscossione ricompense fallita');
        }
        break;
        
    case 'update_profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        
        if (empty($email)) {
            apiError('Email mancante');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            apiError('Email non valida');
        }
        
        $playerId = $auth->getUserId();
        $player = $db->fetch("SELECT * FROM players WHERE id = ?", [$playerId]);
        
        if (!$player) {
            apiError('Giocatore non trovato');
        }
        
        // Check if email is already in use
        $existingEmail = $db->fetch(
            "SELECT id FROM players WHERE email = ? AND id != ?",
            [$email, $playerId]
        );
        
        if ($existingEmail) {
            apiError('Email già in uso');
        }
        
        $updateData = ['email' => $email];
        
        // Password update
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                apiError('La password deve essere di almeno 6 caratteri');
            }
            
            if (empty($currentPassword)) {
                apiError('Password attuale mancante');
            }
            
            if (!password_verify($currentPassword, $player['password'])) {
                apiError('Password attuale non corretta');
            }
            
            $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        
        $result = $db->update('players', $updateData, 'id = ?', [$playerId]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            apiError('Aggiornamento profilo fallito');
        }
        break;
        
    case 'equip_item':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $itemId = $data['item_id'] ?? 0;
        
        if (empty($itemId)) {
            apiError('ID oggetto mancante');
        }
        
        $playerId = $auth->getUserId();
        
        // Verify item belongs to player
        $item = $db->fetch(
            "SELECT * FROM special_items WHERE id = ? AND player_id = ?",
            [$itemId, $playerId]
        );
        
        if (!$item) {
            apiError('Oggetto non trovato');
        }
        
        // Deactivate all other items of same type
        $db->update(
            'special_items',
            ['is_equipped' => 0],
            'player_id = ? AND type = ? AND id != ?',
            [$playerId, $item['type'], $itemId]
        );
        
        // Activate selected item
        $result = $db->update(
            'special_items',
            ['is_equipped' => 1],
            'id = ?',
            [$itemId]
        );
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            apiError('Attivazione oggetto fallita');
        }
        break;
        
    case 'check_notifications':
        try {
            $notificationManager = new \Ironhaven\Core\NotificationManager();
            $notifications = $notificationManager->getUnreadNotifications();
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications ?? []
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => true,
                'notifications' => []
            ]);
        }
        break;
        
    case 'mark_notifications_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }
        
        try {
            $notificationManager = new \Ironhaven\Core\NotificationManager();
            $notificationManager->markAllAsRead();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => true]);
        }
        break;
        
    // Admin endpoints (only if DEBUG_MODE is enabled and user is admin)
    case 'admin_population_health':
        if (!DEBUG_MODE) {
            apiError('Endpoint non disponibile', 404);
        }
        
        verifyAdmin();
        
        $populationManager = new PopulationManager();
        $results = $populationManager->verifyAllSettlements();
        
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
        break;
	    case 'get_resources':
        $playerId = $auth->getUserId();
        $settlementManager = new SettlementManager();
        $settlement = $settlementManager->getPlayerSettlement($playerId);

        if (!$settlement) {
            apiError('Insediamento non trovato');
        }

        $resourceManager = new ResourceManager();

        // Quantità correnti dal DB
        $amounts = $db->fetch(
            "SELECT * FROM resources WHERE settlement_id = ?",
            [$settlement['id']]
        );

        // Produzione oraria calcolata dagli edifici
        $productionData = $resourceManager->calculateProduction($settlement['id']);
        $rates = $productionData['production'];

        // Output strutturato
        $out = [];
        foreach (['wood','stone','food','water','iron','gold'] as $res) {
            $out[$res] = [
                'amount'   => isset($amounts[$res]) ? (int)$amounts[$res] : 0,
                'per_hour' => isset($rates[$res]) ? (int)$rates[$res] : 0,
            ];
        }

        echo json_encode(['success' => true, 'resources' => $out]);
        break;
		    case 'cancel_construction':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiError('Metodo non consentito', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $buildingId = isset($data['building_id']) ? (int)$data['building_id'] : 0;
    if ($buildingId <= 0) {
        apiError('ID edificio mancante o non valido');
    }

    $playerId = $auth->getUserId();
    $settlementManager = new SettlementManager();
    $settlement = $settlementManager->getPlayerSettlement($playerId);
    if (!$settlement) {
        apiError('Insediamento non trovato');
    }

    // Verifica appartenenza + stato "in costruzione"
    $building = $db->fetch(
        "SELECT * FROM buildings WHERE id = ? AND settlement_id = ?",
        [$buildingId, $settlement['id']]
    );
    if (!$building) {
        apiError('Edificio non trovato');
    }

    // Deve essere ancora in costruzione (construction_ends > adesso)
    if (empty($building['construction_ends']) || strtotime($building['construction_ends']) <= time()) {
        apiError('L\'edificio non è annullabile (non in costruzione)');
    }

    // Cancellazione "hard" dell'edificio in costruzione
    // (se preferisci soft-cancel, dimmelo e passiamo a un flag)
    $deletedRows = 0;
    if (method_exists($db, 'delete')) {
        $deletedRows = $db->delete(
            'buildings',
            'id = ? AND settlement_id = ? AND construction_ends > NOW()',
            [$buildingId, $settlement['id']]
        );
    } else {
        // fallback generico
        $ok = $db->execute(
            "DELETE FROM buildings WHERE id = ? AND settlement_id = ? AND construction_ends > NOW()",
            [$buildingId, $settlement['id']]
        );
        $deletedRows = $ok ? 1 : 0;
    }

    if ($deletedRows > 0) {
        echo json_encode(['success' => true]);
    } else {
        apiError('Annullamento costruzione fallito');
    }
    break;
    case 'demolish_building':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $buildingId = isset($data['building_id']) ? (int)$data['building_id'] : 0;
        if ($buildingId <= 0) {
            apiError('ID edificio mancante o non valido');
        }

        $playerId = $auth->getUserId();
        $settlementManager = new SettlementManager();
        $settlement = $settlementManager->getPlayerSettlement($playerId);
        if (!$settlement) {
            apiError('Insediamento non trovato');
        }

        // Esiste ed appartiene al player?
        $building = $db->fetch(
            "SELECT * FROM buildings WHERE id = ? AND settlement_id = ?",
            [$buildingId, $settlement['id']]
        );
        if (!$building) {
            apiError('Edificio non trovato');
        }

        // Solo edifici completati possono essere demoliti
        if (!empty($building['construction_ends']) && strtotime($building['construction_ends']) > time()) {
            apiError('L\'edificio è in costruzione. Usa "Annulla" per interrompere.');
        }

        // Demolizione: cancellazione hard della riga
        $deletedRows = 0;
        if (method_exists($db, 'delete')) {
            $deletedRows = $db->delete(
                'buildings',
                'id = ? AND settlement_id = ?',
                [$buildingId, $settlement['id']]
            );
        } else {
            $ok = $db->execute(
                "DELETE FROM buildings WHERE id = ? AND settlement_id = ?",
                [$buildingId, $settlement['id']]
            );
            $deletedRows = $ok ? 1 : 0;
        }

        if ($deletedRows > 0) {
            // Ricalcola la popolazione (le case influenzano i totali)
            $populationManager = new PopulationManager();
            $populationManager->updatePopulation($settlement['id']);

            echo json_encode(['success' => true]);
        } else {
            apiError('Demolizione fallita');
        }
        break;
		    case 'building_details':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Metodo non consentito', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $buildingId = isset($data['building_id']) ? (int)$data['building_id'] : 0;
        if ($buildingId <= 0) {
            apiError('ID edificio mancante', 400);
        }

        $playerId = $auth->getUserId();
        $db = Database::getInstance();

        // Carica edificio e verifica appartenenza
        $building = $db->fetch(
            "SELECT b.* 
               FROM buildings b
               JOIN settlements s ON s.id = b.settlement_id
              WHERE b.id = ? AND s.player_id = ?",
            [$buildingId, $playerId]
        );

        if (!$building) {
            apiError('Edificio non trovato', 404);
        }

        // Determina classe edificio
        $type = $building['type'];
        $className = str_replace('_', '', ucwords($type, '_'));
        $fullClassName = 'Ironhaven\\Buildings\\' . $className;

        $details = [
            'type'        => $type,
            'level'       => (int)$building['level'],
            'production'  => [],
            'next_level_production' => [],
            'capacity'    => null,
            'upgrade_costs' => null,
            'build_time_sec' => null
        ];

        if (class_exists($fullClassName)) {
            $instance = new $fullClassName($building);

            // produzione attuale e del prossimo livello, se disponibile
            if (method_exists($instance, 'getProduction')) {
                $details['production'] = (array)$instance->getProduction((int)$building['level']);
                $details['next_level_production'] = (array)$instance->getProduction((int)$building['level'] + 1);
            }

            // capienza (prova metodi comuni)
            foreach (['getCapacity','getPopulation','getStorageCapacity'] as $m) {
                if (method_exists($instance, $m)) {
                    $details['capacity'] = $instance->$m((int)$building['level']);
                    break;
                }
            }

            // costi upgrade (prova metodi comuni)
            foreach (['getUpgradeCost','getUpgradeCosts','getConstructionCost','getConstructionCosts'] as $m) {
                if (method_exists($instance, $m)) {
                    $details['upgrade_costs'] = $instance->$m((int)$building['level'] + 1);
                    break;
                }
            }

            // tempo costruzione/upgrade se disponibile
            foreach (['getConstructionTime','getBuildTime'] as $m) {
                if (method_exists($instance, $m)) {
                    $details['build_time_sec'] = (int)$instance->$m((int)$building['level'] + 1);
                    break;
                }
            }
        }

        echo json_encode(['success' => true, 'details' => $details]);
        break;
		case 'buildings_catalog':
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        apiError('Metodo non consentito', 405);
    }

    $db = Database::getInstance();

    // Prendi tutti i tipi definiti
    try {
        $types = $db->fetchAll("SELECT * FROM building_types ORDER BY name ASC");
    } catch (\Throwable $e) {
        apiError('Tabella building_types non trovata. Importa i dati prima.', 500);
    }

    $geo = function($base, $mult, $level) {
        $base = (float)$base; $mult = (float)$mult; $level = (int)$level;
        if ($level <= 1) return $base;
        return $base * pow($mult, $level - 1);
    };

    $out = [];
    foreach ($types as $t) {
        $max = max(1, (int)$t['max_level']);

        $entry = [
            'type' => $t['slug'],
            'display' => $t['name'],
            'max_level' => $max,
            'levels' => []
        ];

        $cm = (float)($t['upgrade_cost_multiplier'] ?? 1.5);
        $pm = (float)($t['production_multiplier'] ?? 1.2);
        $capm= (float)($t['capacity_multiplier'] ?? 1.2);
        $tm = (float)($t['time_multiplier'] ?? 1.0);

        $base_time_min = (int)($t['build_time_minutes'] ?? 5);

        $cumSec = 0;
        for ($lvl = 1; $lvl <= $max; $lvl++) {
            // costi per raggiungere QUESTO livello
            $costs = [
                'water' => (int)round($geo($t['water_cost'], $cm, $lvl)),
                'food'  => (int)round($geo($t['food_cost'],  $cm, $lvl)),
                'wood'  => (int)round($geo($t['wood_cost'],  $cm, $lvl)),
                'stone' => (int)round($geo($t['stone_cost'], $cm, $lvl)),
                'iron'  => (int)round($geo($t['iron_cost'],  $cm, $lvl)),
                'gold'  => (int)round($geo($t['gold_cost'],  $cm, $lvl)),
            ];

            // produzione oraria a livello L
            $prod = [
                'water' => (int)round($geo($t['water_production'], $pm, $lvl)),
                'food'  => (int)round($geo($t['food_production'],  $pm, $lvl)),
                'wood'  => (int)round($geo($t['wood_production'],  $pm, $lvl)),
                'stone' => (int)round($geo($t['stone_production'], $pm, $lvl)),
                'iron'  => (int)round($geo($t['iron_production'],  $pm, $lvl)),
                'gold'  => (int)round($geo($t['gold_production'],  $pm, $lvl)),
            ];

            // capienza (numero generico; opzionalmente puoi usare capacity_resource)
            $capacity = (int)round($geo($t['capacity_increase'], $capm, $lvl));

            // tempo per QUESTO livello
            $time_sec = (int)round($geo($base_time_min, $tm, $lvl)) * 60;
            $cumSec += $time_sec;

            // pulizia: elimina chiavi a zero per snellire
            $prod = array_filter($prod, fn($v)=>$v>0);
            $costs = array_filter($costs, fn($v)=>$v>0);

            $entry['levels'][] = [
                'level' => $lvl,
                'costs' => $costs,
                'time_sec' => $time_sec,
                'time_cum_sec' => $cumSec,
                'production' => $prod,
                'capacity' => $capacity
            ];
        }

        $out[] = $entry;
    }

    echo json_encode(['success' => true, 'buildings' => $out]);
    break;
	    // =========================
    //  TECH-TREE / BUILDING_TYPES
    // =========================
    case 'get_building_types':
        // elenco compatto per lista
        $rows = $db->fetchAll("SELECT 
            id, slug, name, description, max_level, image_url,
            level_required,
            water_production, food_production, wood_production, stone_production, iron_production, gold_production,
            capacity_increase, capacity_resource,
            water_cost, food_cost, wood_cost, stone_cost, iron_cost, gold_cost,
            upgrade_cost_multiplier, production_multiplier, capacity_multiplier, time_multiplier,
            build_time_minutes
        FROM building_types ORDER BY name ASC");
        echo json_encode(['success' => true, 'items' => $rows]);
        break;

    case 'get_building_type':
        $slug = $_GET['slug'] ?? '';
        $id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$slug && !$id) apiError('Parametro mancante (slug o id).', 400);

        if ($slug) {
            $row = $db->fetch("SELECT * FROM building_types WHERE slug = ?", [$slug]);
        } else {
            $row = $db->fetch("SELECT * FROM building_types WHERE id = ?", [$id]);
        }
        if (!$row) apiError('Struttura non trovata', 404);

        echo json_encode(['success' => true, 'item' => $row]);
        break;

    case 'export_building_types_csv':
        verifyAdmin(); // opzionale: togli se vuoi permettere export a tutti
        $rows = $db->fetchAll("SELECT * FROM building_types ORDER BY name ASC");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=building_types.csv');
        $out = fopen('php://output', 'w');
        // intestazioni compatibili con l’import
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

    case 'export_building_type_csv':
        verifyAdmin(); // opzionale
        $slug = $_GET['slug'] ?? '';
        $id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$slug && !$id) apiError('Parametro mancante (slug o id).', 400);
        $row = $slug
            ? $db->fetch("SELECT * FROM building_types WHERE slug = ?", [$slug])
            : $db->fetch("SELECT * FROM building_types WHERE id = ?", [$id]);
        if (!$row) apiError('Struttura non trovata', 404);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=building_type_'.$row['slug'].'.csv');
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
            $row['slug'],$row['name'],$row['description'],$row['level_required'],
            $row['water_production'],$row['food_production'],$row['wood_production'],$row['stone_production'],$row['iron_production'],$row['gold_production'],
            $row['capacity_increase'],$row['capacity_resource'],
            $row['water_cost'],$row['food_cost'],$row['wood_cost'],$row['stone_cost'],$row['iron_cost'],$row['gold_cost'],
            $row['upgrade_cost_multiplier'],$row['production_multiplier'],$row['capacity_multiplier'],$row['time_multiplier'],
            $row['build_time_minutes'],$row['max_level'],$row['image_url'],$row['xp_per_building']
        ]);
        fclose($out);
        exit;

    case 'update_building_type':
        verifyAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiError('Metodo non consentito', 405);
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $slug = $data['slug'] ?? '';
        $id   = isset($data['id']) ? (int)$data['id'] : 0;
        if (!$slug && !$id) apiError('Parametro mancante (slug o id).', 400);

        $fields = [
            'description','max_level','upgrade_cost_multiplier','production_multiplier',
            'capacity_multiplier','time_multiplier','image_url'
        ];
        $updates = [];
        $params  = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $updates[] = "$f = ?";
                $params[]  = $data[$f];
            }
        }
        if (!$updates) apiError('Nessun campo da aggiornare.', 400);

        if ($slug) { $where = 'slug = ?'; $params[] = $slug; }
        else       { $where = 'id = ?';   $params[] = $id;   }

        $sql = "UPDATE building_types SET ".implode(', ', $updates)." WHERE $where";
        $db->query($sql, $params);
        echo json_encode(['success' => true]);
        break;



	
    default:
        apiError('Azione non riconosciuta');
}
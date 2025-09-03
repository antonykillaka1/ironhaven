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
        
    default:
        apiError('Azione non riconosciuta');
}
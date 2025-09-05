<?php
// index.php
require_once 'config.php';
require_once 'includes/autoload.php';

use Ironhaven\Core\Auth;

$auth = Auth::getInstance();
$isLoggedIn   = $auth->isLoggedIn();
$currentUser  = $isLoggedIn ? $auth->getCurrentUser() : null;

// Se l'utente non è loggato: login/registrazione
if (!$isLoggedIn) {
    $pageScript = 'login';
    include 'templates/header.php';
    include 'templates/login.php';
    include 'templates/footer.php';
    exit;
}

// Ottieni l'insediamento dell'utente
$db = Ironhaven\Core\Database::getInstance();
$settlement = $db->fetch(
    "SELECT * FROM settlements WHERE player_id = ? LIMIT 1",
    [isset($currentUser['id']) ? $currentUser['id'] : 0]
);

// Se non c'è insediamento: pagina creazione
if (!$settlement) {
    $pageScript = 'create_settlement';
    include 'templates/header.php';
    include 'templates/create_settlement.php';
    include 'templates/footer.php';
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : null;

// Mappa pagine valide (le pagine includono da sole header/footer e settano $pageScript/$pageStylesheet)
$validPages = [
    'profile'               => 'pages/profile.php',
    'missions'              => 'pages/missions.php',
    'buildings'             => 'pages/buildings.php',
    'admin'                 => 'pages/admin.php',
    'tech_tree'             => 'pages/tech_tree.php',           // <-- NUOVO
    'admin_building_types'  => 'pages/admin_building_types.php' // <-- NUOVO (import strutture)
];

// Guard permessi admin
if (
    ($page === 'admin' || $page === 'admin_building_types') &&
    (!isset($currentUser['is_admin']) || !$currentUser['is_admin'])
) {
    header('Location: index.php');
    exit;
}

// Routing
if ($page && isset($validPages[$page])) {
    // Le singole pagine si occupano di:
    // - settare $pageScript e (se serve) $pageStylesheet
    // - includere templates/header.php e templates/footer.php
    include $validPages[$page];
} else {
    // Pagina predefinita (città / game)
    $pageScript = 'game';
    include 'templates/header.php';
    include 'templates/game.php';
    include 'templates/footer.php';
}

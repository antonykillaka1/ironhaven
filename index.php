<?php
// index.php
require_once 'config.php';
require_once 'includes/autoload.php';

use Ironhaven\Core\Auth;

$auth = Auth::getInstance();
$isLoggedIn = $auth->isLoggedIn();
$currentUser = $isLoggedIn ? $auth->getCurrentUser() : null;

// Se l'utente non è loggato, mostra la pagina di login/registrazione
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

// Se l'utente non ha ancora un insediamento, mostra la pagina di creazione
if (!$settlement) {
	$pageScript = 'create_settlement';
	include 'templates/header.php';
	include 'templates/create_settlement.php';
	include 'templates/footer.php';
	exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : null;

// Gestisci le varie pagine del gioco in base al parametro page
$validPages = [
	'profile' => 'pages/profile.php',
	'missions' => 'pages/missions.php',
	'buildings' => 'pages/buildings.php',
	'admin' => 'pages/admin.php'
	// Altre pagine valide possono essere aggiunte qui
];

// Prima di includere la pagina, verificare i permessi per admin
if ($page === 'admin' && (!isset($currentUser['is_admin']) || !$currentUser['is_admin'])) {
	// Redirect alla pagina principale se l'utente non è admin
	header('Location: index.php');
	exit;
}

if ($page && isset($validPages[$page])) {
	// Imposta lo script JavaScript corrispondente alla pagina
	$pageScript = $page;
	
	// Include la pagina richiesta
	include $validPages[$page];
} else {
	// Pagina predefinita (città/game)
	$pageScript = 'game';
	include 'templates/header.php';
	include 'templates/game.php';
	include 'templates/footer.php';
}
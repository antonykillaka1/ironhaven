<?php
// Attiva logging degli errori
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // Log nella root del sito
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// config.php - Configurazione principale del gioco
define('DB_HOST', 'localhost');
define('DB_NAME', 'u492367864_test');
define('DB_USER', 'u492367864_test');
define('DB_PASS', '9Ang2l5iKtm|'); // Da cambiare in produzione

// Costanti di gioco
define('STARTING_RESOURCES', 200); // Risorse iniziali per giocatore
define('BASE_CONSUMPTION_WATER', 2); // Consumo acqua per abitante/ora
define('BASE_CONSUMPTION_FOOD', 3); // Consumo cibo per abitante/ora
define('GAME_VERSION', '0.1.2.7');
define('GAME_NAME', 'Ironhaven');

// Impostazioni temporali
define('CONSTRUCTION_TIME_UNIT', 60); // Secondi per unità di tempo costruzione
define('RESOURCE_CALCULATION_INTERVAL', 300); // Calcolo risorse ogni 5 minuti

// Percorsi dell'applicazione
define('ROOT_PATH', dirname(__FILE__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('SRC_PATH', ROOT_PATH . '/src');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// Configurazione sessione
date_default_timezone_set('Europe/Rome');

// Modalità debug
define('DEBUG_MODE', true); // Impostare a false in produzione
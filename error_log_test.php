<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // Salva in public_html/error.log
error_reporting(E_ALL);

// Simula un errore
trigger_error("Test di scrittura log errori", E_USER_NOTICE);

echo "Errore scritto nel log.";
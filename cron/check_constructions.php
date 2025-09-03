<?php
// cron/check_constructions.php
// Questo script dovrebbe essere eseguito ogni minuto

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/autoload.php';

$settlementManager = new \Ironhaven\Core\SettlementManager();

// Verifica edifici in costruzione completati
$completedCount = $settlementManager->checkConstructionStatus();

echo "Verifica costruzioni completata: $completedCount edifici completati.\n";
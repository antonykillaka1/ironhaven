<?php
// cron/update_resources.php
// Questo script dovrebbe essere eseguito ogni minuto

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/autoload.php';

$db = \Ironhaven\Core\Database::getInstance();
$resourceManager = new \Ironhaven\Core\ResourceManager();

// Ottieni tutti gli insediamenti
$settlements = $db->fetchAll("SELECT id FROM settlements");

foreach ($settlements as $settlement) {
    // Aggiorna risorse per ogni insediamento
    $resourceManager->updateResources($settlement['id']);
}

echo "Aggiornamento risorse completato: " . count($settlements) . " insediamenti.\n";
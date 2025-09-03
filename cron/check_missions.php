<?php
// cron/check_missions.php
// Questo script dovrebbe essere eseguito ogni minuto

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/autoload.php';

$db = \Ironhaven\Core\Database::getInstance();
$missionManager = new \Ironhaven\Missions\MissionManager();

// Ottieni tutte le missioni attive
$activeMissions = $db->fetchAll(
    "SELECT id FROM player_missions WHERE status = 'active'"
);

$completedCount = 0;
foreach ($activeMissions as $mission) {
    $status = $missionManager->checkMissionStatus($mission['id']);
    if ($status === 'completed') {
        $completedCount++;
    }
}

echo "Verifica missioni completata: $completedCount missioni completate.\n";
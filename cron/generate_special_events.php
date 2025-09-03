<?php
// cron/generate_special_events.php
// Questo script dovrebbe essere eseguito ogni ora

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/autoload.php';

$db = \Ironhaven\Core\Database::getInstance();

// Ottieni tutti gli edifici che possono generare eventi speciali
$specialBuildings = $db->fetchAll(
    "SELECT b.*, s.player_id FROM buildings b
     JOIN settlements s ON b.settlement_id = s.id
     WHERE b.type IN ('hunting_lodge') 
     AND b.construction_ends IS NULL"
);

$eventsGenerated = 0;
foreach ($specialBuildings as $building) {
    $className = 'Ironhaven\\Buildings\\' . str_replace('_', '', ucwords($building['type'], '_'));
    $instance = new $className($building);
    
    if (method_exists($instance, 'checkSpecialEvent')) {
        $event = $instance->checkSpecialEvent();
        
        if ($event) {
            // Processo l'evento
            if ($event['type'] === 'premium_meat') {
                // Aggiungi carne pregiata
                $db->query(
                    "UPDATE resources SET premium_meat = premium_meat + ? WHERE settlement_id = ?",
                    [$event['amount'], $building['settlement_id']]
                );
                
                // Registra il boost temporaneo
                $expires = date('Y-m-d H:i:s', time() + ($event['effect']['duration'] * 3600));
                $db->insert('temporary_effects', [
                    'settlement_id' => $building['settlement_id'],
                    'type' => $event['effect']['type'],
                    'amount' => $event['effect']['amount'],
                    'starts_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $expires
                ]);
                
                // Crea notifica
                $db->insert('notifications', [
                    'player_id' => $building['player_id'],
                    'type' => 'special_event',
                    'message' => "Il tuo Capanno di Caccia ha trovato Carne Pregiata! Hai ottenuto un bonus di +{$event['effect']['amount']}% alla produzione di cibo per {$event['effect']['duration']} ore.",
                    'data' => json_encode($event),
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_read' => 0
                ]);
                
                $eventsGenerated++;
            }
        }
    }
}

echo "Generazione eventi speciali completata: $eventsGenerated eventi generati.\n";
<?php
// src/Core/NotificationManager.php
namespace Ironhaven\Core;

class NotificationManager {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
    }
    
    public function addNotification($playerId, $type, $message, $data = []) {
        return $this->db->insert('notifications', [
            'player_id' => $playerId,
            'type' => $type,
            'message' => $message,
            'data' => !empty($data) ? json_encode($data) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'is_read' => 0
        ]);
    }
    
    public function getUnreadNotifications() {
        if (!$this->auth->isLoggedIn()) {
            return [];
        }
        
        return $this->db->fetchAll(
            "SELECT * FROM notifications 
             WHERE player_id = ? AND is_read = 0
             ORDER BY created_at DESC",
            [$this->auth->getUserId()]
        );
    }
    
    public function getAllNotifications($limit = 50, $offset = 0) {
        if (!$this->auth->isLoggedIn()) {
            return [];
        }
        
        return $this->db->fetchAll(
            "SELECT * FROM notifications 
             WHERE player_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$this->auth->getUserId(), $limit, $offset]
        );
    }
    
    public function markAsRead($notificationId) {
        if (!$this->auth->isLoggedIn()) {
            return false;
        }
        
        return $this->db->update(
            'notifications',
            ['is_read' => 1],
            'id = ? AND player_id = ?',
            [$notificationId, $this->auth->getUserId()]
        );
    }
    
    public function markAllAsRead() {
        if (!$this->auth->isLoggedIn()) {
            return false;
        }
        
        return $this->db->update(
            'notifications',
            ['is_read' => 1],
            'player_id = ? AND is_read = 0',
            [$this->auth->getUserId()]
        );
    }
    
    public function checkGameEvents() {
        if (!$this->auth->isLoggedIn()) {
            return;
        }
        
        $playerId = $this->auth->getUserId();
        
        // Verifica costruzioni completate
        $completedBuildings = $this->db->fetchAll(
            "SELECT b.* FROM buildings b
             JOIN settlements s ON b.settlement_id = s.id
             WHERE s.player_id = ? 
             AND b.construction_ends IS NOT NULL
             AND b.construction_ends <= NOW()",
            [$playerId]
        );
        
        foreach ($completedBuildings as $building) {
            $buildingName = $this->getBuildingName($building['type']);
            $this->addNotification(
                $playerId,
                'building_complete',
                "La costruzione di $buildingName livello {$building['level']} è stata completata!",
                ['building_id' => $building['id'], 'building_type' => $building['type']]
            );
        }
        
        // Verifica missioni completate
        $completedMissions = $this->db->fetchAll(
            "SELECT pm.*, m.title FROM player_missions pm
             JOIN missions m ON pm.mission_id = m.id
             WHERE pm.player_id = ? 
             AND pm.status = 'active'
             AND pm.ends_at <= NOW()",
            [$playerId]
        );
        
        foreach ($completedMissions as $mission) {
            $this->addNotification(
                $playerId,
                'mission_complete',
                "La missione \"{$mission['title']}\" è stata completata!",
                ['player_mission_id' => $mission['id']]
            );
        }
        
        // Verifica risorse insufficienti
        $settlements = $this->db->fetchAll(
            "SELECT s.id, s.name FROM settlements s
             WHERE s.player_id = ?",
            [$playerId]
        );
        
        foreach ($settlements as $settlement) {
            $resources = $this->db->fetch(
                "SELECT food, water FROM resources WHERE settlement_id = ?",
                [$settlement['id']]
            );
            
            $population = $this->db->fetch(
                "SELECT total FROM population WHERE settlement_id = ?",
                [$settlement['id']]
            );
            
            $requiredFood = $population['total'] * BASE_CONSUMPTION_FOOD;
            $requiredWater = $population['total'] * BASE_CONSUMPTION_WATER;
            
            if ($resources['food'] < $requiredFood) {
                $this->addNotification(
                    $playerId,
                    'resource_shortage',
                    "Attenzione: {$settlement['name']} sta esaurendo il cibo!",
                    ['settlement_id' => $settlement['id'], 'resource' => 'food']
                );
            }
            
            if ($resources['water'] < $requiredWater) {
                $this->addNotification(
                    $playerId,
                    'resource_shortage',
                    "Attenzione: {$settlement['name']} sta esaurendo l'acqua!",
                    ['settlement_id' => $settlement['id'], 'resource' => 'water']
                );
            }
        }
    }
    
    private function getBuildingName($type) {
        $buildingNames = [
            'house' => 'Casa',
            'farm' => 'Fattoria',
            'woodcutter' => 'Falegnameria',
            'quarry' => 'Cava di Pietra',
            'well' => 'Pozzo',
            'hunting_lodge' => 'Capanno di Caccia',
            'mill' => 'Mulino',
            'water_tank' => 'Serbatoio d\'Acqua',
            'water_pipes' => 'Condutture d\'Acqua',
            // Aggiungi altri edifici
        ];
        
        return isset($buildingNames[$type]) ? $buildingNames[$type] : ucfirst(str_replace('_', ' ', $type));
    }
}
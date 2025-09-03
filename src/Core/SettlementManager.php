<?php
// src/Core/SettlementManager.php - VERSIONE PULITA COMPLETA
namespace Ironhaven\Core;

class SettlementManager {
    private $db;
    private $populationManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->populationManager = new PopulationManager();
    }
    
    /**
     * Ottiene l'insediamento del giocatore
     */
    public function getPlayerSettlement($playerId) {
        return $this->db->fetch(
            "SELECT * FROM settlements WHERE player_id = ? LIMIT 1",
            [$playerId]
        );
    }
    
    /**
     * Ottiene gli edifici dell'insediamento
     */
    public function getBuildings($settlementId) {
        return $this->db->fetchAll(
            "SELECT * FROM buildings WHERE settlement_id = ? ORDER BY id ASC",
            [$settlementId]
        );
    }
    
    /**
     * Costruisce un nuovo edificio - VERSIONE PULITA
     */
    public function constructBuilding($settlementId, $type, $x = null, $y = null) {
        // Find coordinates if not provided
        if ($x === null || $y === null) {
            $coords = $this->findNextAvailableSlot($settlementId);
            if (!$coords) {
                return false; // No space available
            }
            $x = $coords['x'];
            $y = $coords['y'];
        }
        
        // Verify cell is empty
        $existingBuilding = $this->db->fetch(
            "SELECT id FROM buildings WHERE settlement_id = ? AND position_x = ? AND position_y = ?",
            [$settlementId, $x, $y]
        );
        
        if ($existingBuilding) {
            return false; // Cell occupied
        }
        
        // Get building class and costs
        $buildingClass = $this->getBuildingClass($type);
        if (!$buildingClass) {
            return false; // Invalid building type
        }
        
        try {
            $buildingInstance = new $buildingClass(['type' => $type]);
            $costs = $buildingInstance->getBaseCosts(1);
            $constructionTime = $buildingInstance->getConstructionTime(1);
        } catch (\Exception $e) {
            return false; // Failed to create building instance
        }
        
        // Check and deduct resources
        if (!$this->deductResources($settlementId, $costs)) {
            return false; // Insufficient resources
        }
        
        // Calculate construction end time
        $constructionEnds = new \DateTime();
        $constructionEnds->add(new \DateInterval('PT' . $constructionTime . 'S'));
        
        // Insert building
        return $this->db->insert('buildings', [
            'settlement_id' => $settlementId,
            'type' => $type,
            'level' => 1,
            'position_x' => $x,
            'position_y' => $y,
            'construction_started' => (new \DateTime())->format('Y-m-d H:i:s'),
            'construction_ends' => $constructionEnds->format('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Verifica lo stato delle costruzioni - VERSIONE PULITA
     */
    public function checkConstructionStatus() {
        // Get completed buildings
        $buildings = $this->db->fetchAll(
            "SELECT * FROM buildings 
             WHERE construction_ends IS NOT NULL 
             AND construction_ends <= NOW()"
        );
        
        $completedBuildings = [];
        
        foreach ($buildings as $building) {
            // Mark as completed
            $this->db->update(
                'buildings',
                ['construction_ends' => null],
                'id = ?',
                [$building['id']]
            );
            
            // Update population using PopulationManager
            $this->populationManager->onBuildingCompleted($building);
            
            // Create notification
            $this->createBuildingCompletionNotification($building);
            
            $completedBuildings[] = $building;
        }
        
        return $completedBuildings;
    }
    
    /**
     * Trova il prossimo slot disponibile
     */
    private function findNextAvailableSlot($settlementId) {
        // Simple grid search - start from (0,0) and find first empty cell
        for ($y = 0; $y < 15; $y++) {
            for ($x = 0; $x < 15; $x++) {
                $existing = $this->db->fetch(
                    "SELECT id FROM buildings WHERE settlement_id = ? AND position_x = ? AND position_y = ?",
                    [$settlementId, $x, $y]
                );
                
                if (!$existing) {
                    return ['x' => $x, 'y' => $y];
                }
            }
        }
        
        return null; // Map is full
    }
    
    /**
     * Deduce resources for construction
     */
    private function deductResources($settlementId, $costs) {
        // Get current resources
        $resources = $this->db->fetch(
            "SELECT * FROM resources WHERE settlement_id = ?",
            [$settlementId]
        );
        
        if (!$resources) {
            return false;
        }
        
        // Check if we have enough resources
        foreach ($costs as $resource => $amount) {
            if (!isset($resources[$resource]) || $resources[$resource] < $amount) {
                return false;
            }
        }
        
        // Deduct resources
        $updateData = [];
        foreach ($costs as $resource => $amount) {
            $updateData[$resource] = $resources[$resource] - $amount;
        }
        
        return $this->db->update(
            'resources',
            $updateData,
            'settlement_id = ?',
            [$settlementId]
        ) > 0;
    }
    
    /**
     * Get building class from type
     */
    private function getBuildingClass($type) {
        $className = str_replace('_', '', ucwords($type, '_'));
        $fullClassName = 'Ironhaven\\Buildings\\' . $className;
        
        return class_exists($fullClassName) ? $fullClassName : null;
    }
    
    /**
     * Crea notifica per edificio completato
     */
    private function createBuildingCompletionNotification($building) {
        // Ottieni nome edificio
        $buildingNames = [
            'house' => 'Casa',
            'farm' => 'Fattoria',
            'woodcutter' => 'Falegnameria',
            'quarry' => 'Cava di Pietra',
            'well' => 'Pozzo',
            'hunting_lodge' => 'Capanno di Caccia',
            'water_tank' => 'Serbatoio d\'Acqua',
            'mill' => 'Mulino',
            'water_pipes' => 'Condutture d\'Acqua'
        ];
        
        $buildingName = $buildingNames[$building['type']] ?? $building['type'];
        
        // Ottieni giocatore
        $settlementPlayerId = $this->db->fetch(
            "SELECT player_id FROM settlements WHERE id = ?",
            [$building['settlement_id']]
        );
        
        if (!$settlementPlayerId) {
            return false;
        }
        
        // Crea notifica usando NotificationManager se disponibile
        try {
            $notificationManager = new NotificationManager();
            $notificationManager->addNotification(
                $settlementPlayerId['player_id'],
                'building_completed',
                'Costruzione completata: ' . $buildingName . ' (livello ' . $building['level'] . ')'
            );
        } catch (\Exception $e) {
            // NotificationManager non disponibile, skip silently
        }
        
        return true;
    }
    
    /**
     * Verifica e corregge la popolazione - DELEGA al PopulationManager
     */
    public function verifyAndFixPopulation($settlementId) {
        return $this->populationManager->verifyAndFix($settlementId);
    }
    
    /**
     * Demolisce un edificio e aggiorna automaticamente la popolazione
     */
    public function demolishBuilding($buildingId) {
        // Find the building before removing
        $building = $this->db->fetch("SELECT * FROM buildings WHERE id = ?", [$buildingId]);
        if (!$building) {
            return false;
        }
        
        // Remove the building
        $result = $this->db->query("DELETE FROM buildings WHERE id = ?", [$buildingId]);
        
        // Update population using PopulationManager
        if ($result) {
            $this->populationManager->onBuildingDestroyed($building);
            return true;
        }
        
        return false;
    }
}
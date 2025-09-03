<?php
// src/Core/ResourceManager.php
namespace Ironhaven\Core;

class ResourceManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Aggiorna le risorse basate sul tempo trascorso
     */
    public function updateResources($settlementId) {
        // Ottieni risorse correnti
        $resources = $this->db->fetch(
            "SELECT * FROM resources WHERE settlement_id = ?",
            [$settlementId]
        );
        
        if (!$resources) {
            // Risorse non trovate, non fare nulla
            error_log("Nessuna risorsa trovata per l'insediamento ID: " . $settlementId);
            return false;
        }
        
        // Calcola tempo trascorso dall'ultimo aggiornamento
        $lastUpdate = new \DateTime($resources['last_update']);
        $now = new \DateTime();
        $hoursPassed = ($now->getTimestamp() - $lastUpdate->getTimestamp()) / 3600;
        
        // Se sono passate meno di 5 minuti (0.0833 ore), non aggiornare
        if ($hoursPassed < 0.0833) {
            return false;
        }
        
        // Calcola produzione basata sugli edifici
        $production = $this->calculateProduction($settlementId);
        
        // Calcola nuove risorse
        $updateData = [];
        $updateData['last_update'] = $now->format('Y-m-d H:i:s');
        
        foreach ($production['production'] as $resource => $amountPerHour) {
            if (isset($resources[$resource])) {
                $updateData[$resource] = $resources[$resource] + ($amountPerHour * $hoursPassed);
            }
        }
        
        // Aggiorna database
        if (count($updateData) > 1) { // Più di solo last_update
            $this->db->update(
                'resources',
                $updateData,
                'settlement_id = ?',
                [$settlementId]
            );
            
            error_log("Risorse aggiornate per insediamento ID: " . $settlementId . " dopo " . $hoursPassed . " ore");
            return true;
        }
        
        return false;
    }
    
    /**
     * Calcola produzione basata sugli edifici
     */
    public function calculateProduction($settlementId) {
        // Ottieni edifici completati
        $buildings = $this->db->fetchAll(
            "SELECT * FROM buildings 
             WHERE settlement_id = ? 
             AND (construction_ends IS NULL OR construction_ends <= NOW())",
            [$settlementId]
        );
        
        // Produzioni di base per risorsa
        $production = [
            'wood' => 0,
            'stone' => 0,
            'food' => 0,
            'water' => 0,
            'iron' => 0,
            'gold' => 0
        ];
        
        // Log buildings retrieved for debugging
        error_log("Calculating production for settlement ID: $settlementId with " . count($buildings) . " buildings");
        
        // Calcola produzione basata sugli edifici
        foreach ($buildings as $building) {
            $buildingClass = $this->getBuildingClass($building['type']);
            if (!$buildingClass) continue;
            
            $instance = new $buildingClass($building);
            $buildingProduction = $instance->getProduction($building['level']);
            
            // Log per ogni edificio
            error_log("Building type: " . $building['type'] . ", level: " . $building['level'] . 
                      ", production: " . json_encode($buildingProduction));
            
            // Aggiungi produzione dell'edificio
            foreach ($buildingProduction as $resource => $amount) {
                if (isset($production[$resource])) {
                    $production[$resource] += $amount;
                }
            }
        }
        
        // Ottieni effetti temporanei attivi
        $temporaryEffects = $this->db->fetchAll(
            "SELECT * FROM temporary_effects 
             WHERE settlement_id = ? AND expires_at > NOW()",
            [$settlementId]
        );
        
        // Applica effetti temporanei
        foreach ($temporaryEffects as $effect) {
            switch ($effect['type']) {
                case 'food_production_boost':
                    // Aumenta produzione cibo del X%
                    $production['food'] *= (1 + ($effect['amount'] / 100));
                    error_log("Applied food production boost: +" . $effect['amount'] . "%");
                    break;
                // Altri tipi di effetti
            }
        }
        
        error_log("Final production calculation: " . json_encode($production));
        
        return [
            'production' => $production,
            'buildings' => $buildings
        ];
    }
    
    /**
     * Ottiene la classe edificio basata sul tipo
     */
    private function getBuildingClass($type) {
        // Converti snake_case in PascalCase
        $className = str_replace('_', '', ucwords($type, '_'));
        $fullClassName = 'Ironhaven\\Buildings\\' . $className;
        
        if (class_exists($fullClassName)) {
            return $fullClassName;
        }
        
        error_log("Class not found for building type: $type ($fullClassName)");
        return null;
    }
}
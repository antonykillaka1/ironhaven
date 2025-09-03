<?php
// src/Buildings/WaterTank.php
namespace Ironhaven\Buildings;

class WaterTank extends Building {
    public static function getType() {
        return 'water_tank';
    }
    
    public function getBaseCosts($level) {
        $woodBase = 100 * pow(1.4, $level - 1);
        $stoneBase = 150 * pow(1.45, $level - 1);
        
        $costs = [
            'wood' => ceil($woodBase),
            'stone' => ceil($stoneBase)
        ];
        
        if ($level >= 9) {
            $costs['iron'] = ceil(50 * pow(1.35, $level - 9));
        }
        
        if ($level >= 16) {
            $costs['iron'] = ceil(100 * pow(1.35, $level - 16));
            $costs['steel'] = ceil(30 * pow(1.3, $level - 16));
        }
        
        return $costs;
    }
    
    public function getConstructionTime($level) {
        // Tempo base: 40 minuti × 1.2^(livello-1)
        $minutes = 40 * pow(1.2, $level - 1);
        return ceil($minutes * 60); // Conversione in secondi
    }
    
    public function getProduction($level) {
        // Non produce risorse direttamente
        return [];
    }
    
    public function getStorageCapacity($level) {
        // Capacità base: 500 unità acqua, +300 per livello
        return 500 + (300 * ($level - 1));
    }
    
    public function getBonus($level) {
        // +2% capacità stoccaggio acqua per livello
        return [
            'water_storage' => $level * 2
        ];
    }
    
    public function getSpecialEffect() {
        // In caso di carenza, ritarda di 12 ore gli effetti negativi sulla popolazione
        return [
            'type' => 'shortage_delay',
            'resource' => 'water',
            'hours' => 12
        ];
    }
}
<?php
// src/Buildings/Woodcutter.php
namespace Ironhaven\Buildings;

class Woodcutter extends Building {
    public static function getType() {
        return 'woodcutter';
    }
    
    public function getBaseCosts($level) {
        $woodBase = 30 * pow(1.4, $level - 1);
        $stoneBase = 40 * pow(1.4, $level - 1);
        
        $costs = [
            'wood' => ceil($woodBase),
            'stone' => ceil($stoneBase)
        ];
        
        if ($level >= 11) {
            $costs['iron'] = ceil(15 * pow(1.3, $level - 11));
            $costs['food'] = ceil(20 * pow(1.3, $level - 11));
        }
        
        if ($level >= 16) {
            $costs['iron'] = ceil(30 * pow(1.3, $level - 16));
            $costs['food'] = ceil(40 * pow(1.3, $level - 16));
            $costs['coins'] = ceil(10 * pow(1.3, $level - 16));
        }
        
        return $costs;
    }
    
    public function getConstructionTime($level) {
        // Tempo base: 40 minuti × 1.2^(livello-1)
        $minutes = 40 * pow(1.2, $level - 1);
        return ceil($minutes * 60); // Conversione in secondi
    }
    
    public function getProduction($level) {
        // Produzione base: 20 unità/ora, +12 per livello
        $woodProduction = 20 + (12 * ($level - 1));
        
        return [
            'wood' => $woodProduction
        ];
    }
    
    public function getForceWork($level) {
        // Formula forza lavoro: 3 × 1.1^(livello-1)
        return ceil(3 * pow(1.1, $level - 1));
    }
    
    public function getBonus($level) {
        // +1% produzione legno per livello
        return [
            'wood_production' => $level
        ];
    }
}
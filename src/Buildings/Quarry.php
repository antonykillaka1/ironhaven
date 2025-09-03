<?php
// src/Buildings/Quarry.php
namespace Ironhaven\Buildings;

class Quarry extends Building {
    public static function getType() {
        return 'quarry';
    }
    
    public function getBaseCosts($level) {
        $woodBase = 50 * pow(1.45, $level - 1);
        $ironBase = 10 * pow(1.4, $level - 1);
        
        $costs = [
            'wood' => ceil($woodBase)
        ];
        
        // Il ferro è richiesto anche a livelli bassi per la cava
        if ($level >= 2) {
            $costs['iron'] = ceil($ironBase);
        }
        
        if ($level >= 11) {
            $costs['iron'] = ceil(30 * pow(1.35, $level - 11));
            $costs['food'] = ceil(40 * pow(1.3, $level - 11));
        }
        
        if ($level >= 16) {
            $costs['iron'] = ceil(60 * pow(1.35, $level - 16));
            $costs['food'] = ceil(80 * pow(1.3, $level - 16));
            $costs['water'] = ceil(50 * pow(1.3, $level - 16));
            $costs['coins'] = ceil(20 * pow(1.3, $level - 16));
        }
        
        return $costs;
    }
    
    public function getConstructionTime($level) {
        // Tempo base: 50 minuti × 1.22^(livello-1)
        $minutes = 50 * pow(1.22, $level - 1);
        return ceil($minutes * 60); // Conversione in secondi
    }
    
    public function getProduction($level) {
        // Produzione base: 15 unità/ora, +9 per livello
        $stoneProduction = 15 + (9 * ($level - 1));
        
        return [
            'stone' => $stoneProduction
        ];
    }
    
    public function getForceWork($level) {
        // Formula forza lavoro: 3 × 1.1^(livello-1)
        return ceil(3 * pow(1.1, $level - 1));
    }
    
    public function getBonus($level) {
        // +1% produzione pietra per livello
        return [
            'stone_production' => $level
        ];
    }
}
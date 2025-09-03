<?php
// src/Buildings/Farm.php
namespace Ironhaven\Buildings;

class Farm extends Building {
    public static function getType() {
        return 'farm';
    }
    
    public function getBaseCosts($level) {
        $woodBase = 40 * pow(1.45, $level - 1);
        $stoneBase = 30 * pow(1.45, $level - 1);
        
        $costs = [
            'wood' => ceil($woodBase),
            'stone' => ceil($stoneBase)
        ];
        
        if ($level >= 11) {
            $costs['iron'] = ceil(20 * pow(1.35, $level - 11));
            $costs['water'] = ceil(50 * pow(1.3, $level - 11));
        }
        
        if ($level >= 16) {
            $costs['iron'] = ceil(40 * pow(1.35, $level - 16));
            $costs['water'] = ceil(100 * pow(1.3, $level - 16));
            $costs['coins'] = ceil(15 * pow(1.3, $level - 16));
        }
        
        return $costs;
    }
    
    public function getConstructionTime($level) {
        // Per il livello 1, restituisci sempre 5 minuti (300 secondi)
        if ($level === 1) {
            return 300; // 5 minuti in secondi
        }
        
        // Per livelli superiori, usa la formula originale
        // Tempo base: 45 minuti × 1.18^(livello-1)
        $minutes = 45 * pow(1.18, $level - 1);
        return ceil($minutes * 60); // Conversione in secondi
    }
    
    public function getProduction($level) {
        // Produzione base: 15 unità/ora, +10 per livello
        $foodProduction = 15 + (10 * ($level - 1));
        
        return [
            'food' => $foodProduction
        ];
    }
    
    public function getForceWork($level) {
        // Formula forza lavoro: 2 × 1.12^(livello-1)
        return ceil(2 * pow(1.12, $level - 1));
    }
    
    public function getBonus($level) {
        // +1% produzione cibo per livello
        return [
            'food_production' => $level
        ];
    }
}

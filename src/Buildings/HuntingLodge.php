<?php
// src/Buildings/HuntingLodge.php
namespace Ironhaven\Buildings;

class HuntingLodge extends Building {
    public static function getType() {
        return 'hunting_lodge';
    }
    
    public function getBaseCosts($level) {
        $woodBase = 80 * pow(1.4, $level - 1);
        $stoneBase = 60 * pow(1.4, $level - 1);
        
        $costs = [
            'wood' => ceil($woodBase),
            'stone' => ceil($stoneBase)
        ];
        
        if ($level >= 11) {
            $costs['iron'] = ceil(40 * pow(1.35, $level - 11));
        }
        
        if ($level >= 16) {
            $costs['iron'] = ceil(80 * pow(1.35, $level - 16));
            $costs['food'] = ceil(100 * pow(1.3, $level - 16));
        }
        
        return $costs;
    }
    
    public function getConstructionTime($level) {
        // Tempo base: 35 minuti × 1.2^(livello-1)
        $minutes = 35 * pow(1.2, $level - 1);
        return ceil($minutes * 60); // Conversione in secondi
    }
    
    public function getProduction($level) {
        // Produzione base: 12 unità cibo/ora, +8 per livello
        $foodProduction = 12 + (8 * ($level - 1));
        
        return [
            'food' => $foodProduction
        ];
    }
    
    public function getBonus($level) {
        // +1.5% chance di carne pregiata per livello
        return [
            'premium_meat_chance' => $level * 1.5
        ];
    }
    
    public function checkSpecialEvent() {
        // Chance dello 0,001% per ora di ottenere carne pregiata
        $chance = 0.001;
        $roll = mt_rand(1, 100000) / 1000;
        
        if ($roll <= $chance) {
            // Successo! Ottieni carne pregiata
            return [
                'type' => 'premium_meat',
                'amount' => 1,
                'effect' => [
                    'type' => 'food_production_boost',
                    'amount' => 30, // +30%
                    'duration' => 12 // 12 ore
                ]
            ];
        }
        
        return null;
    }
}
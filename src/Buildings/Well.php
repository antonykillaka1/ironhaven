<?php
// src/Buildings/Well.php
namespace Ironhaven\Buildings;

class Well extends Building {
    public static function getType() {
        return 'well';
    }
    
    public function getBaseCosts($level) {
        $woodBase = 35 * pow(1.4, $level - 1);
        $stoneBase = 45 * pow(1.45, $level - 1);
        
        $costs = [
            'wood' => ceil($woodBase),
            'stone' => ceil($stoneBase)
        ];
        
        if ($level >= 11) {
            $costs['iron'] = ceil(25 * pow(1.35, $level - 11));
        }
        
        if ($level >= 16) {
            $costs['iron'] = ceil(50 * pow(1.35, $level - 16));
            $costs['gold'] = ceil(10 * pow(1.3, $level - 16));
        }
        
        return $costs;
    }
    
    public function getConstructionTime($level) {
        // Per il livello 1, sempre 5 minuti (300 secondi)
        if ($level === 1) {
            return 300;
        }
        
        // Tempo base: 20 minuti × 1.25^(livello-1)
        $minutes = 20 * pow(1.25, $level - 1);
        return ceil($minutes * 60); // Conversione in secondi
    }
    
    public function getProduction($level) {
        // Produzione base: 10 unità/ora, +7 per livello
        $waterProduction = 10 + (7 * ($level - 1));
        
        return [
            'water' => $waterProduction
        ];
    }
    
    public function getForceWork($level) {
        // Forza lavoro richiesta: 1 lavoratore base, +0.5 per livello
        // Arrotondiamo per eccesso per assicurarci di avere sempre almeno 1 lavoratore
        return ceil(1 + (0.5 * ($level - 1)));
    }
    
    public function getBonus($level) {
        // +1% capacità di stoccaggio per livello
        return [
            'water_storage' => $level
        ];
    }
}
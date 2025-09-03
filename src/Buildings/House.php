<?php

namespace Ironhaven\Buildings;

class House extends Building {
    public static function getType() {
        return 'house';
    }

    protected function setupBuilding(): void {
        $this->name = 'Casa';
        $this->description = 'Fornisce abitanti per il tuo insediamento';
        $this->baseBuildTime = 60 * 5; // 5 minuti in secondi
        $this->baseCost = ['wood' => 50, 'stone' => 50];
        $this->populationIncrease = 5;
        $this->resourceIncrease = 0.01; // +1%
    }

    public function getForceWork($level) {
        return 0;
    }

    public function getBaseCosts($level) {
        $woodBase = 50 * pow(1.5, $level - 1);
        $stoneBase = 30 * pow(1.5, $level - 1);

        $costs = [
            'wood' => ceil($woodBase),
            'stone' => ceil($stoneBase)
        ];

        if ($level >= 11) {
            $costs['iron'] = ceil(20 * pow(1.4, $level - 11));
        }

        if ($level >= 16) {
            $costs['gold'] = ceil(10 * pow(1.3, $level - 16));
            $costs['crystals'] = ceil(5 * pow(1.3, $level - 16));
        }

        return $costs;
    }

    public function getConstructionTime($level) {
        // Verifica che baseBuildTime sia definita, altrimenti la inizializza
        if (!isset($this->baseBuildTime)) {
            error_log("ATTENZIONE: baseBuildTime non definita, inizializzando...");
            $this->baseBuildTime = 60 * 5; // 5 minuti in secondi
        }
        
        // Per il livello 1, usa il tempo base di 5 minuti
        if ($level == 1) {
            return $this->baseBuildTime; // 5 minuti (300 secondi)
        }
        
        // Per livelli superiori, scala da 5 minuti invece che da 30
        $baseMinutes = $this->baseBuildTime / 60; // Converte secondi in minuti
        $minutes = $baseMinutes * pow(1.2, $level - 1);
        return ceil($minutes * 60); // Converte di nuovo in secondi
    }

    public function getProduction($level) {
        return [];
    }

    public function getPopulation($level) {
        return ceil(5 * pow(1.15, $level - 1));
    }

    public function getBonus($level) {
        return [
            'resource_production' => $level
        ];
    }
}
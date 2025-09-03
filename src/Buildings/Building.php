<?php
// src/Buildings/Building.php
namespace Ironhaven\Buildings;

abstract class Building {
    protected $data;
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public static function getType() {
        return 'building';
    }
    
    /**
     * Ottiene i costi base per il livello specificato
     */
    abstract public function getBaseCosts($level);
    
    /**
     * Ottiene la produzione per il livello specificato
     */
    abstract public function getProduction($level);
    
    /**
     * Ottiene la forza lavoro richiesta per il livello specificato
     */
    abstract public function getForceWork($level);
    
    /**
     * Ottiene bonus per il livello specificato
     */
    abstract public function getBonus($level);
    
    /**
     * Ottiene il tempo di costruzione per il livello specificato
     * Questa implementazione di base imposta 5 minuti per gli edifici di livello 1
     */
    public function getConstructionTime($level) {
        // Per gli edifici di livello 1, sempre 5 minuti (300 secondi)
        if ($level === 1) {
            return 300; // 5 minuti in secondi
        }
        
        // Per livelli superiori, usare una formula specificata dalle classi figlie
        // o restituire un valore predefinito se non viene sovrascritta
        return 1800; // 30 minuti in secondi come valore predefinito
    }
    
    /**
     * Ottiene il livello dell'edificio
     */
    public function getLevel() {
        return $this->data['level'] ?? 1;
    }
    
    /**
     * Ottiene il tipo dell'edificio
     */
    public function getBuildingType() {
        return $this->data['type'] ?? static::getType();
    }
    
    /**
     * Ottiene i dati dell'edificio
     */
    public function getData() {
        return $this->data;
    }
}
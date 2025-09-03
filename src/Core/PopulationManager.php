<?php
// src/Core/PopulationManager.php
namespace Ironhaven\Core;

/**
 * Gestione centralizzata e stabile della popolazione
 * Sostituisce tutti i metodi frammentati presenti in SettlementManager
 */
class PopulationManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Calcola la popolazione totale di un insediamento
     * UNICO METODO per calcolare popolazione - elimina duplicazioni
     */
    public function calculateTotalPopulation(int $settlementId): int {
        // Base population
        $totalPopulation = 5;
        
        // Get completed houses only
        $houses = $this->db->fetchAll(
            "SELECT level FROM buildings 
             WHERE settlement_id = ? 
             AND type = 'house' 
             AND (construction_ends IS NULL OR construction_ends <= NOW())",
            [$settlementId]
        );
        
        // Add population from houses - formula semplificata
        foreach ($houses as $house) {
            $totalPopulation += $this->getHousePopulation($house['level']);
        }
        
        return $totalPopulation;
    }
    
    /**
     * Aggiorna la popolazione nel database
     * UNICO PUNTO per aggiornare popolazione
     */
    public function updatePopulation(int $settlementId): bool {
        $totalPopulation = $this->calculateTotalPopulation($settlementId);
        
        // Check if population record exists
        $currentPop = $this->db->fetch(
            "SELECT total FROM population WHERE settlement_id = ?",
            [$settlementId]
        );
        
        if ($currentPop) {
            // Update existing record
            $result = $this->db->update(
                'population',
                [
                    'total' => $totalPopulation,
                    'available' => $totalPopulation, // Simplified: assume all available
                    'last_update' => date('Y-m-d H:i:s')
                ],
                'settlement_id = ?',
                [$settlementId]
            );
            return $result > 0;
        } else {
            // Create new record
            $result = $this->db->insert('population', [
                'settlement_id' => $settlementId,
                'total' => $totalPopulation,
                'available' => $totalPopulation,
                'satisfaction' => 100.0,
                'last_update' => date('Y-m-d H:i:s')
            ]);
            return $result > 0;
        }
    }
    
    /**
     * Ottiene la popolazione corrente dal database
     */
    public function getPopulation(int $settlementId): ?array {
        return $this->db->fetch(
            "SELECT * FROM population WHERE settlement_id = ?",
            [$settlementId]
        );
    }
    
    /**
     * Verifica e corregge la popolazione se necessario
     * UNICO METODO per verificare integrità
     */
    public function verifyAndFix(int $settlementId): array {
        $calculated = $this->calculateTotalPopulation($settlementId);
        $current = $this->getPopulation($settlementId);
        $currentTotal = $current ? $current['total'] : 0;
        
        $result = [
            'settlement_id' => $settlementId,
            'calculated' => $calculated,
            'current' => $currentTotal,
            'fixed' => false
        ];
        
        if ($calculated !== $currentTotal) {
            $this->updatePopulation($settlementId);
            $result['fixed'] = true;
        }
        
        return $result;
    }
    
    /**
     * Hook per quando un edificio viene completato
     * Chiamata automaticamente quando una costruzione finisce
     */
    public function onBuildingCompleted(array $building): void {
        if ($building['type'] === 'house') {
            $this->updatePopulation($building['settlement_id']);
        }
    }
    
    /**
     * Hook per quando un edificio viene demolito
     */
    public function onBuildingDestroyed(array $building): void {
        if ($building['type'] === 'house') {
            $this->updatePopulation($building['settlement_id']);
        }
    }
    
    /**
     * Popolazione aggiunta da una casa di livello specifico
     * Formula semplice e stabile
     */
    private function getHousePopulation(int $level): int {
        // Formula semplice: ogni livello casa = 5 abitanti
        return $level * 5;
    }
    
    /**
     * Verifica integrità di tutti gli insediamenti (per admin)
     */
    public function verifyAllSettlements(): array {
        $settlements = $this->db->fetchAll("SELECT id FROM settlements");
        $results = [];
        $totalFixed = 0;
        
        foreach ($settlements as $settlement) {
            $result = $this->verifyAndFix($settlement['id']);
            $results[] = $result;
            if ($result['fixed']) {
                $totalFixed++;
            }
        }
        
        return [
            'settlements_checked' => count($settlements),
            'settlements_fixed' => $totalFixed,
            'details' => $results
        ];
    }
}
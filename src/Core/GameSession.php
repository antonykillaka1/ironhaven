<?php
// src/Core/GameSession.php
namespace Ironhaven\Core;

class GameSession {
    private static $instance = null;
    private $db;
    private $auth;
    private $settlementManager;
    private $resourceManager;
    private $currentSettlement;
    private $lastActivity;
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
        $this->settlementManager = new SettlementManager();
        $this->resourceManager = new ResourceManager();
        $this->lastActivity = time();
        
        // Carica l'insediamento corrente se l'utente è loggato
        if ($this->auth->isLoggedIn()) {
            $this->loadSettlement();
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadSettlement() {
        $playerId = $this->auth->getUserId();
        $this->currentSettlement = $this->settlementManager->getPlayerSettlement($playerId);
        
        if ($this->currentSettlement) {
            // Aggiorna risorse in base al tempo trascorso
            $this->resourceManager->updateResources($this->currentSettlement['id']);
            
            // Verifica costruzioni completate
            $this->settlementManager->checkConstructionStatus();
            
            // Verifica eventi speciali
            $this->checkSpecialEvents();
        }
    }
    
    public function getCurrentSettlement() {
        return $this->currentSettlement;
    }
    
    public function getSettlementResources() {
        if (!$this->currentSettlement) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT * FROM resources WHERE settlement_id = ?",
            [$this->currentSettlement['id']]
        );
    }
    
    public function getSettlementBuildings() {
        if (!$this->currentSettlement) {
            return [];
        }
        
        return $this->settlementManager->getBuildings($this->currentSettlement['id']);
    }
    
    public function getSettlementPopulation() {
        if (!$this->currentSettlement) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT * FROM population WHERE settlement_id = ?",
            [$this->currentSettlement['id']]
        );
    }
    
    public function updateActivity() {
        $this->lastActivity = time();
        
        // Aggiorna risorse se è passato più di un minuto dall'ultimo aggiornamento
        if ($this->currentSettlement && (time() - $this->lastActivity > 60)) {
            $this->resourceManager->updateResources($this->currentSettlement['id']);
        }
    }
    
    private function checkSpecialEvents() {
        // Ottieni edifici che possono generare eventi speciali
        $specialBuildings = $this->db->fetchAll(
            "SELECT * FROM buildings 
             WHERE settlement_id = ? 
             AND type IN ('hunting_lodge') 
             AND construction_ends IS NULL",
            [$this->currentSettlement['id']]
        );
        
        foreach ($specialBuildings as $building) {
            $className = 'Ironhaven\\Buildings\\' . $this->getClassNameFromType($building['type']);
            $instance = new $className($building);
            
            if (method_exists($instance, 'checkSpecialEvent')) {
                $event = $instance->checkSpecialEvent();
                
                if ($event) {
                    $this->processSpecialEvent($event);
                }
            }
        }
    }
    
    private function getClassNameFromType($type) {
        // Converti snake_case in PascalCase
        return str_replace('_', '', ucwords($type, '_'));
    }
    
    private function processSpecialEvent($event) {
        if ($event['type'] === 'premium_meat') {
            // Aggiungi carne pregiata
            $this->db->query(
                "UPDATE resources SET premium_meat = premium_meat + ? WHERE settlement_id = ?",
                [$event['amount'], $this->currentSettlement['id']]
            );
            
            // Registra il boost temporaneo
            $this->registerTemporaryBoost($event['effect']);
            
            // Crea log dell'evento
            $this->logGameEvent('special_event', [
                'event_type' => 'premium_meat',
                'amount' => $event['amount'],
                'effect' => $event['effect']
            ]);
        }
    }
    
    private function registerTemporaryBoost($effect) {
        $expires = date('Y-m-d H:i:s', time() + ($effect['duration'] * 3600));
        
        $this->db->insert('temporary_effects', [
            'settlement_id' => $this->currentSettlement['id'],
            'type' => $effect['type'],
            'amount' => $effect['amount'],
            'starts_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expires
        ]);
    }
    
    public function logGameEvent($type, $data) {
        $this->db->insert('game_logs', [
            'player_id' => $this->auth->getUserId(),
            'settlement_id' => $this->currentSettlement ? $this->currentSettlement['id'] : null,
            'action_type' => $type,
            'action_data' => json_encode($data),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
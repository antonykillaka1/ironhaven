<?php
// src/Missions/MissionManager.php
namespace Ironhaven\Missions;

use Ironhaven\Core\Database;

class MissionManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAvailableMissions($playerId) {
        $player = $this->db->fetch(
            "SELECT level, fame FROM players WHERE id = ?",
            [$playerId]
        );
        
        if (!$player) {
            return [];
        }
        
        // Ottieni missioni disponibili per il livello del giocatore
        $missions = $this->db->fetchAll(
            "SELECT * FROM missions 
             WHERE unlock_level <= ? AND is_active = 1
             ORDER BY fame_cost ASC",
            [$player['level']]
        );
        
        // Filtra missioni già in corso o completate
        $activeMissions = $this->db->fetchAll(
            "SELECT mission_id FROM player_missions 
             WHERE player_id = ? AND (status = 'active' OR status = 'completed')",
            [$playerId]
        );
        
        $activeMissionIds = array_column($activeMissions, 'mission_id');
        
        $availableMissions = [];
        foreach ($missions as $mission) {
            if (!in_array($mission['id'], $activeMissionIds) && $player['fame'] >= $mission['fame_cost']) {
                $availableMissions[] = $mission;
            }
        }
        
        return $availableMissions;
    }
    
    public function startMission($playerId, $missionId) {
        $mission = $this->db->fetch(
            "SELECT * FROM missions WHERE id = ?",
            [$missionId]
        );
        
        $player = $this->db->fetch(
            "SELECT level, fame FROM players WHERE id = ?",
            [$playerId]
        );
        
        if (!$mission || !$player) {
            return false;
        }
        
        // Verifica requisiti
        if ($player['level'] < $mission['unlock_level'] || $player['fame'] < $mission['fame_cost']) {
            return false;
        }
        
        // Verifica che la missione non sia già attiva
        $existingMission = $this->db->fetch(
            "SELECT * FROM player_missions 
             WHERE player_id = ? AND mission_id = ? AND status = 'active'",
            [$playerId, $missionId]
        );
        
        if ($existingMission) {
            return false;
        }
        
        // Sottrai fama se richiesta
        if ($mission['fame_cost'] > 0) {
            $this->db->update(
                'players',
                ['fame' => $player['fame'] - $mission['fame_cost']],
                'id = ?',
                [$playerId]
            );
        }
        
        // Calcola tempo fine missione
        $endsAt = date('Y-m-d H:i:s', time() + ($mission['duration_hours'] * 3600));
        
        // Inserisci missione attiva
        $missionId = $this->db->insert('player_missions', [
            'player_id' => $playerId,
            'mission_id' => $missionId,
            'started_at' => date('Y-m-d H:i:s'),
            'ends_at' => $endsAt,
            'status' => 'active'
        ]);
        
        return $missionId;
    }
    
    public function checkMissionStatus($playerMissionId) {
        $playerMission = $this->db->fetch(
            "SELECT * FROM player_missions WHERE id = ?",
            [$playerMissionId]
        );
        
        if (!$playerMission) {
            return false;
        }
        
        if ($playerMission['status'] !== 'active') {
            return $playerMission['status'];
        }
        
        // Verifica se la missione è terminata
        if (strtotime($playerMission['ends_at']) <= time()) {
            // Calcola successo missione (potrebbe includere logica più complessa)
            $success = true; // Per ora tutte le missioni hanno successo
            
            $newStatus = $success ? 'completed' : 'failed';
            
            $this->db->update(
                'player_missions',
                ['status' => $newStatus],
                'id = ?',
                [$playerMissionId]
            );
            
            return $newStatus;
        }
        
        return 'active';
    }
    
    public function claimRewards($playerMissionId) {
        $playerMission = $this->db->fetch(
            "SELECT pm.*, m.* FROM player_missions pm
             JOIN missions m ON pm.mission_id = m.id
             WHERE pm.id = ?",
            [$playerMissionId]
        );
        
        if (!$playerMission || $playerMission['status'] !== 'completed' || $playerMission['rewards_claimed']) {
            return false;
        }
        
        // Ottieni giocatore
        $player = $this->db->fetch(
            "SELECT * FROM players WHERE id = ?",
            [$playerMission['player_id']]
        );
        
        // Assegna esperienza e fama
        $newExperience = $player['experience'] + $playerMission['experience_reward'];
        $newFame = $player['fame'] + $playerMission['fame_reward'];
        
        $this->db->update(
            'players',
            [
                'experience' => $newExperience,
                'fame' => $newFame
            ],
            'id = ?',
            [$player['id']]
        );
        
        // Verifica level up
        $this->checkLevelUp($player['id'], $newExperience);
        
// Assegna ricompense risorse
        if ($playerMission['resource_rewards']) {
            $resourceRewards = json_decode($playerMission['resource_rewards'], true);
            
            // Ottieni risorse attuali dell'insediamento
            $settlement = $this->db->fetch(
                "SELECT id FROM settlements WHERE player_id = ? LIMIT 1",
                [$player['id']]
            );
            
            if ($settlement) {
                $resources = $this->db->fetch(
                    "SELECT * FROM resources WHERE settlement_id = ?",
                    [$settlement['id']]
                );
                
                $updateData = [];
                foreach ($resourceRewards as $resource => $amount) {
                    if (isset($resources[$resource])) {
                        $updateData[$resource] = $resources[$resource] + $amount;
                    }
                }
                
                if (!empty($updateData)) {
                    $this->db->update(
                        'resources',
                        $updateData,
                        'settlement_id = ?',
                        [$settlement['id']]
                    );
                }
            }
        }
        
        // Gestisci ricompense speciali (drop rari, drop leggendari)
        if ($playerMission['special_rewards']) {
            $specialRewards = json_decode($playerMission['special_rewards'], true);
            
            // Implementazione per gestire i vari tipi di ricompense speciali
            // (Sblocco strutture, bonus, ecc.)
            // Questa parte sarà espansa nelle fasi successive dello sviluppo
        }
        
        // Marca le ricompense come reclamate
        $this->db->update(
            'player_missions',
            ['rewards_claimed' => 1],
            'id = ?',
            [$playerMissionId]
        );
        
        return true;
    }
    
    private function checkLevelUp($playerId, $currentExperience) {
        $player = $this->db->fetch(
            "SELECT level FROM players WHERE id = ?",
            [$playerId]
        );
        
        $nextLevel = $player['level'] + 1;
        $expRequired = $this->getExperienceForLevel($nextLevel);
        
        if ($currentExperience >= $expRequired) {
            // Level up!
            $this->db->update(
                'players',
                ['level' => $nextLevel],
                'id = ?',
                [$playerId]
            );
            
            // Aggiungi fama per il level up
            $this->db->query(
                "UPDATE players SET fame = fame + 100 WHERE id = ?",
                [$playerId]
            );
            
            // Controlla se sono necessari altri level up
            $this->checkLevelUp($playerId, $currentExperience);
            
            return true;
        }
        
        return false;
    }
    
    private function getExperienceForLevel($level) {
        // Formula esperienza: livello 1-10: 1000 × livello
        if ($level <= 10) {
            return 1000 * $level;
        }
        // Livello 11-20: 2500 × livello
        else if ($level <= 20) {
            return 2500 * $level;
        }
        // Livello 21-30: 5000 × livello
        else if ($level <= 30) {
            return 5000 * $level;
        }
        // Livello 31-40: 10000 × livello
        else if ($level <= 40) {
            return 10000 * $level;
        }
        // Livello 41-50: 25000 × livello
        else {
            return 25000 * $level;
        }
    }
    
    public function generateRandomDrop($difficulty) {
        // Sistema per generare drop in base alla difficoltà della missione
        $rng = mt_rand(1, 1000) / 10; // Numero da 0.1 a 100.0
        
        // Modificatore di probabilità basato su difficoltà
        $rarityModifier = 1.0;
        switch ($difficulty) {
            case 'medium':
                $rarityModifier = 1.2;
                break;
            case 'hard':
                $rarityModifier = 1.5;
                break;
            case 'epic':
                $rarityModifier = 2.0;
                break;
        }
        
        // Calcola probabilità
        $legendaryChance = 3 * $rarityModifier; // Base 3%
        $rareChance = 12 * $rarityModifier;     // Base 12%
        
        // Determina tipo di drop
        if ($rng <= $legendaryChance) {
            return $this->generateLegendaryDrop();
        } else if ($rng <= ($legendaryChance + $rareChance)) {
            return $this->generateRareDrop();
        } else {
            return $this->generateNormalDrop();
        }
    }
    
    private function generateNormalDrop() {
        // Esempi di drop normali
        $possibleDrops = [
            ['type' => 'resources', 'data' => ['wood' => mt_rand(100, 300)]],
            ['type' => 'resources', 'data' => ['stone' => mt_rand(100, 300)]],
            ['type' => 'resources', 'data' => ['food' => mt_rand(100, 300)]],
            ['type' => 'resources', 'data' => ['water' => mt_rand(100, 300)]],
            ['type' => 'resources', 'data' => ['iron' => mt_rand(50, 150)]],
            ['type' => 'experience', 'data' => ['amount' => mt_rand(500, 1000)]]
        ];
        
        return $possibleDrops[array_rand($possibleDrops)];
    }
    
    private function generateRareDrop() {
        // Esempi di drop rari
        $possibleDrops = [
            ['type' => 'resources', 'data' => ['gold' => mt_rand(10, 50)]],
            ['type' => 'resources', 'data' => ['crystals' => mt_rand(10, 30)]],
            ['type' => 'resources', 'data' => ['premium_meat' => mt_rand(1, 3)]],
            ['type' => 'bonus', 'data' => [
                'type' => 'production',
                'amount' => 20,
                'duration' => 24 // Ore
            ]],
            ['type' => 'experience', 'data' => ['amount' => mt_rand(1000, 2500)]]
        ];
        
        return $possibleDrops[array_rand($possibleDrops)];
    }
    
    private function generateLegendaryDrop() {
        // Esempi di drop leggendari
        $possibleDrops = [
            ['type' => 'building', 'data' => [
                'name' => 'Monumento del Vincitore',
                'description' => 'Struttura unica che conferisce +5% a tutte le produzioni',
                'bonus' => ['type' => 'all_production', 'amount' => 5]
            ]],
            ['type' => 'unit', 'data' => [
                'name' => 'Campione di Ironhaven',
                'description' => 'Unità d\'élite con statistiche notevolmente superiori'
            ]],
            ['type' => 'permanent_bonus', 'data' => [
                'name' => 'Benedizione del Fondatore',
                'description' => '+10% esperienza e fama ottenuta',
                'bonus' => ['type' => 'experience_fame', 'amount' => 10]
            ]],
            ['type' => 'experience', 'data' => ['amount' => 5000]]
        ];
        
        return $possibleDrops[array_rand($possibleDrops)];
    }
}
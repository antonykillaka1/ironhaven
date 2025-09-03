<?php
// pages/missions.php
$gameSession = \Ironhaven\Core\GameSession::getInstance();
$player = \Ironhaven\Core\Auth::getInstance()->getCurrentUser();
$missionManager = new \Ironhaven\Missions\MissionManager();

// Ottieni missioni disponibili
$availableMissions = $missionManager->getAvailableMissions($player['id']);

// Ottieni missioni attive
$db = \Ironhaven\Core\Database::getInstance();
$activeMissions = $db->fetchAll(
    "SELECT pm.*, m.title, m.description, m.difficulty 
     FROM player_missions pm
     JOIN missions m ON pm.mission_id = m.id
     WHERE pm.player_id = ? AND pm.status = 'active'
     ORDER BY pm.ends_at ASC",
    [$player['id']]
);

// Ottieni missioni completate
$completedMissions = $db->fetchAll(
    "SELECT pm.*, m.title, m.description, m.difficulty 
     FROM player_missions pm
     JOIN missions m ON pm.mission_id = m.id
     WHERE pm.player_id = ? AND pm.status = 'completed'
     ORDER BY pm.ends_at DESC
     LIMIT 10",
    [$player['id']]
);

$pageStylesheet = 'missions';
$pageScript = 'missions';
include 'templates/header.php';
?>

<div class="missions-container">
    <h2>Centro Missioni</h2>
    
    <div class="player-stats">
        <div class="stat">
            <span class="stat-label">Livello:</span>
            <span class="stat-value"><?php echo $player['level']; ?></span>
        </div>
        <div class="stat">
            <span class="stat-label">Esperienza:</span>
            <span class="stat-value"><?php echo number_format($player['experience']); ?></span>
        </div>
        <div class="stat">
            <span class="stat-label">Fama:</span>
            <span class="stat-value"><?php echo number_format($player['fame']); ?></span>
        </div>
    </div>
    
    <div class="missions-sections">
        <div class="missions-section">
            <h3>Missioni Attive</h3>
            
            <?php if (empty($activeMissions)): ?>
            <div class="empty-list">
                <p>Non hai missioni attive al momento.</p>
            </div>
            <?php else: ?>
            <div class="missions-list active-missions">
                <?php foreach ($activeMissions as $mission): ?>
                <div class="mission-card" data-mission-id="<?php echo $mission['id']; ?>">
                    <div class="mission-header">
                        <h4><?php echo htmlspecialchars($mission['title']); ?></h4>
                        <span class="mission-difficulty <?php echo strtolower($mission['difficulty']); ?>"><?php echo ucfirst(strtolower($mission['difficulty'])); ?></span>
                    </div>
                    <div class="mission-body">
                        <p><?php echo htmlspecialchars($mission['description']); ?></p>
                    </div>
                    <div class="mission-footer">
                        <div class="mission-time" data-ends="<?php echo strtotime($mission['ends_at']); ?>">
                            <?php 
                            $now = time();
                            $ends = strtotime($mission['ends_at']);
                            $remainingHours = floor(($ends - $now) / 3600);
                            $remainingMinutes = floor((($ends - $now) % 3600) / 60);
                            echo "Tempo rimanente: {$remainingHours}h {$remainingMinutes}m";
                            ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="missions-section">
            <h3>Missioni Disponibili</h3>
            
            <?php if (empty($availableMissions)): ?>
            <div class="empty-list">
                <p>Non hai missioni disponibili al momento. Aumenta la tua fama per sbloccare nuove missioni!</p>
            </div>
            <?php else: ?>
            <div class="missions-list available-missions">
                <?php foreach ($availableMissions as $mission): ?>
                <div class="mission-card" data-mission-id="<?php echo $mission['id']; ?>">
                    <div class="mission-header">
                        <h4><?php echo htmlspecialchars($mission['title']); ?></h4>
                        <span class="mission-difficulty <?php echo strtolower($mission['difficulty']); ?>"><?php echo ucfirst(strtolower($mission['difficulty'])); ?></span>
                    </div>
                    <div class="mission-body">
                        <p><?php echo htmlspecialchars($mission['description']); ?></p>
                        
                        <div class="mission-costs">
                            <span class="mission-cost">
                                <img src="assets/images/resources/fame.png" alt="Fama">
                                Costo: <?php echo $mission['fame_cost']; ?> Fama
                            </span>
                        </div>
                        
                        <div class="mission-rewards">
                            <div class="reward">
                                <img src="assets/images/resources/experience.png" alt="Esperienza">
                                Esperienza: <?php echo number_format($mission['experience_reward']); ?>
                            </div>
                            <div class="reward">
                                <img src="assets/images/resources/fame.png" alt="Fama">
                                Fama: <?php echo number_format($mission['fame_reward']); ?>
                            </div>
                            
                            <?php 
                            // Risorse aggiuntive
                            if ($mission['resource_rewards']) {
                                $resourceRewards = json_decode($mission['resource_rewards'], true);
                                foreach ($resourceRewards as $resource => $amount) {
                                    echo '<div class="reward">';
                                    echo '<img src="assets/images/resources/' . $resource . '.png" alt="' . ucfirst($resource) . '">';
                                    echo ucfirst($resource) . ': ' . number_format($amount);
                                    echo '</div>';
                                }
                            }
                            ?>
                            
                            <div class="reward special">
                                <img src="assets/images/resources/chest.png" alt="Ricompensa Speciale">
                                Possibilità di ricompensa speciale
                            </div>
                        </div>
                    </div>
                    <div class="mission-footer">
                        <div class="mission-time">
                            Durata: <?php echo $mission['duration_hours']; ?> ore
                        </div>
                        <button class="btn primary start-mission" data-mission-id="<?php echo $mission['id']; ?>">
                            Inizia Missione
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="missions-section">
            <h3>Missioni Completate</h3>
            
            <?php if (empty($completedMissions)): ?>
            <div class="empty-list">
                <p>Non hai ancora completato nessuna missione.</p>
            </div>
            <?php else: ?>
            <div class="missions-list completed-missions">
                <?php foreach ($completedMissions as $mission): ?>
                <div class="mission-card<?php echo $mission['rewards_claimed'] ? ' claimed' : ''; ?>" data-mission-id="<?php echo $mission['id']; ?>">
                    <div class="mission-header">
                        <h4><?php echo htmlspecialchars($mission['title']); ?></h4>
                        <span class="mission-difficulty <?php echo strtolower($mission['difficulty']); ?>"><?php echo ucfirst(strtolower($mission['difficulty'])); ?></span>
                    </div>
                    <div class="mission-body">
                        <p><?php echo htmlspecialchars($mission['description']); ?></p>
                    </div>
                    <div class="mission-footer">
                        <div class="mission-time">
                            Completata: <?php echo date('d/m/Y H:i', strtotime($mission['ends_at'])); ?>
                        </div>
                        
                        <?php if (!$mission['rewards_claimed']): ?>
                        <button class="btn primary claim-rewards" data-mission-id="<?php echo $mission['id']; ?>">
                            Riscuoti Ricompense
                        </button>
                        <?php else: ?>
                        <span class="rewards-claimed">Ricompense Riscosse</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal per dettagli ricompensa -->
<div id="reward-modal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Ricompensa Ottenuta!</h2>
        
        <div class="reward-details">
            <div class="reward-image">
                <img id="reward-image" src="assets/images/resources/chest.png" alt="Ricompensa">
            </div>
            <div class="reward-info">
                <h3 id="reward-name">Nome Ricompensa</h3>
                <p id="reward-description">Descrizione della ricompensa.</p>
            </div>
        </div>
        
        <div class="standard-rewards">
            <h3>Ricompense Standard</h3>
            <ul id="standard-rewards-list">
                <!-- Lista ricompense standard -->
            </ul>
        </div>
        
        <div class="modal-actions">
            <button class="btn primary close-modal">Continua</button>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
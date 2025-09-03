<?php
// pages/profile.php
$auth = \Ironhaven\Core\Auth::getInstance();
$player = $auth->getCurrentUser();
$db = \Ironhaven\Core\Database::getInstance();

// Ottieni insediamento
$settlement = $db->fetch(
    "SELECT * FROM settlements WHERE player_id = ? LIMIT 1",
    [$player['id']]
);

// Ottieni statistiche
$stats = $db->fetch(
    "SELECT COUNT(*) as completed_missions FROM player_missions 
     WHERE player_id = ? AND status = 'completed'",
    [$player['id']]
);

// Calcola esperienza per livello successivo
$nextLevel = $player['level'] + 1;
$experienceForNextLevel = calculateExperienceForLevel($nextLevel);
$experienceProgress = min(100, ($player['experience'] / $experienceForNextLevel) * 100);

// Ottieni oggetti speciali
$specialItems = $db->fetchAll(
    "SELECT * FROM special_items WHERE player_id = ? ORDER BY obtained_at DESC",
    [$player['id']]
);

$pageStylesheet = 'profile';
$pageScript = 'profile';
include 'templates/header.php';

// Funzione per calcolare esperienza richiesta per livello
function calculateExperienceForLevel($level) {
    if ($level <= 10) {
        return 1000 * $level;
    } else if ($level <= 20) {
        return 2500 * $level;
    } else if ($level <= 30) {
        return 5000 * $level;
    } else if ($level <= 40) {
        return 10000 * $level;
    } else {
        return 25000 * $level;
    }
}
?>

<div class="profile-container">
    <h2>Profilo Giocatore</h2>
    
    <div class="profile-header">
        <div class="player-avatar">
            <img src="assets/images/avatars/default.png" alt="Avatar">
        </div>
        <div class="player-info">
            <h3><?php echo htmlspecialchars($player['username']); ?></h3>
            <div class="player-level">
                <span class="level-badge">Livello <?php echo $player['level']; ?></span>
                <div class="level-progress">
                    <div class="progress-bar" style="width: <?php echo $experienceProgress; ?>%"></div>
                </div>
                <span class="level-stats">
                    <?php echo number_format($player['experience']); ?> / <?php echo number_format($experienceForNextLevel); ?> XP
                </span>
            </div>
            <div class="player-fame">
                <img src="assets/images/resources/fame.png" alt="Fama">
                <span><?php echo number_format($player['fame']); ?> Fama</span>
            </div>
        </div>
    </div>
    
    <div class="profile-sections">
        <div class="profile-section">
            <h3>Informazioni Generali</h3>
            <div class="info-card">
                <div class="info-item">
                    <span class="info-label">Giocatore dal</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($player['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ultimo accesso</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($player['last_login'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Missioni completate</span>
                    <span class="info-value"><?php echo $stats['completed_missions']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Insediamento</span>
                    <span class="info-value"><?php echo $settlement ? htmlspecialchars($settlement['name']) : 'Nessuno'; ?></span>
                </div>
            </div>
        </div>
        
        <div class="profile-section">
            <h3>Bonus Attivi</h3>
            <?php
            // Bonus per livello
            $productionBonus = $player['level'] * 2;
            $constructionSpeedBonus = $player['level'] * 3;
            $resourceConsumptionBonus = $player['level'];
            ?>
            <div class="bonus-card">
                <div class="bonus-item">
                    <span class="bonus-label">Produzione risorse</span>
                    <span class="bonus-value">+<?php echo $productionBonus; ?>%</span>
                </div>
                <div class="bonus-item">
                    <span class="bonus-label">Velocità costruzione</span>
                    <span class="bonus-value">+<?php echo $constructionSpeedBonus; ?>%</span>
                </div>
                <div class="bonus-item">
                    <span class="bonus-label">Riduzione consumo</span>
                    <span class="bonus-value">-<?php echo $resourceConsumptionBonus; ?>%</span>
                </div>
                
                <?php if (!empty($specialItems)): ?>
                <div class="bonus-divider"></div>
                <h4>Bonus da Oggetti Speciali</h4>
                
                <?php foreach ($specialItems as $item): ?>
                <?php if ($item['bonus_type'] && $item['bonus_amount']): ?>
                <div class="bonus-item special">
                    <span class="bonus-label"><?php echo htmlspecialchars($item['name']); ?></span>
                    <span class="bonus-value">
                        <?php
                        $prefix = in_array($item['bonus_type'], ['resource_production', 'construction_speed']) ? '+' : '-';
                        echo $prefix . $item['bonus_amount'] . '%';
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-section">
            <h3>Oggetti Speciali</h3>
            <?php if (empty($specialItems)): ?>
            <div class="empty-list">
                <p>Non possiedi ancora oggetti speciali. Completando missioni difficili potrai ottenerne!</p>
            </div>
            <?php else: ?>
            <div class="items-list">
                <?php foreach ($specialItems as $item): ?>
                <div class="item-card">
                    <div class="item-icon">
                        <img src="assets/images/items/<?php echo $item['type']; ?>.png" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    </div>
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                        
                        <?php if ($item['bonus_type'] && $item['bonus_amount']): ?>
                        <div class="item-bonus">
                            <?php
                            $bonusText = '';
                            switch ($item['bonus_type']) {
                                case 'resource_production':
                                    $bonusText = '+' . $item['bonus_amount'] . '% produzione risorse';
                                    break;
                                case 'construction_speed':
                                    $bonusText = '+' . $item['bonus_amount'] . '% velocità costruzione';
                                    break;
                                case 'resource_consumption':
                                    $bonusText = '-' . $item['bonus_amount'] . '% consumo risorse';
                                    break;
                            }
                            echo $bonusText;
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="item-status <?php echo $item['is_equipped'] ? 'equipped' : ''; ?>">
                        <?php if ($item['is_equipped']): ?>
                        <span>In uso</span>
                        <?php else: ?>
                        <button class="btn equip-item" data-item-id="<?php echo $item['id']; ?>">Usa</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="profile-section">
            <h3>Impostazioni Account</h3>
            <form id="account-settings-form" class="settings-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($player['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="current-password">Password Attuale</label>
                    <input type="password" id="current-password" name="current_password">
                </div>
                
                <div class="form-group">
                    <label for="new-password">Nuova Password</label>
                    <input type="password" id="new-password" name="new_password">
                </div>
                
                <div class="form-group">
                    <label for="confirm-password">Conferma Nuova Password</label>
                    <input type="password" id="confirm-password" name="confirm_password">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn primary">Salva Modifiche</button>
                </div>
                
                <div class="error-message" id="settings-error"></div>
                <div class="success-message" id="settings-success"></div>
            </form>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
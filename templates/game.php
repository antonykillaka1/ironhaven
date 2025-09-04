<?php
// templates/game.php - VERSIONE PULITA
$gameSession = \Ironhaven\Core\GameSession::getInstance();
$settlement = $gameSession->getCurrentSettlement();
$resources = $gameSession->getSettlementResources();
$population = $gameSession->getSettlementPopulation();
$buildings = $gameSession->getSettlementBuildings();
$currentUser = $GLOBALS['currentUser'] ?? null;

// Ottieni eventuali effetti temporanei attivi
$db = \Ironhaven\Core\Database::getInstance();
$temporaryEffects = $db->fetchAll(
    "SELECT * FROM temporary_effects 
     WHERE settlement_id = ? AND expires_at > NOW()",
    [$settlement['id']]
);

// Ottieni notifiche non lette
try {
    $notificationManager = new \Ironhaven\Core\NotificationManager();
    $unreadNotifications = $notificationManager->getUnreadNotifications();
} catch (\Exception $e) {
    $unreadNotifications = [];
}
?>

<div class="game-container">
    <div class="resource-bar">
        <div class="resource" title="Legno">
            <img src="assets/images/resources/wood.png" alt="Legno">
            <span id="resource-wood"><?php echo number_format($resources['wood']); ?></span>
            (<span id="wood-production">+0</span>/h)
        </div>
        <div class="resource" title="Pietra">
            <img src="assets/images/resources/stone.png" alt="Pietra">
            <span id="resource-stone"><?php echo number_format($resources['stone']); ?></span>
            (<span id="stone-production">+0</span>/h)
        </div>
        <div class="resource" title="Cibo">
            <img src="assets/images/resources/food.png" alt="Cibo">
            <span id="resource-food"><?php echo number_format($resources['food']); ?></span>
            (<span id="food-production">+0</span>/h)
        </div>
        <div class="resource" title="Acqua">
            <img src="assets/images/resources/water.png" alt="Acqua">
            <span id="resource-water"><?php echo number_format($resources['water']); ?></span>
            (<span id="water-production">+0</span>/h)
        </div>
        <?php if ($resources['iron'] > 0): ?>
        <div class="resource" title="Ferro">
            <img src="assets/images/resources/iron.png" alt="Ferro">
            <span id="resource-iron"><?php echo number_format($resources['iron']); ?></span>
            (<span id="iron-production">+0</span>/h)
        </div>
        <?php endif; ?>
        <?php if ($resources['gold'] > 0): ?>
        <div class="resource" title="Oro">
            <img src="assets/images/resources/gold.png" alt="Oro">
            <span id="resource-gold"><?php echo number_format($resources['gold']); ?></span>
            (<span id="gold-production">+0</span>/h)
        </div>
        <?php endif; ?>
    </div>
    
    <div class="settlement-info">
        <h2><?php echo htmlspecialchars($settlement['name']); ?></h2>
        <div class="settlement-stats">
            <div class="stat" title="Popolazione">
                <img src="assets/images/ui/population.png" alt="Abitanti">
                <span id="population"><?php echo $population['total']; ?></span>
                (<span id="available-population"><?php echo $population['available']; ?></span> disponibili)
            </div>
            <div class="stat" title="Soddisfazione">
                <img src="assets/images/ui/satisfaction.png" alt="Soddisfazione">
                <span id="satisfaction"><?php echo number_format($population['satisfaction']); ?>%</span>
            </div>
        </div>
    </div>
    
    <!-- Effetti temporanei attivi -->
    <?php if (!empty($temporaryEffects)): ?>
    <div class="active-effects">
        <h3>Effetti Attivi</h3>
        <ul>
            <?php foreach ($temporaryEffects as $effect): ?>
            <li>
                <?php 
                $effectName = '';
                $effectDescription = '';
                
                switch ($effect['type']) {
                    case 'food_production_boost':
                        $effectName = 'Banchetto di Carne Pregiata';
                        $effectDescription = '+' . $effect['amount'] . '% produzione di cibo';
                        break;
                    default:
                        $effectName = ucwords(str_replace('_', ' ', $effect['type']));
                        $effectDescription = '+' . $effect['amount'];
                        break;
                }
                ?>
                <span class="effect-name"><?php echo $effectName; ?></span>
                <span class="effect-description"><?php echo $effectDescription; ?></span>
                <span class="effect-time" data-expires="<?php echo strtotime($effect['expires_at']); ?>">
                    <?php 
                    $now = time();
                    $expires = strtotime($effect['expires_at']);
                    $remainingHours = floor(($expires - $now) / 3600);
                    $remainingMinutes = floor((($expires - $now) % 3600) / 60);
                    echo $remainingHours . 'h ' . $remainingMinutes . 'm';
                    ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="game-content">
        <div class="settlement-view">
            <div id="city-map" class="city-map">
                <div class="city-grid" id="city-grid">
                    <!-- La griglia verrà popolata via JavaScript -->
                </div>
            </div>
        </div>
        
        <div class="game-sidebar">
            <div class="buildings-list">
                <h3>Edifici Disponibili</h3>
                <div class="buildings-container" id="available-buildings">
                    <!-- Lista edifici disponibili generata via JavaScript -->
                </div>
            </div>
            
            <div class="quick-actions">
                <h3>Azioni Rapide</h3>
                <button id="collect-all" class="btn">Raccogli Tutte le Risorse</button>
                <button id="check-missions" class="btn">Controlla Missioni</button>
                
                <?php 
                // Mostra pulsanti admin solo se DEBUG_MODE è attivo E utente è admin
                if (defined('DEBUG_MODE') && DEBUG_MODE && $currentUser && isset($currentUser['is_admin']) && $currentUser['is_admin'] == 1): 
                ?>
                <div class="debug-actions" style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;">
                    <h4 style="color: #ff6600;">Admin Tools (Debug Mode)</h4>
                    <button id="admin-population-check" class="btn" style="background-color: #0099cc; color: white;">Verifica Popolazione</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Template per la costruzione di edifici -->
<div id="building-construction-modal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Costruisci <span id="building-name"></span></h2>
        
        <div class="building-info">
            <div class="building-image">
                <img id="building-image" src="" alt="">
            </div>
            <div class="building-description" id="building-description"></div>
        </div>
        
        <div class="building-costs">
            <h3>Costi di Costruzione</h3>
            <ul id="building-costs-list"></ul>
        </div>
        
        <div class="building-benefits">
            <h3>Benefici</h3>
            <ul id="building-benefits-list"></ul>
        </div>
        
        <div class="construction-time">
            <h3>Tempo di Costruzione</h3>
            <p id="construction-time"></p>
        </div>
        
        <div class="construction-actions">
            <button id="start-construction" class="btn primary">Costruisci</button>
            <button class="btn close-modal">Annulla</button>
        </div>
    </div>
</div>

<!-- Container per notifiche -->
<div id="notifications-container" class="notifications-container"></div>

<!-- Script principale del gioco -->
<script src="assets/js/game.js?v=<?= GAME_VERSION ?>"></script>

<!-- Script admin semplificato (solo per admin in debug mode) -->
<?php if (defined('DEBUG_MODE') && DEBUG_MODE && $currentUser && isset($currentUser['is_admin']) && $currentUser['is_admin'] == 1): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Unico pulsante admin rimasto
    const adminPopBtn = document.getElementById('admin-population-check');
    if (adminPopBtn) {
        adminPopBtn.addEventListener('click', async function() {
            try {
                const response = await fetch('api.php?action=admin_population_health', {
                    method: 'POST'
                });
                const data = await response.json();
                
                if (data.success) {
                    const fixed = data.results.settlements_fixed || 0;
                    const total = data.results.settlements_checked || 0;
                    alert(`Controllo popolazione completato:\n${total} insediamenti controllati\n${fixed} insediamenti corretti`);
                } else {
                    alert('Errore: ' + (data.error || 'Sconosciuto'));
                }
            } catch (error) {
                console.error('Admin error:', error);
                alert('Errore durante il controllo popolazione');
            }
        });
    }
});
</script>
<?php endif; ?>
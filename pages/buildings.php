<?php
// pages/buildings.php
// Richiedi file necessari
require_once 'includes/autoload.php';
require_once 'config.php';

// Imposta lo script della pagina
$pageScript = 'buildings';
$pageStylesheet = 'buildings';

// Usa le classi necessarie
use Ironhaven\Core\Auth;
use Ironhaven\Core\GameSession;
use Ironhaven\Core\SettlementManager;

// Inizializza auth e controlla se l'utente è loggato
$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Ottieni la sessione di gioco corrente e l'insediamento
$settlementManager = new SettlementManager();
$settlement = $settlementManager->getPlayerSettlement($auth->getUserId());

if (!$settlement) {
    // Reindirizza alla pagina di creazione insediamento se l'utente non ne ha uno
    header('Location: index.php?page=create_settlement');
    exit();
}

// Ottieni tutti gli edifici nell'insediamento
$buildings = $settlementManager->getBuildings($settlement['id']);

// Includi l'header
include_once 'templates/header.php';
?>

<div class="buildings-container">
    <h1>Gestione Edifici</h1>
    
    <div class="buildings-overview">
        <h2>Edifici dell'Insediamento</h2>
        
        <?php if (empty($buildings)): ?>
            <p>Non hai ancora costruito nessuna struttura. Inizia a costruire per far crescere il tuo insediamento!</p>
        <?php else: ?>
            <div class="buildings-grid">
                <?php foreach ($buildings as $building): ?>
                    <div class="building-card" data-id="<?php echo $building['id']; ?>">
                        <img src="assets/images/buildings/<?php echo strtolower($building['type']); ?>.png" alt="<?php echo $building['type']; ?>">
                        <h3><?php echo $building['type']; ?></h3>
                        <p>Livello: <?php echo $building['level']; ?></p>
                        <p>Stato: <?php echo $building['construction_ends'] ? 'In costruzione' : 'Completato'; ?></p>
                        <div class="building-actions">
                            <button class="upgrade-btn" data-id="<?php echo $building['id']; ?>" <?php echo $building['construction_ends'] ? 'disabled' : ''; ?>>Potenzia</button>
                            <button class="manage-btn" data-id="<?php echo $building['id']; ?>" <?php echo $building['construction_ends'] ? 'disabled' : ''; ?>>Gestisci</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="new-buildings">
        <h2>Costruisci Nuove Strutture</h2>
        <div class="available-buildings">
            <div class="building-option" data-type="house">
                <img src="assets/images/buildings/house.png" alt="Casa">
                <h3>Casa</h3>
                <p>Fornisce alloggio per i tuoi coloni</p>
                <p class="cost">Costo: 50 legno, 30 pietra</p>
                <button class="build-btn" data-type="house">Costruisci</button>
            </div>
            <div class="building-option" data-type="farm">
                <img src="assets/images/buildings/farm.png" alt="Fattoria">
                <h3>Fattoria</h3>
                <p>Produce cibo per il tuo insediamento</p>
                <p class="cost">Costo: 40 legno, 30 pietra</p>
                <button class="build-btn" data-type="farm">Costruisci</button>
            </div>
            <!-- Altri tipi di edifici possono essere aggiunti qui -->
        </div>
    </div>
</div>
<!-- Carica il file principale con i moduli ES6 -->
<script src="assets/js/buildings.js"></script>
<?php
// Includi il footer
include_once 'templates/footer.php';
?>

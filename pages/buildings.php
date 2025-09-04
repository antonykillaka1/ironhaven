<?php
// pages/buildings.php
// Richiedi file necessari
require_once 'includes/autoload.php';
require_once 'config.php';

// Imposta lo script e lo stylesheet della pagina (il footer caricherà assets/js/buildings.js?v=GAME_VERSION)
$pageScript = 'buildings';
$pageStylesheet = 'buildings';

// Usa le classi necessarie
use Ironhaven\Core\Auth;
use Ironhaven\Core\SettlementManager;

// Inizializza auth e controlla se l'utente è loggato
$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Ottieni l'insediamento e gli edifici
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
                    <?php
                        $isUnderConstruction = !empty($building['construction_ends']);
                        $endsEpoch = $isUnderConstruction ? strtotime($building['construction_ends']) : null;
                    ?>
                    <div
                        class="building-card"
                        data-id="<?php echo (int)$building['id']; ?>"
                        data-status="<?php echo $isUnderConstruction ? 'building' : 'completed'; ?>"
                        <?php if ($isUnderConstruction): ?>
                            data-ends="<?php echo $endsEpoch; ?>"
                        <?php endif; ?>
                    >
                        <img
                            src="assets/images/buildings/<?php echo strtolower($building['type']); ?>.png"
                            alt="<?php echo htmlspecialchars($building['type']); ?>"
                        >
                        <h3><?php echo htmlspecialchars($building['type']); ?></h3>

                        <p>Livello: <?php echo (int)$building['level']; ?></p>

                        <p>
                            Stato:
                            <span class="status-text">
                                <?php echo $isUnderConstruction ? 'In costruzione' : 'Completato'; ?>
                            </span>
                            <?php if ($isUnderConstruction): ?>
                                <!-- Timer countdown (aggiornato da assets/js/buildings.js) -->
                                <span class="construction-timer" data-ends="<?php echo $endsEpoch; ?>"></span>
                            <?php endif; ?>
                        </p>

                        <div class="building-actions">
                            <?php if (!empty($building['construction_ends'])): ?>
                              <button class="cancel-build" data-id="<?php echo (int)$building['id']; ?>">Annulla</button>
                              <button class="upgrade-btn" data-id="<?php echo (int)$building['id']; ?>" disabled>Potenzia</button>
                              <button class="manage-btn"  data-id="<?php echo (int)$building['id']; ?>" disabled>Gestisci</button>
                            <?php else: ?>
                              <button class="upgrade-btn" data-id="<?php echo (int)$building['id']; ?>">Potenzia</button>
                              <button class="manage-btn"  data-id="<?php echo (int)$building['id']; ?>">Gestisci</button>
                            <?php endif; ?>
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

            <!-- Aggiungi qui altre opzioni di edifici -->
        </div>
    </div>
</div>

<?php
// Includi il footer (carica automaticamente assets/js/buildings.js come modulo)
include_once 'templates/footer.php';
?>

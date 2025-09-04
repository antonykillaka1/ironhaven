<?php
// pages/buildings.php
require_once 'includes/autoload.php';
require_once 'config.php';

$pageScript = 'buildings';
$pageStylesheet = 'buildings';

use Ironhaven\Core\Auth;
use Ironhaven\Core\SettlementManager;

$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$settlementManager = new SettlementManager();
$settlement = $settlementManager->getPlayerSettlement($auth->getUserId());

if (!$settlement) {
    header('Location: index.php?page=create_settlement');
    exit();
}

$buildings = $settlementManager->getBuildings($settlement['id']);

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
                        $now     = time();
                        $endsTs  = !empty($building['construction_ends']) ? strtotime($building['construction_ends']) : null;

                        // prova a trovare il campo di "start" con i nomi più comuni
                        $startTs = null;
                        foreach (['construction_starts','construction_started','construction_start','started_at'] as $k) {
                            if (!empty($building[$k])) { $startTs = strtotime($building[$k]); break; }
                        }

                        $isUnderConstruction = !empty($endsTs) && $endsTs > $now;
                        $isUpgrade           = $isUnderConstruction && ((int)$building['level'] > 1);
                        $variantClass        = $isUpgrade ? 'upgrade' : 'new';
                    ?>
                    <div class="building-card"
                         data-id="<?php echo (int)$building['id']; ?>"
                         data-status="<?php echo $isUnderConstruction ? 'building' : 'completed'; ?>">

                        <?php if ($isUnderConstruction): ?>
                            <!-- Progress bar in alto -->
                            <div class="build-progress <?php echo $variantClass; ?><?php echo $startTs ? '' : ' indeterminate'; ?>"
                                 <?php if ($startTs): ?>
                                     data-starts="<?php echo $startTs; ?>" data-ends="<?php echo $endsTs; ?>"
                                 <?php endif; ?>></div>

                            <!-- Badge con countdown -->
                            <div class="build-badge <?php echo $variantClass; ?>" title="ETA...">
                                <span class="badge-label">In costruzione<?php echo $isUpgrade ? ' (upgrade)' : ''; ?></span>
                                <span class="construction-timer"
                                      data-ends="<?php echo $endsTs; ?>"
                                      <?php if ($startTs): ?>data-starts="<?php echo $startTs; ?>"<?php endif; ?>></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($isUnderConstruction): ?>
                            <!-- Thumb con progress ring -->
                            <div class="building-thumb in-progress <?php echo $variantClass; ?><?php echo $startTs ? '' : ' indeterminate'; ?>"
                                 <?php if ($startTs): ?>data-starts="<?php echo $startTs; ?>"<?php endif; ?>
                                 data-ends="<?php echo $endsTs; ?>">
                                <svg class="build-ring" viewBox="0 0 100 100" aria-hidden="true">
                                    <circle class="build-ring-track"   cx="50" cy="50" r="46"></circle>
                                    <circle class="build-ring-progress" cx="50" cy="50" r="46"></circle>
                                </svg>
                                <img src="assets/images/buildings/<?php echo strtolower($building['type']); ?>.png"
                                     alt="<?php echo htmlspecialchars($building['type']); ?>">
                            </div>
                        <?php else: ?>
                            <div class="building-thumb">
                                <img src="assets/images/buildings/<?php echo strtolower($building['type']); ?>.png"
                                     alt="<?php echo htmlspecialchars($building['type']); ?>">
                            </div>
                        <?php endif; ?>

                        <h3><?php echo htmlspecialchars($building['type']); ?></h3>
                        <p>Livello: <?php echo (int)$building['level']; ?></p>

                        <p>
                            Stato:
                            <span class="status-text"><?php echo $isUnderConstruction ? 'In costruzione' : 'Completato'; ?></span>
                        </p>

                        <div class="building-actions">
                            <?php if ($isUnderConstruction): ?>
                                <button class="cancel-build" data-id="<?php echo (int)$building['id']; ?>">Annulla</button>
                                <button class="upgrade-btn" data-id="<?php echo (int)$building['id']; ?>" disabled>Potenzia</button>
                                <button class="manage-btn"  data-id="<?php echo (int)$building['id']; ?>" disabled>Gestisci</button>
                            <?php else: ?>
                                <button class="upgrade-btn" data-id="<?php echo (int)$building['id']; ?>">Potenzia</button>
                                <button class="manage-btn"  data-id="<?php echo (int)$building['id']; ?>">Gestisci</button>
                                <button class="demolish-build" data-id="<?php echo (int)$building['id']; ?>">Demolisci</button>
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

            <!-- Aggiungi qui altre opzioni -->
        </div>
    </div>
</div>

<?php include_once 'templates/footer.php'; ?>

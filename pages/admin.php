<?php
// pages/admin.php
$auth = \Ironhaven\Core\Auth::getInstance();
$player = $auth->getCurrentUser();

// Verifica accesso admin
if (!$player['is_admin']) {
    header('Location: index.php');
    exit;
}

$db = \Ironhaven\Core\Database::getInstance();

// Ottieni statistiche generali
$stats = [
    'players' => $db->fetch("SELECT COUNT(*) as count FROM players")['count'],
    'settlements' => $db->fetch("SELECT COUNT(*) as count FROM settlements")['count'],
    'buildings' => $db->fetch("SELECT COUNT(*) as count FROM buildings")['count'],
    'missions_completed' => $db->fetch("SELECT COUNT(*) as count FROM player_missions WHERE status = 'completed'")['count']
];

// Ottieni ultimi giocatori registrati
$latestPlayers = $db->fetchAll(
    "SELECT * FROM players ORDER BY created_at DESC LIMIT 10"
);

// Ottieni ultime missioni completate
$latestMissions = $db->fetchAll(
    "SELECT pm.*, p.username, m.title, m.difficulty 
     FROM player_missions pm
     JOIN players p ON pm.player_id = p.id
     JOIN missions m ON pm.mission_id = m.id
     WHERE pm.status = 'completed'
     ORDER BY pm.ends_at DESC
     LIMIT 10"
);

// Ricerca giocatore se richiesto
$searchResults = [];
$searchTerm = $_GET['search'] ?? '';
if (!empty($searchTerm)) {
    $searchResults = $db->fetchAll(
        "SELECT * FROM players 
         WHERE username LIKE ? OR email LIKE ?
         ORDER BY username ASC",
        ['%' . $searchTerm . '%', '%' . $searchTerm . '%']
    );
}

$pageStylesheet = 'admin';
$pageScript = 'admin';
include 'templates/header.php';
?>

<div class="admin-container">
    <h2>Pannello di Amministrazione</h2>
    
    <div class="admin-header">
        <div class="search-box">
            <form method="get" action="">
                <input type="text" name="search" placeholder="Cerca giocatore..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit" class="btn">Cerca</button>
            </form>
        </div>
        <div class="admin-actions">
            <a href="index.php?page=admin_logs" class="btn">Log di Gioco</a>
            <a href="index.php?page=admin_missions" class="btn">Gestione Missioni</a>
        </div>
    </div>
    
    <div class="stats-dashboard">
        <div class="stat-card">
            <h3>Giocatori</h3>
            <div class="stat-value"><?php echo number_format($stats['players']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Insediamenti</h3>
            <div class="stat-value"><?php echo number_format($stats['settlements']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Edifici</h3>
            <div class="stat-value"><?php echo number_format($stats['buildings']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Missioni Completate</h3>
            <div class="stat-value"><?php echo number_format($stats['missions_completed']); ?></div>
        </div>
    </div>
    
    <!-- Sezione Debug -->
<!-- Sostituisci la sezione debug con questo HTML -->
<!-- Sezione Debug -->
<div class="admin-section debug-section">
    <h3>Strumenti Debug</h3>
    <div class="debug-tools">
        <!-- Card Edifici -->
        <div class="debug-card">
            <h4>Edifici</h4>
            <p>Completa immediatamente tutti gli edifici in costruzione</p>
            <button id="complete-buildings" class="btn debug-btn">Completa Edifici</button>
        </div>
        
        <!-- Card Risorse Avanzata -->
        <div class="debug-card resource-manager">
            <h4>Gestione Risorse</h4>
            <p>Aggiungi o rimuovi risorse dal tuo insediamento</p>
            <div class="debug-resource-controls">
                <input type="number" id="resource-amount" placeholder="Quantità" value="1000" min="1">
                <select id="resource-type">
                    <option value="all">Tutte le risorse</option>
                    <option value="wood">Legno</option>
                    <option value="stone">Pietra</option>
                    <option value="food">Cibo</option>
                    <option value="water">Acqua</option>
                    <option value="iron">Ferro</option>
                    <option value="gold">Oro</option>
                </select>
                <div style="display: flex; gap: 0.5rem;">
                    <button id="add-resources" class="btn debug-btn" style="flex: 1;">Aggiungi</button>
                    <button id="remove-resources" class="btn debug-btn danger" style="flex: 1;">Rimuovi</button>
                </div>
            </div>
        </div>
        
        <!-- Card Tempo -->
        <div class="debug-card">
            <h4>Tempo</h4>
            <p>Avanza il tempo del gioco per test</p>
            <div class="debug-time-controls">
                <input type="number" id="advance-hours" placeholder="Ore" value="1" min="0.1" step="0.1">
                <button id="advance-time" class="btn debug-btn">Avanza Tempo</button>
            </div>
        </div>
        
        <!-- Card Giocatore Specifico (a larghezza intera) -->
        <div class="debug-card full-width">
            <h4>Gestione Giocatore Specifico</h4>
            <p>Gestisci lo stato di un giocatore specifico</p>
            <div class="debug-player-controls">
                <input type="text" id="debug-player-search" placeholder="Nome giocatore o email">
                <button id="find-player" class="btn">
                    <span class="status-indicator info"></span>Trova
                </button>
            </div>
            
            <div id="debug-player-info" class="debug-player-info" style="display:none;">
                <!-- Informazioni giocatore verranno inserite qui -->
            </div>
            
            <div id="debug-player-actions" style="display:none; margin-top: 1rem;">
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <button id="complete-all-player-buildings" class="btn debug-btn">Completa Edifici</button>
                    <button id="reset-player-resources" class="btn debug-btn danger">Reset Risorse</button>
                </div>
                
                <!-- Sezione rimozione edifici -->
                <div id="player-buildings-manager" style="margin-top: 1.5rem;">
                    <h5 style="color: var(--primary-color); margin-bottom: 0.5rem;">Gestione Edifici</h5>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                        <label>
                            <input type="checkbox" id="select-all-buildings"> 
                            Seleziona tutti gli edifici
                        </label>
                        <div id="player-buildings-list" class="buildings-checkbox-container">
                            <!-- Lista edifici verrà caricata dinamicamente -->
                        </div>
                    </div>
                    <button id="remove-selected-buildings" class="btn debug-btn danger">
                        Rimuovi Edifici Selezionati
                    </button>
                </div>
                
                <!-- Sezione rimozione risorse specifiche -->
                <div id="player-resources-manager" style="margin-top: 1.5rem;">
                    <h5 style="color: var(--primary-color); margin-bottom: 0.5rem;">Gestione Risorse Specifica</h5>
                    <div class="debug-resource-controls">
                        <input type="number" id="player-resource-amount" placeholder="Quantità" value="1000" min="1">
                        <select id="player-resource-type">
                            <option value="all">Tutte le risorse</option>
                            <option value="wood">Legno</option>
                            <option value="stone">Pietra</option>
                            <option value="food">Cibo</option>
                            <option value="water">Acqua</option>
                            <option value="iron">Ferro</option>
                            <option value="gold">Oro</option>
                        </select>
                        <div style="display: flex; gap: 0.5rem;">
                            <button id="add-player-resources" class="btn debug-btn" style="flex: 1;">Aggiungi</button>
                            <button id="remove-player-resources" class="btn debug-btn danger" style="flex: 1;">Rimuovi</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card Diagnostica Sistema -->
        <div class="debug-card full-width">
            <h4>Diagnostica Sistema</h4>
            <p>Strumenti per diagnosticare e risolvere problemi del gioco</p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button id="sync-all-populations" class="btn debug-btn">
                    <span class="status-indicator warning"></span>Sincronizza Popolazione
                </button>
                <button id="population-health-check" class="btn debug-btn">
                    <span class="status-indicator success"></span>Controllo Salute
                </button>
                <button id="find-sql-errors" class="btn debug-btn">
                    <span class="status-indicator error"></span>Trova Errori SQL
                </button>
            </div>
            <div id="diagnostic-results" style="margin-top: 1rem; display: none;">
                <!-- Risultati diagnostica verranno mostrati qui -->
            </div>
        </div>
    </div>
</div>
    
    <?php if (!empty($searchResults)): ?>
    <div class="admin-section">
        <h3>Risultati Ricerca</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Livello</th>
                    <th>Fama</th>
                    <th>Registrato</th>
                    <th>Ultimo Accesso</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($searchResults as $player): ?>
                <tr>
                    <td><?php echo $player['id']; ?></td>
                    <td><?php echo htmlspecialchars($player['username']); ?></td>
                    <td><?php echo htmlspecialchars($player['email']); ?></td>
                    <td><?php echo $player['level']; ?></td>
                    <td><?php echo number_format($player['fame']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($player['created_at'])); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($player['last_login'])); ?></td>
                    <td class="actions">
                        <button class="btn-small edit-player" data-player-id="<?php echo $player['id']; ?>">Modifica</button>
                        <?php if ($player['id'] != $auth->getUserId()): ?>
                        <button class="btn-small delete-player" data-player-id="<?php echo $player['id']; ?>">Elimina</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="admin-sections">
        <div class="admin-section">
            <h3>Ultimi Giocatori Registrati</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Livello</th>
                        <th>Registrato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latestPlayers as $player): ?>
                    <tr>
                        <td><?php echo $player['id']; ?></td>
                        <td><?php echo htmlspecialchars($player['username']); ?></td>
                        <td><?php echo $player['level']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($player['created_at'])); ?></td>
                        <td class="actions">
                            <button class="btn-small edit-player" data-player-id="<?php echo $player['id']; ?>">Modifica</button>
                            <?php if ($player['id'] != $auth->getUserId()): ?>
                            <button class="btn-small delete-player" data-player-id="<?php echo $player['id']; ?>">Elimina</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="admin-section">
            <h3>Ultime Missioni Completate</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Giocatore</th>
                        <th>Missione</th>
                        <th>Difficoltà</th>
                        <th>Completata</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latestMissions as $mission): ?>
                    <tr>
                        <td><?php echo $mission['id']; ?></td>
                        <td><?php echo htmlspecialchars($mission['username']); ?></td>
                        <td><?php echo htmlspecialchars($mission['title']); ?></td>
                        <td>
                            <span class="mission-difficulty <?php echo strtolower($mission['difficulty']); ?>">
                                <?php echo ucfirst(strtolower($mission['difficulty'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($mission['ends_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal modifica giocatore -->
<div id="edit-player-modal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Modifica Giocatore</h2>
        
        <form id="edit-player-form">
            <input type="hidden" id="player-id" name="player_id">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="level">Livello</label>
                <input type="number" id="level" name="level" min="1" max="50" required>
            </div>
            
            <div class="form-group">
                <label for="experience">Esperienza</label>
                <input type="number" id="experience" name="experience" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="fame">Fama</label>
                <input type="number" id="fame" name="fame" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="is-admin">Amministratore</label>
                <select id="is-admin" name="is_admin">
                    <option value="0">No</option>
                    <option value="1">Sì</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn primary">Salva Modifiche</button>
                <button type="button" class="btn close-modal">Annulla</button>
            </div>
            
            <div class="error-message" id="edit-error"></div>
        </form>
    </div>
</div>

<!-- Modal conferma eliminazione -->
<div id="delete-confirm-modal" class="modal hidden">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Conferma Eliminazione</h2>
        
        <p>Sei sicuro di voler eliminare questo giocatore? Questa azione è irreversibile.</p>
        
        <div class="form-actions">
            <button id="confirm-delete" class="btn error" data-player-id="">Elimina</button>
            <button class="btn close-modal">Annulla</button>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
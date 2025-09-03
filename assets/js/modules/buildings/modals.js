/**
 * Gestione dei modal per gli edifici
 */

/**
 * Chiude tutti i modal aperti
 */
function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.add('hidden');
    });
}

/**
 * Mostra il modal di potenziamento edificio
 */
function showUpgradeModal(buildingId) {
    // Trova l'edificio selezionato
    const building = window.buildingsPageData.buildings.find(b => b.id == buildingId);
    if (!building) return;
    
    window.buildingsPageData.selectedBuilding = building;
    
    // Calcola costi potenziamento
    const upgradeCosts = window.getUpgradeCosts(building.type, building.level + 1);
    
    // Trova o crea il modal
    let modal = document.getElementById('upgrade-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'upgrade-modal';
        modal.className = 'modal';
        
        modal.innerHTML = `
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h2>Potenzia <span id="upgrade-building-name"></span></h2>
                
                <div class="building-info">
                    <img id="upgrade-building-image" src="" alt="Immagine Edificio">
                    <div>
                        <p>Livello attuale: <span id="current-level"></span></p>
                        <p>Livello successivo: <span id="next-level"></span></p>
                    </div>
                </div>
                
                <div class="upgrade-costs">
                    <h3>Costi di Potenziamento</h3>
                    <ul id="upgrade-costs-list"></ul>
                </div>
                
                <div class="upgrade-benefits">
                    <h3>Benefici al livello successivo</h3>
                    <ul id="upgrade-benefits-list"></ul>
                </div>
                
                <div class="construction-time">
                    <p>Tempo di costruzione: <span id="upgrade-time"></span></p>
                </div>
                
                <button id="start-upgrade">Avvia Potenziamento</button>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Aggiungi evento al pulsante di potenziamento
        document.getElementById('start-upgrade').addEventListener('click', window.startUpgrade);
        
        // Aggiungi evento al pulsante di chiusura
        modal.querySelector('.close-modal').addEventListener('click', () => {
            closeAllModals();
        });
    }
    
    // Aggiorna contenuto del modal
    document.getElementById('upgrade-building-name').textContent = window.getBuildingName(building.type);
    document.getElementById('upgrade-building-image').src = `assets/images/buildings/${building.type}.png`;
    document.getElementById('current-level').textContent = building.level;
    document.getElementById('next-level').textContent = building.level + 1;
    
    // Costi di potenziamento
    let costsHtml = '';
    let canAfford = true;
    
    for (const [resource, amount] of Object.entries(upgradeCosts)) {
        const hasEnough = window.buildingsPageData.resources[resource] >= amount;
        if (!hasEnough) canAfford = false;
        
        costsHtml += `
            <li class="${hasEnough ? '' : 'insufficient'}">
                <img src="assets/images/resources/${resource}.png" alt="${resource}">
                ${resource}: ${amount} / ${Math.floor(window.buildingsPageData.resources[resource] || 0)}
            </li>
        `;
    }
    
    document.getElementById('upgrade-costs-list').innerHTML = costsHtml;
    
    // Benefici del potenziamento
    let benefitsHtml = window.getBuildingUpgradeBenefits(building.type, building.level + 1);
    document.getElementById('upgrade-benefits-list').innerHTML = benefitsHtml;
    
    // Tempo di costruzione
    const constructionTime = window.getUpgradeTime(building.type, building.level + 1);
    const hours = Math.floor(constructionTime / 3600);
    const minutes = Math.floor((constructionTime % 3600) / 60);
    document.getElementById('upgrade-time').textContent = `${hours}h ${minutes}m`;
    
    // Abilita/disabilita pulsante in base alle risorse
    document.getElementById('start-upgrade').disabled = !canAfford;
    
    // Mostra il modal
    modal.classList.remove('hidden');
}

/**
 * Mostra il modal di gestione edificio
 */
function showManageModal(buildingId) {
    // Trova l'edificio selezionato
    const building = window.buildingsPageData.buildings.find(b => b.id == buildingId);
    if (!building) return;
    
    window.buildingsPageData.selectedBuilding = building;
    
    // Trova o crea il modal
    let modal = document.getElementById('manage-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'manage-modal';
        modal.className = 'modal';
        
        modal.innerHTML = `
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h2>Gestisci <span id="manage-building-name"></span></h2>
                
                <div class="building-info">
                    <img id="manage-building-image" src="" alt="Immagine Edificio">
                    <div>
                        <p>Livello: <span id="building-level"></span></p>
                        <p>Stato: <span id="building-status"></span></p>
                    </div>
                </div>
                
                <div class="building-stats">
                    <h3>Statistiche</h3>
                    <ul id="building-stats-list"></ul>
                </div>
                
                <div class="building-actions-container">
                    <h3>Azioni</h3>
                    <div id="building-actions-buttons">
                        <!-- I pulsanti vengono aggiunti dinamicamente -->
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Aggiungi evento al pulsante di chiusura
        modal.querySelector('.close-modal').addEventListener('click', () => {
            closeAllModals();
        });
    }
    
    // Aggiorna contenuto del modal
    document.getElementById('manage-building-name').textContent = window.getBuildingName(building.type);
    document.getElementById('manage-building-image').src = `assets/images/buildings/${building.type}.png`;
    document.getElementById('building-level').textContent = building.level;
    document.getElementById('building-status').textContent = building.construction_ends ? 'In costruzione' : 'Completato';
    
    // Statistiche dell'edificio
    let statsHtml = window.getBuildingStats(building);
    document.getElementById('building-stats-list').innerHTML = statsHtml;
    
    // Azioni disponibili
    let actionsHtml = '';
    
    // Azioni in base al tipo di edificio
    if (!building.construction_ends) {
        switch (building.type) {
            case 'house':
                actionsHtml += `<button id="assign-workers">Assegna Lavoratori</button>`;
                break;
            case 'farm':
            case 'woodcutter':
            case 'quarry':
            case 'well':
            case 'hunting_lodge':
                actionsHtml += `<button id="collect-resources">Raccogli Risorse</button>`;
                actionsHtml += `<button id="assign-workers">Assegna Lavoratori</button>`;
                break;
        }
    }
    
    // Aggiungi pulsante per demolire l'edificio
    actionsHtml += `<button id="demolish-building" class="danger">Demolisci</button>`;
    
    document.getElementById('building-actions-buttons').innerHTML = actionsHtml;
    
    // Aggiungi eventi ai pulsanti
    const collectButton = document.getElementById('collect-resources');
    if (collectButton) {
        collectButton.addEventListener('click', () => window.collectBuildingResources(building.id));
    }
    
    const assignButton = document.getElementById('assign-workers');
    if (assignButton) {
        assignButton.addEventListener('click', () => window.showAssignWorkersModal(building.id));
    }
    
    const demolishButton = document.getElementById('demolish-building');
    if (demolishButton) {
        demolishButton.addEventListener('click', () => window.confirmDemolishBuilding(building.id));
    }
    
    // Mostra il modal
    modal.classList.remove('hidden');
}

/**
 * Mostra il modal di costruzione edificio
 */
function showBuildingConstructionModal(buildingType) {
    // Implementare in futuro
    window.showNotification('info', 'Funzionalità di costruzione da questa pagina non ancora implementata');
}

// Rendi disponibili globalmente
window.closeAllModals = closeAllModals;
window.showUpgradeModal = showUpgradeModal;
window.showManageModal = showManageModal;
window.showBuildingConstructionModal = showBuildingConstructionModal;
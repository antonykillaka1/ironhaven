/**
 * Interfaccia utente per la gestione degli edifici
 */

/**
 * Aggiorna la visualizzazione degli edifici
 */
function updateBuildingsDisplay() {
    const buildingsGrid = document.querySelector('.buildings-grid');
    if (!buildingsGrid) return;
    
    if (window.buildingsPageData.buildings.length === 0) {
        buildingsGrid.innerHTML = '<p>Non hai ancora costruito nessuna struttura. Inizia a costruire per far crescere il tuo insediamento!</p>';
        return;
    }
    
    let html = '';
    window.buildingsPageData.buildings.forEach(building => {
        html += `
            <div class="building-card" data-id="${building.id}">
                <img src="assets/images/buildings/${building.type.toLowerCase()}.png" alt="${building.type}">
                <h3>${window.getBuildingName(building.type)}</h3>
                <p>Livello: ${building.level}</p>
                <p>Stato: ${building.construction_ends ? 'In costruzione' : 'Completato'}</p>
                <div class="building-actions">
                    <button class="upgrade-btn" data-id="${building.id}" ${building.construction_ends ? 'disabled' : ''}>Potenzia</button>
                    <button class="manage-btn" data-id="${building.id}" ${building.construction_ends ? 'disabled' : ''}>Gestisci</button>
                </div>
            </div>
        `;
    });
    
    buildingsGrid.innerHTML = html;
}

/**
 * Set up event listeners for profile UI elements
 */
function setupProfileUI() {
    // Implementazione di funzioni UI specifiche per il profilo
    console.log('Profile UI initialized');
}

// Rendi disponibili globalmente
window.updateBuildingsDisplay = updateBuildingsDisplay;
window.setupProfileUI = setupProfileUI;
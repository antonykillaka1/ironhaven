/**
 * Gestione dati degli edifici e comunicazione con il server
 */

// Dati degli edifici e dello stato corrente
let buildingsPageData = {
    settlement: null,
    buildings: [],
    resources: {},
    selectedBuilding: null
};

/**
 * Inizializza la gestione degli edifici
 */
function initBuildingManagement() {
    console.log('Inizializzazione gestione edifici...');
    
    // Carica i dati degli edifici
    fetchBuildingsData();
    
    // Inizializza gli eventi per i pulsanti
    initBuildingEvents();
}

/**
 * Carica i dati degli edifici dal server
 */
async function fetchBuildingsData() {
    try {
        const data = await window.apiRequest('get_settlement');
        
        if (data && data.success) {
            // Memorizza i dati
            buildingsPageData.buildings = data.buildings || [];
            buildingsPageData.resources = data.resources || {};
            buildingsPageData.settlement = data.settlement || null;
            
            // Aggiorna la UI
            window.updateBuildingsDisplay();
        } else if (data && data.error) {
            console.error('Errore caricamento dati:', data.error);
            window.showNotification('error', data.error);
        }
    } catch (error) {
        console.error('Errore caricamento dati:', error);
        window.showNotification('error', 'Errore di connessione');
    }
}

/**
 * Inizializza gli eventi per i pulsanti di gestione edifici
 */
function initBuildingEvents() {
    // Pulsanti di potenziamento edifici
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('upgrade-btn')) {
            const buildingId = e.target.dataset.id;
            if (buildingId) {
                window.showUpgradeModal(buildingId);
            }
        }
    });
    
    // Pulsanti di gestione edifici
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('manage-btn')) {
            const buildingId = e.target.dataset.id;
            if (buildingId) {
                window.showManageModal(buildingId);
            }
        }
    });
    
    // Pulsanti di costruzione
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('build-btn')) {
            const buildingType = e.target.dataset.type;
            if (buildingType) {
                window.showBuildingConstructionModal(buildingType);
            }
        }
    });
    
    // Pulsanti di chiusura modal
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            window.closeAllModals();
        });
    });
}

// Rendi disponibili globalmente
window.buildingsPageData = buildingsPageData;
window.initBuildingManagement = initBuildingManagement;
window.fetchBuildingsData = fetchBuildingsData;
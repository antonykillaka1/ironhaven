/**
 * Azioni degli edifici: raccolta risorse, potenziamento, demolizione
 */

/**
 * Avvia il potenziamento dell'edificio selezionato
 */
function startUpgrade() {
    if (!window.buildingsPageData.selectedBuilding) return;
    
    // Richiesta API per potenziamento
    fetch('api.php?action=upgrade_building', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            building_id: window.buildingsPageData.selectedBuilding.id
        })
    })
    .then(response => {
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'index.php'; // Redirect to login
                return null;
            }
            throw new Error(`Server error: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data) return; // Early return for 401 redirect
        
        if (data.success) {
            // Chiudi modal
            window.closeAllModals();
            
            // Aggiorna dati
            window.fetchBuildingsData();
            
            // Mostra notifica di successo
            window.showNotification('success', `Potenziamento di ${window.getBuildingName(window.buildingsPageData.selectedBuilding.type)} avviato`);
        } else {
            window.showNotification('error', data.error || 'Errore durante il potenziamento');
        }
    })
    .catch(error => {
        console.error('Errore durante il potenziamento:', error);
        window.showNotification('error', 'Errore di connessione');
    });
}

/**
 * Raccoglie risorse da un edificio specifico
 */
function collectBuildingResources(buildingId) {
    // Richiesta API per raccolta
    fetch('api.php?action=collect_building_resources', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            building_id: buildingId
        })
    })
    .then(response => {
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'index.php'; // Redirect to login
                return null;
            }
            throw new Error(`Server error: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data) return; // Early return for 401 redirect
        
        if (data.success) {
            // Aggiorna dati
            window.buildingsPageData.resources = data.resources;
            
            // Mostra notifica di successo
            window.showNotification('success', `Risorse raccolte con successo: ${formatResourcesGained(data.collected)}`);
        } else {
            window.showNotification('error', data.error || 'Errore durante la raccolta');
        }
    })
    .catch(error => {
        console.error('Errore durante la raccolta:', error);
        window.showNotification('error', 'Errore di connessione');
    });
}

/**
 * Formatta le risorse guadagnate per la notifica
 */
function formatResourcesGained(resources) {
    let result = [];
    
    for (const [resource, amount] of Object.entries(resources)) {
        if (amount > 0) {
            result.push(`${Math.floor(amount)} ${resource}`);
        }
    }
    
    return result.join(', ');
}

/**
 * Conferma la demolizione di un edificio
 */
function confirmDemolishBuilding(buildingId) {
    if (confirm('Sei sicuro di voler demolire questo edificio? Questa azione non può essere annullata.')) {
        // Richiesta API per demolizione
        fetch('api.php?action=demolish_building', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                building_id: buildingId
            })
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = 'index.php'; // Redirect to login
                    return null;
                }
                throw new Error(`Server error: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data) return; // Early return for 401 redirect
            
            if (data.success) {
                // Chiudi modal
                window.closeAllModals();
                
                // Aggiorna dati
                window.fetchBuildingsData();
                
                // Mostra notifica di successo
                window.showNotification('success', 'Edificio demolito con successo');
            } else {
                window.showNotification('error', data.error || 'Errore durante la demolizione');
            }
        })
        .catch(error => {
            console.error('Errore durante la demolizione:', error);
            window.showNotification('error', 'Errore di connessione');
        });
    }
}

/**
 * Visualizza il modal per assegnare lavoratori
 */
function showAssignWorkersModal(buildingId) {
    // Implementazione futura
    window.showNotification('info', 'Funzionalità non ancora implementata');
}

// Rendi disponibili globalmente
window.startUpgrade = startUpgrade;
window.collectBuildingResources = collectBuildingResources;
window.confirmDemolishBuilding = confirmDemolishBuilding;
window.showAssignWorkersModal = showAssignWorkersModal;
/**
 * Ironhaven - Buildings Management JavaScript
 * Gestisce le interazioni della pagina degli edifici
 */

// Inizializza quando il DOM è pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inizializza le funzioni di gestione degli edifici
    try {
        // Invece di importare, usiamo funzioni globali
        if (typeof window.initBuildingManagement === 'function') {
            window.initBuildingManagement();
        } else {
            console.error('Funzione initBuildingManagement non disponibile globalmente');
            // Implementazione alternativa
            initBasicBuildingFunctions();
        }
    } catch (error) {
        console.error('Errore durante l\'inizializzazione:', error);
    }
});

// Implementazione alternativa di base
function initBasicBuildingFunctions() {
    console.log('Inizializzazione funzioni edifici di base');
    
    // Gestisci click sui pulsanti degli edifici
    document.querySelectorAll('.building-button').forEach(button => {
        button.addEventListener('click', function() {
            const buildingType = this.dataset.type;
            console.log('Selezione edificio:', buildingType);
            
            // Implementa la logica di base qui...
        });
    });
}
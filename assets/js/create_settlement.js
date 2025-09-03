// assets/js/create_settlement.js
document.addEventListener('DOMContentLoaded', function() {
    console.log('Script di creazione insediamento caricato');
    initMap();
    
    // Form creazione insediamento
    const form = document.getElementById('create-settlement-form');
    if (form) {
        console.log('Form trovato, aggiungo event listener');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('settlement-name').value;
            const x = parseInt(document.getElementById('coordinates-x').value);
            const y = parseInt(document.getElementById('coordinates-y').value);
            const errorElement = document.getElementById('settlement-error');
            
            console.log('Tentativo di creazione insediamento:', {name, x, y});
            
            // Validazione
            if (!name) {
                errorElement.textContent = "Inserisci un nome per l'insediamento";
                errorElement.style.display = 'block';
                return;
            }
            
            if (isNaN(x) || isNaN(y)) {
                errorElement.textContent = "Seleziona una posizione sulla mappa";
                errorElement.style.display = 'block';
                return;
            }
            
            // Reset errore
            errorElement.textContent = '';
            errorElement.style.display = 'none';
            
            // Invia richiesta
            fetch('api.php?action=create_settlement', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name, x, y })
            })
            .then(response => {
                console.log('Risposta ricevuta:', response);
                return response.json();
            })
            .then(data => {
                console.log('Dati ricevuti:', data);
                if (data.success) {
                    // Redirect
                    window.location.href = 'index.php';
                } else {
                    errorElement.textContent = data.error || "Errore durante la creazione dell'insediamento";
                    errorElement.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Errore creazione insediamento:', error);
                errorElement.textContent = 'Errore di connessione';
                errorElement.style.display = 'block';
            });
        });
    } else {
        console.error('Form di creazione insediamento non trovato');
    }
});

// Inizializza mappa per selezione posizione
function initMap() {
    const mapContainer = document.getElementById('settlement-map');
    if (!mapContainer) {
        console.error('Container mappa non trovato');
        return;
    }
    
    console.log('Inizializzazione mappa');
    
    // Pulisci contenitore mappa
    mapContainer.innerHTML = '';
    
    // Crea griglia 20x20
    for (let y = 0; y < 20; y++) {
        for (let x = 0; x < 20; x++) {
            const cell = document.createElement('div');
            cell.className = 'map-cell';
            cell.dataset.x = x;
            cell.dataset.y = y;
            
            // Genera tipo terreno casuale
            const terrainType = getRandomTerrainType();
            cell.classList.add('terrain-' + terrainType);
            
            // Listener click
            cell.addEventListener('click', function() {
                // Rimuovi selezione precedente
                document.querySelectorAll('.map-cell.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                
                // Seleziona cella
                this.classList.add('selected');
                
                // Aggiorna coordinate
                document.getElementById('coordinates-x').value = this.dataset.x;
                document.getElementById('coordinates-y').value = this.dataset.y;
                
                console.log('Selezionata posizione:', this.dataset.x, this.dataset.y);
            });
            
            mapContainer.appendChild(cell);
        }
    }
}

// Genera tipo terreno casuale
function getRandomTerrainType() {
    const types = ['plains', 'forest', 'hills', 'water'];
    const weights = [0.5, 0.3, 0.15, 0.05]; // Probabilità
    
    const rand = Math.random();
    let cumulativeWeight = 0;
    
    for (let i = 0; i < types.length; i++) {
        cumulativeWeight += weights[i];
        if (rand < cumulativeWeight) {
            return types[i];
        }
    }
    
    return 'plains'; // Default
}
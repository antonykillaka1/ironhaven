// assets/js/game.js - SISTEMA JAVASCRIPT UNIFICATO E COMPLETO
// Sostituisce tutti i file frammentati e risolve le dipendenze

/**
 * Oggetto globale per gestire lo stato del gioco
 */
window.IronHaven = {
    // Stato del gioco
    state: {
        resources: {},
        buildings: [],
        population: {},
        settlement: {},
        selectedBuildingType: null
    },
    
    // Configurazioni
    config: {
        updateInterval: 30000, // 30 secondi
        apiEndpoint: 'api.php'
    },
    
    // Flag per evitare inizializzazioni multiple
    initialized: false
};

/**
 * Modulo API - Gestione chiamate al server
 */
IronHaven.api = {
    /**
     * Chiamata API generica
     */
    async call(action, data = null, method = 'GET') {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        let url = `${IronHaven.config.apiEndpoint}?action=${action}`;
        
        if (method === 'POST' && data) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const result = await response.json();
            
            if (!result.success) {
                IronHaven.ui.showNotification('error', result.error || 'Errore sconosciuto');
                return null;
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            IronHaven.ui.showNotification('error', 'Errore di comunicazione con il server');
            return null;
        }
    },
    
    /**
     * Ottieni dati insediamento
     */
    async getSettlement() {
        const result = await this.call('get_settlement');
        if (result) {
            IronHaven.state.settlement = result.settlement;
            IronHaven.state.resources = result.resources;
            IronHaven.state.buildings = result.buildings;
            IronHaven.state.population = result.population;
            
            // Aggiorna UI
            IronHaven.ui.updateResourceDisplay();
            IronHaven.ui.updatePopulationDisplay();
            IronHaven.ui.updateProductionDisplay();
            IronHaven.buildings.updateBuildingsGrid();
        }
        return result;
    },
    
    /**
     * Costruisci edificio
     */
    async constructBuilding(type, x = null, y = null) {
        const data = { type };
        if (x !== null) data.x = x;
        if (y !== null) data.y = y;
        
        const result = await this.call('construct_building', data, 'POST');
        if (result) {
            IronHaven.ui.showNotification('success', `Costruzione di ${IronHaven.buildings.getBuildingName(type)} iniziata!`);
            // Ricarica dati insediamento
            await this.getSettlement();
        }
        return result;
    },
    
    /**
     * Raccogli tutte le risorse
     */
    async collectAllResources() {
        const result = await this.call('collect_all_resources', null, 'POST');
        if (result) {
            if (result.resources) {
                IronHaven.state.resources = result.resources;
                IronHaven.ui.updateResourceDisplay();
                IronHaven.ui.showNotification('success', 'Risorse raccolte!');
            } else {
                IronHaven.ui.showNotification('info', result.message || 'Nessuna risorsa da raccogliere');
            }
        }
        return result;
    },
    
    /**
     * Controlla stato costruzioni
     */
    async checkConstructionStatus() {
        const result = await this.call('check_construction_status', null, 'POST');
        if (result && result.completed_buildings && result.completed_buildings.length > 0) {
            IronHaven.ui.showNotification('success', `${result.completed_buildings.length} costruzioni completate!`);
            await this.getSettlement(); // Ricarica dati
        }
        return result;
    }
};

/**
 * Modulo UI - Gestione interfaccia utente
 */
IronHaven.ui = {
    /**
     * Mostra notifica all'utente
     */
    showNotification(type, message) {
        let container = document.getElementById('notifications-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        container.appendChild(notification);
        
        // Auto-rimuovi dopo 5 secondi
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 500);
        }, 5000);
    },
    
    /**
     * Aggiorna visualizzazione risorse
     */
    updateResourceDisplay() {
        const resources = IronHaven.state.resources;
        if (!resources) return;
        
        Object.keys(resources).forEach(resourceType => {
            const element = document.getElementById(`resource-${resourceType}`);
            if (element) {
                element.textContent = Math.floor(resources[resourceType]).toLocaleString();
            }
        });
    },
    
    /**
     * Aggiorna visualizzazione popolazione
     */
    updatePopulationDisplay() {
        const population = IronHaven.state.population;
        if (!population) return;
        
        const totalElement = document.getElementById('population');
        if (totalElement) {
            totalElement.textContent = population.total || 0;
        }
        
        const availableElement = document.getElementById('available-population');
        if (availableElement) {
            availableElement.textContent = population.available || 0;
        }
        
        const satisfactionElement = document.getElementById('satisfaction');
        if (satisfactionElement) {
            satisfactionElement.textContent = Math.round(population.satisfaction || 100);
        }
    },
	/**
 * Aggiorna visualizzazione produzione oraria
 */
updateProductionDisplay() {
    const buildings = IronHaven.state.buildings;
    if (!buildings) return;
    
    const production = { wood: 0, stone: 0, food: 0, water: 0, iron: 0, gold: 0 };
    
    buildings.forEach(building => {
        if (building.construction_ends) return;
        const level = building.level;
        switch(building.type) {
            case 'farm': production.food += 15 + (10 * (level - 1)); break;
            case 'woodcutter': production.wood += 20 + (12 * (level - 1)); break;
            case 'quarry': production.stone += 15 + (9 * (level - 1)); break;
            case 'well': production.water += 10 + (7 * (level - 1)); break;
        }
    });
    
    Object.keys(production).forEach(resource => {
        const element = document.getElementById(`${resource}-production`);
        if (element) {
            element.textContent = production[resource] > 0 ? `+${production[resource]}` : '+0';
            if (production[resource] > 0) {
                element.style.color = '#4caf50';
                element.style.fontWeight = 'bold';
            }
        }
    });
},
    
    /**
     * Inizializza eventi UI
     */
    initializeEvents() {
        // Bottone raccogli risorse
        const collectBtn = document.getElementById('collect-all');
        if (collectBtn) {
            collectBtn.addEventListener('click', () => {
                IronHaven.api.collectAllResources();
            });
        }
        
        // Bottone missioni
        const missionsBtn = document.getElementById('check-missions');
        if (missionsBtn) {
            missionsBtn.addEventListener('click', () => {
                window.location.href = 'index.php?page=missions';
            });
        }
        
        // Eventi per la costruzione edifici
        this.initializeBuildingEvents();
        
        // Chiusura modal con escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    },
    
    /**
     * Inizializza eventi per edifici
     */
    initializeBuildingEvents() {
        // Click su edifici disponibili
        document.addEventListener('click', (e) => {
            // Se clicca su un elemento della lista edifici
            if (e.target.closest('.building-item')) {
                const buildingItem = e.target.closest('.building-item');
                const buildingType = buildingItem.dataset.type;
                if (buildingType) {
                    IronHaven.buildings.showBuildingModal(buildingType);
                }
            }
            
            // Se clicca su una cella della griglia
            if (e.target.classList.contains('city-cell')) {
                const x = parseInt(e.target.dataset.x);
                const y = parseInt(e.target.dataset.y);
                if (IronHaven.state.selectedBuildingType) {
                    IronHaven.buildings.constructBuildingAt(IronHaven.state.selectedBuildingType, x, y);
                }
            }
        });
        
        // Gestione modal costruzione
        const modal = document.getElementById('building-construction-modal');
        if (modal) {
            const closeButtons = modal.querySelectorAll('.close-modal');
            closeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.closeModal(modal);
                });
            });
            
            const constructBtn = document.getElementById('start-construction');
            if (constructBtn) {
                constructBtn.addEventListener('click', () => {
                    const buildingType = constructBtn.dataset.buildingType;
                    if (buildingType) {
                        IronHaven.api.constructBuilding(buildingType);
                        this.closeModal(modal);
                    }
                });
            }
            
            // Chiudi modal cliccando fuori
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal(modal);
                }
            });
        }
    },
    
    /**
     * Chiudi modal specifico
     */
    closeModal(modal) {
        modal.classList.add('hidden');
        IronHaven.state.selectedBuildingType = null;
    },
    
    /**
     * Chiudi tutti i modal
     */
    closeAllModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.classList.add('hidden');
        });
        IronHaven.state.selectedBuildingType = null;
    }
};

/**
 * Modulo Buildings - Gestione edifici
 */
IronHaven.buildings = {
    // Configurazione edifici
    buildingConfig: {
        house: {
            name: 'Casa',
            description: 'Fornisce alloggio per i tuoi coloni (+5 popolazione)',
            costs: { wood: 50, stone: 30 },
            constructionTime: 300, // 5 minuti
            benefits: ['Popolazione: +5 abitanti']
        },
        farm: {
            name: 'Fattoria',
            description: 'Produce cibo per il tuo insediamento',
            costs: { wood: 40, stone: 20 },
            constructionTime: 240,
            benefits: ['Cibo: +10/ora']
        },
        woodcutter: {
            name: 'Boscaiolo',
            description: 'Produce legno per la costruzione',
            costs: { wood: 30, stone: 10 },
            constructionTime: 180,
            benefits: ['Legno: +8/ora']
        },
        quarry: {
            name: 'Cava di Pietra',
            description: 'Estrae pietra dalle montagne',
            costs: { wood: 20, stone: 30 },
            constructionTime: 300,
            benefits: ['Pietra: +6/ora']
        },
        well: {
            name: 'Pozzo',
            description: 'Fornisce acqua pulita per l\'insediamento',
            costs: { wood: 25, stone: 35 },
            constructionTime: 360,
            benefits: ['Acqua: +12/ora']
        }
    },
    
    /**
     * Ottiene nome edificio tradotto
     */
    getBuildingName(type) {
        return this.buildingConfig[type]?.name || type;
    },
    
    /**
     * Mostra modal per costruzione edificio
     */
    showBuildingModal(buildingType) {
        const config = this.buildingConfig[buildingType];
        if (!config) return;
        
        const modal = document.getElementById('building-construction-modal');
        if (!modal) return;
        
        // Aggiorna contenuto modal
        const nameElement = document.getElementById('building-name');
        if (nameElement) nameElement.textContent = config.name;
        
        const descElement = document.getElementById('building-description');
        if (descElement) descElement.textContent = config.description;
        
        const imageElement = document.getElementById('building-image');
        if (imageElement) {
            imageElement.src = `assets/images/buildings/${buildingType}.png`;
            imageElement.alt = config.name;
        }
        
        // Aggiorna costi
        this.updateCostsList(config.costs);
        
        // Aggiorna benefici
        this.updateBenefitsList(config.benefits);
        
        // Aggiorna tempo costruzione
        const timeElement = document.getElementById('construction-time');
        if (timeElement) {
            const minutes = Math.floor(config.constructionTime / 60);
            const seconds = config.constructionTime % 60;
            timeElement.textContent = `${minutes}m ${seconds}s`;
        }
        
        // Imposta tipo edificio sul bottone
        const constructBtn = document.getElementById('start-construction');
        if (constructBtn) {
            constructBtn.dataset.buildingType = buildingType;
            
            // Verifica se ha abbastanza risorse
            const canAfford = this.canAffordBuilding(config.costs);
            constructBtn.disabled = !canAfford;
            constructBtn.textContent = canAfford ? 'Costruisci' : 'Risorse insufficienti';
        }
        
        // Mostra modal
        modal.classList.remove('hidden');
    },
    
    /**
     * Aggiorna lista costi nel modal
     */
    updateCostsList(costs) {
        const costsList = document.getElementById('building-costs-list');
        if (!costsList) return;
        
        costsList.innerHTML = '';
        
        Object.keys(costs).forEach(resource => {
            const amount = costs[resource];
            const current = IronHaven.state.resources[resource] || 0;
            const sufficient = current >= amount;
            
            const li = document.createElement('li');
            li.className = sufficient ? '' : 'insufficient';
            li.innerHTML = `
                <img src="assets/images/resources/${resource}.png" alt="${resource}">
                ${amount} ${resource} (hai: ${current})
            `;
            
            costsList.appendChild(li);
        });
    },
    
    /**
     * Aggiorna lista benefici nel modal
     */
    updateBenefitsList(benefits) {
        const benefitsList = document.getElementById('building-benefits-list');
        if (!benefitsList) return;
        
        benefitsList.innerHTML = '';
        
        benefits.forEach(benefit => {
            const li = document.createElement('li');
            li.textContent = benefit;
            benefitsList.appendChild(li);
        });
    },
    
    /**
     * Verifica se può permettersi l'edificio
     */
    canAffordBuilding(costs) {
        return Object.keys(costs).every(resource => {
            const required = costs[resource];
            const available = IronHaven.state.resources[resource] || 0;
            return available >= required;
        });
    },
    
    /**
     * Aggiorna lista edifici disponibili nella sidebar
     */
    updateBuildingsList() {
        const container = document.getElementById('available-buildings');
        if (!container) return;
        
        container.innerHTML = '';
        
        Object.keys(this.buildingConfig).forEach(buildingType => {
            const config = this.buildingConfig[buildingType];
            
            const buildingItem = document.createElement('div');
            buildingItem.className = 'building-item';
            buildingItem.dataset.type = buildingType;
            
            buildingItem.innerHTML = `
                <div class="building-icon" style="background-image: url('assets/images/buildings/${buildingType}.png')"></div>
                <div class="building-info">
                    <h4>${config.name}</h4>
                    <p>${config.description}</p>
                </div>
            `;
            
            container.appendChild(buildingItem);
        });
    },
    
    /**
     * Aggiorna griglia edifici nella mappa
     */
    updateBuildingsGrid() {
        const grid = document.getElementById('city-grid');
        if (!grid) return;
        
        // Pulisci griglia esistente
        grid.innerHTML = '';
        
        // Crea griglia 15x15
        for (let y = 0; y < 15; y++) {
            for (let x = 0; x < 15; x++) {
                const cell = document.createElement('div');
                cell.className = 'city-cell';
                cell.dataset.x = x;
                cell.dataset.y = y;
                
                // Trova edificio in questa posizione
                const building = IronHaven.state.buildings.find(b => 
                    b.position_x == x && b.position_y == y
                );
                
                if (building) {
                    cell.classList.add('building');
                    if (building.construction_ends) {
                        cell.classList.add('under-construction');
                        cell.innerHTML = `<div class="construction-indicator">In costruzione...</div>`;
                    } else {
                        cell.innerHTML = `
                            <div class="building-icon" style="background-image: url('assets/images/buildings/${building.type}.png')"></div>
                            <div class="building-level">L${building.level}</div>
                        `;
                    }
                }
                
                grid.appendChild(cell);
            }
        }
    },
    
    /**
     * Costruisce edificio in posizione specifica
     */
    async constructBuildingAt(type, x, y) {
        const result = await IronHaven.api.constructBuilding(type, x, y);
        if (result) {
            IronHaven.state.selectedBuildingType = null;
        }
        return result;
    }
};

/**
 * Modulo Resources - Gestione risorse
 */
IronHaven.resources = {
    /**
     * Avvia monitoraggio automatico risorse
     */
    startAutoUpdate() {
        // Controlla costruzioni completate ogni 30 secondi
        setInterval(async () => {
            await IronHaven.api.checkConstructionStatus();
        }, 30000);
        
        // Aggiorna dati insediamento ogni 60 secondi
        setInterval(async () => {
            await IronHaven.api.getSettlement();
        }, 60000);
    }
};

/**
 * Inizializzazione quando il DOM è pronto
 */
document.addEventListener('DOMContentLoaded', function() {
    // Evita inizializzazioni multiple
    if (IronHaven.initialized) return;
    
    console.log('Iron Haven: Initializing game systems...');
    
    try {
        // Inizializza UI
        IronHaven.ui.initializeEvents();
        
        // Carica lista edifici
        IronHaven.buildings.updateBuildingsList();
		
		if (!IronHaven.ui || typeof IronHaven.ui.updateProductionDisplay !== 'function') {
  IronHaven.ui = IronHaven.ui || {};
  IronHaven.ui.updateProductionDisplay = function() {
    const buildings = IronHaven.state.buildings || [];
    const production = { wood: 0, stone: 0, food: 0, water: 0, iron: 0, gold: 0 };

    buildings.forEach(b => {
      if (b.construction_ends) return;
      const level = b.level;
      switch (b.type) {
        case 'farm':       production.food  += 15 + 10 * (level - 1); break;
        case 'woodcutter': production.wood  += 20 + 12 * (level - 1); break;
        case 'quarry':     production.stone += 15 +  9 * (level - 1); break;
        case 'well':       production.water += 10 +  7 * (level - 1); break;
      }
    });

    Object.keys(production).forEach(res => {
      const el = document.getElementById(`${res}-production`);
      if (!el) return;
      el.textContent = production[res] > 0 ? `+${production[res]}` : '+0';
      if (production[res] > 0) { el.style.color = '#4caf50'; el.style.fontWeight = 'bold'; }
    });
  };
}

        
        // Carica dati iniziali
        IronHaven.api.getSettlement();
        
        // Avvia monitoraggio automatico
        IronHaven.resources.startAutoUpdate();
        
        // Marca come inizializzato
        IronHaven.initialized = true;
        
        console.log('Iron Haven: Game systems initialized successfully');
    } catch (error) {
        console.error('Iron Haven: Initialization error:', error);
        IronHaven.ui.showNotification('error', 'Errore durante l\'inizializzazione del gioco');
    }
});

// Compatibilità con codice esistente
window.gameData = IronHaven.state;
window.updateGameData = function(newData) {
    Object.assign(IronHaven.state, newData);
};
window.showNotification = IronHaven.ui.showNotification;
window.collectAllResources = IronHaven.api.collectAllResources;

// Funzioni di compatibilità per il sistema buildings
window.initBuildingManagement = function() {
    // Già inizializzato nel DOMContentLoaded
    return true;
};

// Funzioni globali per debug/compatibilità
window.IronHaven = IronHaven;
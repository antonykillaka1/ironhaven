// assets/js/admin.js - Versione Modulo ES6

// Log di inizio caricamento
console.log('===== ADMIN.JS START =====');
console.log('Document readyState:', document.readyState);

// Funzione showNotification
function showNotification(type, message) {
    console.log('Showing notification:', type, message);
    
    let container = document.querySelector('.notifications-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notifications-container';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '10000';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.marginBottom = '10px';
    notification.style.padding = '10px 15px';
    notification.style.borderRadius = '4px';
    notification.style.color = 'white';
    notification.style.minWidth = '250px';
    notification.style.maxWidth = '400px';
    notification.style.wordWrap = 'break-word';
    
    switch(type) {
        case 'success':
            notification.style.backgroundColor = '#27ae60';
            break;
        case 'error':
            notification.style.backgroundColor = '#e74c3c';
            break;
        case 'warning':
            notification.style.backgroundColor = '#f39c12';
            break;
        default:
            notification.style.backgroundColor = '#34495e';
    }
    
    notification.textContent = message;
    container.appendChild(notification);
    
    // Animazione fade in
    notification.style.opacity = '0';
    notification.style.transition = 'opacity 0.3s';
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 10);
    
    // Rimuovi dopo 5 secondi con fade out
    setTimeout(() => {
        notification.style.transition = 'opacity 0.5s';
        notification.style.opacity = '0';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
            if (container.children.length === 0) {
                container.remove();
            }
        }, 500);
    }, 5000);
}

// Funzione per testare se i pulsanti esistono
function testButtons() {
    console.log('=== Testing buttons ===');
    const buttons = {
        'remove-resources': document.getElementById('remove-resources'),
        'sync-all-populations': document.getElementById('sync-all-populations'),
        'population-health-check': document.getElementById('population-health-check'),
        'find-sql-errors': document.getElementById('find-sql-errors')
    };
    
    for (const [name, button] of Object.entries(buttons)) {
        console.log(`${name}:`, button ? 'FOUND' : 'NOT FOUND');
    }
}

// Test immediato
testButtons();

// Test dopo DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('===== DOMContentLoaded FIRED =====');
    testButtons();
    
    // Pulsante rimuovi con funzionalità completa
    const removeBtn = document.getElementById('remove-resources');
    if (removeBtn) {
        console.log('Adding listener to remove button...');
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Remove button clicked - working!');
            
            const amount = parseInt(document.getElementById('resource-amount').value) || 1000;
            const resourceType = document.getElementById('resource-type').value;
            
            console.log('Amount:', amount, 'Type:', resourceType);
            
            if (confirm('Sei sicuro di voler rimuovere le risorse?')) {
                removeBtn.disabled = true;
                
                fetch('api.php?action=debug_remove_resources', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        amount: amount,
                        resourceType: resourceType
                    })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    removeBtn.disabled = false;
                    
                    if (data.success) {
                        showNotification('success', data.message);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showNotification('error', data.error || 'Errore durante la rimozione risorse');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    removeBtn.disabled = false;
                    showNotification('error', 'Errore di connessione: ' + error.message);
                });
            }
        });
        console.log('Remove button listener added successfully');
    } else {
        console.error('Remove button not found in DOMContentLoaded!');
    }
    
    // Pulsante sync population
    const syncBtn = document.getElementById('sync-all-populations');
    if (syncBtn) {
        console.log('Adding listener to sync button...');
        syncBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Sync button clicked - working!');
            
            if (!confirm('Vuoi sincronizzare la popolazione di tutti gli insediamenti?')) {
                return;
            }
            
            try {
                syncBtn.disabled = true;
                const response = await fetch('api.php?action=sync_all_populations', {
                    method: 'POST'
                });
                
                console.log('Sync response status:', response.status);
                const data = await response.json();
                console.log('Sync response data:', data);
                
                const resultsDiv = document.getElementById('diagnostic-results');
                if (resultsDiv) {
                    resultsDiv.style.display = 'block';
                    resultsDiv.innerHTML = `
                        <h5>Risultati Sincronizzazione</h5>
                        <p>Insediamenti totali: ${data.settlements_total}</p>
                        <p>Insediamenti aggiornati: ${data.settlements_updated}</p>
                        <p>Errori: ${data.errors}</p>
                    `;
                }
                
                showNotification('success', 'Sincronizzazione completata: ' + data.settlements_updated + ' insediamenti aggiornati');
            } catch (error) {
                console.error('Sync error:', error);
                showNotification('error', 'Errore durante la sincronizzazione: ' + error.message);
            } finally {
                syncBtn.disabled = false;
            }
        });
        console.log('Sync button listener added successfully');
    } else {
        console.error('Sync button not found in DOMContentLoaded!');
    }
    
    // Pulsante health check
    const healthBtn = document.getElementById('population-health-check');
    if (healthBtn) {
        console.log('Adding listener to health button...');
        healthBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Health button clicked - working!');
            
            try {
                healthBtn.disabled = true;
                const response = await fetch('api.php?action=population_health_check', {
                    method: 'POST'
                });
                
                console.log('Health response status:', response.status);
                const data = await response.json();
                console.log('Health response data:', data);
                
                const resultsDiv = document.getElementById('diagnostic-results');
                if (resultsDiv) {
                    resultsDiv.style.display = 'block';
                    
                    let html = `
                        <h5>Controllo Salute Popolazione</h5>
                        <p>Insediamenti totali: ${data.total_settlements}</p>
                        <p>Salutari: ${data.healthy}</p>
                        <p>Con problemi: ${data.issues}</p>
                    `;
                    
                    if (data.issues > 0) {
                        html += '<h6>Insediamenti con problemi:</h6><ul>';
                        data.settlement_details.forEach(settlement => {
                            if (!settlement.is_valid) {
                                html += `<li>${settlement.settlement_name} (ID: ${settlement.settlement_id}): 
                                        Popolazione attuale: ${settlement.current_population}, 
                                        Prevista: ${settlement.expected_population}, 
                                        Discrepanza: ${settlement.discrepancy}</li>`;
                            }
                        });
                        html += '</ul>';
                    }
                    
                    resultsDiv.innerHTML = html;
                }
                
                showNotification('success', 'Controllo salute completato');
            } catch (error) {
                console.error('Health error:', error);
                showNotification('error', 'Errore durante il controllo: ' + error.message);
            } finally {
                healthBtn.disabled = false;
            }
        });
        console.log('Health button listener added successfully');
    } else {
        console.error('Health button not found in DOMContentLoaded!');
    }
    
    // Pulsante SQL errors
    const sqlBtn = document.getElementById('find-sql-errors');
    if (sqlBtn) {
        console.log('Adding listener to SQL button...');
        sqlBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('SQL button clicked - working!');
            
            try {
                sqlBtn.disabled = true;
                const response = await fetch('api.php?action=find_sql_error', {
                    method: 'POST'
                });
                
                console.log('SQL response status:', response.status);
                const data = await response.json();
                console.log('SQL response data:', data);
                
                const resultsDiv = document.getElementById('diagnostic-results');
                if (resultsDiv) {
                    resultsDiv.style.display = 'block';
                    
                    let html = `
                        <h5>Ricerca Errori SQL</h5>
                        <p>Endpoint testati: ${data.total_tested}</p>
                        <p>Errori trovati: ${data.errors_found.length}</p>
                    `;
                    
                    if (data.errors_found.length > 0) {
                        html += '<h6>Errori rilevati:</h6><ul>';
                        data.errors_found.forEach(error => {
                            html += `<li>
                                <strong>Endpoint:</strong> ${error.endpoint}<br>
                                <strong>Errore:</strong> ${error.error}
                            </li>`;
                        });
                        html += '</ul>';
                    } else {
                        html += '<p>Nessun errore SQL trovato!</p>';
                    }
                    
                    resultsDiv.innerHTML = html;
                }
                
                showNotification('success', 'Ricerca errori completata');
            } catch (error) {
                console.error('SQL error:', error);
                showNotification('error', 'Errore durante la ricerca: ' + error.message);
            } finally {
                sqlBtn.disabled = false;
            }
        });
        console.log('SQL button listener added successfully');
    } else {
        console.error('SQL button not found in DOMContentLoaded!');
    }
    
    // === NUOVO CODICE PER I PULSANTI MANCANTI ===
    
    // Pulsante completa edifici
    const completeBuildingsBtn = document.getElementById('complete-buildings');
    if (completeBuildingsBtn) {
        console.log('Adding listener to complete buildings button...');
        completeBuildingsBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Complete buildings button clicked');
            
            if (!confirm('Vuoi completare tutti gli edifici in costruzione?')) {
                return;
            }
            
            try {
                completeBuildingsBtn.disabled = true;
                const response = await fetch('api.php?action=debug_complete_buildings', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('error', data.error || 'Errore durante il completamento edifici');
                }
            } catch (error) {
                console.error('Complete buildings error:', error);
                showNotification('error', 'Errore durante il completamento: ' + error.message);
            } finally {
                completeBuildingsBtn.disabled = false;
            }
        });
        console.log('Complete buildings button listener added successfully');
    } else {
        console.error('Complete buildings button not found!');
    }
    
    // Pulsante aggiungi risorse
    const addResourcesBtn = document.getElementById('add-resources');
    if (addResourcesBtn) {
        console.log('Adding listener to add resources button...');
        addResourcesBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Add resources button clicked');
            
            const amount = parseInt(document.getElementById('resource-amount').value) || 1000;
            const resourceType = document.getElementById('resource-type').value;
            
            if (!confirm('Sei sicuro di voler aggiungere le risorse?')) {
                return;
            }
            
            try {
                addResourcesBtn.disabled = true;
                const response = await fetch('api.php?action=debug_add_resources', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        amount: amount,
                        resourceType: resourceType
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('error', data.error || 'Errore durante l\'aggiunta risorse');
                }
            } catch (error) {
                console.error('Add resources error:', error);
                showNotification('error', 'Errore durante l\'aggiunta: ' + error.message);
            } finally {
                addResourcesBtn.disabled = false;
            }
        });
        console.log('Add resources button listener added successfully');
    } else {
        console.error('Add resources button not found!');
    }
    
    // Pulsante avanza tempo
    const advanceTimeBtn = document.getElementById('advance-time');
    if (advanceTimeBtn) {
        console.log('Adding listener to advance time button...');
        advanceTimeBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Advance time button clicked');
            
            const hours = parseFloat(document.getElementById('advance-hours').value) || 1;
            
            if (!confirm(`Vuoi avanzare il tempo di ${hours} ore?`)) {
                return;
            }
            
            try {
                advanceTimeBtn.disabled = true;
                const response = await fetch('api.php?action=debug_advance_time', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        hours: hours
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('error', data.error || 'Errore durante l\'avanzamento tempo');
                }
            } catch (error) {
                console.error('Advance time error:', error);
                showNotification('error', 'Errore durante l\'avanzamento: ' + error.message);
            } finally {
                advanceTimeBtn.disabled = false;
            }
        });
        console.log('Advance time button listener added successfully');
    } else {
        console.error('Advance time button not found!');
    }
    
    // Pulsante trova giocatore
    const findPlayerBtn = document.getElementById('find-player');
    if (findPlayerBtn) {
        console.log('Adding listener to find player button...');
        findPlayerBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('Find player button clicked');
            
            const username = document.getElementById('debug-player-search').value.trim();
            
            if (!username) {
                showNotification('warning', 'Inserisci un nome giocatore');
                return;
            }
            
            try {
                findPlayerBtn.disabled = true;
                const response = await fetch('api.php?action=debug_find_player', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Mostra le informazioni del giocatore
                    const playerInfo = document.getElementById('debug-player-info');
                    const playerActions = document.getElementById('debug-player-actions');
                    
                    if (playerInfo && playerActions) {
                        playerInfo.innerHTML = `
                            <h5>Giocatore Trovato</h5>
                            <p><strong>ID:</strong> ${data.player.id}</p>
                            <p><strong>Username:</strong> ${data.player.username}</p>
                            <p><strong>Email:</strong> ${data.player.email}</p>
                            <p><strong>Livello:</strong> ${data.player.level}</p>
                            <p><strong>Fama:</strong> ${data.player.fame}</p>
                        `;
                        playerInfo.style.display = 'block';
                        playerActions.style.display = 'block';
                        
                        // Aggiungi l'ID del giocatore agli altri pulsanti
                        document.getElementById('complete-all-player-buildings').dataset.playerId = data.player.id;
                        document.getElementById('reset-player-resources').dataset.playerId = data.player.id;
                        document.getElementById('add-player-resources').dataset.playerId = data.player.id;
                        document.getElementById('remove-player-resources').dataset.playerId = data.player.id;
                        document.getElementById('remove-selected-buildings').dataset.playerId = data.player.id;
                    }
                    
                    showNotification('success', 'Giocatore trovato!');
                } else {
                    showNotification('error', data.error || 'Giocatore non trovato');
                }
            } catch (error) {
                console.error('Find player error:', error);
                showNotification('error', 'Errore durante la ricerca: ' + error.message);
            } finally {
                findPlayerBtn.disabled = false;
            }
        });
        console.log('Find player button listener added successfully');
    } else {
        console.error('Find player button not found!');
    }
    
    // === FINE NUOVO CODICE ===
    
    // === RESTO DELLE FUNZIONALITÀ ADMIN (modifica giocatore, etc.) ===
    
    // Gestione modal
    document.querySelectorAll('.close-modal').forEach(el => {
        el.addEventListener('click', closeModals);
    });
    
    // Modifica giocatore
    document.querySelectorAll('.edit-player').forEach(button => {
        button.addEventListener('click', function() {
            const playerId = this.dataset.playerId;
            
            // Ottieni dati giocatore
            fetch('api.php?action=get_player&id=' + playerId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Popola form
                        const player = data.player;
                        document.getElementById('player-id').value = player.id;
                        document.getElementById('username').value = player.username;
                        document.getElementById('email').value = player.email;
                        document.getElementById('level').value = player.level;
                        document.getElementById('experience').value = player.experience;
                        document.getElementById('fame').value = player.fame;
                        document.getElementById('is-admin').value = player.is_admin;
                        
                        // Mostra modal
                        document.getElementById('edit-player-modal').classList.remove('hidden');
                    } else {
                        showNotification('error', data.error || 'Errore nel recupero dati giocatore');
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    showNotification('error', 'Errore di connessione');
                });
        });
    });
    
    // Form modifica giocatore
    const editForm = document.getElementById('edit-player-form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                player_id: document.getElementById('player-id').value,
                username: document.getElementById('username').value,
                email: document.getElementById('email').value,
                level: document.getElementById('level').value,
                experience: document.getElementById('experience').value,
                fame: document.getElementById('fame').value,
                is_admin: document.getElementById('is-admin').value
            };
            
            const errorElement = document.getElementById('edit-error');
            errorElement.textContent = '';
            
            // Invia richiesta
            fetch('api.php?action=update_player', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Chiudi modal
                    closeModals();
                    
                    // Mostra notifica
                    showNotification('success', 'Giocatore aggiornato con successo');
                    
                    // Ricarica pagina dopo 1 secondo
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    errorElement.textContent = data.error || 'Errore durante l\'aggiornamento';
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                errorElement.textContent = 'Errore di connessione';
            });
        });
    }
    
    // Elimina giocatore
    document.querySelectorAll('.delete-player').forEach(button => {
        button.addEventListener('click', function() {
            const playerId = this.dataset.playerId;
            
            // Imposta ID giocatore nel pulsante di conferma
            document.getElementById('confirm-delete').dataset.playerId = playerId;
            
            // Mostra modal conferma
            document.getElementById('delete-confirm-modal').classList.remove('hidden');
        });
    });
    
    // Conferma eliminazione
    const confirmDeleteButton = document.getElementById('confirm-delete');
    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', function() {
            const playerId = this.dataset.playerId;
            
            // Invia richiesta
            fetch('api.php?action=delete_player', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ player_id: playerId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Chiudi modal
                    closeModals();
                    
                    // Mostra notifica
                    showNotification('success', 'Giocatore eliminato con successo');
                    
                    // Ricarica pagina dopo 1 secondo
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification('error', data.error || 'Errore durante l\'eliminazione');
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                showNotification('error', 'Errore di connessione');
            });
        });
    }
});

// Chiudi tutti i modal
function closeModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.add('hidden');
    });
}

console.log('===== ADMIN.JS END =====');
// assets/js/missions.js
document.addEventListener('DOMContentLoaded', function() {
    // Aggiorna timer missioni
    updateMissionTimers();
    setInterval(updateMissionTimers, 1000);
    
    // Eventi per pulsanti missioni
    document.querySelectorAll('.start-mission').forEach(button => {
        button.addEventListener('click', startMission);
    });
    
    document.querySelectorAll('.claim-rewards').forEach(button => {
        button.addEventListener('click', claimMissionRewards);
    });
    
    // Gestione modal
    document.querySelectorAll('.close-modal').forEach(el => {
        el.addEventListener('click', closeModals);
    });
});

// Aggiorna timer missioni attive
function updateMissionTimers() {
    const missionTimeElements = document.querySelectorAll('.mission-time[data-ends]');
    const now = Math.floor(Date.now() / 1000);
    
    missionTimeElements.forEach(element => {
        const endsTimestamp = parseInt(element.dataset.ends);
        if (!endsTimestamp) return;
        
        const timeDiff = Math.max(0, endsTimestamp - now);
        
        if (timeDiff <= 0) {
            // Missione completata
            element.textContent = 'Missione completata!';
            
            // Ricarica pagina dopo 2 secondi
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            // Aggiorna timer
            const hours = Math.floor(timeDiff / 3600);
            const minutes = Math.floor((timeDiff % 3600) / 60);
            const seconds = Math.floor(timeDiff % 60);
            
            element.textContent = `Tempo rimanente: ${hours}h ${minutes}m ${seconds}s`;
        }
    });
}

// Inizia missione
function startMission(e) {
    const button = e.currentTarget;
    const missionId = button.dataset.missionId;
    
    // Disabilita pulsante
    button.disabled = true;
    button.textContent = 'Avvio...';
    
    // Invia richiesta
    fetch('api.php?action=start_mission', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ mission_id: missionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Ricarica pagina
            window.location.reload();
        } else {
            showNotification('error', data.error || 'Errore durante l\'avvio della missione');
            
            // Riabilita pulsante
            button.disabled = false;
            button.textContent = 'Inizia Missione';
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showNotification('error', 'Errore di connessione');
        
        // Riabilita pulsante
        button.disabled = false;
        button.textContent = 'Inizia Missione';
    });
}

// Riscuoti ricompense missione
function claimMissionRewards(e) {
    const button = e.currentTarget;
    const missionId = button.dataset.missionId;
    
    // Disabilita pulsante
    button.disabled = true;
    button.textContent = 'Riscossione...';
    
    // Invia richiesta
    fetch('api.php?action=claim_mission_rewards', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ player_mission_id: missionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostra modal ricompensa se presente
            if (data.reward && data.reward.type) {
                showRewardModal(data.reward);
            } else {
                // Altrimenti ricarica pagina
                window.location.reload();
            }
        } else {
            showNotification('error', data.error || 'Errore durante la riscossione delle ricompense');
            
            // Riabilita pulsante
            button.disabled = false;
            button.textContent = 'Riscuoti Ricompense';
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showNotification('error', 'Errore di connessione');
        
        // Riabilita pulsante
        button.disabled = false;
        button.textContent = 'Riscuoti Ricompense';
    });
}

// Mostra modal ricompensa
function showRewardModal(reward) {
    const modal = document.getElementById('reward-modal');
    if (!modal) {
        window.location.reload();
        return;
    }
    
    // Imposta dati ricompensa
    document.getElementById('reward-name').textContent = reward.name || 'Ricompensa';
    document.getElementById('reward-description').textContent = reward.description || '';
    
    // Imposta immagine in base al tipo
    let imagePath = 'assets/images/resources/chest.png';
    if (reward.type === 'building') {
        imagePath = `assets/images/buildings/${reward.data.id || 'special'}.png`;
    } else if (reward.type === 'resources') {
        const resourceType = Object.keys(reward.data)[0] || 'chest';
        imagePath = `assets/images/resources/${resourceType}.png`;
    } else if (reward.type === 'item') {
        imagePath = `assets/images/items/${reward.data.id || 'special'}.png`;
    }
    
    document.getElementById('reward-image').src = imagePath;
    
    // Lista ricompense standard
    const standardRewardsList = document.getElementById('standard-rewards-list');
    let rewardsHtml = '';
    
    // Aggiungi ricompense standard
    if (reward.standard_rewards) {
        if (reward.standard_rewards.experience) {
            rewardsHtml += `<li><img src="assets/images/resources/experience.png" alt="Esperienza"> Esperienza: +${reward.standard_rewards.experience}</li>`;
        }
        
        if (reward.standard_rewards.fame) {
            rewardsHtml += `<li><img src="assets/images/resources/fame.png" alt="Fama"> Fama: +${reward.standard_rewards.fame}</li>`;
        }
        
        // Aggiungi risorse
        if (reward.standard_rewards.resources) {
            for (const [resource, amount] of Object.entries(reward.standard_rewards.resources)) {
                rewardsHtml += `<li><img src="assets/images/resources/${resource}.png" alt="${resource}"> ${ucfirst(resource)}: +${amount}</li>`;
            }
        }
    }
    
    standardRewardsList.innerHTML = rewardsHtml;
    
    // Mostra modal
    modal.classList.remove('hidden');
    
    // Chiudi modal e ricarica pagina
    modal.querySelector('.btn.primary').addEventListener('click', function() {
        window.location.reload();
    });
}

// Chiudi tutti i modal
function closeModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.add('hidden');
    });
}

// Mostra notifica
function showNotification(type, message) {
    const container = document.createElement('div');
    container.className = 'notifications-container';
    document.body.appendChild(container);
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    container.appendChild(notification);
    
    // Rimuovi dopo 5 secondi
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
            if (container.children.length === 0) {
                container.remove();
            }
        }, 500);
    }, 5000);
}

// Capitalizza prima lettera
function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
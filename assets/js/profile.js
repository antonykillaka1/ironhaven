// assets/js/profile.js
// Versione aggiornata senza importazioni ES6

document.addEventListener('DOMContentLoaded', function() {
    // Form impostazioni account
    const settingsForm = document.getElementById('account-settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            const errorElement = document.getElementById('settings-error');
            const successElement = document.getElementById('settings-success');
            
            // Reset messaggi
            errorElement.textContent = '';
            errorElement.style.display = 'none';
            successElement.textContent = '';
            successElement.style.display = 'none';
            
            // Validazione
            if (!email) {
                errorElement.textContent = 'L\'email è obbligatoria';
                errorElement.style.display = 'block';
                return;
            }
            
            // Validazione email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errorElement.textContent = 'Email non valida';
                errorElement.style.display = 'block';
                return;
            }
            
            // Validazione password
            if (newPassword && newPassword.length < 6) {
                errorElement.textContent = 'La password deve essere di almeno 6 caratteri';
                errorElement.style.display = 'block';
                return;
            }
            
            if (newPassword && newPassword !== confirmPassword) {
                errorElement.textContent = 'Le password non coincidono';
                errorElement.style.display = 'block';
                return;
            }
            
            // Se si vuole cambiare password, è necessaria la password attuale
            if (newPassword && !currentPassword) {
                errorElement.textContent = 'Inserisci la password attuale per cambiarla';
                errorElement.style.display = 'block';
                return;
            }
            
            // Invia richiesta
            fetch('api.php?action=update_profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    email: email, 
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostra messaggio successo
                    successElement.textContent = 'Profilo aggiornato con successo';
                    successElement.style.display = 'block';
                    
                    // Reset campi password
                    document.getElementById('current-password').value = '';
                    document.getElementById('new-password').value = '';
                    document.getElementById('confirm-password').value = '';
                } else {
                    errorElement.textContent = data.error || 'Errore durante l\'aggiornamento del profilo';
                    errorElement.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                errorElement.textContent = 'Errore di connessione';
                errorElement.style.display = 'block';
            });
        });
    }
    
    // Gestione oggetti speciali
    document.querySelectorAll('.equip-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            
            // Invia richiesta
            fetch('api.php?action=equip_item', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ item_id: itemId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Ricarica pagina
                    window.location.reload();
                } else {
                    showNotification('error', data.error || 'Errore durante l\'utilizzo dell\'oggetto');
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                showNotification('error', 'Errore di connessione');
            });
        });
    });
});

// Funzione per mostrare notifiche, ora locale invece di importata
function showNotification(type, message) {
    const container = document.getElementById('notifications');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    container.appendChild(notification);
    
    // Auto-rimuovi dopo 5 secondi
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 500);
    }, 5000);
}
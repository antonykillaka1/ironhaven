/**
 * Sistema di notifiche comune per tutte le pagine
 */

/**
 * Mostra una notifica
 * @param {string} type - Tipo di notifica (success, error, info, warning)
 * @param {string} message - Messaggio da mostrare
 */
function showNotification(type, message) {
    const container = document.getElementById('notifications');
    if (!container) {
        // Crea container per notifiche se non esiste
        const notifContainer = document.createElement('div');
        notifContainer.id = 'notifications';
        notifContainer.className = 'notifications-container';
        document.body.appendChild(notifContainer);
        
        // Usa il container appena creato per la prima notifica
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        notifContainer.appendChild(notification);
        
        // Rimuovi dopo 5 secondi
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 5000);
        
        return;
    }
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    container.appendChild(notification);
    
    // Rimuovi dopo 5 secondi
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 5000);
}

// Rendi disponibile globalmente
window.showNotification = showNotification;
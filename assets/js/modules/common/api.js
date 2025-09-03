/**
 * Funzioni API di base riutilizzabili
 */

/**
 * Effettua una richiesta API generica gestendo errori e risposte comuni
 * @param {string} endpoint - Endpoint API (es. 'get_settlement')
 * @param {Object} data - Dati da inviare (per POST)
 * @param {string} method - Metodo HTTP (GET o POST)
 * @returns {Promise} Promise con i dati della risposta
 */
async function apiRequest(endpoint, data = null, method = 'GET') {
    try {
        const url = `api.php?action=${endpoint}`;
        const options = {
            method: method,
            headers: {}
        };
        
        if (data && method === 'POST') {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            if (response.status === 401) {
                // Sessione scaduta, reindirizza al login
                console.warn('Sessione scaduta, reindirizzamento al login...');
                window.location.href = 'index.php';
                return null;
            }
            throw new Error(`Errore server: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error(`Errore API (${endpoint}):`, error);
        throw error;
    }
}

// Rendi disponibile globalmente
window.apiRequest = apiRequest;
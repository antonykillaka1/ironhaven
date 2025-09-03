// assets/js/login.js
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            // Attiva tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Mostra contenuto
            tabContents.forEach(content => {
                content.classList.add('hidden');
                if (content.id === tabId + '-tab') {
                    content.classList.remove('hidden');
                }
            });
        });
    });
    
    // Form login
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            const errorElement = document.getElementById('login-error');
            
            // Validazione
            if (!username || !password) {
                errorElement.textContent = 'Inserisci username e password';
                return;
            }
            
            // Reset errore
            errorElement.textContent = '';
            
            // Invia richiesta
            fetch('api.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect
                    window.location.href = 'index.php';
                } else {
                    errorElement.textContent = data.error || 'Credenziali non valide';
                }
            })
            .catch(error => {
                console.error('Errore login:', error);
                errorElement.textContent = 'Errore di connessione';
            });
        });
    }
    
    // Form registrazione
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('register-username').value;
            const email = document.getElementById('register-email').value;
            const password = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('register-confirm-password').value;
            const errorElement = document.getElementById('register-error');
            
            // Validazione
            if (!username || !email || !password) {
                errorElement.textContent = 'Tutti i campi sono obbligatori';
                return;
            }
            
            if (password !== confirmPassword) {
                errorElement.textContent = 'Le password non coincidono';
                return;
            }
            
            // Validazione email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errorElement.textContent = 'Email non valida';
                return;
            }
            
            // Reset errore
            errorElement.textContent = '';
            
            // Invia richiesta
            fetch('api.php?action=register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, email, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect
                    window.location.href = 'index.php';
                } else {
                    errorElement.textContent = data.error || 'Errore durante la registrazione';
                }
            })
            .catch(error => {
                console.error('Errore registrazione:', error);
                errorElement.textContent = 'Errore di connessione';
            });
        });
    }
});
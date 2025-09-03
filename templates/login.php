<?php
// templates/login.php
?>
<div class="auth-container">
    <div class="auth-box">
        <div class="tabs">
            <div class="tab active" data-tab="login">Accedi</div>
            <div class="tab" data-tab="register">Registrati</div>
        </div>
        
        <div class="tab-content" id="login-tab">
            <h2>Accedi a Ironhaven</h2>
            <form id="login-form" class="auth-form">
                <div class="form-group">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn primary">Accedi</button>
                </div>
                <div class="error-message" id="login-error"></div>
            </form>
        </div>
        
        <div class="tab-content hidden" id="register-tab">
            <h2>Crea un nuovo account</h2>
            <form id="register-form" class="auth-form">
                <div class="form-group">
                    <label for="register-username">Username</label>
                    <input type="text" id="register-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="register-password">Password</label>
                    <input type="password" id="register-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="register-confirm-password">Conferma Password</label>
                    <input type="password" id="register-confirm-password" name="confirm_password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn primary">Registrati</button>
                </div>
                <div class="error-message" id="register-error"></div>
            </form>
        </div>
    </div>
</div>
<?php
// templates/create_settlement.php
?>
<div class="settlement-creation">
    <h2>Crea il tuo insediamento</h2>
    
    <div class="settlement-creation-intro">
        <p>Benvenuto in Ironhaven, <?php echo htmlspecialchars($currentUser['username'] ?? 'Avventuriero'); ?>!</p>
        <p>È ora di fondare il tuo primo insediamento. Scegli con cura il nome, poiché sarà cruciale per il tuo futuro nell'Era del Ferro.</p>
    </div>
    
    <form id="create-settlement-form">
        <div class="form-group">
            <label for="settlement-name">Nome dell'insediamento</label>
            <input type="text" id="settlement-name" name="name" required maxlength="50">
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn primary">Fonda Insediamento</button>
        </div>
        
        <div class="error-message" id="settlement-error"></div>
    </form>
    <script src="assets/js/create_settlement.js"></script>
</div>
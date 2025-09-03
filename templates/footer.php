<?php
// templates/footer.php
?>
    </main>
    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Ironhaven | Versione <?php echo GAME_VERSION; ?></p>
        </div>
    </footer>
    
    <?php if (!isset($pageScript)): ?>
    <!-- Nessuno script da includere qui -->
    <?php elseif ($pageScript !== 'game'): ?>
    <!-- Include script come moduli ES6 per tutti tranne game.js (già incluso in game.php) -->
    <script type="module" src="assets/js/<?php echo $pageScript; ?>.js?v=<?php echo GAME_VERSION; ?>"></script>
    <?php endif; ?>
</body>
</html>
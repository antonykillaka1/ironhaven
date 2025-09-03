<?php
require_once SRC_PATH . '/Core/PopulationManager.php';
// includes/autoload.php
spl_autoload_register(function ($className) {
    // Converti namespace in percorso file
    $namespace = str_replace('\\', '/', $className);
    
    // Rimuovi il prefisso Ironhaven
    $path = str_replace('Ironhaven/', '', $namespace);
    
    // Percorso completo
    $filePath = SRC_PATH . '/' . $path . '.php';
    // Debug
    //echo "Cercando classe: $className<br>";
    //echo "Percorso file: $filePath<br>";
    //echo "File esiste: " . (file_exists($filePath) ? 'Sì' : 'No') . "<br>";
    
    if (file_exists($filePath)) {
        require_once $filePath;
    }
});
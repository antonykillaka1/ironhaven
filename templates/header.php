<?php
// templates/header.php
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ironhaven - La tua città medievale</title>
    
    <!-- CSS in ordine di specificità crescente -->
    <!-- 1. Variabili globali (se esiste) -->
    <?php if (file_exists('assets/css/variables.css')): ?>
    <link rel="stylesheet" href="assets/css/variables.css">
    <?php endif; ?>
    
    <!-- 2. CSS principale -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- 3. CSS per modali compatti -->
    <link rel="stylesheet" href="assets/css/compact-modal-images.css">
    
    <!-- 4. CSS per modali di potenziamento e gestione -->
    <link rel="stylesheet" href="assets/css/upgrade-manage-modals.css">
    
    <!-- 5. CSS specifico della pagina -->
    <?php if (isset($pageStylesheet)): ?>
    <link rel="stylesheet" href="assets/css/<?php echo $pageStylesheet; ?>.css?v=<?php echo GAME_VERSION; ?>">
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="logo">
            <h1>Ironhaven</h1>
        </div>
        <nav>
            <?php if (isset($currentUser)): ?>
            <ul>
                <li><a href="index.php">Città</a></li>
                <li><a href="index.php?page=buildings">Edifici</a></li>
                <li><a href="index.php?page=missions">Missioni</a></li>
                <li><a href="index.php?page=profile">Profilo</a></li>
                <?php if (isset($currentUser['is_admin']) && $currentUser['is_admin'] == 1): ?>
                <li><a href="index.php?page=admin">Admin</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
            <?php endif; ?>
        </nav>
    </header>
    <div class="notifications-container" id="notifications">
        <!-- Le notifiche verranno inserite qui -->
    </div>
    <main>
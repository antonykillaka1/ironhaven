<?php
// pages/tech_tree.php
require_once 'includes/autoload.php';
require_once 'config.php';

use Ironhaven\Core\Auth;

$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) { header('Location: index.php'); exit; }

$currentUser  = $auth->getCurrentUser();
$pageStylesheet = 'tech-tree';
$pageScript     = 'tech_tree';

include_once 'templates/header.php';
?>
<script>window.__IH_IS_ADMIN = <?= (!empty($currentUser['is_admin']) ? 'true' : 'false'); ?>;</script>

<div class="tech-container">
  <div class="tech-header">
    <h1>Strutture</h1>
    <div class="tt-controls">
      <input id="tt-search" type="search" placeholder="Cerca struttura..." />
      <label class="tt-inline">
        <input id="tt-show-empty" type="checkbox" checked />
        <span>Mostra livelli senza valori</span>
      </label>
	  <!-- ▼▼ nuovo: ordinamento -->
      <select id="tt-sort" class="tt-select">
        <option value="name_asc">Nome A→Z</option>
        <option value="name_desc">Nome Z→A</option>
        <option value="req_asc">Liv. richiesto ↑</option>
        <option value="req_desc">Liv. richiesto ↓</option>
        <option value="max_asc">Liv. max ↑</option>
        <option value="max_desc">Liv. max ↓</option>
      </select>
      <!-- ▲▲ nuovo -->
      <?php if (!empty($currentUser['is_admin'])): ?>
      <button id="tt-export-all" class="tt-btn ghost">Esporta tutte (CSV)</button>
      <?php endif; ?>
    </div>
  </div>

  <div id="tt-root" class="tt-root">
    <div class="tt-skeleton">Caricamento strutture…</div>
  </div>
</div>
<!-- Flag admin per JS -->
<script>
  window.IH_IS_ADMIN = <?php echo (!empty($currentUser['is_admin']) ? 'true' : 'false'); ?>;
</script>

<?php include_once 'templates/footer.php'; ?>

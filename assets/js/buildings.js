// assets/js/buildings.js (ES module, caricato dal footer con ?v=GAME_VERSION)

const API = 'api.php';

let __IH_isSyncing = false; // evita sync paralleli
let __IH_timerId = null;

/* -------------------- UI helpers -------------------- */
function notify(type, message) {
  if (typeof window.showNotification === 'function') {
    window.showNotification(type, message);
  } else {
    if (type === 'error') console.error(message);
    alert(message);
  }
}

/* -------------------- API helper -------------------- */
async function apiCall(action, data = null, method = 'GET') {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin'
  };
  let url = `${API}?action=${encodeURIComponent(action)}`;
  if (method === 'POST' && data) options.body = JSON.stringify(data);

  try {
    const res = await fetch(url, options);
    if (!res.ok) {
      if (res.status === 401) { location.href = 'index.php'; return null; }
      let errText = '';
      try { errText = await res.text(); } catch {}
      console.error('[buildings] HTTP error', res.status, errText);
      notify('error', `Errore server: ${res.status}`);
      return null;
    }
    const json = await res.json();
    if (!json.success) {
      notify('error', json.error || 'Operazione non riuscita');
      return null;
    }
    return json;
  } catch (e) {
    console.error('[buildings] fetch error:', e);
    notify('error', 'Errore di comunicazione con il server');
    return null;
  }
}

/* -------------------- Countdown costruzione -------------------- */
function fmtTime(ms) {
  let s = Math.max(0, Math.floor(ms / 1000));
  const d = Math.floor(s / 86400); s %= 86400;
  const h = Math.floor(s / 3600);  s %= 3600;
  const m = Math.floor(s / 60);    s %= 60;
  if (d > 0) return `${d}g ${h}h ${m}m ${s}s`;
  if (h > 0) return `${h}h ${m}m ${s}s`;
  return `${m}m ${s}s`;
}

function initConstructionTimers() {
  const timers = [...document.querySelectorAll('.construction-timer[data-ends]')];
  if (!timers.length) return;

  const update = async () => {
    const now = Date.now();

    for (const el of timers) {
      const ends = parseInt(el.dataset.ends, 10) * 1000; // epoch s → ms
      const remain = ends - now;

      if (remain <= 0) {
        // UI: segna completato localmente
        el.textContent = ' (0m 0s)';
        const card = el.closest('.building-card');
        if (card) {
          card.dataset.status = 'completed';
          const st = card.querySelector('.status-text');
          if (st) st.textContent = 'Completato';
          card.querySelector('.upgrade-btn')?.removeAttribute('disabled');
          card.querySelector('.manage-btn')?.removeAttribute('disabled');
        }

        // Richiedi il sync UNA SOLA VOLTA
        if (!el.dataset.syncRequested && !__IH_isSyncing) {
          el.dataset.syncRequested = '1';
          __IH_isSyncing = true;

          const result = await apiCall('check_construction_status', null, 'POST');

          __IH_isSyncing = false;

          // Ricarica SOLO se il backend ha effettivamente chiuso delle costruzioni
          if (result && Array.isArray(result.completed_buildings) && result.completed_buildings.length > 0) {
            location.reload();
            return;
          }
          // Se non ha chiuso nulla, NON ricaricare. La UI resta aggiornata localmente
          // e non ritenteremo il sync finché non si ricarica manualmente.
        }
      } else {
        el.textContent = ` (${fmtTime(remain)})`;
      }
    }
  };

  update();
  __IH_timerId = setInterval(update, 1000);
}

/* -------------------- Init pagina Edifici -------------------- */
function initBuildingsPage() {
  // Click su "Costruisci"
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.build-btn');
    if (!btn) return;

    const type = btn.dataset.type || btn.getAttribute('data-type');
    if (!type) return;

    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Costruzione...';

    const result = await apiCall('construct_building', { type }, 'POST');

    if (result) {
      notify('success', 'Costruzione avviata!');
      location.reload(); // una sola ricarica qui va bene
    } else {
      btn.disabled = false;
      btn.textContent = original;
    }
  });
    // Click su "Annulla" (cancella una costruzione in corso)
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.cancel-build');
  if (!btn) return;

  const id = parseInt(btn.dataset.id, 10);
  if (!id) return;

  if (!confirm('Annullare questa costruzione? I materiali non verranno rimborsati.')) {
    return;
  }

  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Annullamento...';

  const result = await apiCall('cancel_construction', { building_id: id }, 'POST');

  if (result) {
    notify('success', 'Costruzione annullata');
    location.reload();
  } else {
    btn.disabled = false;
    btn.textContent = original;
  }
});
  // Click su "Demolisci" (rimuove un edificio completato)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.demolish-build');
    if (!btn) return;

    const id = parseInt(btn.dataset.id, 10);
    if (!id) return;

    if (!confirm('Demolire questo edificio? Operazione permanente (nessun rimborso).')) {
      return;
    }

    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Demolizione...';

    const result = await apiCall('demolish_building', { building_id: id }, 'POST');

    if (result) {
      notify('success', 'Edificio demolito');
      location.reload();
    } else {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  // Avvia countdown per le card "In costruzione"
  initConstructionTimers();

  console.log('Inizializzazione funzioni edifici di base');
}

document.addEventListener('DOMContentLoaded', initBuildingsPage);

/* -------------------- Compat vecchi script -------------------- */
window.initBuildingManagement = window.initBuildingManagement || (() => true);

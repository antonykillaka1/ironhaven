// assets/js/buildings.js (ES module)

const API = 'api.php';

let __IH_isSyncing = false;
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

function fmtDateTime(tsMs) {
  try {
    return new Date(tsMs).toLocaleString(); // usa locale del browser
  } catch {
    return '';
  }
}

/**
 * Nice confirm modal (Promise<boolean>)
 */
function confirmNice({
  title = 'Conferma',
  message = 'Sei sicuro?',
  confirmText = 'Conferma',
  cancelText = 'Annulla',
  variant = 'primary'
} = {}) {
  return new Promise((resolve) => {
    const backdrop = document.createElement('div');
    backdrop.className = 'ih-modal-backdrop';
    backdrop.innerHTML = `
      <div class="ih-modal" role="dialog" aria-modal="true" aria-labelledby="ih-modal-title">
        <h3 id="ih-modal-title">${title}</h3>
        <p>${message}</p>
        <div class="actions">
          <button class="ih-btn ih-cancel">${cancelText}</button>
          <button class="ih-btn ih-btn-primary ${variant === 'danger' ? 'ih-btn-danger ih-btn-primary' : ''} ih-ok">${confirmText}</button>
        </div>
      </div>
    `;
    document.body.appendChild(backdrop);

    const btnCancel = backdrop.querySelector('.ih-cancel');
    const btnOk = backdrop.querySelector('.ih-ok');

    const close = (val) => {
      document.removeEventListener('keydown', onKey);
      backdrop.remove();
      resolve(val);
    };
    const onKey = (e) => {
      if (e.key === 'Escape') close(false);
      if (e.key === 'Enter')  close(true);
    };

    btnCancel.addEventListener('click', () => close(false));
    btnOk.addEventListener('click', () => close(true));
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(false); });
    document.addEventListener('keydown', onKey);

    btnOk.focus();
  });
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

/* -------------------- Countdown costruzione + progress -------------------- */
function fmtTime(ms) {
  let s = Math.max(0, Math.floor(ms / 1000));
  const d = Math.floor(s / 86400); s %= 86400;
  const h = Math.floor(s / 3600);  s %= 3600;
  const m = Math.floor(s / 60);    s %= 60;
  if (d > 0) return `${d}g ${h}h ${m}m ${s}s`;
  if (h > 0) return `${h}h ${m}m ${s}s`;
  return `${m}m ${s}s`;
}

function clamp01(x) { return Math.max(0, Math.min(1, x)); }

function initConstructionTimers() {
  const timers = [...document.querySelectorAll('.construction-timer[data-ends]')];
  if (!timers.length) return;

  // helper per configurare la ring (una volta per elemento)
  function setupRing(ringProgress) {
    const r = parseFloat(ringProgress.getAttribute('r')) || 46;
    const C = 2 * Math.PI * r;
    ringProgress.dataset.circ = String(C);
    ringProgress.style.strokeDasharray = `${C} ${C}`;
    // start da 0% (offset pieno)
    ringProgress.style.strokeDashoffset = `${C}`;
  }

  const update = async () => {
    const now = Date.now();

    for (const el of timers) {
      const endsSec = parseInt(el.dataset.ends, 10);
      if (!endsSec) continue;

      const endsMs = endsSec * 1000;
      const remain = endsMs - now;

      // elementi correlati
      const card   = el.closest('.building-card');
      const badge  = el.closest('.build-badge');
      const thumb  = card ? card.querySelector('.building-thumb.in-progress') : null;
      const bar    = card ? card.querySelector('.build-progress') : null;
      const ringPr = thumb ? thumb.querySelector('.build-ring-progress') : null;

      const startsSec = parseInt(
        el.dataset.starts ||
        (thumb && thumb.dataset.starts) ||
        (bar && bar.dataset.starts) || '0', 10
      );
      const startsMs = startsSec ? (startsSec * 1000) : 0;

      // ETA tooltip
      const etaText = `ETA: ${remain <= 0 ? '0m 0s' : fmtTime(remain)} • Fine: ${fmtDateTime(endsMs)}`;
      el.title = etaText; el.setAttribute('aria-label', etaText);
      if (badge) { badge.title = etaText; badge.setAttribute('aria-label', etaText); }

      // Progress bar lineare (se sappiamo lo start)
      if (bar) {
        if (startsMs && endsMs > startsMs) {
          const total = endsMs - startsMs;
          const elapsed = now - startsMs;
          const pct = clamp01(elapsed / total) * 100;
          bar.style.width = `${pct}%`;
          bar.classList.remove('indeterminate');
        } else {
          bar.classList.add('indeterminate');
        }
      }

      // Progress ring circolare
      if (ringPr) {
        if (!ringPr.dataset.circ) setupRing(ringPr);
        const C = parseFloat(ringPr.dataset.circ);

        if (startsMs && endsMs > startsMs) {
          const total = endsMs - startsMs;
          const elapsed = Math.max(0, Math.min(total, now - startsMs));
          const pct = elapsed / total; // 0..1
          const offset = C * (1 - pct);
          ringPr.style.strokeDashoffset = `${offset}`;
          thumb && thumb.classList.remove('indeterminate');
        } else {
          // indeterminate: lascia la rotazione CSS
          thumb && thumb.classList.add('indeterminate');
        }
      }

      // Fine countdown
      if (remain <= 0) {
        el.textContent = '0m 0s';
        if (badge) badge.remove();
        if (bar)   bar.remove();
        if (thumb) thumb.classList.remove('in-progress', 'indeterminate');

        if (card) {
          card.dataset.status = 'completed';
          const st = card.querySelector('.status-text');
          if (st) st.textContent = 'Completato';
          card.querySelector('.upgrade-btn')?.removeAttribute('disabled');
          card.querySelector('.manage-btn')?.removeAttribute('disabled');
        }

        // sincronizza una sola volta col server
        if (!el.dataset.syncRequested && !__IH_isSyncing) {
          el.dataset.syncRequested = '1';
          __IH_isSyncing = true;
          const result = await apiCall('check_construction_status', null, 'POST');
          __IH_isSyncing = false;

          if (result && Array.isArray(result.completed_buildings) && result.completed_buildings.length > 0) {
            location.reload();
            return;
          }
        }
      } else {
        el.textContent = fmtTime(remain);
      }
    }
  };

  update();
  __IH_timerId = setInterval(update, 1000);
}


/* -------------------- Init pagina Edifici -------------------- */
function initBuildingsPage() {
  // Costruisci nuova struttura
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
      location.reload();
    } else {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  // Annulla costruzione in corso
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.cancel-build');
    if (!btn) return;

    const id = parseInt(btn.dataset.id, 10);
    if (!id) return;

    const ok = await confirmNice({
      title: 'Annulla costruzione',
      message: 'Annullare questa costruzione? I materiali non verranno rimborsati.',
      confirmText: 'Annulla costruzione',
      cancelText: 'No',
      variant: 'danger'
    });
    if (!ok) return;

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

  // Demolisci edificio completato
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.demolish-build');
    if (!btn) return;

    const id = parseInt(btn.dataset.id, 10);
    if (!id) return;

    const ok = await confirmNice({
      title: 'Demolisci edificio',
      message: 'Demolire questo edificio? Operazione permanente (nessun rimborso).',
      confirmText: 'Demolisci',
      cancelText: 'Annulla',
      variant: 'danger'
    });
    if (!ok) return;

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
  function fmtResKey(k) {
  const map = { wood: 'Legno', stone: 'Pietra', food: 'Cibo', water: 'Acqua', iron: 'Ferro', gold: 'Oro' };
  return map[k] || k;
}
function fmtNum(n) {
  if (n == null) return 'N/D';
  return Number(n).toLocaleString('it-IT');
}
function fmtTimeHMS(sec) {
  if (!sec && sec !== 0) return 'N/D';
  let s = Math.max(0, parseInt(sec, 10));
  const h = Math.floor(s / 3600); s %= 3600;
  const m = Math.floor(s / 60);   s %= 60;
  if (h > 0) return `${h}h ${m}m ${s}s`;
  if (m > 0) return `${m}m ${s}s`;
  return `${s}s`;
}

function renderResList(obj, suffix = '') {
  if (!obj || Object.keys(obj).length === 0) {
    return '<div class="muted">N/D</div>';
  }
  return `
    <ul class="ih-list">
      ${Object.entries(obj).map(([k,v]) => `
        <li>
          <img src="assets/images/resources/${k}.png" alt="${fmtResKey(k)}">
          <span>${fmtResKey(k)}</span>
          <strong>${fmtNum(v)}${suffix}</strong>
        </li>
      `).join('')}
    </ul>
  `;
}

function openDetailsModal(details) {
  const html = `
    <div class="ih-modal-backdrop">
      <div class="ih-modal ih-details" role="dialog" aria-modal="true" aria-labelledby="ih-details-title">
        <h3 id="ih-details-title">
          Dettagli: ${details.type} — Livello ${details.level}
        </h3>

        <div class="ih-sections">
          <section>
            <h4>Produzione attuale</h4>
            ${renderResList(details.production, '/h')}
          </section>

          <section>
            <h4>Produzione al prossimo livello</h4>
            ${renderResList(details.next_level_production, '/h')}
          </section>

          <section>
            <h4>Capienza</h4>
            <div>${details.capacity != null ? fmtNum(details.capacity) : 'N/D'}</div>
          </section>

          <section>
            <h4>Costi upgrade</h4>
            ${renderResList(details.upgrade_costs)}
            <div class="muted">Tempo upgrade: ${fmtTimeHMS(details.build_time_sec)}</div>
          </section>
        </div>

        <div class="actions">
          <button class="ih-btn ih-btn-primary ih-close">Chiudi</button>
        </div>
      </div>
    </div>
  `;
  const wrapper = document.createElement('div');
  wrapper.innerHTML = html.trim();
  const backdrop = wrapper.firstElementChild;
  document.body.appendChild(backdrop);

  const close = () => backdrop.remove();
  backdrop.querySelector('.ih-close').addEventListener('click', close);
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', function onKey(e){
    if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); }
  });
}

  

  // Countdown + progress
  initConstructionTimers();
    // Apri Dettagli (usa il bottone "Gestisci" come trigger)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.manage-btn, .details-btn');
    if (!btn) return;

    const id = parseInt(btn.dataset.id, 10);
    if (!id) return;

    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Caricamento...';

    const res = await apiCall('building_details', { building_id: id }, 'POST');

    btn.disabled = false;
    btn.textContent = original;

    if (!res) return;
    openDetailsModal(res.details);
  });


  console.log('Inizializzazione funzioni edifici di base');
}

document.addEventListener('DOMContentLoaded', initBuildingsPage);

/* -------------------- Compat vecchi script -------------------- */
window.initBuildingManagement = window.initBuildingManagement || (() => true);

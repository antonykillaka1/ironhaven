// assets/js/tech_tree.js  — ES Module
// Strutture / Tech tree

const API = 'api.php';

// ---- helpers UI -------------------------------------------------------------

function $(sel, root = document) { return root.querySelector(sel); }
function $all(sel, root = document) { return [...root.querySelectorAll(sel)]; }

function notify(type, message) {
  if (typeof window.showNotification === 'function') {
    window.showNotification(type, message);
  } else {
    if (type === 'error') console.error(message);
    alert(message);
  }
}

function fmtNum(n) {
  if (n === null || n === undefined || Number.isNaN(n)) return '0';
  return new Intl.NumberFormat('it-IT').format(Math.round(n));
}

function fmtTimeMinutes(min) {
  const m = Math.max(0, Math.round(min));
  const h = Math.floor(m / 60);
  const rem = m % 60;
  return h > 0 ? `${h}h ${rem}m` : `${rem}m`;
}

// ---- API helper (FIX: supporto params GET corretto) ------------------------

async function api(action, data = null, method = 'GET', params = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin', // cookie sessione
  };

  let url = `${API}?action=${encodeURIComponent(action)}`;
  if (params && typeof params === 'object') {
    const qs = new URLSearchParams(params).toString();
    if (qs) url += `&${qs}`;
  }

  if (method === 'POST' && data) {
    opts.body = JSON.stringify(data);
  }

  const res = await fetch(url, opts);
  if (!res.ok) {
    // tenta di leggere testo per debug
    let t = '';
    try { t = await res.text(); } catch {}
    console.error('[tech_tree] HTTP', res.status, t);
    throw new Error(String(res.status));
  }

  const ct = res.headers.get('content-type') || '';
  if (ct.includes('text/csv')) return res.text();
  if (ct.includes('application/json')) return res.json();
  // fallback
  return res.text();
}

// ---- Stato pagina ----------------------------------------------------------

let TYPES = []; // lista base
const ROOT = $('#tt-root');
const SEARCH = $('#tt-search');
const SHOW_EMPTY = $('#tt-show-empty');
const BTN_EXPORT_ALL = $('#tt-export-all'); // opzionale, se presente nel markup

// heuristica: mostriamo controlli admin se vede la voce Admin nel menù
const IS_ADMIN = !!document.querySelector('a[href*="page=admin"]');

// ---- Caricamento e rendering lista ----------------------------------------
// ---- Caricamento e rendering lista ----------------------------------------
async function loadTypes() {
  ROOT.innerHTML = `<div class="tt-skeleton">Caricamento strutture…</div>`;

  // helper che estrae l’array di tipi da qualsiasi forma di payload
  const unwrapTypes = (o) => {
    if (!o) return [];
    if (Array.isArray(o)) return o;
    if (Array.isArray(o.types)) return o.types;
    if (Array.isArray(o.building_types)) return o.building_types;
    if (o.data) return unwrapTypes(o.data);
    if (o.result) return unwrapTypes(o.result);
    // a volte le API tornano { success:true, payload:[...] }
    if (o.payload) return unwrapTypes(o.payload);
    return [];
  };

  try {
    const res = await api('get_building_types');
    TYPES = unwrapTypes(res);

    // Se è davvero vuoto, mostro un messaggio utile
    if (!TYPES.length) {
      ROOT.innerHTML = `
        <div class="tt-note">
          Nessuna struttura presente. Se hai appena aggiornato il codice,
          importa i tipi da <a href="index.php?page=admin_building_types">Import Strutture</a>.
        </div>`;
      return;
    }

    renderList(); // procede con il rendering della lista
  } catch (e) {
    console.error('[tech-tree] loadTypes error:', e);
    ROOT.innerHTML = `<div class="tt-note error">Errore nel caricamento: ${e.message}</div>`;
  }
}


function renderList() {
  const q = (SEARCH?.value || '').trim().toLowerCase();

  const filtered = TYPES.filter(t => {
    if (!q) return true;
    return (t.name || '').toLowerCase().includes(q) ||
           (t.slug || '').toLowerCase().includes(q) ||
           (t.description || '').toLowerCase().includes(q);
  });

  if (!filtered.length) {
    ROOT.innerHTML = `<div class="tt-note">Nessuna struttura trovata.</div>`;
    return;
  }

  const frag = document.createDocumentFragment();

  filtered.forEach(t => {
    const row = document.createElement('div');
    row.className = 'tt-row';
    row.dataset.slug = t.slug || '';

    row.innerHTML = `
      <div class="tt-title">
        <strong>${escapeHtml(t.name || t.slug || '—')}</strong>
        <small class="tt-slug">(${escapeHtml(t.slug || '')})</small>
        ${t.description ? `<div class="tt-desc">${escapeHtml(t.description)}</div>` : ''}
      </div>
      <div class="tt-actions">
        <button class="tt-btn tt-outline tt-export" title="Esporta CSV">CSV</button>
        <button class="tt-btn tt-primary tt-details">Dettagli</button>
      </div>
    `;

    row.querySelector('.tt-details').addEventListener('click', () => openDetails(t.slug));
    row.querySelector('.tt-export').addEventListener('click', () => exportOneCSV(t.slug, t.name));

    frag.appendChild(row);
  });

  ROOT.innerHTML = '';
  ROOT.appendChild(frag);
}

// ---- Modale “Dettagli” -----------------------------------------------------

async function openDetails(slug) {
  try {
    // FIX: passiamo slug come parametro, non dentro action
    const j = await api('get_building_type', null, 'GET', { slug });

    if (!j?.type) {
      notify('error', 'Struttura non trovata');
      return;
    }
    const t = j.type;

    const levels = computeLevels(t);
    const modal = buildDetailsModal(t, levels);

    document.body.appendChild(modal.backdrop);
    modal.open();

  } catch (e) {
    notify('error', `Errore: ${e.message}`);
  }
}

function computeLevels(t) {
  // Campi base
  const maxL = Math.max(1, parseInt(t.max_level || 1, 10));

  const prodMult = parseFloat(t.production_multiplier ?? 1.2);
  const costMult = parseFloat(t.upgrade_cost_multiplier ?? 1.5);
  const capMult  = parseFloat(t.capacity_multiplier ?? 1.2);
  const timeMult = parseFloat(t.time_multiplier ?? 1.0);

  // base level (L1)
  const base = {
    wood:  parseFloat(t.wood_production  ?? 0),
    stone: parseFloat(t.stone_production ?? 0),
    food:  parseFloat(t.food_production  ?? 0),
    water: parseFloat(t.water_production ?? 0),
    iron:  parseFloat(t.iron_production  ?? 0),
    gold:  parseFloat(t.gold_production  ?? 0),

    cwood:  parseFloat(t.wood_cost  ?? 0),
    cstone: parseFloat(t.stone_cost ?? 0),
    cfood:  parseFloat(t.food_cost  ?? 0),
    cwater: parseFloat(t.water_cost ?? 0),
    ciron:  parseFloat(t.iron_cost  ?? 0),
    cgold:  parseFloat(t.gold_cost  ?? 0),

    capacity: parseFloat(t.capacity_increase ?? 0),
    timeMin:  parseFloat(t.build_time_minutes ?? 5),
  };

  const levels = [];
  for (let L = 1; L <= maxL; L++) {
    const fProd = Math.pow(prodMult, L - 1);
    const fCost = Math.pow(costMult, L - 1);
    const fCap  = Math.pow(capMult,  L - 1);
    const fTime = Math.pow(timeMult, L - 1);

    const row = {
      L,
      prod: {
        wood:  round2(base.wood  * fProd),
        stone: round2(base.stone * fProd),
        food:  round2(base.food  * fProd),
        water: round2(base.water * fProd),
        iron:  round2(base.iron  * fProd),
        gold:  round2(base.gold  * fProd),
      },
      cost: {
        wood:  round2(base.cwood  * fCost),
        stone: round2(base.cstone * fCost),
        food:  round2(base.cfood  * fCost),
        water: round2(base.cwater * fCost),
        iron:  round2(base.ciron  * fCost),
        gold:  round2(base.cgold  * fCost),
      },
      capacity: round2(base.capacity * fCap),
      timeMin:  Math.round(base.timeMin * fTime),
    };

    levels.push(row);
  }

  return levels;
}

function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

function buildDetailsModal(t, levels) {
  const backdrop = document.createElement('div');
  backdrop.className = 'tt-modal-backdrop';
  backdrop.innerHTML = `
    <div class="tt-modal" role="dialog" aria-modal="true">
      <button class="tt-close" aria-label="Chiudi">×</button>
      <div class="tt-modal-header">
        <div class="tt-modal-title">
          ${t.image_url ? `<img class="tt-icon" src="${escapeAttr(t.image_url)}" alt="">` : ''}
          <h3>${escapeHtml(t.name || t.slug)}</h3>
          <small class="tt-slug">(${escapeHtml(t.slug)})</small>
        </div>
        <div class="tt-modal-actions">
          <button class="tt-btn tt-outline" id="tt-export-one">Esporta CSV</button>
        </div>
      </div>

      ${t.description ? `<p class="tt-modal-desc">${escapeHtml(t.description)}</p>` : ''}

      <div class="tt-sim">
        <label>Simula livello:
          <input type="range" id="tt-lvl" min="1" max="${levels.length}" value="1">
          <span id="tt-lvl-val">1</span> / ${levels.length}
        </label>
        <div class="tt-sim-cards">
          <div class="tt-sim-card">
            <div class="tt-sim-title">Produzione oraria a L<span id="tt-lbl-prod">1</span></div>
            <div class="tt-grid tt-prod" id="tt-prod"></div>
          </div>
          <div class="tt-sim-card">
            <div class="tt-sim-title">Costi L<span id="tt-lbl-cost">1</span> & Tempo</div>
            <div class="tt-grid" id="tt-cost"></div>
            <div class="tt-time" id="tt-time"></div>
          </div>
          <div class="tt-sim-card">
            <div class="tt-sim-title">Costo totale upgrade 1 → L<span id="tt-lbl-cum">1</span></div>
            <div class="tt-grid" id="tt-cum"></div>
            <div class="tt-time" id="tt-time-cum"></div>
          </div>
        </div>
      </div>

      <details class="tt-raw" ${SHOW_EMPTY?.checked ? 'open' : ''}>
        <summary>Livelli dettagliati</summary>
        <div class="tt-table-wrap">
          <table class="tt-table">
            <thead>
              <tr>
                <th>Lv</th>
                <th colspan="6">Produzione / h</th>
                <th colspan="6">Costi</th>
                <th>Capienza</th>
                <th>Tempo</th>
              </tr>
              <tr class="tt-subhead">
                <th></th>
                <th>Legno</th><th>Pietra</th><th>Cibo</th><th>Acqua</th><th>Ferro</th><th>Oro</th>
                <th>Legno</th><th>Pietra</th><th>Cibo</th><th>Acqua</th><th>Ferro</th><th>Oro</th>
                <th>+Cap</th>
                <th>Build</th>
              </tr>
            </thead>
            <tbody id="tt-rows"></tbody>
          </table>
        </div>
      </details>
    </div>
  `;

  const modal = $('.tt-modal', backdrop);
  const btnClose = $('.tt-close', modal);
  const btnExport = $('#tt-export-one', modal);
  const slider = $('#tt-lvl', modal);
  const outLvl = $('#tt-lvl-val', modal);
  const lblProd = $('#tt-lbl-prod', modal);
  const lblCost = $('#tt-lbl-cost', modal);
  const lblCum  = $('#tt-lbl-cum', modal);
  const prodBox = $('#tt-prod', modal);
  const costBox = $('#tt-cost', modal);
  const timeBox = $('#tt-time', modal);
  const cumBox  = $('#tt-cum', modal);
  const cumTime = $('#tt-time-cum', modal);
  const rowsTbody = $('#tt-rows', modal);

  // Render tabella livelli (rispetta "Mostra livelli senza valori")
  const showEmpty = !!(SHOW_EMPTY?.checked);
  rowsTbody.innerHTML = '';
  levels.forEach(LR => {
    const allZeroProd = Object.values(LR.prod).every(v => !v);
    const allZeroCost = Object.values(LR.cost).every(v => !v);
    if (!showEmpty && allZeroProd && allZeroCost && !LR.capacity) return;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="tt-col-l">${LR.L}</td>
      <td>${fmtNum(LR.prod.wood)}</td>
      <td>${fmtNum(LR.prod.stone)}</td>
      <td>${fmtNum(LR.prod.food)}</td>
      <td>${fmtNum(LR.prod.water)}</td>
      <td>${fmtNum(LR.prod.iron)}</td>
      <td>${fmtNum(LR.prod.gold)}</td>
      <td>${fmtNum(LR.cost.wood)}</td>
      <td>${fmtNum(LR.cost.stone)}</td>
      <td>${fmtNum(LR.cost.food)}</td>
      <td>${fmtNum(LR.cost.water)}</td>
      <td>${fmtNum(LR.cost.iron)}</td>
      <td>${fmtNum(LR.cost.gold)}</td>
      <td>${fmtNum(LR.capacity)}</td>
      <td>${fmtTimeMinutes(LR.timeMin)}</td>
    `;
    rowsTbody.appendChild(tr);
  });

  function fillSim(L) {
    const i = Math.max(1, Math.min(L, levels.length)) - 1;
    const row = levels[i];

    outLvl.textContent = String(L);
    lblProd.textContent = String(L);
    lblCost.textContent = String(L);
    lblCum.textContent  = String(L);

    prodBox.innerHTML = gridRes(row.prod);
    costBox.innerHTML = gridRes(row.cost);
    timeBox.innerHTML = `Tempo costruzione: <strong>${fmtTimeMinutes(row.timeMin)}</strong>`;

    // cumulativo 1 → L
    const agg = { wood:0, stone:0, food:0, water:0, iron:0, gold:0, timeMin:0 };
    for (let k = 0; k < L; k++) {
      const r = levels[k];
      agg.wood  += r.cost.wood;
      agg.stone += r.cost.stone;
      agg.food  += r.cost.food;
      agg.water += r.cost.water;
      agg.iron  += r.cost.iron;
      agg.gold  += r.cost.gold;
      agg.timeMin += r.timeMin;
    }

    cumBox.innerHTML = gridRes({
      wood: agg.wood, stone: agg.stone, food: agg.food,
      water: agg.water, iron: agg.iron, gold: agg.gold
    });
    cumTime.innerHTML = `Tempo totale (1→L${L}): <strong>${fmtTimeMinutes(agg.timeMin)}</strong>`;
  }

  slider.addEventListener('input', () => fillSim(parseInt(slider.value, 10)));
  fillSim(1);

  btnExport.addEventListener('click', () => exportOneCSV(t.slug, t.name));

  const open = () => {
    requestAnimationFrame(() => backdrop.classList.add('open'));
  };
  const close = () => backdrop.remove();

  btnClose.addEventListener('click', close);
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', escClose);

  function escClose(e) {
    if (e.key === 'Escape') {
      document.removeEventListener('keydown', escClose);
      close();
    }
  }

  return { backdrop, open, close };
}

function gridRes(obj) {
  return `
    <div class="tt-res"><span>Legno</span><strong>${fmtNum(obj.wood)}</strong></div>
    <div class="tt-res"><span>Pietra</span><strong>${fmtNum(obj.stone)}</strong></div>
    <div class="tt-res"><span>Cibo</span><strong>${fmtNum(obj.food)}</strong></div>
    <div class="tt-res"><span>Acqua</span><strong>${fmtNum(obj.water)}</strong></div>
    <div class="tt-res"><span>Ferro</span><strong>${fmtNum(obj.iron)}</strong></div>
    <div class="tt-res"><span>Oro</span><strong>${fmtNum(obj.gold)}</strong></div>
  `;
}

// ---- Export CSV -------------------------------------------------------------

async function exportOneCSV(slug, name='struttura') {
  try {
    const csv = await api('export_building_type_csv', null, 'GET', { slug });
    downloadText(csv, `${slug || name}.csv`, 'text/csv');
  } catch (e) {
    notify('error', `Export fallito: ${e.message}`);
  }
}

async function exportAllCSV() {
  try {
    const csv = await api('export_all_building_types_csv');
    downloadText(csv, `strutture.csv`, 'text/csv');
  } catch (e) {
    notify('error', `Export tutto fallito: ${e.message}`);
  }
}

function downloadText(text, filename, mime='text/plain') {
  const blob = new Blob([text], { type: `${mime};charset=utf-8` });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

// ---- Utils -----------------------------------------------------------------

function escapeHtml(s) {
  return String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function escapeAttr(s) { return escapeHtml(s).replace(/"/g, '&quot;'); }

// ---- Init ------------------------------------------------------------------

function init() {
  loadTypes();

  SEARCH?.addEventListener('input', () => renderList());
  SHOW_EMPTY?.addEventListener('change', () => {
    // se è aperto un details, lo ricostruiremo quando riaperto
    renderList();
  });
  BTN_EXPORT_ALL?.addEventListener('click', exportAllCSV);
}

document.addEventListener('DOMContentLoaded', init);

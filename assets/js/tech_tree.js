// assets/js/tech_tree.js
const API = 'api.php';
const IS_ADMIN = !!window.__IH_IS_ADMIN;

function q(sel, root=document){ return root.querySelector(sel); }
function qa(sel, root=document){ return [...root.querySelectorAll(sel)]; }
const fmt = (n)=> (Math.round(n) || 0).toLocaleString('it-IT');
const clamp=(v,min,max)=>Math.max(min,Math.min(max,v));

// helper API aggiornato: supporta params per GET
async function api(action, data = null, method = 'GET', params = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin'
  };

  // costruzione URL corretta
  let url = `${API}?action=${encodeURIComponent(action)}`;
  if (params && typeof params === 'object') {
    const qs = new URLSearchParams(params).toString();
    if (qs) url += `&${qs}`;
  }

  if (method === 'POST' && data) {
    opts.body = JSON.stringify(data);
  }

  const res = await fetch(url, opts);
  if (!res.ok) throw new Error(`${res.status}`);

  const isCsv = res.headers.get('content-type')?.includes('text/csv');
  return isCsv ? res.text() : res.json();
}


// --- compute at level
function pow(v, exp){ return Math.pow(v, exp); }
function levelRow(b, L){
  const k = L-1;
  const prodMul = pow(b.production_multiplier || 1, k);
  const capMul  = pow(b.capacity_multiplier   || 1, k);
  const costMul = pow(b.upgrade_cost_multiplier || 1, k);
  const timeMul = pow(b.time_multiplier || 1, k);

  const prod = {
    water: Math.floor((b.water_production||0) * prodMul),
    food:  Math.floor((b.food_production ||0) * prodMul),
    wood:  Math.floor((b.wood_production ||0) * prodMul),
    stone: Math.floor((b.stone_production||0) * prodMul),
    iron:  Math.floor((b.iron_production ||0) * prodMul),
    gold:  Math.floor((b.gold_production ||0) * prodMul)
  };
  const cost = {
    water: Math.floor((b.water_cost||0) * costMul),
    food:  Math.floor((b.food_cost ||0) * costMul),
    wood:  Math.floor((b.wood_cost ||0) * costMul),
    stone: Math.floor((b.stone_cost||0) * costMul),
    iron:  Math.floor((b.iron_cost ||0) * costMul),
    gold:  Math.floor((b.gold_cost ||0) * costMul)
  };
  const cap  = Math.floor((b.capacity_increase||0) * capMul);
  const mins = Math.round((b.build_time_minutes||0) * timeMul);

  return { L, prod, cost, cap, mins };
}
function sumToLevel(b, N){
  const sum = {water:0,food:0,wood:0,stone:0,iron:0,gold:0, mins:0};
  for(let L=1; L<=N; L++){
    const r = levelRow(b,L);
    Object.keys(r.cost).forEach(k=>sum[k]+=r.cost[k]);
    sum.mins += r.mins;
  }
  return sum;
}
function minsToStr(m){
  const d = Math.floor(m/1440), h = Math.floor((m%1440)/60), mi = m%60;
  if (d>0) return `${d}g ${h}h ${mi}m`;
  if (h>0) return `${h}h ${mi}m`;
  return `${mi}m`;
}

// --- render list
async function loadList(){
  const root = q('#tt-root');
  root.innerHTML = '<div class="tt-skeleton">Carico…</div>';
  const json = await api('get_building_types');
  const items = json.items || [];

  const search = q('#tt-search');
  const showEmpty = q('#tt-show-empty');

  const draw = ()=>{
    const term = (search.value||'').toLowerCase();
    const show = !!showEmpty.checked;
    root.innerHTML = '';
    items
      .filter(b => !term || b.name.toLowerCase().includes(term) || b.slug.toLowerCase().includes(term))
      .forEach(b=>{
        const el = document.createElement('div');
        el.className = 'tt-row';
        el.innerHTML = `
          <div class="tt-row-main">
            <div class="tt-row-title">
              <strong>${b.name}</strong>
              <span class="tt-slug">(${b.slug})</span>
            </div>
            ${b.description ? `<div class="tt-desc">${b.description}</div>` : (show ? `<div class="tt-desc tt-muted">—</div>`:'')}
          </div>
          <div class="tt-row-actions">
            ${IS_ADMIN ? `<button class="tt-btn ghost tt-export" data-slug="${b.slug}">CSV</button>`:''}
            <button class="tt-btn details" data-slug="${b.slug}">Dettagli</button>
          </div>
        `;
        root.appendChild(el);
      });

    // bind
    qa('.tt-btn.details', root).forEach(btn=>{
      btn.addEventListener('click', ()=> openDetails(btn.dataset.slug));
    });
    qa('.tt-export', root).forEach(btn=>{
      btn.addEventListener('click', ()=>{
        window.open(`api.php?action=export_building_type_csv&slug=${encodeURIComponent(btn.dataset.slug)}`, '_blank');
      });
    });
  };

  search.addEventListener('input', draw);
  showEmpty.addEventListener('change', draw);
  draw();

  const expAll = q('#tt-export-all');
  if (expAll) expAll.addEventListener('click', ()=>{
    window.open('api.php?action=export_building_types_csv','_blank');
  });
}

// --- modal
function modal(html){
  const wrap = document.createElement('div');
  wrap.className = 'tt-modal-backdrop';
  wrap.innerHTML = `
    <div class="tt-modal" role="dialog" aria-modal="true">
      <button class="tt-modal-close" aria-label="Chiudi">&times;</button>
      <div class="tt-modal-body">${html}</div>
    </div>`;
  document.body.appendChild(wrap);
  q('.tt-modal-close', wrap).addEventListener('click', ()=>wrap.remove());
  wrap.addEventListener('click', (e)=>{ if (e.target===wrap) wrap.remove(); });
  return wrap;
}

// --- open details
async function openDetails(slug){
  const j = await api('get_building_type&slug='+encodeURIComponent(slug));
  const b = j.item;

  const fallbackImg = `assets/images/buildings/${b.slug}.png`;
  const img = b.image_url || fallbackImg;

  const m = modal(`
    <div class="tt-head">
      <div class="tt-pic">
        <img src="${img}" onerror="this.src='assets/images/buildings/placeholder.png'">
      </div>
      <div class="tt-titlebox">
        <div class="tt-title-line">
          <h3>${b.name}</h3>
          ${IS_ADMIN ? `<button class="tt-btn ghost small tt-edit">Modifica</button>`:''}
        </div>
        <div class="tt-sub">slug: <code>${b.slug}</code> • max L: <span class="tt-max">${b.max_level}</span></div>
        <div class="tt-desc-edit">
          <div class="tt-desc ro">${b.description || '<span class="tt-muted">—</span>'}</div>
          ${IS_ADMIN ? `
          <div class="tt-edit-form hidden">
            <label>Descrizione</label>
            <textarea class="f-desc" rows="3">${b.description||''}</textarea>

            <div class="tt-grid2">
              <label>Max level <input type="number" class="f-max" min="1" value="${b.max_level||1}"></label>
              <label>Image URL <input type="text" class="f-img" value="${b.image_url||''}"></label>
            </div>

            <div class="tt-grid4">
              <label>Cost xL mul <input type="number" step="0.01" class="f-ucm" value="${b.upgrade_cost_multiplier||1.5}"></label>
              <label>Prod xL mul <input type="number" step="0.01" class="f-pm"  value="${b.production_multiplier||1.2}"></label>
              <label>Cap xL mul <input type="number" step="0.01" class="f-cm"  value="${b.capacity_multiplier||1.2}"></label>
              <label>Time xL mul <input type="number" step="0.01" class="f-tm"  value="${b.time_multiplier||1.0}"></label>
            </div>

            <div class="tt-right">
              <button class="tt-btn ghost tt-cancel">Annulla</button>
              <button class="tt-btn tt-save">Salva</button>
            </div>
          </div>`:''}
        </div>
      </div>
    </div>

    <div class="tt-sim">
      <label>Simula fino al livello:
        <input type="range" min="1" max="${b.max_level||1}" value="1" class="sim-range">
        <span class="sim-L">1</span>
      </label>
      <div class="sim-cards">
        <div class="scard"><div class="st">Costo totale</div>
          <div class="sv costs"></div>
        </div>
        <div class="scard"><div class="st">Tempo totale</div>
          <div class="sv tot-time"></div>
        </div>
        <div class="scard"><div class="st">Produzione a L<span class="atL">1</span></div>
          <div class="sv prod-at"></div>
        </div>
      </div>
    </div>

    <div class="tt-tablewrap">
      <table class="tt-table levels">
        <thead>
          <tr>
            <th>L</th>
            <th>Produzione (Wtr/Food/Wood/Stone/Iron/Gold)</th>
            <th>Capienza +</th>
            <th>Costo (Wtr/Food/Wood/Stone/Iron/Gold)</th>
            <th>Tempo</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div class="tt-hint">Formule: base × moltiplicatore<sup>L−1</sup> • tempo in minuti.</div>
    </div>

    <div class="tt-foot">
      ${IS_ADMIN ? `<button class="tt-btn ghost tt-exp-one">Esporta CSV</button>`:''}
      <button class="tt-btn ghost tt-close">Chiudi</button>
    </div>
  `);

  q('.tt-close', m).addEventListener('click', ()=>m.remove());
  if (IS_ADMIN) {
    q('.tt-exp-one', m).addEventListener('click', ()=>{
      window.open(`api.php?action=export_building_type_csv&slug=${encodeURIComponent(b.slug)}`,'_blank');
    });
  }

  // fill levels
  const tbody = q('tbody', m);
  tbody.innerHTML = '';
  for(let L=1; L<= (b.max_level||1); L++){
    const r = levelRow(b,L);
    const tr = document.createElement('tr');

    // evidenzia se tutto zero
    const isEmpty = Object.values(r.prod).every(v=>v===0) && Object.values(r.cost).every(v=>v===0) && r.cap===0 && r.mins===0;
    if (isEmpty) tr.classList.add('tt-empty');

    tr.innerHTML = `
      <td class="c">${L}</td>
      <td class="mono">${fmt(r.prod.water)}/${fmt(r.prod.food)}/${fmt(r.prod.wood)}/${fmt(r.prod.stone)}/${fmt(r.prod.iron)}/${fmt(r.prod.gold)}</td>
      <td class="c">${fmt(r.cap)}</td>
      <td class="mono">${fmt(r.cost.water)}/${fmt(r.cost.food)}/${fmt(r.cost.wood)}/${fmt(r.cost.stone)}/${fmt(r.cost.iron)}/${fmt(r.cost.gold)}</td>
      <td class="c" title="build_time_minutes × time_multiplier^(L−1)">${minsToStr(r.mins)}</td>
    `;
    tbody.appendChild(tr);
  }

  // simulator
  const range = q('.sim-range', m);
  const outL  = q('.sim-L', m);
  const atL   = q('.atL', m);
  const costs = q('.costs', m);
  const totT  = q('.tot-time', m);
  const prodA = q('.prod-at', m);

  const redrawSim = ()=>{
    const L = parseInt(range.value,10);
    outL.textContent = L;
    atL.textContent   = L;
    const sum = sumToLevel(b, L);
    costs.textContent = `Wtr ${fmt(sum.water)} • Food ${fmt(sum.food)} • Wood ${fmt(sum.wood)} • Stone ${fmt(sum.stone)} • Iron ${fmt(sum.iron)} • Gold ${fmt(sum.gold)}`;
    totT.textContent  = minsToStr(sum.mins);

    const r = levelRow(b,L);
    prodA.textContent = `Wtr ${fmt(r.prod.water)} • Food ${fmt(r.prod.food)} • Wood ${fmt(r.prod.wood)} • Stone ${fmt(r.prod.stone)} • Iron ${fmt(r.prod.iron)} • Gold ${fmt(r.prod.gold)}  ${r.cap?`• Cap +${fmt(r.cap)}`:''}`;
  };
  range.addEventListener('input', redrawSim);
  redrawSim();

  // edit (admin)
  if (IS_ADMIN){
    const btnEdit = q('.tt-edit', m);
    const boxR    = q('.tt-desc', m);
    const form    = q('.tt-edit-form', m);
    const fDesc = q('.f-desc', m),
          fMax  = q('.f-max', m),
          fImg  = q('.f-img', m),
          fUcm  = q('.f-ucm', m),
          fPm   = q('.f-pm',  m),
          fCm   = q('.f-cm',  m),
          fTm   = q('.f-tm',  m);

    const toggle=()=>{
      boxR.classList.toggle('hidden');
      form.classList.toggle('hidden');
      btnEdit.textContent = form.classList.contains('hidden') ? 'Modifica' : 'Chiudi modifica';
    };

    btnEdit.addEventListener('click', toggle);
    q('.tt-cancel', m).addEventListener('click', toggle);
    q('.tt-save', m).addEventListener('click', async ()=>{
      const payload = {
        slug: b.slug,
        description: fDesc.value,
        max_level: clamp(parseInt(fMax.value,10)||1, 1, 999),
        image_url: fImg.value,
        upgrade_cost_multiplier: parseFloat(fUcm.value)||1,
        production_multiplier:   parseFloat(fPm.value)||1,
        capacity_multiplier:     parseFloat(fCm.value)||1,
        time_multiplier:         parseFloat(fTm.value)||1
      };
      try {
        const r = await api('update_building_type', payload, 'POST');
        // refresh UI base
        q('.tt-max', m).textContent = payload.max_level;
        q('.tt-desc', m).innerHTML  = payload.description || '<span class="tt-muted">—</span>';
        toggle();
        // ricarica liste in background
        loadList().catch(()=>{});
      } catch(err){
        alert('Errore salvataggio: '+err.message);
      }
    });
  }
}

// boot
document.addEventListener('DOMContentLoaded', loadList);

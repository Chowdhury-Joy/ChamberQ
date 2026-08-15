/**
 * ChamberQ offline kit — IndexedDB bag + pending queue.
 * Chamber outage: save/print the pad on this computer, freeze Call next.
 * Visiting day: pack the bag on good internet, write at the camp, upload later.
 */
(() => {
    const DB_NAME = 'chamberq-offline';
    const DB_VERSION = 1;
    const QUEUE_STORE = 'queue';
    const META_STORE = 'meta';

    const state = {
        online: typeof navigator !== 'undefined' ? navigator.onLine : true,
        unreachable: false,
        pending: 0,
        bag: null,
        listeners: [],
    };

    function csrf() {
        return document.querySelector('meta[name=csrf-token]')?.content
            || document.querySelector('input[name=_token]')?.value
            || '';
    }

    function config() {
        return window.ChamberQOfflineConfig || {};
    }

    function uuid() {
        if (crypto?.randomUUID) {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(QUEUE_STORE)) {
                    db.createObjectStore(QUEUE_STORE, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(META_STORE)) {
                    db.createObjectStore(META_STORE, { keyPath: 'key' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function withStore(store, mode, fn) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(store, mode);
            const result = fn(tx.objectStore(store));
            tx.oncomplete = () => resolve(result);
            tx.onerror = () => reject(tx.error);
        });
    }

    async function setMeta(key, value) {
        await withStore(META_STORE, 'readwrite', (store) => {
            store.put({ key, value });
        });
    }

    async function getMeta(key) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(META_STORE, 'readonly');
            const req = tx.objectStore(META_STORE).get(key);
            req.onsuccess = () => resolve(req.result?.value ?? null);
            req.onerror = () => reject(req.error);
        });
    }

    async function allQueue() {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(QUEUE_STORE, 'readonly');
            const req = tx.objectStore(QUEUE_STORE).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async function putQueue(item) {
        await withStore(QUEUE_STORE, 'readwrite', (store) => store.put(item));
        await refreshPending();
    }

    async function deleteQueue(id) {
        await withStore(QUEUE_STORE, 'readwrite', (store) => store.delete(id));
        await refreshPending();
    }

    async function refreshPending() {
        const items = await allQueue();
        state.pending = items.length;
        emit();
        return items;
    }

    function isLikelyOnline() {
        return state.online && !state.unreachable;
    }

    function emit() {
        const snapshot = {
            online: isLikelyOnline(),
            pending: state.pending,
            packedAt: state.bag?.packed_at || null,
        };
        document.body?.classList.toggle('cq-is-offline', !snapshot.online);
        renderBanner(snapshot);
        state.listeners.forEach((fn) => {
            try { fn(snapshot); } catch (e) {}
        });
    }

    function renderBanner(snapshot) {
        const el = document.getElementById('cq-offline-banner');
        if (!el) return;
        const title = document.getElementById('cq-offline-banner-title');
        const body = document.getElementById('cq-offline-banner-body');
        const btn = document.getElementById('cq-offline-sync-btn');
        if (!snapshot.online) {
            el.hidden = false;
            title.textContent = 'No internet. ';
            body.textContent = snapshot.pending
                ? `${snapshot.pending} item(s) saved on this computer — Call next still works here. The TV updates if this screen is on the TV.`
                : 'Call next still works on this computer. The TV updates if this screen is on the TV. Prescriptions and walk-ins wait until the line is back.';
            if (btn) btn.hidden = true;
            return;
        }
        if (snapshot.pending > 0) {
            el.hidden = false;
            title.textContent = 'Line is back. ';
            body.textContent = `${snapshot.pending} visit(s) still on this computer.`;
            if (btn) btn.hidden = false;
            return;
        }
        el.hidden = true;
    }

    async function packBag() {
        const url = config().bagUrl;
        if (!url) throw new Error('Missing bag URL');
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Could not pack the bag');
        const bag = await res.json();
        bag.search_cache = bag.search_cache || [];
        await setMeta('bag', bag);
        state.bag = bag;
        try {
            const visiting = config().visitingUrl;
            if (visiting && 'caches' in window) {
                const cache = await caches.open('clinic-shell-v6');
                await cache.add(visiting).catch(() => {});
            }
        } catch (e) {}
        emit();
        return bag;
    }

    async function loadBag() {
        state.bag = await getMeta('bag');
        return state.bag;
    }

    function searchMedicines(q) {
        const needle = (q || '').trim().toLowerCase();
        if (needle.length < 2 || !state.bag) return [];
        const rows = [];
        const seen = new Set();
        const push = (row) => {
            const name = (row.medicine_name || row.brand_name || '').trim();
            if (!name || seen.has(name.toUpperCase())) return;
            seen.add(name.toUpperCase());
            rows.push({
                brand_name: name.toUpperCase(),
                medicine_name: name.toUpperCase(),
                label: row.label || name,
                generic_name: row.generic_name || null,
                dose: row.dose || row.last_dose || null,
                frequency: row.frequency || row.last_frequency || null,
                duration: row.duration || row.last_duration || null,
                timing: row.timing || row.last_timing || null,
                source: 'offline',
            });
        };
        (state.bag.my_medicines || []).forEach(push);
        (state.bag.packs || []).forEach((pack) => (pack.items || []).forEach(push));
        (state.bag.search_cache || []).forEach(push);
        return rows.filter((row) => {
            const hay = `${row.brand_name} ${row.generic_name || ''} ${row.label}`.toLowerCase();
            return hay.includes(needle);
        }).slice(0, 20);
    }

    async function rememberSearchResults(results) {
        if (!Array.isArray(results) || results.length === 0) return;
        const bag = state.bag || (await loadBag()) || { search_cache: [] };
        bag.search_cache = bag.search_cache || [];
        const seen = new Set(bag.search_cache.map((r) => (r.brand_name || r.medicine_name || '').toUpperCase()));
        results.forEach((row) => {
            const name = (row.brand_name || row.medicine_name || '').toUpperCase();
            if (!name || seen.has(name)) return;
            seen.add(name);
            bag.search_cache.push(row);
        });
        bag.search_cache = bag.search_cache.slice(-200);
        state.bag = bag;
        await setMeta('bag', bag);
    }

    async function enqueue(partial) {
        const item = {
            id: partial.id || uuid(),
            type: partial.type,
            created_at: new Date().toISOString(),
            ...partial,
        };
        await putQueue(item);
        return item;
    }

    async function flush() {
        if (!isLikelyOnline()) return [];
        const url = config().syncUrl;
        if (!url) return [];
        const items = await allQueue();
        if (items.length === 0) return [];

        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ items }),
        });
        if (!res.ok) {
            state.unreachable = true;
            emit();
            throw new Error('Upload failed');
        }
        state.unreachable = false;
        const data = await res.json();
        const results = data.results || [];
        for (const result of results) {
            if (result.ok) {
                await deleteQueue(result.id);
            }
            if (result.conflict && result.halt) {
                break;
            }
        }
        emit();
        return results;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function timingLabel(key) {
        const labels = state.bag?.timing_labels || {
            after_food: 'After food',
            before_food: 'Before food',
            empty_stomach: 'Empty stomach',
            at_night: 'At night',
            with_food: 'With food',
        };
        return labels[key] || key || '';
    }

    function printPad(input) {
        const letter = state.bag?.letterhead || {};
        const patient = input.patient || {};
        const data = input.data || {};
        const items = (data.prescription_items || []).filter((row) => (row.medicine_name || '').trim());
        const meds = items.map((row) => {
            const timing = timingLabel(row.timing);
            return `<div class="med">
                <div class="brand">${escapeHtml(row.medicine_name)}</div>
                <div class="line">${escapeHtml([row.dose, row.frequency, row.duration, timing].filter(Boolean).join(' · '))}</div>
            </div>`;
        }).join('');

        const onMyPaper = !!input.on_my_paper;
        const header = onMyPaper ? '' : `<header class="header"><div>
<p class="doctor">${escapeHtml(letter.doctor_name || '')}</p>
<p class="muted">${escapeHtml(letter.qualifications || '')}</p>
<p class="muted">${letter.registration_number ? 'Reg. No. ' + escapeHtml(letter.registration_number) : ''}</p>
</div><div>
<p class="doctor" style="font-size:14px">${escapeHtml(letter.chamber_name || '')}</p>
<p class="muted">${escapeHtml(letter.chamber_address || '')}</p>
<p class="muted">${escapeHtml(letter.chamber_contact || '')}</p>
</div></header>`;
        const padPad = onMyPaper ? 'padding-top:40mm' : '';

        const html = `<!doctype html><html><head><meta charset="utf-8"><title>Prescription</title>
<style>
body{font-family:Helvetica Neue,Arial,sans-serif;color:#111;font-size:13px;margin:0}
.sheet{max-width:794px;margin:0 auto;padding:20px 24px}
.pad{border:1px solid #cbd5e1;border-radius:2px;overflow:hidden;${padPad}}
.header{display:flex;justify-content:space-between;gap:16px;padding:14px 16px;border-bottom:2px solid #0f172a}
.doctor{font-size:18px;font-weight:700;margin:0 0 4px}
.muted{color:#64748b;margin:0}
.band{display:flex;flex-wrap:wrap;gap:12px 20px;padding:10px 16px;background:#f1f5f9;border-bottom:1px solid #cbd5e1}
.field .label{display:block;font-size:10px;text-transform:uppercase;color:#64748b}
.body{display:grid;grid-template-columns:1fr 1.2fr;min-height:360px}
.clinical{padding:12px 16px;border-right:1px solid #e2e8f0}
.rx{padding:12px 16px}
h3{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.med{margin-bottom:10px}
.brand{font-weight:700}
.line{font-size:12px;color:#334155}
.unsynced{margin-top:16px;font-size:11px;color:#92400e}
@media print {.unsynced{display:none} .sheet{padding:0}}
</style></head><body><div class="sheet"><div class="pad">
${header}
<div class="band">
<div class="field"><span class="label">Patient</span><span>${escapeHtml(patient.name || '')}</span></div>
${patient.age ? `<div class="field"><span class="label">Age</span><span>${escapeHtml(patient.age)}</span></div>` : ''}
<div class="field"><span class="label">Date</span><span>${escapeHtml(input.date || new Date().toLocaleDateString())}</span></div>
${data.weight_kg ? `<div class="field"><span class="label">Wt</span><span>${escapeHtml(data.weight_kg)} kg</span></div>` : ''}
${data.bp_systolic ? `<div class="field"><span class="label">BP</span><span>${escapeHtml(data.bp_systolic)}/${escapeHtml(data.bp_diastolic || '')}</span></div>` : ''}
${data.temperature_f ? `<div class="field"><span class="label">Temp</span><span>${escapeHtml(data.temperature_f)} °F</span></div>` : ''}
</div>
<div class="body">
<aside class="clinical">
${data.diagnosis_label || data.diagnosis ? `<h3>Diagnosis</h3><p>${escapeHtml(data.diagnosis_label || String(data.diagnosis).replace(/^__free__:/, ''))}</p>` : ''}
${data.chief_complaint ? `<h3>C/C</h3><p>${escapeHtml(data.chief_complaint)}</p>` : ''}
${data.advice ? `<h3>Advice</h3><p>${escapeHtml(data.advice)}</p>` : ''}
${data.tests_advised ? `<h3>Inv</h3><p>${escapeHtml(data.tests_advised)}</p>` : ''}
</aside>
<section class="rx"><h3>Rx</h3>${meds || '<p class="muted">No medicines yet</p>'}
<p class="unsynced">Not yet uploaded to ChamberQ — paper copy is the record until the line is back.</p>
</section>
</div></div></div>
<script>window.onload=function(){window.print()}<\/script>
</body></html>`;

        const blob = new Blob([html], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank', 'noopener');
    }

    async function boot() {
        try {
            await loadBag();
            await refreshPending();
        } catch (e) {}

        window.addEventListener('online', () => {
            state.online = true;
            state.unreachable = false;
            emit();
            flush().catch(() => {});
        });
        window.addEventListener('offline', () => {
            state.online = false;
            emit();
        });

        document.getElementById('cq-offline-sync-btn')?.addEventListener('click', () => {
            flush().catch(() => {});
        });

        document.addEventListener('livewire:init', () => {
            if (!window.Livewire?.hook) return;
            window.Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 0 || !navigator.onLine) {
                        state.online = navigator.onLine;
                        state.unreachable = true;
                        emit();
                        preventDefault();
                    }
                });
            });
            window.Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    if (state.unreachable) {
                        state.unreachable = false;
                        emit();
                    }
                });
            });
        });

        emit();
        if (isLikelyOnline() && state.pending > 0) {
            flush().catch(() => {});
        }
    }

    window.ChamberQOffline = {
        boot,
        packBag,
        loadBag,
        getBag: () => state.bag,
        getMeta,
        setMeta,
        searchMedicines,
        rememberSearchResults,
        enqueue,
        flush,
        printPad,
        isLikelyOnline,
        pendingCount: () => state.pending,
        onChange(fn) {
            state.listeners.push(fn);
        },
        uuid,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

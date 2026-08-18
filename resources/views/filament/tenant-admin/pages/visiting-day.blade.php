<x-filament-panels::page>
    <style>
        [x-cloak] { display: none !important; }
        .vd-stack { display: flex; flex-direction: column; gap: 1rem; }
        .vd-card { padding: 1rem 1.125rem; border: 1px solid var(--gray-200); border-radius: 0.75rem; background: var(--color-white, #fff); }
        .dark .vd-card { border-color: var(--gray-800); background: var(--gray-900); }
        .vd-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.35rem; }
        .vd-muted { font-size: 0.8125rem; color: var(--gray-500); margin: 0 0 0.75rem; }
        .vd-row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; }
        .vd-field { display: flex; flex-direction: column; gap: 0.25rem; min-width: 10rem; flex: 1; }
        .vd-field label { font-size: 0.75rem; font-weight: 600; color: var(--gray-600); }
        .vd-field input, .vd-field select, .vd-field textarea {
            min-height: 2.5rem; padding: 0.4rem 0.6rem; border: 1px solid var(--gray-300); border-radius: 0.5rem;
            background: transparent; color: inherit; font: inherit;
        }
        .vd-list { display: flex; flex-direction: column; gap: 0.35rem; max-height: 16rem; overflow: auto; }
        .vd-person { display: flex; justify-content: space-between; gap: 0.75rem; text-align: left; width: 100%;
            padding: 0.5rem 0.75rem; border: 1px solid var(--gray-200); border-radius: 0.5rem; background: transparent; cursor: pointer; color: inherit; }
        .vd-person.is-on { border-color: var(--primary-400); background: color-mix(in srgb, var(--primary-50) 70%, transparent); }
        .vd-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
        .vd-chip { min-height: 2rem; padding: 0.2rem 0.6rem; border: 1px solid var(--gray-300); border-radius: 999px; background: transparent; cursor: pointer; color: inherit; font-size: 0.8125rem; }
        .vd-chip.is-on { border-color: var(--primary-500); background: var(--primary-50); }
        .vd-med { display: grid; grid-template-columns: 1.4fr 0.7fr 0.7fr 0.7fr auto; gap: 0.35rem; align-items: center; margin-bottom: 0.35rem; }
        @media (max-width: 700px) { .vd-med { grid-template-columns: 1fr 1fr; } }
        .vd-status { font-size: 0.8125rem; font-weight: 600; }
        .vd-status.ok { color: var(--success-600); }
        .vd-status.warn { color: var(--warning-700); }
        .vd-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; }
    </style>

    <div
        class="vd-stack"
        x-data="visitingDay()"
        x-init="boot()"
    >
        <div class="vd-card">
            <h2 class="vd-title">{{ __('Pack the bag before you leave') }}</h2>
            <p class="vd-muted">{{ __('On good internet, tap Pack bag. That copies your packs, My medicines, and known patients onto this computer. At the camp you write and print here even if the line is dead. The printed sheet is what the patient takes home. Uploading happens when signal returns.') }}</p>
            <div class="vd-row">
                <x-filament::button type="button" color="primary" x-on:click="packBag()" x-bind:disabled="packing">
                    <span x-text="packing ? '{{ __('Packing…') }}' : '{{ __('Pack bag') }}'"></span>
                </x-filament::button>
                <x-filament::button type="button" color="gray" x-on:click="upload()" x-bind:disabled="!online || pending === 0">
                    {{ __('Upload pending visits') }}
                </x-filament::button>
            </div>
            <p class="vd-status" style="margin-top: 0.75rem;" x-bind:class="online ? 'ok' : 'warn'" x-text="statusText"></p>
        </div>

        <div class="vd-card">
            <h2 class="vd-title">{{ __('Who is this for?') }}</h2>
            <p class="vd-muted">{{ __('Add a walk-in, or pick someone already in your bag. This is not the live queue — no Call next, no outdoor TV.') }}</p>
            <div class="vd-row">
                <div class="vd-field">
                    <label>{{ __('Patient name') }}</label>
                    <input type="text" x-model="form.name" autocomplete="name">
                </div>
                <div class="vd-field">
                    <label>{{ __('Phone') }}</label>
                    <input type="tel" x-model="form.phone" inputmode="numeric" placeholder="017XXXXXXXX">
                </div>
                <div class="vd-field">
                    <label>{{ __('Year of birth (optional)') }}</label>
                    <input type="number" x-model="form.yearOfBirth" inputmode="numeric"
                           min="{{ \App\Support\YearOfBirth::minYear() }}"
                           max="{{ \App\Support\YearOfBirth::maxYear() }}"
                           placeholder="1984">
                </div>
                <div class="vd-field">
                    <label>{{ __('Attach to session') }}</label>
                    <select x-model="form.sessionId">
                        <option value="">{{ __('-- Choose a session --') }}</option>
                        <template x-for="session in sessions" :key="session.id">
                            <option :value="String(session.id)" x-text="(session.chamber_name || '') + ' · ' + session.label"></option>
                        </template>
                    </select>
                </div>
            </div>
            <div class="vd-actions">
                <x-filament::button type="button" color="gray" x-on:click="startWalkIn()">
                    {{ __('Start this visit') }}
                </x-filament::button>
            </div>
            <input type="search" x-model="patientQuery" placeholder="{{ __('Search packed patients') }}" style="margin-top: 0.75rem; width: 100%; min-height: 2.5rem; padding: 0.4rem 0.6rem; border: 1px solid var(--gray-300); border-radius: 0.5rem; background: transparent; color: inherit;">
            <div class="vd-list" style="margin-top: 0.5rem;">
                <template x-for="person in filteredPatients" :key="person.id || person.phone">
                    <button type="button" class="vd-person" x-bind:class="current?.phone === person.phone ? 'is-on' : ''" x-on:click="openPatient(person)">
                        <span x-text="person.name + (person.age ? ', ' + person.age : '')"></span>
                        <span class="vd-muted" style="margin:0" x-text="person.phone"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="vd-card" x-show="current" x-cloak>
            <h2 class="vd-title" x-text="current ? current.name : ''"></h2>
            <p class="vd-muted" x-show="current?.last_visit?.diagnosis" x-text="current?.last_visit ? ('{{ __('Last visit') }}: ' + current.last_visit.diagnosis) : ''"></p>

            <div class="vd-field" style="margin-bottom: 0.75rem;">
                <label>{{ __('Diagnosis') }}</label>
                <input type="text" x-model="rx.diagnosisLabel">
            </div>
            <div class="vd-chips" style="margin-bottom: 0.75rem;">
                <template x-for="pack in packs" :key="pack.id">
                    <button type="button" class="vd-chip" x-on:click="applyPack(pack)" x-text="pack.name"></button>
                </template>
            </div>
            <div class="vd-field" style="margin-bottom: 0.5rem;">
                <label>{{ __('Add medicine') }}</label>
                <input type="search" x-model="medicineQuery" x-on:input="medicineHits = window.ChamberQOffline.searchMedicines(medicineQuery)" placeholder="{{ __('Type a brand from My medicines or a pack') }}">
            </div>
            <div class="vd-list" x-show="medicineHits.length" style="margin-bottom: 0.75rem;">
                <template x-for="hit in medicineHits" :key="hit.brand_name">
                    <button type="button" class="vd-person" x-on:click="addMedicine(hit)">
                        <span x-text="hit.label"></span>
                        <span class="vd-muted" style="margin:0" x-text="hit.generic_name || ''"></span>
                    </button>
                </template>
            </div>
            <template x-for="(row, index) in rx.items" :key="index">
                <div class="vd-med">
                    <input type="text" x-model="row.medicine_name" placeholder="{{ __('Brand') }}">
                    <input type="text" x-model="row.dose" placeholder="{{ __('Dose') }}">
                    <input type="text" x-model="row.frequency" placeholder="1+0+1">
                    <input type="text" x-model="row.duration" placeholder="{{ __('5 days') }}">
                    <button type="button" class="vd-chip" x-on:click="rx.items.splice(index, 1)">×</button>
                </div>
            </template>
            <div class="vd-chips" style="margin: 0.5rem 0;">
                <template x-for="freq in frequencies" :key="freq">
                    <button type="button" class="vd-chip" x-on:click="setLast('frequency', freq)" x-text="freq"></button>
                </template>
            </div>
            <div class="vd-field">
                <label>{{ __('Advice') }}</label>
                <textarea rows="2" x-model="rx.advice"></textarea>
            </div>
            <div class="vd-chips" style="margin-top: 0.5rem;">
                <button type="button" class="vd-chip" x-on:click="rx.followUpRelative = '3_days'">{{ __('3 days') }}</button>
                <button type="button" class="vd-chip" x-on:click="rx.followUpRelative = '1_week'">{{ __('1 week') }}</button>
                <button type="button" class="vd-chip" x-on:click="rx.followUpRelative = 'as_needed'">{{ __('As needed') }}</button>
            </div>
            <div class="vd-actions">
                <x-filament::button type="button" color="primary" icon="heroicon-o-printer" x-on:click="saveAndPrint()">
                    {{ __('Save & print') }}
                </x-filament::button>
                <x-filament::button type="button" color="gray" x-on:click="saveOnly()">
                    {{ __('Save on this computer') }}
                </x-filament::button>
            </div>
            <p class="vd-muted" x-show="savedLocal" x-cloak>{{ __('Saved on this computer. It will upload when the line is back.') }}</p>
        </div>
    </div>

    @once
    @script
    <script>
        Alpine.data('visitingDay', () => ({
            packing: false,
            online: true,
            pending: 0,
            packedAt: null,
            patientQuery: '',
            medicineQuery: '',
            medicineHits: [],
            patients: [],
            sessions: [],
            packs: [],
            current: null,
            savedLocal: false,
            frequencies: ['1+0+1', '1+1+1', '0+0+1', '1+0+0', 'SOS'],
            form: { name: '', phone: '', yearOfBirth: '', sessionId: '' },
            rx: { diagnosisLabel: '', advice: '', followUpRelative: '', items: [] },

            get filteredPatients() {
                const q = this.patientQuery.trim().toLowerCase();
                if (!q) return this.patients.slice(0, 30);
                return this.patients.filter((p) => `${p.name} ${p.phone}`.toLowerCase().includes(q)).slice(0, 30);
            },

            get statusText() {
                const packed = this.packedAt ? `Bag packed ${this.packedAt}.` : 'Bag not packed yet.';
                const net = this.online ? 'Online.' : 'No internet.';
                const pend = this.pending ? ` ${this.pending} visit(s) waiting to upload.` : '';
                return `${net} ${packed}${pend}`;
            },

            boot() {
                const applyBag = (bag) => {
                    if (!bag) return;
                    this.patients = bag.patients || [];
                    this.sessions = bag.sessions || [];
                    this.packs = bag.packs || [];
                    this.packedAt = bag.packed_at ? new Date(bag.packed_at).toLocaleString() : null;
                    if (!this.form.sessionId && this.sessions[0]) {
                        this.form.sessionId = String(this.sessions[0].id);
                    }
                };
                applyBag(window.ChamberQOffline?.getBag());
                window.ChamberQOffline?.loadBag()?.then(applyBag);
                window.ChamberQOffline?.onChange((snap) => {
                    this.online = snap.online;
                    this.pending = snap.pending;
                    if (snap.packedAt) this.packedAt = new Date(snap.packedAt).toLocaleString();
                });
                this.online = window.ChamberQOffline?.isLikelyOnline() ?? navigator.onLine;
                this.pending = window.ChamberQOffline?.pendingCount?.() ?? 0;
            },

            async packBag() {
                this.packing = true;
                try {
                    const bag = await window.ChamberQOffline.packBag();
                    this.patients = bag.patients || [];
                    this.sessions = bag.sessions || [];
                    this.packs = bag.packs || [];
                    this.packedAt = new Date(bag.packed_at).toLocaleString();
                    if (!this.form.sessionId && this.sessions[0]) {
                        this.form.sessionId = String(this.sessions[0].id);
                    }
                } catch (e) {
                    alert('Could not pack the bag — need internet for this step.');
                } finally {
                    this.packing = false;
                }
            },

            async upload() {
                try {
                    await window.ChamberQOffline.flush();
                } catch (e) {
                    alert('Upload failed. Visits are still on this computer.');
                }
            },

            startWalkIn() {
                if (!this.form.name.trim() || !this.form.phone.trim()) {
                    alert('Name and phone are required.');
                    return;
                }
                const year = parseInt(this.form.yearOfBirth, 10);
                const age = Number.isFinite(year) ? (new Date().getFullYear() - year) : '';
                this.openPatient({
                    name: this.form.name.trim(),
                    phone: this.form.phone.trim(),
                    age: age === '' || age < 0 ? '' : age,
                    year_of_birth: Number.isFinite(year) ? year : '',
                    last_visit: null,
                });
            },

            openPatient(person) {
                this.current = person;
                this.savedLocal = false;
                this.rx = {
                    diagnosisLabel: '',
                    advice: person.last_visit?.advice || '',
                    followUpRelative: '',
                    items: (person.last_visit?.items || []).map((row) => ({ ...row })),
                };
                this.form.name = person.name;
                this.form.phone = person.phone;
                this.form.yearOfBirth = person.year_of_birth || '';
            },

            applyPack(pack) {
                (pack.items || []).forEach((item) => this.addMedicine(item));
                if (pack.advice && !this.rx.advice) this.rx.advice = pack.advice;
            },

            addMedicine(hit) {
                this.rx.items.push({
                    medicine_name: (hit.medicine_name || hit.brand_name || '').toUpperCase(),
                    generic_name: hit.generic_name || null,
                    dose: hit.dose || null,
                    frequency: hit.frequency || null,
                    duration: hit.duration || null,
                    timing: hit.timing || null,
                    instructions: hit.instructions || null,
                    indication: hit.indication || null,
                });
                this.medicineQuery = '';
                this.medicineHits = [];
            },

            setLast(field, value) {
                const last = this.rx.items[this.rx.items.length - 1];
                if (last) last[field] = value;
            },

            payload() {
                const diagnosis = (this.rx.diagnosisLabel || '').trim();
                const follow = this.rx.followUpRelative;
                const inDays = (n) => {
                    const d = new Date();
                    d.setDate(d.getDate() + n);
                    return d.toISOString().slice(0, 10);
                };
                return {
                    diagnosis: diagnosis ? `__free__:${diagnosis}` : '',
                    diagnosis_label: diagnosis,
                    advice: this.rx.advice,
                    follow_up_relative: follow === '1_week' || follow === 'as_needed' ? follow : null,
                    follow_up_date: follow === '3_days' ? inDays(3) : null,
                    prescription_items: this.rx.items.filter((row) => (row.medicine_name || '').trim()),
                };
            },

            async saveOnly() {
                if (!this.current) return;
                if (!this.form.sessionId) {
                    alert('Pick which chamber session this visit belongs to.');
                    return;
                }
                await window.ChamberQOffline.enqueue({
                    type: 'visiting_visit',
                    schedule_session_id: Number(this.form.sessionId) || this.form.sessionId,
                    patient_name: this.current.name,
                    patient_phone: this.current.phone,
                    year_of_birth: this.current.year_of_birth || this.form.yearOfBirth || null,
                    visit_date: new Date().toISOString().slice(0, 10),
                    data: this.payload(),
                });
                this.savedLocal = true;
                if (window.ChamberQOffline.isLikelyOnline()) {
                    window.ChamberQOffline.flush().catch(() => {});
                }
            },

            async saveAndPrint() {
                await this.saveOnly();
                window.ChamberQOffline.printPad({
                    patient: this.current,
                    data: this.payload(),
                });
            },
        }));
    </script>
    @endscript
    @endonce
</x-filament-panels::page>

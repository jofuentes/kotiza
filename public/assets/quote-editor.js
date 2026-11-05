// Editor de cotizaciones (Alpine.js).
// Los totales replican exactamente src/Domain/Quotes/QuoteCalculator.php; el servidor es la autoridad.
(function () {
    const r2 = v => Math.round((Number(v) + Number.EPSILON) * 100) / 100;
    const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
    const uid = () => 'new:' + Math.random().toString(36).slice(2, 10);

    function discount(base, type, value) {
        value = Math.max(0, num(value));
        const d = type === 'amount' ? value : base * Math.min(100, value) / 100;
        return r2(Math.min(base, d));
    }

    window.quoteEditor = function (cfg) {
        return {
            state: cfg.state,
            providers: cfg.providers,
            units: cfg.units,
            urls: cfg.urls,
            saving: false,
            savedJson: '',
            errors: {},
            itemErrors: {},
            message: null,
            openNotes: {},
            dragKey: null,
            dropTarget: null,
            // Buscador
            drawer: false,
            search: { q: '', inStock: false, selected: cfg.providers.map(p => p.code), results: [], status: {}, loading: false, error: '', section: '', adding: {} },

            init() {
                this.normalize();
                this.savedJson = JSON.stringify(this.payload());
                this.$watch('state.client', c => { this.state.client_id = c ? c.id : null; });
                window.addEventListener('beforeunload', e => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
                window.addEventListener('keydown', e => {
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); this.save(); }
                });
                setInterval(() => { if (this.dirty && !this.saving && this.state.editable) this.save(true); }, 30000);
            },

            // ---------- Estado ----------
            get dirty() { return this.state.editable && JSON.stringify(this.payload()) !== this.savedJson; },

            payload() {
                const s = this.state;
                return {
                    title: s.title, client_id: s.client_id, issue_date: s.issue_date, valid_until: s.valid_until,
                    currency: s.currency, exchange_rate: s.exchange_rate, vat_rate: s.vat_rate,
                    global_discount_type: s.global_discount_type, global_discount_value: s.global_discount_value,
                    notes: s.notes, terms: s.terms,
                    sections: s.sections.map(x => ({ key: x.key, title: x.title })),
                    items: s.items.map(it => ({
                        id: it.id, key: it.key, section_key: it.section_key, description: it.description, unit: it.unit,
                        qty: it.qty, price_manual: it.price_manual === '' ? null : it.price_manual,
                        discount_type: it.discount_type, discount_value: it.discount_value, notes: it.notes,
                        cost: it.type === 'manual' ? (it.cost === '' ? null : it.cost) : undefined, sat_key: it.sat_key,
                    })),
                };
            },

            applyState(st) {
                const keepClient = this.state.client;
                this.state = st;
                if (!st.client && keepClient && st.client_id === keepClient.id) this.state.client = keepClient;
                this.normalize();
                this.savedJson = JSON.stringify(this.payload());
            },

            /** Ordena las partidas: primero sin sección, luego por orden de sección (estable) */
            normalize() {
                const order = { '': -1 };
                this.state.sections.forEach((s, i) => { order[s.key] = i; });
                this.state.items.forEach(it => { if (!(it.section_key in order)) it.section_key = ''; });
                this.state.items = this.state.items
                    .map((it, i) => [it, i])
                    .sort((a, b) => (order[a[0].section_key] - order[b[0].section_key]) || (a[1] - b[1]))
                    .map(x => x[0]);
            },

            get groups() {
                const groups = [{ key: '', title: null, items: [] }];
                this.state.sections.forEach(s => groups.push({ key: s.key, title: s.title, section: s, items: [] }));
                const byKey = Object.fromEntries(groups.map(g => [g.key, g]));
                this.state.items.forEach(it => (byKey[it.section_key] || groups[0]).items.push(it));
                return groups;
            },

            // ---------- Cálculos ----------
            unitPrice(it) { return it.price_manual !== null && it.price_manual !== '' ? num(it.price_manual) : num(it.price_calculated); },
            unitCost(it) {
                if (!this.state.show_cost) return null;
                if (it.type === 'manual') return it.cost === null || it.cost === '' ? null : num(it.cost);
                return it.unit_cost;
            },
            line(it) {
                const qty = Math.max(0, num(it.qty));
                const gross = r2(qty * Math.max(0, this.unitPrice(it)));
                const disc = discount(gross, it.discount_type, it.discount_value);
                const total = r2(gross - disc);
                const uc = this.unitCost(it);
                const cost = uc === null ? null : r2(qty * uc);
                const profit = cost === null ? null : r2(total - cost);
                const margin = cost === null || total <= 0 ? null : Math.round(profit / total * 10000) / 100;
                return { gross, discount: disc, total, cost, profit, margin };
            },
            get totals() {
                let subtotal = 0, costTotal = 0, revenueWithCost = 0, costKnown = true;
                this.state.items.forEach(it => {
                    const l = this.line(it);
                    subtotal += l.total;
                    if (l.cost === null) costKnown = false; else { costTotal += l.cost; revenueWithCost += l.total; }
                });
                subtotal = r2(subtotal);
                const gd = discount(subtotal, this.state.global_discount_type, this.state.global_discount_value);
                const taxable = r2(subtotal - gd);
                const tax = r2(taxable * Math.max(0, num(this.state.vat_rate)) / 100);
                let profit = null, margin = null;
                if (revenueWithCost > 0) {
                    const revenue = revenueWithCost * (subtotal > 0 ? taxable / subtotal : 1);
                    profit = r2(revenue - costTotal);
                    margin = revenue > 0 ? Math.round(profit / revenue * 10000) / 100 : null;
                }
                return { subtotal, discount: gd, taxable, tax, total: r2(taxable + tax), cost: r2(costTotal), costKnown, profit, margin };
            },
            lowMargin(m) { return m !== null && m < this.state.min_margin; },
            money(v, cur) {
                if (v === null || v === undefined || v === '') return '—';
                return new Intl.NumberFormat('es-MX', { style: 'currency', currency: cur || this.state.currency }).format(v);
            },

            // ---------- Partidas ----------
            addManual(preset) {
                const presets = {
                    servicio: { description: 'Servicio', unit: 'Servicio' },
                    mano: { description: 'Mano de obra', unit: 'Servicio' },
                    instalacion: { description: 'Instalación y configuración', unit: 'Servicio' },
                    material: { description: 'Material diverso (conectores, tubería, accesorios)', unit: 'Lote' },
                    libre: { description: '', unit: 'Pieza' },
                };
                const p = presets[preset] || presets.libre;
                const it = {
                    id: null, key: uid(), type: 'manual', section_key: this.search.section || '', provider: null,
                    model: '', brand: '', sku: null, description: p.description, image_url: null, unit: p.unit, sat_key: '',
                    qty: 1, price_calculated: null, price_manual: 0, price_rule: null, discount_type: 'percent', discount_value: 0,
                    notes: '', cost: null, cost_currency: null, unit_cost: null,
                };
                this.state.items.push(it);
                this.normalize();
                this.$nextTick(() => document.getElementById('desc-' + it.key)?.focus());
            },
            removeItem(it) {
                if (!confirm('¿Quitar esta partida?')) return;
                this.state.items = this.state.items.filter(x => x !== it);
            },
            duplicateItem(it) {
                if (it.type !== 'manual') return;
                const copy = Object.assign({}, JSON.parse(JSON.stringify(it)), { id: null, key: uid() });
                this.state.items.splice(this.state.items.indexOf(it) + 1, 0, copy);
            },
            moveItem(it, dir) {
                const list = this.state.items, i = list.indexOf(it), j = i + dir;
                if (j < 0 || j >= list.length) return;
                if (list[j].section_key !== it.section_key) { // cruzar a la sección vecina
                    const order = ['', ...this.state.sections.map(s => s.key)];
                    const k = order.indexOf(it.section_key) + dir;
                    if (k >= 0 && k < order.length) { it.section_key = order[k]; this.normalize(); }
                    return;
                }
                [list[i], list[j]] = [list[j], list[i]];
            },
            togglePriceMode(it) {
                it.price_manual = it.price_manual === null || it.price_manual === '' ? this.unitPrice(it) : null;
            },

            // Arrastrar y soltar
            dragStart(it, e) { this.dragKey = it.key; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', it.key); },
            dragEnd() { this.dragKey = null; this.dropTarget = null; },
            dropOn(target, groupKey) {
                const dragged = this.state.items.find(x => x.key === this.dragKey);
                this.dropTarget = null;
                if (!dragged || dragged === target) return;
                const list = this.state.items.filter(x => x !== dragged);
                dragged.section_key = groupKey;
                const idx = target ? list.indexOf(target) : list.length;
                list.splice(idx, 0, dragged);
                this.state.items = list;
                this.normalize();
            },

            // ---------- Secciones ----------
            addSection() {
                const title = prompt('Nombre de la sección (ej. Cámaras, Cableado, Mano de obra):');
                if (title && title.trim()) this.state.sections.push({ key: uid(), title: title.trim().slice(0, 160) });
            },
            renameSection(s) {
                const title = prompt('Nombre de la sección:', s.title);
                if (title && title.trim()) s.title = title.trim().slice(0, 160);
            },
            removeSection(s) {
                if (!confirm(`¿Quitar la sección «${s.title}»? Sus partidas pasan a «Sin sección».`)) return;
                this.state.items.forEach(it => { if (it.section_key === s.key) it.section_key = ''; });
                this.state.sections = this.state.sections.filter(x => x !== s);
                this.normalize();
            },
            moveSection(s, dir) {
                const l = this.state.sections, i = l.indexOf(s), j = i + dir;
                if (j < 0 || j >= l.length) return;
                [l[i], l[j]] = [l[j], l[i]];
                this.normalize();
            },

            // ---------- Guardar ----------
            async save(silent = false) {
                if (this.saving || !this.state.editable) return;
                this.normalize();
                this.saving = true; this.errors = {}; this.itemErrors = {};
                const sent = this.state.items.map(it => it.key);
                try {
                    const res = await fetch(this.urls.save, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrfToken() },
                        body: JSON.stringify(this.payload()),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.errors = data.errors || {};
                        Object.entries(this.errors).forEach(([k, msg]) => {
                            const m = k.match(/^items\.(\d+)\.(\w+)$/);
                            if (m) (this.itemErrors[sent[+m[1]]] ||= {})[m[2]] = msg;
                        });
                        this.flash('error', data.error || 'No se pudo guardar.');
                        return;
                    }
                    this.applyState(data.state);
                    if (!silent) this.flash('success', 'Cotización guardada.');
                } catch (e) {
                    this.flash('error', 'Sin conexión con el servidor. Tus cambios siguen aquí; intenta de nuevo.');
                } finally { this.saving = false; }
            },
            flash(type, text) {
                this.message = { type, text };
                clearTimeout(this._flashT);
                this._flashT = setTimeout(() => { this.message = null; }, type === 'error' ? 8000 : 2500);
            },
            fieldErrors() {
                return Object.entries(this.errors).filter(([k]) => !k.startsWith('items.')).map(([, v]) => v);
            },

            // Moneda y tipo de cambio: se guardan de inmediato para recalcular precios en el servidor
            async changeCurrency(e) {
                const hasManual = this.state.items.some(it => it.price_manual !== null && it.price_manual !== '');
                if (e && e.target.id === 'q-cur' && hasManual &&
                    !confirm('Los precios capturados a mano se convertirán con el tipo de cambio (redondeados a centavos). ¿Continuar?')) {
                    this.state.currency = this.state.currency === 'MXN' ? 'USD' : 'MXN';
                    return;
                }
                await this.save(true);
            },
            async refreshRate() {
                const res = await fetch(this.urls.rate, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (!data.rate) { this.flash('error', 'No hay tipo de cambio disponible: captúralo manualmente.'); return; }
                this.state.exchange_rate = data.rate;
                await this.save(true);
                this.flash('success', `Tipo de cambio actualizado: ${data.rate} (${data.source}).`);
            },

            // ---------- Buscador de productos ----------
            openDrawer(sectionKey = '') {
                this.search.section = sectionKey;
                this.drawer = true;
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },
            async runSearch() {
                const s = this.search;
                if (s.q.trim().length < 2) return;
                s.loading = true; s.error = '';
                const params = new URLSearchParams({ q: s.q.trim(), page: 1, in_stock: s.inStock ? '1' : '0' });
                s.selected.forEach(c => params.append('providers[]', c));
                try {
                    const res = await fetch(this.urls.search + '?' + params, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error ' + res.status);
                    s.results = data.products; s.status = data.providers;
                } catch (e) { s.error = e.message; s.results = []; }
                finally { s.loading = false; }
            },
            async addProduct(p, qty = 1) {
                const key = p.provider + ':' + p.providerId;
                if (this.dirty) await this.save(true); // no perder cambios locales al recibir el estado del servidor
                this.search.adding[key] = true;
                try {
                    const res = await fetch(this.urls.add, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrfToken() },
                        body: JSON.stringify({ provider: p.provider, product_id: p.providerId, qty, section_key: this.search.section }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'No se pudo agregar.');
                    this.applyState(data.state);
                    this.flash(data.warning ? 'error' : 'success', data.warning ? 'Agregado con precio guardado: ' + data.warning : `${p.model} agregado.`);
                } catch (e) { this.flash('error', e.message); }
                finally { delete this.search.adding[key]; }
            },
            inQuote(p) { return this.state.items.some(it => it.provider === p.provider && it.provider_product_id === String(p.providerId)); },
            ago(min) { return min < 60 ? `${min} min` : min < 1440 ? `${Math.floor(min / 60)} h` : `${Math.floor(min / 1440)} días`; },
        };
    };
})();

<div class="mb-6">
    <h1 class="text-2xl font-semibold">Productos</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Busca en el catálogo de todos tus proveedores activos al mismo tiempo.</p>
</div>

<?php if (!$activeProviders): ?>
<div class="card p-8 text-center">
    <p class="font-medium">No hay proveedores activos.</p>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        <?= $isAdmin ? 'Conecta al menos uno en' : 'Pide a un administrador que conecte uno en' ?> la sección Proveedores.
    </p>
    <?php if ($isAdmin): ?><a href="<?= e(url('/proveedores')) ?>" class="btn btn-primary mt-4">Ir a Proveedores</a><?php endif; ?>
</div>
<?php else: ?>
<div x-data="catalog(<?= e(json_encode(['providers' => $activeProviders, 'showCost' => $showCost])) ?>)" x-init="init()">
    <form @submit.prevent="search(1)" class="card flex flex-wrap items-center gap-3 p-4">
        <div class="relative min-w-[16rem] flex-1">
            <svg class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="search" x-model="q" x-ref="q" class="input pl-10" placeholder="Modelo, marca o descripción… ej. DS-2CD2143G2-I, cámara domo 4MP" autofocus>
        </div>
        <template x-for="p in providers" :key="p.code">
            <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" :value="p.code" x-model="selected" class="h-4 w-4 accent-brand"><span x-text="p.name"></span></label>
        </template>
        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" x-model="inStock" class="h-4 w-4 accent-brand"> Solo con existencia</label>
        <button class="btn btn-primary" :disabled="loading || q.trim().length < 2">
            <svg x-show="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3"/></svg>
            Buscar
        </button>
    </form>

    <!-- Estado por proveedor -->
    <div class="mt-3 flex flex-wrap gap-2 text-xs" x-show="Object.keys(status).length">
        <template x-for="(s, code) in status" :key="code">
            <span class="rounded-full px-2.5 py-1"
                  :class="!s.ok ? (s.stale ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200') : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                  :title="s.error || ''">
                <strong x-text="s.name"></strong>:
                <span x-show="s.ok" x-text="s.total + ' resultados' + (s.cached ? ' · caché de hace ' + ago(s.age_minutes) : ' · ' + s.ms + ' ms')"></span>
                <span x-show="!s.ok" x-text="s.stale ? 'sin respuesta, mostrando datos de hace ' + ago(s.age_minutes) : s.error"></span>
            </span>
        </template>
    </div>

    <div class="mt-4 flex flex-col gap-4 lg:flex-row" x-show="products.length">
        <!-- Filtros -->
        <aside class="card h-max shrink-0 space-y-4 p-4 text-sm lg:w-60">
            <div>
                <div class="label">Marca</div>
                <select x-model="brand" class="input"><option value="">Todas</option><template x-for="b in facets.brands" :key="b[0]"><option :value="b[0]" x-text="`${b[0]} (${b[1]})`"></option></template></select>
            </div>
            <div>
                <div class="label">Categoría</div>
                <select x-model="category" class="input"><option value="">Todas</option><template x-for="c in facets.categories" :key="c[0]"><option :value="c[0]" x-text="`${c[0]} (${c[1]})`"></option></template></select>
            </div>
            <div>
                <div class="label">Ordenar</div>
                <select x-model="sort" class="input">
                    <option value="relevance">Relevancia</option>
                    <option value="price_asc" x-show="showCost">Costo: menor a mayor</option>
                    <option value="price_desc" x-show="showCost">Costo: mayor a menor</option>
                    <option value="stock">Mayor existencia</option>
                </select>
            </div>
            <p class="text-xs text-slate-500" x-text="`${filtered.length} de ${products.length} productos`"></p>
        </aside>

        <!-- Resultados -->
        <div class="card min-w-0 flex-1 overflow-hidden">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="p in filtered" :key="p.provider + p.providerId">
                    <li class="flex gap-4 p-4" :class="p.cheapest && 'bg-emerald-50/60 dark:bg-emerald-950/30'">
                        <button type="button" @click="open(p)" class="h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700">
                            <img x-show="p.images[0]" :src="p.images[0]" loading="lazy" alt="" class="h-full w-full object-contain">
                        </button>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium dark:bg-slate-800" x-text="p.providerName"></span>
                                <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="p.brand"></span>
                                <span class="text-slate-400" x-text="p.category"></span>
                                <span x-show="p.cheapest" class="rounded bg-emerald-600 px-1.5 py-0.5 font-medium text-white">Más barato</span>
                            </div>
                            <button type="button" @click="open(p)" class="mt-0.5 block text-left font-mono text-sm font-semibold hover:underline" x-text="p.model"></button>
                            <p class="line-clamp-2 text-sm text-slate-600 dark:text-slate-300" x-text="p.title"></p>
                            <p x-show="p.note" class="mt-1 text-xs text-amber-600 dark:text-amber-400" x-text="p.note"></p>
                        </div>
                        <div class="w-32 shrink-0 text-right text-sm">
                            <div x-show="showCost">
                                <div class="text-xs text-slate-500">Costo</div>
                                <div class="font-semibold" x-text="p.cost !== null ? money(p.cost, p.currency) : '—'"></div>
                            </div>
                            <div class="mt-1 text-xs" :class="p.stock > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'"
                                 :title="Object.entries(p.stockByWarehouse || {}).map(([w, n]) => `${w}: ${n}`).join('\n')"
                                 x-text="p.stock > 0 ? `${p.stock} en existencia` : 'Sin existencia'"></div>
                        </div>
                    </li>
                </template>
            </ul>
            <div class="border-t border-slate-100 p-3 text-center dark:border-slate-800" x-show="page < maxPages">
                <button type="button" class="btn btn-secondary" @click="search(page + 1)" :disabled="loading">Cargar más resultados</button>
            </div>
        </div>
    </div>

    <div x-show="searched && !loading && !products.length" class="card mt-4 p-8 text-center text-sm text-slate-500">
        No se encontraron productos<span x-show="error">: <span x-text="error"></span></span>.
    </div>

    <!-- Ficha -->
    <div x-cloak x-show="detail" @keydown.escape.window="detail = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="detail = null">
        <div class="card max-h-[90vh] w-full max-w-3xl overflow-y-auto p-6">
            <template x-if="detail">
                <div>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs text-slate-500"><span x-text="detail.p.providerName || detail.p.provider"></span> · <span x-text="detail.p.brand"></span> · ID <span x-text="detail.p.providerId"></span></div>
                            <h2 class="font-mono text-lg font-semibold" x-text="detail.p.model"></h2>
                            <p class="text-sm" x-text="detail.p.title"></p>
                        </div>
                        <button type="button" @click="detail = null" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Cerrar">✕</button>
                    </div>
                    <p x-show="detail.loading" class="mt-4 text-sm text-slate-500">Cargando ficha…</p>
                    <p x-show="detail.error" class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200" x-text="detail.error + (detail.stale ? ` (mostrando datos de hace ${ago(detail.age)})` : '')"></p>
                    <div class="mt-4 grid gap-6 sm:grid-cols-2">
                        <div>
                            <div class="aspect-square overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700">
                                <img :src="detail.img || detail.p.images[0]" alt="" class="h-full w-full object-contain">
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="img in detail.p.images.slice(0, 8)" :key="img">
                                    <button type="button" @click="detail.img = img" class="h-12 w-12 overflow-hidden rounded border border-slate-200 bg-white dark:border-slate-700"><img :src="img" alt="" class="h-full w-full object-contain"></button>
                                </template>
                            </div>
                        </div>
                        <dl class="grid h-max grid-cols-2 gap-x-3 gap-y-1.5 text-sm">
                            <template x-if="showCost"><div class="contents"><dt class="text-slate-500">Costo</dt><dd class="font-semibold" x-text="detail.p.cost !== null ? money(detail.p.cost, detail.p.currency) : '—'"></dd></div></template>
                            <dt class="text-slate-500">Existencia</dt><dd x-text="detail.p.stock"></dd>
                            <template x-for="[w, n] in Object.entries(detail.p.stockByWarehouse || {})" :key="w"><div class="contents"><dt class="pl-3 text-xs capitalize text-slate-400" x-text="w"></dt><dd class="text-xs" x-text="n"></dd></div></template>
                            <dt class="text-slate-500">Categoría</dt><dd x-text="detail.p.category || '—'"></dd>
                            <dt class="text-slate-500">Clave SAT</dt><dd x-text="detail.p.satKey || '—'"></dd>
                            <dt class="text-slate-500">Unidad SAT</dt><dd x-text="(detail.p.satUnit || '—') + (detail.p.unit ? ' · ' + detail.p.unit : '')"></dd>
                            <dt class="text-slate-500">Garantía</dt><dd x-text="detail.p.warranty || '—'"></dd>
                            <dd class="col-span-2 mt-2" x-show="detail.p.url"><a :href="detail.p.url" target="_blank" rel="noopener" class="text-brand underline dark:text-sky-400">Ver ficha en el sitio del proveedor ↗</a></dd>
                        </dl>
                    </div>
                    <ul x-show="detail.p.features?.length" class="mt-4 list-disc space-y-0.5 pl-5 text-sm"><template x-for="f in detail.p.features" :key="f"><li x-text="f"></li></template></ul>
                    <div x-show="detail.p.description" class="prose-sm mt-4 max-w-none space-y-2 border-t border-slate-200 pt-4 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-300 [&_li]:ml-5 [&_li]:list-disc [&_table]:w-full [&_td]:border [&_td]:border-slate-200 [&_td]:px-2 [&_td]:py-1 dark:[&_td]:border-slate-700" x-html="detail.p.description"></div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function catalog(cfg) {
    return {
        providers: cfg.providers, showCost: cfg.showCost,
        selected: cfg.providers.map(p => p.code), q: '', inStock: false,
        products: [], status: {}, page: 1, maxPages: 1, loading: false, searched: false, error: '',
        brand: '', category: '', sort: 'relevance', detail: null,
        init() {
            const params = new URLSearchParams(location.search);
            if (params.get('q')) { this.q = params.get('q'); this.search(1); }
        },
        async search(page) {
            if (this.q.trim().length < 2) return;
            this.loading = true; this.error = '';
            const params = new URLSearchParams({ q: this.q.trim(), page, in_stock: this.inStock ? '1' : '0' });
            this.selected.forEach(c => params.append('providers[]', c));
            try {
                const res = await fetch(<?= json_encode(url('/productos/buscar')) ?> + '?' + params, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Error ' + res.status);
                this.products = page === 1 ? data.products : this.products.concat(data.products);
                this.status = data.providers;
                this.page = page;
                this.maxPages = Math.max(1, ...Object.values(data.providers).map(s => s.pages || 1));
                if (page === 1) { this.brand = ''; this.category = ''; history.replaceState(null, '', '?q=' + encodeURIComponent(this.q.trim())); }
            } catch (e) { this.error = e.message; if (page === 1) this.products = []; }
            finally { this.loading = false; this.searched = true; }
        },
        get facets() {
            const count = key => Object.entries(this.products.reduce((a, p) => { if (p[key]) a[p[key]] = (a[p[key]] || 0) + 1; return a; }, {})).sort((a, b) => b[1] - a[1]);
            return { brands: count('brand'), categories: count('category') };
        },
        get filtered() {
            let list = this.products.filter(p => (!this.brand || p.brand === this.brand) && (!this.category || p.category === this.category));
            const cost = p => p.cost ?? Infinity;
            if (this.sort === 'price_asc') list = [...list].sort((a, b) => cost(a) - cost(b));
            if (this.sort === 'price_desc') list = [...list].sort((a, b) => (b.cost ?? -1) - (a.cost ?? -1));
            if (this.sort === 'stock') list = [...list].sort((a, b) => b.stock - a.stock);
            return list;
        },
        async open(p) {
            this.detail = { p, loading: true, error: '', img: null };
            try {
                const res = await fetch(<?= json_encode(url('/productos/')) ?> + p.provider + '/' + encodeURIComponent(p.providerId), { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (this.detail?.p !== p) return;
                if (data.product) this.detail.p = Object.assign({ providerName: p.providerName }, data.product);
                this.detail.error = data.error || ''; this.detail.stale = data.stale; this.detail.age = data.age_minutes;
            } catch (e) { this.detail.error = 'No se pudo cargar la ficha.'; }
            finally { if (this.detail) this.detail.loading = false; }
        },
        money(v, cur) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: cur || 'MXN' }).format(v) + ' ' + (cur || ''); },
        ago(min) { return min < 60 ? `${min} min` : min < 1440 ? `${Math.floor(min / 60)} h` : `${Math.floor(min / 1440)} días`; },
    };
}
</script>
<?php endif; ?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold">Proveedores</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Conecta tus cuentas de distribuidor. Cada empresa usa sus propias credenciales, que se guardan cifradas.</p>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    <?php foreach ($providers as $p): $row = $p['row']; ?>
    <div class="card flex flex-col p-5" x-data="providerTest(<?= e(json_encode($p['code'])) ?>)">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold"><?= e($p['name']) ?></h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e($p['description']) ?></p>
            </div>
            <?php if (!$row || !$p['configured']): ?>
                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Sin configurar</span>
            <?php elseif ($row['is_active']): ?>
                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Activo</span>
            <?php else: ?>
                <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">Inactivo</span>
            <?php endif; ?>
        </div>

        <?php if ($row): ?>
        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt class="text-slate-500 dark:text-slate-400">Prioridad</dt><dd><?= (int) $row['priority'] ?></dd>
            <dt class="text-slate-500 dark:text-slate-400">Margen por defecto</dt><dd><?= $row['default_margin'] !== null ? e((float) $row['default_margin']) . ' %' : 'El global de la empresa' ?></dd>
            <dt class="text-slate-500 dark:text-slate-400">Última prueba</dt>
            <dd x-ref="last">
                <?php if ($row['last_test_at']): ?>
                    <span class="<?= $row['last_test_ok'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>"><?= $row['last_test_ok'] ? '✔' : '✖' ?></span>
                    <?= e(date('d/m/Y H:i', strtotime($row['last_test_at']))) ?>
                <?php else: ?>Nunca<?php endif; ?>
            </dd>
        </dl>
        <?php endif; ?>

        <div x-cloak x-show="result" class="mt-4 rounded-lg border px-4 py-3 text-sm" :class="result?.ok ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200'">
            <p class="font-medium" x-text="result?.message"></p>
            <dl class="mt-1 grid grid-cols-2 gap-x-4 text-xs" x-show="result?.details">
                <template x-for="(v, k) in (result?.details || {})" :key="k"><div class="contents"><dt x-text="k"></dt><dd class="font-medium" x-text="v"></dd></div></template>
            </dl>
        </div>

        <div class="mt-auto flex flex-wrap gap-2 pt-5">
            <a href="<?= e(url('/proveedores/' . $p['code'])) ?>" class="btn btn-primary"><?= $row ? 'Configurar' : 'Conectar' ?></a>
            <?php if ($row && $p['configured']): ?>
                <button type="button" class="btn btn-secondary" @click="run()" :disabled="loading">
                    <svg x-show="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3"/></svg>
                    <span x-text="loading ? 'Probando…' : 'Probar conexión'"></span>
                </button>
                <a href="<?= e(url('/proveedores/' . $p['code'] . '/log')) ?>" class="btn btn-secondary">Ver log</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('/proveedores/cache')) ?>" class="card mt-6 grid items-end gap-4 p-5 sm:grid-cols-4">
    <?= csrf_field() ?>
    <div class="sm:col-span-4">
        <h2 class="font-semibold">Caché de productos</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Evita consultar al proveedor en cada búsqueda. Si un proveedor no responde se usa el último dato guardado, indicando su antigüedad.</p>
    </div>
    <div>
        <label class="label" for="cache_price_minutes">Vigencia de precios (minutos)</label>
        <input class="input" type="number" min="0" max="10080" id="cache_price_minutes" name="cache_price_minutes" value="<?= (int) $cache['cache_price_minutes'] ?>">
    </div>
    <div>
        <label class="label" for="cache_detail_hours">Vigencia de fichas (horas)</label>
        <input class="input" type="number" min="0" max="2160" id="cache_detail_hours" name="cache_detail_hours" value="<?= (int) $cache['cache_detail_hours'] ?>">
    </div>
    <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="clear" value="1" class="h-4 w-4 accent-brand"> Vaciar caché al guardar</label>
    <div class="text-right"><button class="btn btn-secondary">Guardar</button></div>
</form>

<script>
function providerTest(code) {
    return {
        loading: false, result: null,
        async run() {
            this.loading = true; this.result = null;
            try {
                const res = await fetch(<?= json_encode(url('/proveedores/')) ?> + code + '/probar', {
                    method: 'POST', headers: { 'X-CSRF-Token': csrfToken(), 'Accept': 'application/json' }
                });
                this.result = await res.json();
                if (this.$refs.last && this.result.tested_at) {
                    this.$refs.last.innerHTML = (this.result.ok ? '<span class="text-emerald-600">✔</span> ' : '<span class="text-red-600">✖</span> ') + this.result.tested_at;
                }
            } catch (e) {
                this.result = { ok: false, message: 'No se pudo completar la prueba: ' + e.message };
            } finally { this.loading = false; }
        }
    };
}
</script>

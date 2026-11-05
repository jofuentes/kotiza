<?php
use App\Pricing\PricingRule;

$c = $company;
$methodHelp = [
    'markup' => 'precio = costo × (1 + %)',
    'margin' => 'precio = costo ÷ (1 − %)',
    'list' => 'precio = lista del proveedor sin IVA (el cliente ve la lista con IVA)',
];
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Precios</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Cómo se calcula el precio de venta sugerido de cada producto. Gana la regla más específica: producto → marca → categoría → proveedor → global. El precio manual de una partida siempre tiene la última palabra.</p>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
<div class="min-w-0 space-y-6">

    <!-- Global -->
    <form method="post" action="<?= e(url('/precios')) ?>" class="card space-y-5 p-6"
          x-data="{ method: <?= e(json_encode($c['price_method'])) ?>, rounding: <?= e(json_encode($c['price_rounding'])) ?>, mode: <?= e(json_encode($c['exchange_mode'])) ?> }">
        <?= csrf_field() ?>
        <h2 class="font-semibold">Regla global</h2>
        <div class="grid gap-3 md:grid-cols-3">
            <?php foreach (PricingRule::METHODS as $k => $label): ?>
            <label class="flex cursor-pointer gap-3 rounded-lg border p-3 text-sm" :class="method === '<?= $k ?>' ? 'border-brand bg-brand/5' : 'border-slate-200 dark:border-slate-700'">
                <input type="radio" name="price_method" value="<?= $k ?>" x-model="method" class="mt-0.5 accent-brand">
                <span><span class="font-medium"><?= e($label) ?></span><span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($methodHelp[$k]) ?></span></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label" for="global_margin" x-text="method === 'list' ? 'Si no hay precio de lista: costo + %' : (method === 'margin' ? 'Margen (%)' : 'Porcentaje sobre costo (%)')"></label>
                <input class="input" type="number" step="0.01" min="0" id="global_margin" name="global_margin" value="<?= e((float) $c['global_margin']) ?>" required>
            </div>
            <div x-show="method === 'list'">
                <label class="label" for="list_discount">Descuento sugerido sobre lista (%)</label>
                <input class="input" type="number" step="0.01" min="0" max="100" id="list_discount" name="list_discount" value="<?= e((float) $c['list_discount']) ?>">
                <p class="hint">Se aplica como descuento visible al agregar; 0 = ninguno.</p>
            </div>
            <input type="hidden" name="list_discount" value="<?= e((float) $c['list_discount']) ?>" x-bind:disabled="method === 'list'">
            <div>
                <label class="label" for="price_rounding">Redondeo</label>
                <select class="input" id="price_rounding" name="price_rounding" x-model="rounding">
                    <?php foreach (PricingRule::ROUNDINGS as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div x-show="rounding === 'multiple'">
                <label class="label" for="price_rounding_value">Múltiplo</label>
                <input class="input" type="number" step="0.01" min="0.01" id="price_rounding_value" name="price_rounding_value" value="<?= e($c['price_rounding_value']) ?>" placeholder="Ej. 5">
            </div>
            <div>
                <label class="label" for="min_margin">Margen mínimo de alerta (%)</label>
                <input class="input" type="number" step="0.01" min="0" max="100" id="min_margin" name="min_margin" value="<?= e((float) $c['min_margin']) ?>" required>
                <p class="hint">Marca en rojo las partidas por debajo.</p>
            </div>
        </div>

        <h2 class="border-t border-slate-200 pt-5 font-semibold dark:border-slate-700">Tipo de cambio</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="exchange_mode">Origen</label>
                <select class="input" id="exchange_mode" name="exchange_mode" x-model="mode">
                    <option value="provider">Automático (del proveedor)</option>
                    <option value="provider_buffer">Automático + colchón</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
            <div x-show="mode === 'provider_buffer'">
                <label class="label" for="exchange_buffer">Colchón (MXN)</label>
                <input class="input" type="number" step="0.01" id="exchange_buffer" name="exchange_buffer" value="<?= e((float) $c['exchange_buffer']) ?>" placeholder="Ej. 0.50">
            </div>
            <div x-show="mode === 'manual' || mode === 'provider'">
                <label class="label" for="exchange_manual" x-text="mode === 'manual' ? 'Tipo de cambio manual' : 'Respaldo si el proveedor no responde'"></label>
                <input class="input" type="number" step="0.0001" min="0" id="exchange_manual" name="exchange_manual" value="<?= e($c['exchange_manual']) ?>">
            </div>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-300">Tipo de cambio actual: <strong><?= $rate['rate'] ? '$' . number_format($rate['rate'], 4) : '—' ?></strong> <span class="text-slate-500">(<?= e($rate['source']) ?>)</span>. Se fija en cada cotización al crearla.</p>
        <div class="flex justify-end"><button class="btn btn-primary">Guardar</button></div>
    </form>

    <!-- Reglas -->
    <div class="card" x-data="{ editing: null }">
        <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-800">
            <h2 class="font-semibold">Reglas específicas <span class="text-sm font-normal text-slate-500">(<?= count($rules) ?>)</span></h2>
            <button type="button" class="btn btn-secondary" @click="editing = editing === 'new' ? null : 'new'">+ Nueva regla</button>
        </div>
        <div x-show="editing === 'new'" x-cloak class="border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/30">
            <?php $rule = null; include __DIR__ . '/rule-form.php'; ?>
        </div>
        <?php if (!$rules): ?>
            <p class="p-6 text-sm text-slate-500">Sin reglas: todo usa la regla global. Ejemplos útiles: «Marca UBIQUITI → costo + 15 %», «Proveedor Syscom → precio de lista», «Categoría Cable → redondeo al múltiplo de 5».</p>
        <?php endif; ?>
        <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
            <?php foreach ($rules as $rule): ?>
            <li id="regla-<?= (int) $rule['id'] ?>" class="<?= $rule['is_active'] ? '' : 'opacity-50' ?>">
                <div class="flex flex-wrap items-center gap-3 p-4">
                    <span class="w-24 rounded bg-slate-100 px-2 py-0.5 text-center text-xs dark:bg-slate-800"><?= e(PricingRule::SCOPES[$rule['scope']]) ?></span>
                    <span class="min-w-[8rem] font-medium"><?= e($rule['scope'] === 'provider' ? ($providers[$rule['scope_value']] ?? $rule['scope_value']) : $rule['scope_value']) ?>
                        <?php if ($rule['provider_code'] && $rule['scope'] !== 'provider'): ?><span class="text-xs font-normal text-slate-500">· solo <?= e($providers[$rule['provider_code']] ?? $rule['provider_code']) ?></span><?php endif; ?>
                    </span>
                    <span class="flex-1 text-slate-600 dark:text-slate-300">
                        <?php if ($rule['method'] === 'list'): ?>
                            Precio de lista<?= (float) $rule['list_discount'] > 0 ? ' · desc. sugerido ' . e((float) $rule['list_discount']) . ' %' : '' ?><?= (float) $rule['percent'] > 0 ? ' · sin lista: costo + ' . e((float) $rule['percent']) . ' %' : '' ?>
                        <?php else: ?>
                            <?= $rule['method'] === 'margin' ? 'Margen' : 'Costo +' ?> <?= e((float) $rule['percent']) ?> %
                        <?php endif; ?>
                        <?= $rule['min_price'] !== null ? ' · mín. $' . number_format((float) $rule['min_price'], 2) : '' ?>
                        <?= $rule['rounding'] !== 'none' ? ' · ' . e(PricingRule::ROUNDINGS[$rule['rounding']]) . ($rule['rounding'] === 'multiple' ? ' ' . e((float) $rule['rounding_value']) : '') : '' ?>
                        <?= !$rule['is_active'] ? ' · <em>inactiva</em>' : '' ?>
                    </span>
                    <button type="button" class="text-brand hover:underline dark:text-sky-400" @click="editing = editing === <?= (int) $rule['id'] ?> ? null : <?= (int) $rule['id'] ?>">Editar</button>
                    <form method="post" action="<?= e(url('/precios/reglas/' . (int) $rule['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar esta regla?')">
                        <?= csrf_field() ?><button class="text-red-600 hover:underline">Eliminar</button>
                    </form>
                </div>
                <div x-show="editing === <?= (int) $rule['id'] ?>" x-cloak class="border-t border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/30">
                    <?php include __DIR__ . '/rule-form.php'; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- Simulador -->
<aside class="xl:sticky xl:top-20 xl:h-max">
    <form class="card space-y-3 p-5 text-sm" x-data="{ f: { provider: 'syscom', model: '', brand: '', category: '', cost: 1000, list_price: '' }, r: null,
            async run() { const q = new URLSearchParams(this.f); this.r = await (await fetch(<?= e(json_encode(url('/precios/simular'))) ?> + '?' + q, { headers: { Accept: 'application/json' } })).json(); } }"
          @submit.prevent="run()" @input.debounce.400ms="run()" x-init="run()">
        <h2 class="font-semibold">Simulador</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Prueba qué regla y qué precio tocarían a un producto.</p>
        <div>
            <label class="label">Proveedor</label>
            <select class="input" x-model="f.provider" @change="run()"><?php foreach ($providers as $code => $name): ?><option value="<?= e($code) ?>"><?= e($name) ?></option><?php endforeach; ?></select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label">Marca</label><input class="input" x-model="f.brand" placeholder="HIKVISION"></div>
            <div><label class="label">Modelo</label><input class="input" x-model="f.model"></div>
        </div>
        <div><label class="label">Categoría</label><input class="input" x-model="f.category"></div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label">Costo</label><input class="input" type="number" step="0.01" x-model="f.cost"></div>
            <div><label class="label">Lista sin IVA</label><input class="input" type="number" step="0.01" x-model="f.list_price" placeholder="Opcional"></div>
        </div>
        <div class="rounded-lg bg-slate-100 p-3 dark:bg-slate-800" x-show="r">
            <div class="text-xs text-slate-500" x-text="r?.label"></div>
            <div class="text-xl font-semibold" x-text="r ? new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(r.price) : ''"></div>
            <div class="text-xs text-slate-500" x-show="r?.suggested_discount > 0" x-text="'Con descuento sugerido: ' + new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(r?.price * (1 - r?.suggested_discount / 100))"></div>
        </div>
    </form>
</aside>
</div>

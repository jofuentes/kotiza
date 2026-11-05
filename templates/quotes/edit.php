<?php
$cfg = [
    'state' => $state,
    'providers' => $providers,
    'units' => $units,
    'urls' => [
        'save' => url('/cotizaciones/' . $state['id']),
        'add' => url('/cotizaciones/' . $state['id'] . '/productos'),
        'rate' => url('/cotizaciones/' . $state['id'] . '/tipo-cambio'),
        'search' => url('/productos/buscar'),
        'approve' => url('/cotizaciones/' . $state['id'] . '/aprobar'),
        'pdf' => url('/cotizaciones/' . $state['id'] . '/pdf'),
    ],
];
$st = $state['status'];
?>
<script src="<?= e(asset('assets/quote-editor.js')) ?>"></script>
<div x-data="quoteEditor(<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) ?>)" class="pb-24">

    <!-- Encabezado -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="<?= e(url('/cotizaciones')) ?>" class="text-sm text-slate-500 hover:underline">← Cotizaciones</a>
        <h1 class="font-mono text-2xl font-semibold"><?= e($state['folio']) ?><?= $state['version'] > 1 ? ' v' . (int) $state['version'] : '' ?></h1>
        <?php include __DIR__ . '/status-badge.php'; ?>
        <div class="ml-auto flex items-center gap-3">
            <span class="text-xs" :class="dirty ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'" x-show="state.editable"
                  x-text="saving ? 'Guardando…' : (dirty ? 'Cambios sin guardar' : 'Guardado')"></span>
            <?php if ($state['editable']): ?>
            <form method="post" action="<?= e(url('/cotizaciones/' . $state['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este borrador? No se puede deshacer.')">
                <?= csrf_field() ?><button class="btn btn-secondary text-red-600">Eliminar</button>
            </form>
            <button type="button" class="btn btn-primary" @click="save()" :disabled="saving || !dirty" title="Ctrl+S">Guardar</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$state['editable']): ?>
    <div class="mb-4 rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200">
        Esta cotización ya no es un borrador, por eso está en modo de solo lectura.
    </div>
    <?php endif; ?>

    <!-- Aprobación de descuentos -->
    <div x-cloak x-show="state.approval_status === 'pending' || (needsApproval && !state.is_admin)"
         class="mb-4 flex flex-wrap items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
        <div class="flex-1">
            <p class="font-medium" x-text="state.is_admin ? 'El vendedor aplicó descuentos mayores a su límite.' : 'Hay descuentos mayores a tu límite (' + state.max_discount + ' % adicional al sugerido): un administrador debe aprobarlos antes de enviar la cotización.'"></p>
            <p class="mt-1 whitespace-pre-line text-xs" x-show="state.approval_note" x-text="state.approval_note"></p>
        </div>
        <button type="button" class="btn btn-primary" x-show="state.is_admin && state.approval_status === 'pending'" @click="approve()">Aprobar descuentos</button>
    </div>
    <div x-cloak x-show="state.approval_status === 'approved'" class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
        Descuentos aprobados por un administrador.
    </div>

    <!-- Mensajes -->
    <div x-cloak x-show="message" x-transition class="fixed bottom-4 right-4 z-50 max-w-md rounded-lg px-4 py-3 text-sm shadow-lg"
         :class="message?.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'" x-text="message?.text"></div>
    <div x-cloak x-show="fieldErrors().length" class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
        <template x-for="m in fieldErrors()" :key="m"><div x-text="m"></div></template>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-6">

            <!-- Datos generales -->
            <fieldset class="card grid gap-4 p-5 md:grid-cols-6" :disabled="!state.editable">
                <div class="md:col-span-3">
                    <span class="label">Cliente</span>
                    <?php $pickerModel = 'state.client'; include __DIR__ . '/client-picker.php'; ?>
                </div>
                <div class="md:col-span-3">
                    <label class="label" for="q-title">Referencia / proyecto</label>
                    <input id="q-title" class="input" x-model="state.title" maxlength="200" placeholder="Ej. CCTV bodega norte">
                    <p class="hint">Vendedor: <span x-text="state.seller"></span></p>
                </div>
                <div class="md:col-span-1">
                    <label class="label" for="q-date">Fecha</label>
                    <input id="q-date" type="date" class="input" x-model="state.issue_date" :class="errors.issue_date && 'input-error'">
                </div>
                <div class="md:col-span-1">
                    <label class="label" for="q-valid">Vigencia</label>
                    <input id="q-valid" type="date" class="input" x-model="state.valid_until" :class="errors.valid_until && 'input-error'">
                </div>
                <div class="md:col-span-1">
                    <label class="label" for="q-cur">Moneda</label>
                    <select id="q-cur" class="input" x-model="state.currency" @change="changeCurrency($event)">
                        <option value="MXN">MXN</option><option value="USD">USD</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="label" for="q-rate">Tipo de cambio (MXN por USD)</label>
                    <div class="flex gap-2">
                        <input id="q-rate" type="number" step="0.0001" min="0" class="input" x-model.number="state.exchange_rate"
                               @change="changeCurrency($event)" :class="errors.exchange_rate && 'input-error'" placeholder="Sin tipo de cambio">
                        <button type="button" class="btn btn-secondary whitespace-nowrap" @click="refreshRate()" title="Usar el tipo de cambio actual">Actualizar</button>
                    </div>
                    <p class="hint" x-text="'Fuente: ' + (state.exchange_source || '—') + '. Queda fijo en esta cotización.'"></p>
                </div>
            </fieldset>

            <!-- Partidas -->
            <div class="card">
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="mr-auto font-semibold">Partidas <span class="text-sm font-normal text-slate-500" x-text="'(' + state.items.length + ')'"></span></h2>
                    <template x-if="state.editable">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" @click="openDrawer()">+ Productos</button>
                            <div class="relative" x-data="{ o: false }" @click.outside="o = false">
                                <button type="button" class="btn btn-secondary" @click="o = !o">+ Partida manual ▾</button>
                                <div x-cloak x-show="o" class="card absolute right-0 z-20 mt-1 w-60 p-1 text-sm">
                                    <template x-for="[k, label] in [['libre','Partida libre'],['servicio','Servicio'],['mano','Mano de obra'],['instalacion','Instalación'],['material','Material diverso']]" :key="k">
                                        <button type="button" class="block w-full rounded px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-800" @click="search.section = ''; addManual(k); o = false" x-text="label"></button>
                                    </template>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" @click="addSection()">+ Sección</button>
                        </div>
                    </template>
                </div>

                <div x-show="!state.items.length && !state.sections.length" class="p-10 text-center text-sm text-slate-500">
                    Aún no hay partidas. Agrega productos de tus proveedores o partidas manuales (servicios, mano de obra, material).
                </div>

                <div x-show="state.items.length || state.sections.length">
                    <template x-for="g in groups" :key="g.key">
                        <div class="border-b border-slate-100 last:border-b-0 dark:border-slate-800"
                             @dragover.prevent="dropTarget = 'g:' + g.key" @drop.prevent="dropOn(null, g.key)">
                            <!-- Encabezado de sección -->
                            <div x-show="g.title !== null" class="flex flex-wrap items-center gap-2 bg-slate-50 px-4 py-2 dark:bg-slate-800/50">
                                <span class="font-semibold" x-text="g.title"></span>
                                <span class="text-xs text-slate-500" x-text="g.items.length + ' partidas · ' + money(g.items.reduce((a, it) => a + line(it).total, 0))"></span>
                                <div class="ml-auto flex items-center gap-1 text-xs" x-show="state.editable">
                                    <button type="button" class="px-1 text-brand hover:underline dark:text-sky-400" @click="openDrawer(g.key)">+ Productos</button>
                                    <button type="button" class="px-1 text-brand hover:underline dark:text-sky-400" @click="search.section = g.key; addManual('libre')">+ Manual</button>
                                    <button type="button" class="px-1 text-base text-slate-500 hover:text-slate-800 dark:hover:text-white" @click="moveSection(g.section, -1)" title="Subir sección">↑</button>
                                    <button type="button" class="px-1 text-base text-slate-500 hover:text-slate-800 dark:hover:text-white" @click="moveSection(g.section, 1)" title="Bajar sección">↓</button>
                                    <button type="button" class="px-1 text-base text-slate-500 hover:text-slate-800 dark:hover:text-white" @click="renameSection(g.section)" title="Renombrar">✎</button>
                                    <button type="button" class="px-1 text-base text-red-500 hover:text-red-700" @click="removeSection(g.section)" title="Quitar sección">✕</button>
                                </div>
                            </div>
                            <div x-show="g.title !== null && !g.items.length" class="px-4 py-4 text-center text-xs text-slate-400" :class="dropTarget === 'g:' + g.key && 'bg-brand/5'">
                                Arrastra partidas aquí o usa «+ Productos».
                            </div>

                            <!-- Partidas -->
                            <template x-for="it in g.items" :key="it.key">
                                <div class="flex gap-2 border-t border-slate-100 px-3 py-3 first:border-t-0 dark:border-slate-800"
                                     :class="{ 'opacity-40': dragKey === it.key, 'border-t-2 !border-t-brand': dropTarget === it.key, 'bg-red-50 dark:bg-red-950/30': itemErrors[it.key] }"
                                     :draggable="state.editable" @dragstart="dragStart(it, $event)" @dragend="dragEnd()"
                                     @dragover.prevent.stop="dropTarget = it.key" @drop.prevent.stop="dropOn(it, g.key)">
                                    <div class="w-4 shrink-0 cursor-grab select-none pt-2 text-slate-400" title="Arrastra para reordenar" x-show="state.editable">⋮⋮</div>
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded border border-slate-200 bg-white dark:border-slate-700" x-show="it.image_url">
                                        <img :src="it.image_url" alt="" class="h-full w-full object-contain" loading="lazy">
                                    </div>
                                    <div class="min-w-0 flex-1 space-y-2">
                                        <!-- Renglón 1: identificación, descripción y acciones -->
                                        <div class="flex items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="mb-1 flex flex-wrap items-center gap-x-2 text-xs" x-show="it.type === 'product'">
                                                    <span class="font-mono font-semibold" x-text="it.model"></span>
                                                    <span class="text-slate-500" x-text="it.brand"></span>
                                                    <span class="rounded bg-slate-100 px-1 text-slate-500 dark:bg-slate-800" x-text="(providers.find(p => p.code === it.provider)?.name || it.provider)"></span>
                                                    <span class="text-slate-400" x-show="it.sku" x-text="'Clave ' + it.sku"></span>
                                                </div>
                                                <textarea :id="'desc-' + it.key" rows="2" class="input py-1.5 text-sm" x-model="it.description" :disabled="!state.editable"
                                                          :class="itemErrors[it.key]?.description && 'input-error'" placeholder="Descripción de la partida"></textarea>
                                            </div>
                                            <div class="flex shrink-0 items-center text-base leading-none" x-show="state.editable">
                                                <button type="button" class="p-1 text-slate-400 hover:text-slate-800 dark:hover:text-white" @click="moveItem(it, -1)" title="Subir">↑</button>
                                                <button type="button" class="p-1 text-slate-400 hover:text-slate-800 dark:hover:text-white" @click="moveItem(it, 1)" title="Bajar">↓</button>
                                                <button type="button" class="p-1 text-slate-400 hover:text-slate-800 dark:hover:text-white" x-show="it.type === 'manual'" @click="duplicateItem(it)" title="Duplicar">⧉</button>
                                                <button type="button" class="p-1 text-red-400 hover:text-red-600" @click="removeItem(it)" title="Quitar">✕</button>
                                            </div>
                                        </div>

                                        <!-- Renglón 2: cantidades y precios -->
                                        <div class="grid grid-cols-2 gap-x-3 gap-y-2 sm:grid-cols-3 lg:grid-cols-6">
                                            <label class="text-[11px] text-slate-500">Cantidad
                                                <input type="number" min="0.001" step="any" class="input px-2 py-1 text-right text-sm" x-model="it.qty" :disabled="!state.editable" :class="itemErrors[it.key]?.qty && 'input-error'">
                                            </label>
                                            <label class="text-[11px] text-slate-500">Unidad
                                                <input class="input px-2 py-1 text-sm" x-model="it.unit" list="units-list" maxlength="40" :disabled="!state.editable">
                                            </label>
                                            <label class="text-[11px] text-slate-500">Precio unitario
                                                <input type="number" min="0" step="0.01" class="input px-2 py-1 text-right text-sm" :disabled="!state.editable"
                                                       :class="[it.price_manual !== null && it.price_manual !== '' && it.type === 'product' ? 'border-amber-400' : '', itemErrors[it.key]?.price_manual ? 'input-error' : '']"
                                                       :value="unitPrice(it).toFixed(2)" @change="it.price_manual = $event.target.value === '' ? null : $event.target.value">
                                                <span class="block leading-tight" x-show="it.type === 'product'">
                                                    <template x-if="it.price_manual !== null && it.price_manual !== ''">
                                                        <button type="button" class="text-amber-600 hover:underline" @click="it.price_manual = null" :title="'Calculado: ' + money(it.price_calculated)">Manual · restaurar</button>
                                                    </template>
                                                    <template x-if="it.price_manual === null || it.price_manual === ''"><span x-text="it.price_rule"></span></template>
                                                </span>
                                                <span class="block leading-tight text-slate-400" x-show="it.list_price" x-text="'Lista ' + money(it.list_price, it.cost_currency) + ' s/IVA'"></span>
                                            </label>
                                            <label class="text-[11px] text-slate-500">Descuento
                                                <span class="flex">
                                                    <input type="number" min="0" step="0.01" class="input rounded-r-none px-2 py-1 text-right text-sm" x-model="it.discount_value" :disabled="!state.editable"
                                                           :class="itemErrors[it.key]?.discount_value && 'input-error'">
                                                    <button type="button" class="rounded-r-lg border border-l-0 border-slate-300 px-2 text-xs dark:border-slate-700" :disabled="!state.editable"
                                                            @click="it.discount_type = it.discount_type === 'percent' ? 'amount' : 'percent'" x-text="it.discount_type === 'percent' ? '%' : '$'"
                                                            title="Cambiar entre porcentaje y monto"></button>
                                                </span>
                                                <span class="block" x-show="line(it).discount > 0" x-text="'− ' + money(line(it).discount)"></span>
                                                <span class="block font-semibold text-amber-600" x-show="overLimit(it)" x-text="'Excede tu límite (' + discountPct(it) + ' %)'"></span>
                                            </label>
                                            <div class="text-[11px] text-slate-500" x-show="state.show_cost">
                                                <template x-if="it.type === 'manual'">
                                                    <label>Costo unitario
                                                        <input type="number" min="0" step="0.01" class="input px-2 py-1 text-right text-sm" :disabled="!state.editable"
                                                               :value="it.cost === null || it.cost === '' ? '' : (+it.cost).toFixed(2)" @change="it.cost = $event.target.value === '' ? null : $event.target.value" placeholder="Opcional">
                                                    </label>
                                                </template>
                                                <template x-if="it.type === 'product'">
                                                    <div :title="'Costo ' + (it.cost_includes_vat ? 'con IVA' : 'sin IVA') + ' del proveedor: ' + money(it.cost, it.cost_currency) + (it.cost_fetched_at ? ' · consultado ' + it.cost_fetched_at : '')">
                                                        Costo unitario<div class="py-1 text-sm text-slate-700 dark:text-slate-300" x-text="money(it.unit_cost)"></div>
                                                    </div>
                                                </template>
                                                <template x-if="line(it).profit !== null">
                                                    <div :class="lowMargin(line(it).margin) ? 'font-semibold text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'"
                                                         x-text="'Utilidad ' + money(line(it).profit) + ' · ' + (line(it).margin ?? '—') + ' %' + (lowMargin(line(it).margin) ? ' (bajo el mínimo)' : '')"></div>
                                                </template>
                                            </div>
                                            <div class="text-right text-[11px] text-slate-500" :class="!state.show_cost && 'lg:col-span-2'">Importe
                                                <div class="py-1 text-base font-semibold text-slate-900 dark:text-slate-100" x-text="money(line(it).total)"></div>
                                            </div>
                                        </div>

                                        <!-- Renglón 3: notas y clave SAT -->
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                            <button type="button" class="text-slate-500 hover:underline" @click="openNotes[it.key] = !openNotes[it.key]" x-text="it.notes ? 'Notas ▾' : '+ Notas'"></button>
                                            <label class="flex items-center gap-1 text-slate-500" x-show="it.type === 'manual'">
                                                Clave SAT <input class="input w-28 px-1 py-0.5 text-xs" x-model="it.sat_key" maxlength="20" :disabled="!state.editable">
                                            </label>
                                            <span class="text-slate-400" x-show="it.type === 'product' && it.sat_key" x-text="'SAT ' + it.sat_key"></span>
                                        </div>
                                        <textarea x-show="openNotes[it.key] || it.notes" rows="1" class="input py-1 text-xs" x-model="it.notes" placeholder="Notas de la partida (se imprimen en la cotización)" :disabled="!state.editable"></textarea>
                                        <template x-if="itemErrors[it.key]">
                                            <div class="text-xs text-red-600"><template x-for="m in Object.values(itemErrors[it.key])" :key="m"><div x-text="m"></div></template></div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <datalist id="units-list"><?php foreach ($units as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist>
                </div>
            </div>

            <!-- Notas y condiciones -->
            <fieldset class="card grid gap-4 p-5 md:grid-cols-2" :disabled="!state.editable">
                <div>
                    <label class="label" for="q-notes">Notas</label>
                    <textarea id="q-notes" rows="4" class="input" x-model="state.notes" maxlength="5000"></textarea>
                </div>
                <div>
                    <label class="label" for="q-terms">Condiciones comerciales</label>
                    <textarea id="q-terms" rows="4" class="input" x-model="state.terms" maxlength="10000"></textarea>
                    <p class="hint">Vienen del perfil de la empresa; puedes ajustarlas para esta cotización.</p>
                </div>
            </fieldset>
        </div>

        <!-- Totales -->
        <aside class="space-y-4 xl:sticky xl:top-20 xl:h-max">
            <fieldset class="card space-y-2 p-5 text-sm" :disabled="!state.editable">
                <h2 class="mb-2 font-semibold">Totales <span class="text-xs font-normal text-slate-500" x-text="state.currency"></span></h2>
                <div class="flex justify-between"><span>Subtotal</span><span x-text="money(totals.subtotal)"></span></div>
                <div class="flex items-center justify-between gap-2">
                    <span>Descuento</span>
                    <div class="flex w-32">
                        <input type="number" min="0" step="0.01" class="input rounded-r-none px-2 py-1 text-right" x-model="state.global_discount_value" :class="errors.global_discount_value && 'input-error'">
                        <button type="button" class="rounded-r-lg border border-l-0 border-slate-300 px-2 text-xs dark:border-slate-700"
                                @click="state.global_discount_type = state.global_discount_type === 'percent' ? 'amount' : 'percent'"
                                x-text="state.global_discount_type === 'percent' ? '%' : '$'"></button>
                    </div>
                </div>
                <div class="flex justify-between text-slate-500" x-show="totals.discount > 0"><span></span><span x-text="'− ' + money(totals.discount)"></span></div>
                <p class="text-right text-xs font-semibold text-amber-600" x-show="globalOverLimit" x-text="'Excede tu límite de ' + state.max_discount + ' %'"></p>
                <div class="flex items-center justify-between gap-2">
                    <span>IVA</span>
                    <div class="flex items-center gap-1"><input type="number" min="0" max="100" step="0.01" class="input w-20 px-2 py-1 text-right" x-model="state.vat_rate"><span>%</span></div>
                </div>
                <div class="flex justify-between text-slate-500"><span></span><span x-text="money(totals.tax)"></span></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-semibold dark:border-slate-700"><span>Total</span><span x-text="money(totals.total)"></span></div>
            </fieldset>

            <fieldset class="card space-y-3 p-5 text-sm" :disabled="!state.editable && false">
                <h2 class="font-semibold">PDF</h2>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                    <template x-for="[k, label] in [['show_images', 'Imágenes'], ['show_brands', 'Marca y modelo'], ['show_stock', 'Existencias'], ['show_tax', 'Desglose de IVA']]" :key="k">
                        <label class="flex items-center gap-2"><input type="checkbox" class="h-4 w-4 accent-brand" x-model="state.pdf_options[k]" :disabled="!state.editable"><span x-text="label"></span></label>
                    </template>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary flex-1" @click="openPreview()">Vista previa</button>
                    <button type="button" class="btn btn-primary flex-1" @click="downloadPdf()">Descargar</button>
                </div>
            </fieldset>

            <div class="card space-y-1.5 p-5 text-sm" x-show="state.show_cost">
                <h2 class="mb-2 font-semibold">Interno <span class="text-xs font-normal text-slate-500">(no aparece en el PDF)</span></h2>
                <div class="flex justify-between"><span>Costo</span><span x-text="money(totals.cost)"></span></div>
                <div class="flex justify-between"><span>Utilidad</span><span :class="lowMargin(totals.margin) ? 'text-red-600 font-semibold' : 'text-emerald-700 dark:text-emerald-400'" x-text="money(totals.profit)"></span></div>
                <div class="flex justify-between"><span>Margen</span><span :class="lowMargin(totals.margin) ? 'text-red-600 font-semibold' : ''" x-text="totals.margin === null ? '—' : totals.margin + ' %'"></span></div>
                <p class="text-xs text-amber-600" x-show="!totals.costKnown">Hay partidas sin costo; no cuentan en la utilidad.</p>
                <p class="text-xs text-red-600" x-show="lowMargin(totals.margin)" x-text="'Por debajo del margen mínimo (' + state.min_margin + ' %).'"></p>
            </div>
        </aside>
    </div>

    <!-- Vista previa del PDF -->
    <div x-cloak x-show="preview" class="fixed inset-0 z-40 bg-black/40" @click="preview = false" x-transition.opacity></div>
    <div x-cloak x-show="preview" class="fixed inset-y-0 right-0 z-50 flex w-full max-w-4xl flex-col bg-slate-100 shadow-xl dark:bg-slate-900"
         x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="translate-x-full"
         @keydown.escape.window="preview = false">
        <div class="flex items-center gap-3 border-b border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold">Vista previa</h2>
            <span class="text-xs text-slate-500" x-text="saving ? 'Guardando…' : (previewLoading ? 'Generando PDF…' : 'Se actualiza al editar')"></span>
            <div class="ml-auto flex gap-2">
                <button type="button" class="btn btn-secondary" @click="refreshPreview()">Actualizar</button>
                <button type="button" class="btn btn-primary" @click="downloadPdf()">Descargar</button>
                <button type="button" class="rounded p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="preview = false" aria-label="Cerrar">✕</button>
            </div>
        </div>
        <template x-if="preview && previewUrl">
            <iframe :src="previewUrl" class="h-full w-full flex-1 border-0 bg-white" title="Vista previa del PDF" @load="previewLoading = false"></iframe>
        </template>
    </div>

    <!-- Panel de búsqueda de productos -->
    <div x-cloak x-show="drawer" class="fixed inset-0 z-40 bg-black/40" @click="drawer = false" x-transition.opacity></div>
    <div x-cloak x-show="drawer" x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col bg-white shadow-xl dark:bg-slate-900" @keydown.escape.window="drawer = false">
        <div class="space-y-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Agregar productos</h2>
                <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="drawer = false" aria-label="Cerrar">✕</button>
            </div>
            <?php if (!$providers): ?>
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">No hay proveedores activos. Puedes agregar partidas manuales.</p>
            <?php endif; ?>
            <form class="flex gap-2" @submit.prevent="runSearch()">
                <input x-ref="searchInput" type="search" class="input" x-model="search.q" placeholder="Modelo, marca o descripción…">
                <button class="btn btn-primary" :disabled="search.loading || search.q.trim().length < 2">Buscar</button>
            </form>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <template x-for="p in providers" :key="p.code">
                    <label class="flex items-center gap-1.5"><input type="checkbox" :value="p.code" x-model="search.selected" class="h-4 w-4 accent-brand"><span x-text="p.name"></span></label>
                </template>
                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="search.inStock" class="h-4 w-4 accent-brand"> Con existencia</label>
                <label class="ml-auto flex items-center gap-1.5 text-xs text-slate-500" x-show="state.sections.length">
                    Agregar a
                    <select class="input w-auto py-1 text-xs" x-model="search.section">
                        <option value="">Sin sección</option>
                        <template x-for="s in state.sections" :key="s.key"><option :value="s.key" x-text="s.title"></option></template>
                    </select>
                </label>
            </div>
            <div class="flex flex-wrap gap-2 text-xs" x-show="Object.keys(search.status).length">
                <template x-for="(s, code) in search.status" :key="code">
                    <span class="rounded-full px-2 py-0.5" :class="s.ok ? 'bg-slate-100 dark:bg-slate-800' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'" :title="s.error || ''"
                          x-text="s.name + ': ' + (s.ok ? s.total + ' resultados' : (s.stale ? 'datos de hace ' + ago(s.age_minutes) : s.error))"></span>
                </template>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto">
            <p x-show="search.loading" class="p-6 text-center text-sm text-slate-500">Buscando…</p>
            <p x-show="search.error" class="p-6 text-center text-sm text-red-600" x-text="search.error"></p>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="p in search.results" :key="p.provider + p.providerId">
                    <li class="flex gap-3 p-3" :class="p.cheapest && 'bg-emerald-50/60 dark:bg-emerald-950/30'" x-data="{ qty: 1 }">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded border border-slate-200 bg-white dark:border-slate-700"><img x-show="p.images[0]" :src="p.images[0]" alt="" class="h-full w-full object-contain" loading="lazy"></div>
                        <div class="min-w-0 flex-1 text-sm">
                            <div class="flex flex-wrap items-center gap-x-2 text-xs">
                                <span class="rounded bg-slate-100 px-1 dark:bg-slate-800" x-text="p.providerName"></span>
                                <span class="font-semibold" x-text="p.brand"></span>
                                <span x-show="p.cheapest" class="rounded bg-emerald-600 px-1 text-white">Más barato</span>
                                <span x-show="inQuote(p)" class="rounded bg-sky-600 px-1 text-white">Ya en la cotización</span>
                            </div>
                            <div class="font-mono font-semibold" x-text="p.model"></div>
                            <div class="line-clamp-2 text-slate-600 dark:text-slate-300" x-text="p.title"></div>
                            <div class="mt-0.5 text-xs" :class="p.stock > 0 ? 'text-emerald-600' : 'text-red-500'" x-text="p.stock > 0 ? p.stock + ' en existencia' : 'Sin existencia'"></div>
                        </div>
                        <div class="flex w-32 shrink-0 flex-col items-end gap-1 text-right text-sm">
                            <div x-show="state.show_cost && p.cost !== null" class="text-xs text-slate-500" x-text="'Costo ' + money(p.cost, p.currency)"></div>
                            <div x-show="p.listPrice" class="text-xs text-slate-500" x-text="'Lista ' + money(p.listPrice, p.currency)"></div>
                            <div x-show="p.cost === null" class="text-xs text-amber-600" x-text="p.note || 'Sin precio'"></div>
                            <div class="flex">
                                <input type="number" min="1" step="1" class="input w-14 rounded-r-none px-1 py-1 text-right text-xs" x-model.number="qty">
                                <button type="button" class="btn btn-primary rounded-l-none px-2 py-1 text-xs" :disabled="search.adding[p.provider + ':' + p.providerId] || p.cost === null"
                                        @click="addProduct(p, qty)" x-text="search.adding[p.provider + ':' + p.providerId] ? '…' : 'Agregar'"></button>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>

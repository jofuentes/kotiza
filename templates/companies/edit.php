<?php
use App\Domain\TaxRegimes;
use App\Support\Form;

Form::bind($profile, $errors);
$tabs = ['general' => 'Datos generales', 'branding' => 'Identidad visual', 'document' => 'Cotización', 'folios' => 'Folios e impuestos'];
$tab = isset($tabs[$tab]) ? $tab : 'general';
$letterheadIsImage = $letterheadUrl && ($profile['letterhead_type'] ?? '') === 'image';
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">Perfil de empresa</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Estos datos aparecen en tus cotizaciones.</p>
    </div>
</div>

<?php if ($errors): ?>
<div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">No se guardó: revisa los campos marcados.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/empresa')) ?>" enctype="multipart/form-data" x-data="{ tab: <?= e(json_encode($tab)) ?> }">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" :value="tab">

    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800">
        <?php foreach ($tabs as $key => $label): ?>
        <button type="button" @click="tab = '<?= $key ?>'; history.replaceState(null, '', '?tab=<?= $key ?>')"
                :class="tab === '<?= $key ?>' ? 'border-brand text-brand dark:text-white' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="-mb-px whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium"><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>

    <!-- Datos generales -->
    <div x-show="tab === 'general'" class="card grid gap-4 p-6 sm:grid-cols-2">
        <?= Form::input('trade_name', 'Nombre comercial', 'text', ['attrs' => ['required' => true, 'maxlength' => 200]]) ?>
        <?= Form::input('legal_name', 'Razón social', 'text', ['attrs' => ['required' => true, 'maxlength' => 200]]) ?>
        <?= Form::input('rfc', 'RFC', 'text', ['attrs' => ['maxlength' => 13, 'style' => 'text-transform:uppercase']]) ?>
        <?= Form::input('tax_regime', 'Régimen fiscal', 'text', ['attrs' => ['list' => 'regimenes', 'maxlength' => 120], 'hint' => 'Elige de la lista o escríbelo.']) ?>
        <datalist id="regimenes"><?php foreach (TaxRegimes::labels() as $r): ?><option value="<?= e($r) ?>"><?php endforeach; ?></datalist>
        <?= Form::textarea('address', 'Dirección', ['class' => 'sm:col-span-2', 'rows' => 2]) ?>
        <?= Form::input('phone', 'Teléfonos', 'text', ['attrs' => ['maxlength' => 80]]) ?>
        <?= Form::input('email', 'Correo', 'email', ['attrs' => ['maxlength' => 190]]) ?>
        <?= Form::input('website', 'Sitio web', 'text', ['attrs' => ['maxlength' => 190, 'placeholder' => 'www.tuempresa.com']]) ?>
    </div>

    <!-- Identidad visual -->
    <div x-show="tab === 'branding'" x-cloak class="grid gap-6 lg:grid-cols-2"
         x-data="{
            primary: <?= e(json_encode(Form::value('color_primary', '#1E40AF'))) ?>,
            secondary: <?= e(json_encode(Form::value('color_secondary', '#64748B'))) ?>,
            mt: <?= (float) Form::value('pdf_margin_top') ?>, mr: <?= (float) Form::value('pdf_margin_right') ?>,
            mb: <?= (float) Form::value('pdf_margin_bottom') ?>, ml: <?= (float) Form::value('pdf_margin_left') ?>
         }">
        <div class="card space-y-5 p-6">
            <?= Form::file('logo', 'Logo', $logoUrl, ['accept' => '.png,.jpg,.jpeg,.svg', 'hint' => 'PNG, JPG o SVG, máx. 2 MB. Se usa en el sistema y en el PDF si no hay membrete.']) ?>
            <?= Form::file('letterhead', 'Hoja membretada (opcional)', $letterheadUrl, ['accept' => '.pdf,.png,.jpg,.jpeg', 'hint' => 'PDF o imagen tamaño carta/A4, máx. 10 MB. Se usa como fondo de cada página de la cotización.']) ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach (['color_primary' => ['Color primario', 'primary'], 'color_secondary' => ['Color secundario', 'secondary']] as $name => [$label, $model]): ?>
                <div>
                    <label class="label" for="<?= $name ?>"><?= e($label) ?></label>
                    <div class="flex gap-2">
                        <input type="color" x-model="<?= $model ?>" class="h-10 w-12 cursor-pointer rounded border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-950" aria-label="<?= e($label) ?>">
                        <input class="input font-mono <?= Form::error($name) ? 'input-error' : '' ?>" id="<?= $name ?>" name="<?= $name ?>" x-model="<?= $model ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
                    </div>
                    <?php if ($err = Form::error($name)): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <span class="label">Márgenes del contenido en el PDF (mm)</span>
                <div class="grid grid-cols-4 gap-2">
                    <?php foreach (['pdf_margin_top' => ['Superior', 'mt'], 'pdf_margin_right' => ['Derecho', 'mr'], 'pdf_margin_bottom' => ['Inferior', 'mb'], 'pdf_margin_left' => ['Izquierdo', 'ml']] as $name => [$label, $model]): ?>
                    <div>
                        <input class="input <?= Form::error($name) ? 'input-error' : '' ?>" type="number" step="0.5" min="0" max="120" name="<?= $name ?>" x-model.number="<?= $model ?>" aria-label="<?= e($label) ?>">
                        <span class="hint"><?= e($label) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="hint">Ajústalos para que el contenido no se encime con el encabezado y pie de tu membrete.</p>
            </div>
        </div>

        <!-- Esquema de página (carta) -->
        <div class="card flex flex-col items-center gap-3 p-6">
            <span class="label self-start">Vista esquemática de la página</span>
            <div class="relative w-full max-w-xs overflow-hidden rounded border border-slate-300 bg-white shadow-sm dark:border-slate-600" style="aspect-ratio: 215.9 / 279.4">
                <?php if ($letterheadIsImage): ?>
                    <img src="<?= e($letterheadUrl) ?>" alt="" class="absolute inset-0 h-full w-full object-fill">
                <?php elseif ($letterheadUrl): ?>
                    <div class="absolute inset-x-0 top-1 text-center text-[10px] text-slate-400">Membrete PDF (se verá en la vista previa del PDF)</div>
                <?php elseif ($logoUrl): ?>
                    <img src="<?= e($logoUrl) ?>" alt="" class="absolute left-[7%] top-[3%] h-[8%] object-contain">
                <?php endif; ?>
                <div class="absolute border-2 border-dashed bg-white/60 p-2" :style="`border-color:${primary};top:${mt/279.4*100}%;bottom:${mb/279.4*100}%;left:${ml/215.9*100}%;right:${mr/215.9*100}%`">
                    <div class="h-2 w-1/2 rounded" :style="`background:${primary}`"></div>
                    <div class="mt-2 h-1.5 w-3/4 rounded" :style="`background:${secondary}`"></div>
                    <div class="mt-3 space-y-1"><?php for ($i = 0; $i < 6; $i++): ?><div class="h-1 rounded bg-slate-300"></div><?php endfor; ?></div>
                    <span class="absolute bottom-1 right-2 text-[9px] text-slate-500">Área de contenido</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Datos de la cotización -->
    <div x-show="tab === 'document'" x-cloak class="card grid gap-4 p-6 sm:grid-cols-2">
        <?= Form::input('default_validity_days', 'Vigencia por defecto (días)', 'number', ['attrs' => ['min' => 1, 'max' => 365, 'required' => true]]) ?>
        <div></div>
        <?= Form::textarea('bank_details', 'Datos bancarios para pago', ['rows' => 4, 'attrs' => ['placeholder' => "Banco: …\nCuenta: …\nCLABE: …\nBeneficiario: …"]]) ?>
        <?= Form::textarea('terms', 'Condiciones comerciales', ['rows' => 4, 'attrs' => ['placeholder' => "Precios sujetos a cambio sin previo aviso.\nTiempo de entrega: …\nForma de pago: …"]]) ?>
        <?= Form::textarea('footer_notes', 'Notas al pie', ['rows' => 2, 'class' => 'sm:col-span-2']) ?>
        <?= Form::file('signature', 'Firma de la empresa (opcional)', $signatureUrl, ['accept' => '.png,.jpg,.jpeg', 'hint' => 'PNG con fondo transparente recomendado. Se usa si el vendedor no tiene firma propia.']) ?>
    </div>

    <!-- Folios e impuestos -->
    <div x-show="tab === 'folios'" x-cloak class="grid gap-6 lg:grid-cols-2"
         x-data="{
            prefix: <?= e(json_encode(Form::value('folio_prefix'))) ?>,
            year: <?= Form::value('folio_include_year') === '1' ? 'true' : 'false' ?>,
            pad: <?= (int) Form::value('folio_padding', 4) ?>,
            next: <?= (int) Form::value('folio_next', 1) ?>,
            get preview() {
                const p = (this.prefix || '').toUpperCase().replace(/[^A-Z0-9-]/g, '');
                const n = String(parseInt(this.next) || 1).padStart(Math.min(8, Math.max(1, parseInt(this.pad) || 1)), '0');
                return [p, this.year ? '<?= date('Y') ?>' : '', n].filter(Boolean).join('-');
            }
         }">
        <div class="card space-y-4 p-6">
            <h2 class="font-semibold">Folios</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="folio_prefix">Prefijo</label>
                    <input class="input uppercase <?= Form::error('folio_prefix') ? 'input-error' : '' ?>" id="folio_prefix" name="folio_prefix" x-model="prefix" maxlength="20">
                    <?php if ($err = Form::error('folio_prefix')): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="label" for="folio_next">Siguiente número</label>
                    <input class="input <?= Form::error('folio_next') ? 'input-error' : '' ?>" type="number" min="1" id="folio_next" name="folio_next" x-model="next" required>
                    <?php if ($err = Form::error('folio_next')): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="label" for="folio_padding">Dígitos</label>
                    <input class="input" type="number" min="1" max="8" id="folio_padding" name="folio_padding" x-model="pad" required>
                </div>
            </div>
            <input type="hidden" name="folio_include_year" :value="year ? 1 : 0">
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" x-model="year" class="h-4 w-4 accent-brand"> <span class="font-medium">Incluir el año en el folio</span></label>
            <?= Form::checkbox('folio_reset_yearly', 'Reiniciar el consecutivo cada año', ['hint' => 'El 1 de enero el número vuelve a 1.']) ?>
            <div class="rounded-lg bg-slate-100 px-4 py-3 text-sm dark:bg-slate-800">
                Próxima cotización: <span class="font-mono font-semibold" x-text="preview"></span>
            </div>
            <p class="hint">Si ya traes un consecutivo de otro sistema, escribe en «Siguiente número» el que te toca.</p>
        </div>
        <div class="card space-y-4 p-6">
            <h2 class="font-semibold">Moneda, impuestos y permisos</h2>
            <?= Form::select('default_currency', 'Moneda por defecto', ['MXN' => 'Pesos mexicanos (MXN)', 'USD' => 'Dólares (USD)']) ?>
            <?= Form::input('vat_rate', 'Tasa de IVA (%)', 'number', ['attrs' => ['step' => '0.01', 'min' => 0, 'max' => 100, 'required' => true]]) ?>
            <?= Form::checkbox('hide_costs_from_sellers', 'Ocultar costos y utilidad a los vendedores', ['hint' => 'Los vendedores solo verán precios de venta.']) ?>
        </div>
    </div>

    <div class="sticky bottom-0 mt-6 flex justify-end border-t border-slate-200 bg-slate-50/90 py-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
        <button class="btn btn-primary">Guardar cambios</button>
    </div>
</form>

<?php
use App\Pricing\PricingRule;

/** Formulario de regla. Variables: $rule (fila o null), $providers (código => nombre) */
$r = $rule ?? ['scope' => 'brand', 'scope_value' => '', 'provider_code' => null, 'method' => 'markup', 'percent' => '',
    'list_discount' => 0, 'min_price' => null, 'rounding' => 'none', 'rounding_value' => null, 'notes' => '', 'is_active' => 1];
$action = $rule ? '/precios/reglas/' . (int) $rule['id'] : '/precios/reglas';
?>
<form method="post" action="<?= e(url($action)) ?>" class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"
      x-data="{ scope: <?= e(json_encode($r['scope'])) ?>, method: <?= e(json_encode($r['method'])) ?>, rounding: <?= e(json_encode($r['rounding'])) ?> }">
    <?= csrf_field() ?>
    <div>
        <label class="label">Aplica a</label>
        <select name="scope" class="input" x-model="scope">
            <?php foreach (PricingRule::SCOPES as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="label" x-text="{ product: 'Modelo', brand: 'Marca', category: 'Categoría', provider: 'Proveedor' }[scope]"></label>
        <template x-if="scope === 'provider'">
            <select name="scope_value" class="input">
                <?php foreach ($providers as $code => $name): ?><option value="<?= e($code) ?>" <?= $r['scope_value'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </template>
        <template x-if="scope !== 'provider'">
            <input name="scope_value" class="input" value="<?= e($r['scope'] !== 'provider' ? $r['scope_value'] : '') ?>" required maxlength="190"
                   :placeholder="{ product: 'Ej. DS-2CD2143G2-I', brand: 'Ej. HIKVISION', category: 'Ej. Cámaras IP' }[scope]">
        </template>
        <p class="hint" x-show="scope !== 'provider'">Como aparece en el proveedor (sin importar mayúsculas).</p>
    </div>
    <div x-show="scope !== 'provider'">
        <label class="label">Solo para proveedor</label>
        <select name="provider_code" class="input">
            <option value="">Todos</option>
            <?php foreach ($providers as $code => $name): ?><option value="<?= e($code) ?>" <?= $r['provider_code'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="label">Método</label>
        <select name="method" class="input" x-model="method">
            <?php foreach (PricingRule::METHODS as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="label" x-text="method === 'list' ? 'Sin lista: costo + %' : (method === 'margin' ? 'Margen (%)' : 'Costo + (%)')"></label>
        <input name="percent" type="number" step="0.01" min="0" class="input" value="<?= e($r['percent'] === '' ? '' : (float) $r['percent']) ?>" :required="method !== 'list'" placeholder="0">
    </div>
    <div x-show="method === 'list'">
        <label class="label">Descuento sugerido (%)</label>
        <input name="list_discount" type="number" step="0.01" min="0" max="100" class="input" value="<?= e((float) $r['list_discount']) ?>">
    </div>
    <div>
        <label class="label">Precio mínimo (<?= e($company['default_currency']) ?>)</label>
        <input name="min_price" type="number" step="0.01" min="0" class="input" value="<?= e($r['min_price']) ?>" placeholder="Opcional">
    </div>
    <div>
        <label class="label">Redondeo</label>
        <select name="rounding" class="input" x-model="rounding">
            <?php foreach (PricingRule::ROUNDINGS as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div x-show="rounding === 'multiple'">
        <label class="label">Múltiplo</label>
        <input name="rounding_value" type="number" step="0.01" min="0.01" class="input" value="<?= e($r['rounding_value']) ?>" placeholder="Ej. 5">
    </div>
    <div class="sm:col-span-2">
        <label class="label">Notas</label>
        <input name="notes" class="input" maxlength="255" value="<?= e($r['notes']) ?>" placeholder="Opcional">
    </div>
    <div class="flex items-end justify-between gap-3 sm:col-span-2 lg:col-span-4">
        <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="h-4 w-4 accent-brand" <?= $r['is_active'] ? 'checked' : '' ?>> Activa</label>
        <button class="btn btn-primary"><?= $rule ? 'Guardar regla' : 'Crear regla' ?></button>
    </div>
</form>

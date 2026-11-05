<?php
$pageTitle = 'Configuración inicial';
$wide = true;
$field = function (string $name, string $label, string $type = 'text', array $opts = []) use ($old, $errors) {
    $err = $errors[$name] ?? null;
    $value = $type === 'password' ? '' : ($old[$name] ?? '');
    $attrs = '';
    foreach ($opts['attrs'] ?? [] as $k => $v) {
        $attrs .= ' ' . e($k) . '="' . e($v) . '"';
    }
    $html = '<div class="' . e($opts['class'] ?? '') . '"><label class="label" for="' . e($name) . '">' . e($label) . '</label>';
    if (isset($opts['options'])) {
        $html .= '<select class="input" id="' . e($name) . '" name="' . e($name) . '">';
        foreach ($opts['options'] as $val => $text) {
            $html .= '<option value="' . e($val) . '"' . ((string) $val === (string) $value ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        $html .= '</select>';
    } else {
        $html .= '<input class="input' . ($err ? ' input-error' : '') . '" type="' . e($type) . '" id="' . e($name) . '" name="' . e($name) . '" value="' . e($value) . '"' . $attrs . '>';
    }
    if ($err) {
        $html .= '<p class="field-error">' . e($err) . '</p>';
    } elseif (!empty($opts['hint'])) {
        $html .= '<p class="hint">' . e($opts['hint']) . '</p>';
    }
    return $html . '</div>';
};
?>
<div class="card p-6 sm:p-8">
    <h1 class="text-xl font-semibold">Bienvenido a Cotizador</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configura la cuenta de administrador y tu primera empresa. Todo lo demás (logo, membrete, colores, proveedores…) lo podrás ajustar después desde la interfaz.</p>

    <?php if ($errors): ?>
        <div class="mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">Revisa los campos marcados.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/setup')) ?>" class="mt-6 space-y-8">
        <?= csrf_field() ?>

        <fieldset>
            <legend class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand text-xs text-white">1</span> Administrador
            </legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <?= $field('name', 'Nombre completo', 'text', ['attrs' => ['required' => 'required', 'autocomplete' => 'name']]) ?>
                <?= $field('email', 'Correo', 'email', ['attrs' => ['required' => 'required', 'autocomplete' => 'username']]) ?>
                <?= $field('password', 'Contraseña', 'password', ['hint' => 'Mínimo 10 caracteres.', 'attrs' => ['required' => 'required', 'minlength' => '10', 'autocomplete' => 'new-password']]) ?>
                <?= $field('password_confirmation', 'Confirmar contraseña', 'password', ['attrs' => ['required' => 'required', 'autocomplete' => 'new-password']]) ?>
            </div>
        </fieldset>

        <fieldset>
            <legend class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand text-xs text-white">2</span> Empresa
            </legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <?= $field('trade_name', 'Nombre comercial', 'text', ['attrs' => ['required' => 'required']]) ?>
                <?= $field('legal_name', 'Razón social', 'text', ['attrs' => ['required' => 'required']]) ?>
                <?= $field('rfc', 'RFC (opcional)', 'text', ['attrs' => ['maxlength' => '13', 'style' => 'text-transform:uppercase']]) ?>
                <?= $field('default_currency', 'Moneda por defecto', 'select', ['options' => ['MXN' => 'Pesos mexicanos (MXN)', 'USD' => 'Dólares (USD)']]) ?>
                <?= $field('vat_rate', 'IVA (%)', 'number', ['attrs' => ['step' => '0.01', 'min' => '0', 'max' => '100', 'required' => 'required']]) ?>
            </div>
        </fieldset>

        <fieldset x-data="{ prefix: <?= e(json_encode($old['folio_prefix'] ?? 'COT')) ?>, next: <?= e(json_encode($old['folio_next'] ?? '1')) ?> }">
            <legend class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand text-xs text-white">3</span> Folios
            </legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="folio_prefix">Prefijo</label>
                    <input class="input <?= isset($errors['folio_prefix']) ? 'input-error' : '' ?>" id="folio_prefix" name="folio_prefix" x-model="prefix" maxlength="20" required style="text-transform:uppercase">
                    <?php if (isset($errors['folio_prefix'])): ?><p class="field-error"><?= e($errors['folio_prefix']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="label" for="folio_next">Siguiente número</label>
                    <input class="input <?= isset($errors['folio_next']) ? 'input-error' : '' ?>" type="number" min="1" id="folio_next" name="folio_next" x-model="next" required>
                    <?php if (isset($errors['folio_next'])): ?><p class="field-error"><?= e($errors['folio_next']) ?></p>
                    <?php else: ?><p class="hint">¿Ya llevas un consecutivo? Escribe aquí el siguiente número que te toca.</p><?php endif; ?>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                Tu próxima cotización será:
                <span class="font-mono font-semibold" x-text="(prefix || 'COT').toUpperCase().replace(/[^A-Z0-9]/g, '') + '-<?= date('Y') ?>-' + String(parseInt(next) || 1).padStart(4, '0')"></span>
                <span class="text-slate-400">· el consecutivo reinicia cada año (configurable).</span>
            </p>
        </fieldset>

        <div class="flex justify-end">
            <button class="btn btn-primary">Crear cuenta y empresa</button>
        </div>
    </form>
</div>

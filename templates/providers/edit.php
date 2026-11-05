<?php
use App\Support\Form;

Form::bind($old, $errors);
$settings = $old['settings'] ?? [];
?>
<div class="mx-auto max-w-3xl">
    <a href="<?= e(url('/proveedores')) ?>" class="text-sm text-slate-500 hover:underline">← Proveedores</a>
    <h1 class="mt-1 text-2xl font-semibold"><?= e($name) ?></h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400"><?= e($description) ?></p>

    <?php if ($errors): ?>
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">No se guardó: revisa los campos marcados.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/proveedores/' . $code)) ?>" class="space-y-6" autocomplete="off">
        <?= csrf_field() ?>

        <div class="card space-y-4 p-6">
            <div>
                <h2 class="font-semibold">Credenciales</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Se guardan cifradas y nunca se vuelven a mostrar.
                    <?php if (!empty($config['credentials_url'])): ?>
                        Genéralas en <a href="<?= e($config['credentials_url']) ?>" target="_blank" rel="noopener" class="text-brand underline dark:text-sky-400"><?= e(parse_url($config['credentials_url'], PHP_URL_HOST)) ?></a>.
                    <?php endif; ?>
                </p>
            </div>
            <?php foreach ($credentialFields as $f):
                $saved = in_array($f->key, $storedKeys, true);
                $err = $errors['credentials.' . $f->key] ?? null; ?>
            <div x-data="{ replace: <?= $saved && !$err ? 'false' : 'true' ?> }">
                <label class="label" for="cred_<?= e($f->key) ?>"><?= e($f->label) ?><?= $f->required ? '' : ' (opcional)' ?></label>
                <div x-show="!replace" class="flex items-center gap-3">
                    <span class="input flex-1 select-none font-mono tracking-widest text-slate-400">••••••••••••</span>
                    <button type="button" class="btn btn-secondary" @click="replace = true; $nextTick(() => $refs.inp.focus())">Reemplazar</button>
                </div>
                <div x-show="replace" x-cloak class="flex items-center gap-3">
                    <input x-ref="inp" class="input flex-1 font-mono <?= $err ? 'input-error' : '' ?>" id="cred_<?= e($f->key) ?>"
                           type="<?= $f->type === 'secret' ? 'password' : 'text' ?>" name="credentials[<?= e($f->key) ?>]"
                           autocomplete="new-password" spellcheck="false" :disabled="!replace">
                    <?php if ($saved): ?><button type="button" class="btn btn-secondary" @click="replace = false">Cancelar</button><?php endif; ?>
                </div>
                <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php elseif ($f->help): ?><p class="hint"><?= e($f->help) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($syncable): ?>
        <div class="card space-y-3 p-6">
            <h2 class="font-semibold">Sincronización del catálogo</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">La API de <?= e($name) ?> no permite buscar por texto, así que su catálogo se descarga completo en segundo plano y el buscador consulta esa copia. Precios y existencias se confirman en vivo al abrir la ficha de un producto.</p>
            <?php if ($row && $row['is_active'] && $storedKeys): ?>
                <?php include __DIR__ . '/sync-panel.php'; ?>
            <?php else: ?>
                <p class="text-sm">Guarda las credenciales y activa el proveedor para poder sincronizar.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card grid gap-4 p-6 sm:grid-cols-3">
            <h2 class="font-semibold sm:col-span-3">General</h2>
            <?= Form::select('is_active', 'Estado', ['1' => 'Activo', '0' => 'Inactivo']) ?>
            <?= Form::input('priority', 'Prioridad', 'number', ['attrs' => ['min' => 1, 'max' => 99], 'hint' => '1 = se muestra primero.']) ?>
            <?= Form::input('default_margin', 'Margen por defecto (%)', 'number', ['attrs' => ['step' => '0.01', 'min' => 0], 'hint' => 'Vacío = margen global.']) ?>
        </div>

        <?php if ($settingFields): ?>
        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">Ajustes del conector</h2>
            <?php foreach ($settingFields as $f): $val = (string) ($settings[$f->key] ?? ''); ?>
                <?php if ($f->type === 'bool'): ?>
                    <label class="flex items-start gap-3 text-sm sm:col-span-2">
                        <input type="hidden" name="settings[<?= e($f->key) ?>]" value="0">
                        <input type="checkbox" name="settings[<?= e($f->key) ?>]" value="1" class="mt-0.5 h-4 w-4 accent-brand" <?= $val === '1' ? 'checked' : '' ?>>
                        <span><span class="font-medium"><?= e($f->label) ?></span><?php if ($f->help): ?><span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($f->help) ?></span><?php endif; ?></span>
                    </label>
                <?php elseif ($f->type === 'select'): ?>
                    <div>
                        <label class="label" for="set_<?= e($f->key) ?>"><?= e($f->label) ?></label>
                        <select class="input" id="set_<?= e($f->key) ?>" name="settings[<?= e($f->key) ?>]">
                            <?php foreach ($f->options as $ov => $ot): ?><option value="<?= e($ov) ?>" <?= (string) $ov === $val ? 'selected' : '' ?>><?= e($ot) ?></option><?php endforeach; ?>
                        </select>
                        <?php if ($f->help): ?><p class="hint"><?= e($f->help) ?></p><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div>
                        <label class="label" for="set_<?= e($f->key) ?>"><?= e($f->label) ?></label>
                        <input class="input" id="set_<?= e($f->key) ?>" type="<?= $f->type === 'number' ? 'number' : 'text' ?>" name="settings[<?= e($f->key) ?>]" value="<?= e($val) ?>">
                        <?php if ($f->help): ?><p class="hint"><?= e($f->help) ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex justify-end gap-2">
            <a href="<?= e(url('/proveedores')) ?>" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</div>

<?php
use App\Support\Form;

Form::bind($old, $errors);
$action = $member ? '/usuarios/' . (int) $member['id'] : '/usuarios';
?>
<div class="mx-auto max-w-3xl">
    <a href="<?= e(url('/usuarios')) ?>" class="text-sm text-slate-500 hover:underline">← Usuarios</a>
    <h1 class="mb-6 mt-1 text-2xl font-semibold"><?= e($pageTitle) ?></h1>

    <form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" class="space-y-6"
          x-data="{ role: <?= e(json_encode(Form::value('role', 'seller'))) ?> }">
        <?= csrf_field() ?>

        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">Acceso</h2>
            <?php if ($globalEditable): ?>
                <?= Form::input('name', 'Nombre completo', 'text', ['attrs' => ['maxlength' => 120] + ($member ? ['required' => true] : [])]) ?>
                <?= Form::input('email', 'Correo', 'email', ['attrs' => ['required' => true, 'maxlength' => 190], 'hint' => $member ? '' : 'Si el correo ya tiene cuenta en otra empresa, solo se agregará a esta.']) ?>
                <?= Form::input('password', $member ? 'Contraseña nueva (opcional)' : 'Contraseña', 'password', ['attrs' => ['minlength' => 10, 'autocomplete' => 'new-password'], 'hint' => $member ? 'Déjala vacía para no cambiarla.' : 'Mínimo 10 caracteres.']) ?>
                <?= Form::input('password_confirmation', 'Confirmar contraseña', 'password', ['attrs' => ['autocomplete' => 'new-password']]) ?>
            <?php else: ?>
                <div class="sm:col-span-2 text-sm">
                    <div class="font-medium"><?= e($member['name']) ?></div>
                    <div class="text-slate-500 dark:text-slate-400"><?= e($member['email']) ?></div>
                    <p class="hint mt-2">Este usuario también pertenece a otras empresas, así que su nombre, correo y contraseña solo los puede cambiar él desde «Mi perfil».</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">Permisos en <?= e($company['trade_name']) ?></h2>
            <?php if ($isSelf): ?>
                <p class="text-sm text-slate-500 sm:col-span-2 dark:text-slate-400">No puedes cambiar tu propio rol ni desactivarte.</p>
            <?php else: ?>
                <?= Form::select('role', 'Rol', ['seller' => 'Vendedor', 'admin' => 'Administrador'], ['attrs' => ['x-model' => 'role'], 'hint' => 'El administrador ve y configura todo.']) ?>
                <?php if ($member): ?>
                    <?= Form::select('is_active', 'Estado', ['1' => 'Activo', '0' => 'Inactivo (no puede entrar a esta empresa)']) ?>
                <?php endif; ?>
                <div x-show="role === 'seller'">
                    <?= Form::input('max_discount_pct', 'Descuento máximo sin aprobación (%)', 'number', ['attrs' => ['step' => '0.5', 'min' => 0, 'max' => 100], 'hint' => 'Vacío = sin límite.']) ?>
                </div>
            <?php endif; ?>
            <?= Form::input('display_name', 'Nombre en cotizaciones', 'text', ['attrs' => ['maxlength' => 120], 'hint' => 'Cómo aparece como vendedor en el PDF.']) ?>
            <?= Form::file('signature', 'Firma (opcional)', $signatureUrl, ['accept' => '.png,.jpg,.jpeg', 'hint' => 'PNG con fondo transparente, máx. 2 MB.']) ?>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= e(url('/usuarios')) ?>" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><?= $member ? 'Guardar cambios' : 'Crear usuario' ?></button>
        </div>
    </form>
</div>

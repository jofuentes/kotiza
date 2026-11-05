<?php
use App\Support\Form;

Form::bind($old, $errors);
$action = $client ? '/clientes/' . (int) $client['id'] : '/clientes';
?>
<div class="mx-auto max-w-3xl">
    <a href="<?= e(url('/clientes')) ?>" class="text-sm text-slate-500 hover:underline">← Clientes</a>
    <h1 class="mb-6 mt-1 text-2xl font-semibold"><?= e($pageTitle) ?></h1>
    <form method="post" action="<?= e(url($action)) ?>" class="card grid gap-4 p-6 sm:grid-cols-2">
        <?= csrf_field() ?>
        <?= Form::input('name', 'Nombre del contacto', 'text', ['attrs' => ['required' => true, 'maxlength' => 160]]) ?>
        <?= Form::input('company_name', 'Empresa', 'text', ['attrs' => ['maxlength' => 200]]) ?>
        <?= Form::input('rfc', 'RFC', 'text', ['attrs' => ['maxlength' => 13, 'style' => 'text-transform:uppercase']]) ?>
        <?= Form::input('email', 'Correo', 'email', ['attrs' => ['maxlength' => 190]]) ?>
        <?= Form::input('phone', 'Teléfono', 'text', ['attrs' => ['maxlength' => 80]]) ?>
        <div></div>
        <?= Form::textarea('address', 'Dirección', ['rows' => 2, 'class' => 'sm:col-span-2']) ?>
        <?= Form::textarea('notes', 'Notas internas', ['rows' => 2, 'class' => 'sm:col-span-2']) ?>
        <div class="flex justify-between gap-2 sm:col-span-2">
            <div>
            <?php if ($client): ?>
                <button form="delete-client" class="btn btn-secondary text-red-600" onclick="return confirm('¿Eliminar este cliente?')">Eliminar</button>
            <?php endif; ?>
            </div>
            <div class="flex gap-2">
                <a href="<?= e(url('/clientes')) ?>" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary"><?= $client ? 'Guardar' : 'Crear cliente' ?></button>
            </div>
        </div>
    </form>
    <?php if ($client): ?>
    <form id="delete-client" method="post" action="<?= e(url('/clientes/' . (int) $client['id'] . '/eliminar')) ?>"><?= csrf_field() ?></form>
    <?php endif; ?>
</div>

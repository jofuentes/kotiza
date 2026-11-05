<?php
use App\Support\Form;

Form::bind($old, $errors);
?>
<div class="mx-auto max-w-3xl">
    <h1 class="mb-6 text-2xl font-semibold">Mi perfil</h1>
    <form method="post" action="<?= e(url('/perfil')) ?>" enctype="multipart/form-data" class="space-y-6">
        <?= csrf_field() ?>
        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">Datos de acceso</h2>
            <?= Form::input('name', 'Nombre completo', 'text', ['attrs' => ['required' => true, 'maxlength' => 120]]) ?>
            <?= Form::input('email', 'Correo', 'email', ['attrs' => ['required' => true, 'maxlength' => 190]]) ?>
            <?= Form::input('password', 'Contraseña nueva', 'password', ['attrs' => ['minlength' => 10, 'autocomplete' => 'new-password'], 'hint' => 'Déjala vacía para no cambiarla.']) ?>
            <?= Form::input('password_confirmation', 'Confirmar contraseña nueva', 'password', ['attrs' => ['autocomplete' => 'new-password']]) ?>
            <?= Form::input('current_password', 'Contraseña actual', 'password', ['attrs' => ['autocomplete' => 'current-password'], 'hint' => 'Necesaria solo si cambias correo o contraseña.', 'class' => 'sm:col-span-2']) ?>
        </div>
        <div class="card grid gap-4 p-6 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">En <?= e($company['trade_name']) ?></h2>
            <?= Form::input('display_name', 'Nombre en cotizaciones', 'text', ['attrs' => ['maxlength' => 120], 'hint' => 'Cómo apareces como vendedor en el PDF.']) ?>
            <?= Form::file('signature', 'Mi firma (opcional)', $signatureUrl, ['accept' => '.png,.jpg,.jpeg', 'hint' => 'PNG con fondo transparente, máx. 2 MB.']) ?>
        </div>
        <div class="flex justify-end"><button class="btn btn-primary">Guardar</button></div>
    </form>
</div>

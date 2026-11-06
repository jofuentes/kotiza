<?php
use App\Support\Form;

Form::bind($old, $errors);
?>
<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold">Correo</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Cuenta con la que <?= e(app_name()) ?> envía los enlaces para recuperar contraseñas. Se usa en toda la instalación y solo para este fin.</p>

    <?php if ($errors): ?>
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">No se guardó: revisa los campos marcados.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/correo')) ?>" class="card space-y-4 p-6" autocomplete="off">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold">Cuenta de Gmail</h2>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $configured ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' ?>">
                <?= $configured ? 'Activa' : 'Sin configurar' ?>
            </span>
        </div>
        <?= Form::input('account', 'Cuenta de Gmail', 'email', ['attrs' => ['required' => true, 'maxlength' => 190, 'placeholder' => 'tuempresa@gmail.com']]) ?>
        <?= Form::input('sender', 'Nombre del remitente', 'text', ['attrs' => ['required' => true, 'maxlength' => 120]]) ?>
        <?= Form::input('password', 'Contraseña de aplicación', 'password', [
            'attrs' => ['autocomplete' => 'new-password'] + ($configured ? [] : ['required' => true]),
            'hint' => $configured ? 'Déjala vacía para conservar la actual. Se guarda cifrada y no se vuelve a mostrar.' : 'No es tu contraseña normal. Se guarda cifrada y no se vuelve a mostrar.',
        ]) ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Para crearla, activa la verificación en dos pasos en tu cuenta de Google y abre
            <a class="text-brand underline dark:text-sky-400" href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Contraseñas de aplicaciones</a>.
        </p>
        <div class="flex justify-end">
            <button class="btn btn-primary">Comprobar y guardar</button>
        </div>
    </form>

    <?php if ($configured): ?>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
        <form method="post" action="<?= e(url('/correo/eliminar')) ?>" onsubmit="return confirm('¿Eliminar la cuenta? Ya no se podrán recuperar contraseñas por correo.')">
            <?= csrf_field() ?>
            <button class="btn btn-secondary text-red-600 dark:text-red-400">Eliminar cuenta</button>
        </form>
        <form method="post" action="<?= e(url('/correo/probar')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-secondary">Enviarme un correo de prueba</button>
        </form>
    </div>
    <?php endif; ?>
</div>

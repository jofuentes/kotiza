<?php
use App\Support\Form;

$pageTitle = 'Crear contraseña nueva';
Form::bind([], $errors);
?>
<div class="card p-6 sm:p-8">
    <?php if (!$valid): ?>
        <h1 class="mb-2 text-xl font-semibold">Este enlace ya no es válido</h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Los enlaces vencen a los <?= \App\Domain\PasswordResets::LINK_MINUTES ?> minutos y solo funcionan una vez. Si solicitaste varios, usa el más reciente.</p>
        <a class="btn btn-primary w-full" href="<?= e(url('/contrasena/olvide')) ?>">Solicitar otro enlace</a>
    <?php else: ?>
        <h1 class="mb-6 text-xl font-semibold">Crear contraseña nueva</h1>
        <form method="post" action="<?= e(url('/contrasena/restablecer')) ?>" class="space-y-4" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <?= Form::input('password', 'Contraseña nueva', 'password', ['hint' => 'Mínimo 10 caracteres.', 'attrs' => ['required' => true, 'minlength' => 10, 'maxlength' => 200, 'autocomplete' => 'new-password', 'autofocus' => true]]) ?>
            <?= Form::input('password_confirmation', 'Confirmar contraseña', 'password', ['attrs' => ['required' => true, 'autocomplete' => 'new-password']]) ?>
            <button class="btn btn-primary w-full">Guardar contraseña</button>
        </form>
    <?php endif; ?>
</div>

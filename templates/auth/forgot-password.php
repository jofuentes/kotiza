<?php $pageTitle = 'Recuperar contraseña'; ?>
<div class="card p-6 sm:p-8">
    <h1 class="mb-2 text-xl font-semibold">Recuperar contraseña</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Escribe tu correo y te enviaremos un enlace para crear una contraseña nueva.</p>
    <?php if ($error): ?>
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/contrasena/olvide')) ?>" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="label" for="email">Correo</label>
            <input class="input" type="email" id="email" name="email" required autofocus autocomplete="username">
        </div>
        <button class="btn btn-primary w-full">Enviar enlace</button>
    </form>
    <p class="mt-4 text-center text-sm"><a class="text-brand underline dark:text-sky-400" href="<?= e(url('/login')) ?>">Volver a iniciar sesión</a></p>
</div>

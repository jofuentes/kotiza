<?php $pageTitle = 'Iniciar sesión'; ?>
<div class="card p-6 sm:p-8">
    <h1 class="mb-6 text-xl font-semibold">Iniciar sesión</h1>
    <?php if ($error): ?>
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="label" for="email">Correo</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
        </div>
        <div>
            <label class="label" for="password">Contraseña</label>
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary w-full">Entrar</button>
    </form>
    <p class="mt-4 text-center text-sm text-slate-500 dark:text-slate-400">
        <?php if ($canRecover): ?>
            <a class="text-brand underline dark:text-sky-400" href="<?= e(url('/contrasena/olvide')) ?>">¿Olvidaste tu contraseña?</a>
        <?php else: ?>
            ¿Olvidaste tu contraseña? Pide ayuda a tu administrador.
        <?php endif; ?>
    </p>
</div>

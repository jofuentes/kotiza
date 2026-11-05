<?php $pageTitle = 'Elegir empresa'; ?>
<div class="card p-6 sm:p-8">
    <h1 class="mb-1 text-xl font-semibold">¿Con qué empresa vas a trabajar?</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Puedes cambiarla después desde el menú lateral.</p>
    <div class="space-y-2">
        <?php foreach ($companies as $c): ?>
        <form method="post" action="<?= e(url('/empresa/seleccionar')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= (int) $c['id'] ?>">
            <button class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-left hover:border-brand hover:bg-brand/5 dark:border-slate-700">
                <span>
                    <span class="block font-medium"><?= e($c['trade_name']) ?></span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($c['legal_name']) ?></span>
                </span>
                <span class="text-xs text-slate-500"><?= $c['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?></span>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
    <form method="post" action="<?= e(url('/logout')) ?>" class="mt-6 text-center">
        <?= csrf_field() ?>
        <button class="text-sm text-slate-500 hover:underline">Cerrar sesión</button>
    </form>
</div>

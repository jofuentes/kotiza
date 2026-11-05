<?php $pageTitle = 'Inicio'; ?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Hola, <?= e(explode(' ', $authUser['name'])[0]) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Estás trabajando con <strong><?= e($company['trade_name']) ?></strong>.</p>
</div>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ([['Cotizaciones', '0'], ['Borradores', '0'], ['Aceptadas este mes', '0'], ['Clientes', '0']] as [$label, $value]): ?>
    <div class="card p-5">
        <div class="text-sm text-slate-500 dark:text-slate-400"><?= e($label) ?></div>
        <div class="mt-1 text-3xl font-semibold"><?= e($value) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<div class="card mt-6 p-6 text-sm text-slate-600 dark:text-slate-300">
    Próximo folio: <span class="font-mono font-semibold"><?= e($company['folio_prefix']) ?>-<?= date('Y') ?>-<?= str_pad((string) $company['folio_next'], (int) $company['folio_padding'], '0', STR_PAD_LEFT) ?></span>
</div>

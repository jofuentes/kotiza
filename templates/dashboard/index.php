<?php $pageTitle = 'Inicio'; ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">Hola, <?= e(explode(' ', $authUser['name'])[0]) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Estás trabajando con <strong><?= e($company['trade_name']) ?></strong> · próximo folio <span class="font-mono"><?= e(\App\Domain\FolioService::preview($company)) ?></span></p>
    </div>
    <a href="<?= e(url('/cotizaciones/nueva')) ?>" class="btn btn-primary">+ Nueva cotización</a>
</div>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($stats as [$label, $value, $href]): ?>
    <a href="<?= e(url($href)) ?>" class="card p-5 transition hover:border-brand">
        <div class="text-sm text-slate-500 dark:text-slate-400"><?= e($label) ?></div>
        <div class="mt-1 text-3xl font-semibold"><?= (int) $value ?></div>
    </a>
    <?php endforeach; ?>
</div>
<div class="card mt-6 overflow-hidden">
    <h2 class="border-b border-slate-200 px-5 py-3 font-semibold dark:border-slate-800">Actividad reciente</h2>
    <?php if (!$recent): ?>
        <p class="p-8 text-center text-sm text-slate-500">Aún no hay cotizaciones. <a class="text-brand underline dark:text-sky-400" href="<?= e(url('/cotizaciones/nueva')) ?>">Crea la primera</a>.</p>
    <?php else: ?>
    <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
        <?php foreach ($recent as $qt): ?>
        <li><a href="<?= e(url('/cotizaciones/' . (int) $qt['id'])) ?>" class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
            <span class="w-36 font-mono font-medium"><?= e($qt['folio']) ?><?= $qt['version'] > 1 ? ' v' . (int) $qt['version'] : '' ?></span>
            <span class="min-w-0 flex-1 truncate"><?= e($qt['client_name'] ?? 'Sin cliente') ?><?= $qt['title'] ? ' · ' . e($qt['title']) : '' ?></span>
            <?php $st = $qt['status']; include __DIR__ . '/../quotes/status-badge.php'; ?>
            <span class="w-36 text-right font-medium">$<?= number_format((float) $qt['total'], 2) ?> <?= e($qt['currency']) ?></span>
        </a></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

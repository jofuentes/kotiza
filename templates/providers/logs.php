<a href="<?= e(url('/proveedores')) ?>" class="text-sm text-slate-500 hover:underline">← Proveedores</a>
<h1 class="mb-1 mt-1 text-2xl font-semibold">Log de llamadas · <?= e($name) ?></h1>
<?php if ($stats && $stats['total']): ?>
<p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
    Últimas 24 h: <?= (int) $stats['total'] ?> llamadas, <?= (int) $stats['ok'] ?> exitosas, <?= (int) $stats['avg_ms'] ?> ms en promedio.
</p>
<?php else: ?><p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Se muestran las últimas 200 llamadas.</p><?php endif; ?>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
            <tr><th class="px-4 py-2">Fecha</th><th class="px-4 py-2">Método</th><th class="px-4 py-2">Endpoint</th><th class="px-4 py-2 text-right">Código</th><th class="px-4 py-2 text-right">Tiempo</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono text-xs dark:divide-slate-800">
        <?php if (!$logs): ?>
            <tr><td colspan="5" class="px-4 py-8 text-center font-sans text-sm text-slate-500">Aún no hay llamadas registradas.</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $l):
            $code = $l['http_code'];
            $cls = $code === null ? 'text-red-600 dark:text-red-400' : ($code < 300 ? 'text-emerald-600 dark:text-emerald-400' : ($code < 500 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400')); ?>
            <tr>
                <td class="whitespace-nowrap px-4 py-2 text-slate-500"><?= e(date('d/m H:i:s', strtotime($l['created_at']))) ?></td>
                <td class="px-4 py-2"><?= e($l['method']) ?></td>
                <td class="max-w-xl px-4 py-2">
                    <div class="truncate" title="<?= e($l['endpoint']) ?>"><?= e($l['endpoint']) ?></div>
                    <?php if ($l['error']): ?><div class="mt-0.5 truncate font-sans text-red-600 dark:text-red-400" title="<?= e($l['error']) ?>"><?= e($l['error']) ?></div><?php endif; ?>
                </td>
                <td class="px-4 py-2 text-right font-semibold <?= $cls ?>"><?= $code === null ? 'ERR' : (int) $code ?></td>
                <td class="whitespace-nowrap px-4 py-2 text-right"><?= (int) $l['duration_ms'] ?> ms</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php $money = fn ($v, $c) => '$' . number_format((float) $v, 2) . ' ' . $c; ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">Cotizaciones</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= $isAdmin ? 'Todas las cotizaciones de la empresa.' : 'Tus cotizaciones y las que tus compañeros compartieron contigo.' ?></p>
    </div>
    <a href="<?= e(url('/cotizaciones/nueva')) ?>" class="btn btn-primary">+ Nueva cotización</a>
</div>
<form method="get" class="mb-4 flex flex-wrap gap-2">
    <input type="search" name="q" value="<?= e($q) ?>" class="input max-w-sm" placeholder="Folio, cliente o referencia">
    <select name="vista" class="input w-auto" onchange="this.form.submit()">
        <option value=""><?= $isAdmin ? 'Todas' : 'Mías y compartidas' ?></option>
        <option value="mias" <?= $vista === 'mias' ? 'selected' : '' ?>>Creadas por mí</option>
        <option value="compartidas" <?= $vista === 'compartidas' ? 'selected' : '' ?>>Compartidas conmigo</option>
    </select>
    <select name="status" class="input w-auto" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-secondary">Buscar</button>
</form>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
            <tr><th class="px-4 py-3">Folio</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3">Creada por</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php if (!$quotes): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay cotizaciones<?= $q !== '' || $status !== '' ? ' con esos filtros' : ' todavía' ?>.</td></tr>
        <?php endif; ?>
        <?php foreach ($quotes as $qt): ?>
            <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50" onclick="location.href='<?= e(url('/cotizaciones/' . (int) $qt['id'])) ?>'">
                <td class="px-4 py-3">
                    <a href="<?= e(url('/cotizaciones/' . (int) $qt['id'])) ?>" class="font-mono font-medium text-brand hover:underline dark:text-sky-400"><?= e($qt['folio']) ?><?= $qt['version'] > 1 ? ' v' . (int) $qt['version'] : '' ?></a>
                    <?php if ($qt['title']): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($qt['title']) ?></div><?php endif; ?>
                </td>
                <td class="px-4 py-3"><?= e($qt['client_name'] ?? '—') ?><?php if ($qt['client_company']): ?><div class="text-xs text-slate-500"><?= e($qt['client_company']) ?></div><?php endif; ?></td>
                <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300"><?= e(date('d/m/Y', strtotime($qt['issue_date']))) ?></td>
                <td class="px-4 py-3">
                    <?php $st = $qt['status']; include __DIR__ . '/status-badge.php'; ?>
                    <?php if ($qt['approval_status'] === 'pending'): ?><span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">Por aprobar</span><?php endif; ?>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-medium"><?= e($money($qt['total'], $qt['currency'])) ?></td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                    <?= (int) $qt['user_id'] === (int) $authUser['id'] ? 'Tú' : e($qt['user_name']) ?>
                    <?php if ($qt['shared_permission']): ?>
                        <div class="text-xs text-violet-700 dark:text-violet-300">Compartida contigo · <?= $qt['shared_permission'] === 'edit' ? 'puedes editar' : 'solo ver' ?></div>
                    <?php elseif ((int) $qt['shares_count'] > 0): ?>
                        <div class="text-xs text-slate-500">Compartida con <?= (int) $qt['shares_count'] ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

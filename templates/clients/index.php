<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">Clientes</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Catálogo reutilizable para tus cotizaciones.</p>
    </div>
    <a href="<?= e(url('/clientes/nuevo')) ?>" class="btn btn-primary">+ Nuevo cliente</a>
</div>
<form method="get" class="mb-4 flex gap-2">
    <input type="search" name="q" value="<?= e($q) ?>" class="input max-w-md" placeholder="Buscar por nombre, empresa, RFC o correo">
    <button class="btn btn-secondary">Buscar</button>
</form>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
            <tr><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">RFC</th><th class="px-4 py-3">Contacto</th><th class="px-4 py-3 text-right">Cotizaciones</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php if (!$clients): ?>
            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500"><?= $q !== '' ? 'Sin resultados.' : 'Aún no tienes clientes.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($clients as $c): ?>
            <tr>
                <td class="px-4 py-3">
                    <div class="font-medium"><?= e($c['name']) ?></div>
                    <?php if ($c['company_name']): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($c['company_name']) ?></div><?php endif; ?>
                </td>
                <td class="px-4 py-3 font-mono text-xs"><?= e($c['rfc']) ?></td>
                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300"><?= e($c['email']) ?><?= $c['email'] && $c['phone'] ? '<br>' : '' ?><?= e($c['phone']) ?></td>
                <td class="px-4 py-3 text-right"><?= (int) $c['quotes_count'] ?></td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="<?= e(url('/cotizaciones/nueva?cliente=' . (int) $c['id'])) ?>" class="mr-3 text-brand hover:underline dark:text-sky-400">Cotizar</a>
                    <a href="<?= e(url('/clientes/' . (int) $c['id'] . '/editar')) ?>" class="text-slate-600 hover:underline dark:text-slate-300">Editar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

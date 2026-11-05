<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">Usuarios</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Personas con acceso a <?= e($company['trade_name']) ?>.</p>
    </div>
    <a href="<?= e(url('/usuarios/nuevo')) ?>" class="btn btn-primary">+ Nuevo usuario</a>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Rol</th>
                <th class="px-4 py-3">Desc. máx.</th>
                <th class="px-4 py-3">Último acceso</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($users as $u): ?>
            <tr class="<?= $u['is_active'] ? '' : 'opacity-50' ?>">
                <td class="px-4 py-3">
                    <div class="font-medium"><?= e($u['name']) ?><?= (int) $u['id'] === (int) $authUser['id'] ? ' <span class="text-xs text-slate-400">(tú)</span>' : '' ?></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400"><?= e($u['email']) ?></div>
                </td>
                <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $u['role'] === 'admin' ? 'bg-brand/10 text-brand dark:text-white dark:bg-brand/30' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' ?>">
                        <?= $u['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?>
                    </span>
                </td>
                <td class="px-4 py-3"><?= $u['role'] === 'admin' ? '—' : ($u['max_discount_pct'] === null ? 'Sin límite' : e(rtrim(rtrim((string) $u['max_discount_pct'], '0'), '.')) . ' %') ?></td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : 'Nunca' ?></td>
                <td class="px-4 py-3"><?= $u['is_active'] ? '<span class="text-emerald-600 dark:text-emerald-400">Activo</span>' : 'Inactivo' ?></td>
                <td class="px-4 py-3 text-right"><a href="<?= e(url('/usuarios/' . (int) $u['id'] . '/editar')) ?>" class="text-brand hover:underline dark:text-sky-400">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

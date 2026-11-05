<?php
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = [
    ['/', 'Inicio', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
    ['/productos', 'Productos', 'M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
];
if ($isAdmin) {
    $nav[] = ['/empresa', 'Empresa', 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m-1 4h1m4-4h1m-1 4h1M10 21v-4h4v4'];
    $nav[] = ['/proveedores', 'Proveedores', 'M8 7h12m0 0-4-4m4 4-4 4m0 6H4m0 0 4 4m-4-4 4-4'];
    $nav[] = ['/usuarios', 'Usuarios', 'M17 20h5v-2a4 4 0 0 0-5.4-3.7M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.9M7 20H2v-2a4 4 0 0 1 5.4-3.7M7 20v-2c0-.7.1-1.3.4-1.9m0 0a5 5 0 0 1 9.2 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'];
}
?>
<!DOCTYPE html>
<html lang="es-MX" class="h-full">
<head><?php include __DIR__ . '/../partials/head.php'; ?></head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100" x-data="{ sidebar: false }">
<div class="flex min-h-full">
    <!-- Fondo móvil -->
    <div x-cloak x-show="sidebar" @click="sidebar = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

    <!-- Barra lateral -->
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform lg:static lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900">
        <a href="<?= e(url('/')) ?>" class="flex h-16 items-center gap-2 border-b border-slate-200 px-5 font-bold dark:border-slate-800">
            <?php if (!empty($company['logo_path'])): ?>
                <img src="<?= e(url('/archivos/' . $company['logo_path'])) ?>" alt="<?= e($company['trade_name']) ?>" class="h-9 max-w-[11rem] rounded bg-white object-contain px-1">
            <?php else: ?>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-white">C</span>
                Cotizador
            <?php endif; ?>
        </a>
        <nav class="flex-1 space-y-1 p-3">
            <?php foreach ($nav as [$href, $label, $icon]): ?>
                <?php $active = $href === '/' ? $current === '/' : ($current === $href || str_starts_with((string) $current, $href . '/')); ?>
                <a href="<?= e(url($href)) ?>" class="nav-link <?= $active ? 'nav-link-active' : '' ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="<?= e($icon) ?>"/></svg>
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php if (count($companies) > 1): ?>
        <form method="post" action="<?= e(url('/empresa/seleccionar')) ?>" class="border-t border-slate-200 p-3 dark:border-slate-800">
            <?= csrf_field() ?>
            <label class="label text-xs" for="company-switch">Empresa activa</label>
            <select id="company-switch" name="company_id" class="input" onchange="this.form.submit()">
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $company['id'] ? 'selected' : '' ?>><?= e($c['trade_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </aside>

    <!-- Contenido -->
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <button type="button" @click="sidebar = true" class="rounded-lg p-2 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800" aria-label="Menú">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="min-w-0 flex-1 truncate font-semibold"><?= e($company['trade_name'] ?? '') ?></div>
            <?php include __DIR__ . '/../partials/theme-toggle.php'; ?>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 font-semibold dark:bg-slate-700"><?= e(mb_strtoupper(mb_substr($authUser['name'] ?? '?', 0, 1))) ?></span>
                    <span class="hidden sm:block"><?= e($authUser['name'] ?? '') ?></span>
                </button>
                <div x-cloak x-show="open" class="card absolute right-0 mt-2 w-56 p-2">
                    <div class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                        <?= e($authUser['email'] ?? '') ?><br>
                        <?= $isAdmin ? 'Administrador' : 'Vendedor' ?>
                    </div>
                    <a href="<?= e(url('/perfil')) ?>" class="nav-link">Mi perfil</a>
                    <?php if ($isAdmin): ?>
                    <a href="<?= e(url('/empresas/nueva')) ?>" class="nav-link">Nueva empresa</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="nav-link w-full">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>

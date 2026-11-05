<?php
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = [
    ['/', 'Inicio', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
];
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
        <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5 font-bold dark:border-slate-800">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-white">C</span>
            Cotizador
        </div>
        <nav class="flex-1 space-y-1 p-3">
            <?php foreach ($nav as [$href, $label, $icon]): ?>
                <?php $active = $href === '/' ? $current === '/' : str_starts_with((string) $current, $href); ?>
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

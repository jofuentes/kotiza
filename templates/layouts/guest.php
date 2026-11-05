<!DOCTYPE html>
<html lang="es-MX" class="h-full">
<head><?php include __DIR__ . '/../partials/head.php'; ?></head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="absolute right-4 top-4"><?php include __DIR__ . '/../partials/theme-toggle.php'; ?></div>
    <main class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="mb-8">
            <img src="<?= e(asset('assets/brand/kotiza-logo.svg')) ?>" alt="<?= e(app_name()) ?>" class="h-10 dark:hidden">
            <img src="<?= e(asset('assets/brand/kotiza-logo-dark.svg')) ?>" alt="<?= e(app_name()) ?>" class="hidden h-10 dark:block">
        </div>
        <div class="w-full <?= e($wide ?? false ? 'max-w-3xl' : 'max-w-md') ?>">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </div>
    </main>
</body>
</html>

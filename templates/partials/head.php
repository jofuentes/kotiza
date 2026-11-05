<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($pageTitle ?? null) ? $pageTitle . ' · ' . app_name() : app_name()) ?></title>
<link rel="icon" href="<?= e(asset('assets/brand/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('assets/brand/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= e(asset('assets/brand/apple-touch-icon.png')) ?>">
<meta name="theme-color" content="#1E40AF">
<script>
    // Aplica el tema antes de pintar para evitar parpadeo
    (function () {
        var t = localStorage.getItem('theme');
        if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
<?php if (!empty($company['color_primary'])): ?>
<style>:root{--color-brand: <?= e($company['color_primary']) ?>;}</style>
<?php endif; ?>
<script defer src="<?= e(asset('assets/app.js')) ?>"></script>
<script defer src="<?= e(asset('assets/alpine.min.js')) ?>"></script>

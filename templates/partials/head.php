<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($pageTitle ?? null) ? $pageTitle . ' · Cotizador' : 'Cotizador') ?></title>
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

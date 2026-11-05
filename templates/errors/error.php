<?php $pageTitle = $title; ?>
<div class="card p-8 text-center">
    <div class="text-5xl font-bold text-brand"><?= (int) $status ?></div>
    <h1 class="mt-2 text-xl font-semibold"><?= e($title) ?></h1>
    <?php if ($message): ?><p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><?= e($message) ?></p><?php endif; ?>
    <a href="<?= e(url('/')) ?>" class="btn btn-secondary mt-6">Volver al inicio</a>
</div>

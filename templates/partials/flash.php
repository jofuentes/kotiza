<?php foreach (\App\Core\Session::flashes() as $f): ?>
<?php $cls = match ($f['type']) {
    'success' => 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    'error'   => 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
    default   => 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200',
}; ?>
<div x-data="{ open: true }" x-show="open" class="mb-4 flex items-start justify-between gap-4 rounded-lg border px-4 py-3 text-sm <?= $cls ?>">
    <span><?= e($f['message']) ?></span>
    <button type="button" @click="open = false" class="opacity-60 hover:opacity-100" aria-label="Cerrar">✕</button>
</div>
<?php endforeach; ?>

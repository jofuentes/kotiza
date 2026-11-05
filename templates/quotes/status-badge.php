<?php
$cls = [
    'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    'sent' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    'accepted' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    'expired' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
][$st] ?? '';
?><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $cls ?>"><?= e(\App\Http\Controllers\QuoteController::STATUSES[$st] ?? $st) ?></span>

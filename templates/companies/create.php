<?php
use App\Support\Form;

Form::bind($old, $errors);
$pageTitle = 'Nueva empresa';
?>
<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-semibold">Nueva empresa</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Quedarás como administrador. Logo, membrete y demás datos los completas después en su perfil.</p>
    <form method="post" action="<?= e(url('/empresas')) ?>" class="card grid gap-4 p-6 sm:grid-cols-2">
        <?= csrf_field() ?>
        <?= Form::input('trade_name', 'Nombre comercial', 'text', ['attrs' => ['required' => true]]) ?>
        <?= Form::input('legal_name', 'Razón social', 'text', ['attrs' => ['required' => true]]) ?>
        <?= Form::input('rfc', 'RFC (opcional)', 'text', ['attrs' => ['maxlength' => 13, 'style' => 'text-transform:uppercase']]) ?>
        <?= Form::select('default_currency', 'Moneda por defecto', ['MXN' => 'Pesos mexicanos (MXN)', 'USD' => 'Dólares (USD)']) ?>
        <?= Form::input('vat_rate', 'IVA (%)', 'number', ['attrs' => ['step' => '0.01', 'min' => 0, 'max' => 100, 'required' => true]]) ?>
        <div></div>
        <?= Form::input('folio_prefix', 'Prefijo de folio', 'text', ['attrs' => ['maxlength' => 20, 'style' => 'text-transform:uppercase']]) ?>
        <?= Form::input('folio_next', 'Siguiente número de folio', 'number', ['attrs' => ['min' => 1, 'required' => true], 'hint' => 'Para continuar un consecutivo existente.']) ?>
        <div class="flex justify-end gap-2 sm:col-span-2">
            <a href="<?= e(url('/')) ?>" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Crear empresa</button>
        </div>
    </form>
</div>

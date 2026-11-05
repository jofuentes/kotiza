<div class="mx-auto max-w-2xl" x-data="{ client: <?= e(json_encode($client)) ?> }">
    <a href="<?= e(url('/cotizaciones')) ?>" class="text-sm text-slate-500 hover:underline">← Cotizaciones</a>
    <h1 class="mb-6 mt-1 text-2xl font-semibold">Nueva cotización</h1>
    <form method="post" action="<?= e(url('/cotizaciones')) ?>" class="card space-y-5 p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="client_id" :value="client ? client.id : ''">
        <div>
            <span class="label">Cliente</span>
            <?php $pickerModel = 'client'; include __DIR__ . '/client-picker.php'; ?>
            <p class="hint">Puedes dejarlo vacío y elegirlo después.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="label" for="title">Referencia / proyecto (opcional)</label>
                <input class="input" id="title" name="title" maxlength="200" placeholder="Ej. CCTV bodega norte">
            </div>
            <div>
                <label class="label" for="currency">Moneda</label>
                <select class="input" id="currency" name="currency">
                    <option value="MXN" <?= $company['default_currency'] === 'MXN' ? 'selected' : '' ?>>Pesos (MXN)</option>
                    <option value="USD" <?= $company['default_currency'] === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <a href="<?= e(url('/cotizaciones')) ?>" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Crear y agregar partidas →</button>
        </div>
    </form>
</div>

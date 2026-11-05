<?php
/** Plantilla del PDF de cotización (mPDF). Misma para vista previa y descarga. */
$cur = $quote['currency'];
$m = fn ($v) => '$' . number_format((float) $v, 2);
$qtyFmt = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.');
$date = function (string $d): string {
    $months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($d);
    return date('j', $t) . ' de ' . $months[(int) date('n', $t)] . ' de ' . date('Y', $t);
};
// #, descripción, cantidad, unidad, precio unitario, importe + opcionales
$cols = 6 + ($opts['show_images'] ? 1 : 0) + ($hasDiscount ? 1 : 0) + ($opts['show_stock'] ? 1 : 0);
?>
<style>
<?php if ($background): ?>
@page { background: url("<?= e($background) ?>") no-repeat 0 0; background-image-resize: 6; }
<?php endif; ?>
body { color: #1f2937; }
.primary { color: <?= e($primary) ?>; }
.muted { color: #6b7280; }
.small { font-size: 7.5pt; }
table { border-collapse: collapse; width: 100%; }
.head td { vertical-align: top; }
.title { font-size: 18pt; font-weight: bold; color: <?= e($primary) ?>; letter-spacing: 1pt; }
.folio-box td { padding: 1.5mm 3mm; font-size: 8.5pt; }
.folio-box .lbl { color: #6b7280; text-align: right; }
.card { border: 0.3mm solid #e5e7eb; }
.card td { padding: 2.5mm 3mm; vertical-align: top; }
.card-title { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.6pt; color: <?= e($secondary) ?>; font-weight: bold; padding-bottom: 1mm; }
.items th { background-color: <?= e($primary) ?>; color: #ffffff; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; padding: 2mm 1.5mm; text-align: left; }
.items td { padding: 2mm 1.5mm; border-bottom: 0.2mm solid #e5e7eb; vertical-align: top; font-size: 8.5pt; }
.items .num { text-align: right; white-space: nowrap; }
.items .section td { background-color: #f3f4f6; font-weight: bold; color: <?= e($primary) ?>; border-bottom: 0.3mm solid <?= e($secondary) ?>; }
.model { font-weight: bold; font-size: 8pt; }
.brand { color: #6b7280; font-size: 7.5pt; }
.notes { color: #4b5563; font-size: 7.5pt; font-style: italic; }
.totals td { padding: 1.2mm 2mm; font-size: 9pt; }
.totals .lbl { text-align: right; color: #4b5563; }
.totals .val { text-align: right; width: 32mm; }
.totals .grand td { font-size: 11pt; font-weight: bold; color: #ffffff; background-color: <?= e($primary) ?>; }
.words { font-size: 8pt; color: #374151; }
.block-title { font-size: 8pt; font-weight: bold; color: <?= e($primary) ?>; text-transform: uppercase; letter-spacing: 0.5pt; margin-bottom: 1mm; }
.block { font-size: 8pt; color: #374151; }
</style>

<?php if (!$hasLetterhead): ?>
<!-- Encabezado de la empresa (si no hay membrete) -->
<table class="head" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 45%;">
            <?php if ($logo): ?><img src="<?= e($logo) ?>" style="max-height: 20mm; max-width: 70mm;"><?php else: ?>
                <div class="title" style="font-size: 16pt;"><?= e($company['trade_name']) ?></div><?php endif; ?>
        </td>
        <td style="width: 55%; text-align: right; font-size: 8pt;" class="muted">
            <div style="font-size: 10pt; font-weight: bold; color: #111827;"><?= e($company['legal_name']) ?></div>
            <?php if ($company['rfc']): ?>RFC: <?= e($company['rfc']) ?><br><?php endif; ?>
            <?php if ($company['address']): ?><?= nl2br(e($company['address'])) ?><br><?php endif; ?>
            <?= e(implode(' · ', array_filter([$company['phone'], $company['email'], $company['website'] ? preg_replace('#^https?://#', '', $company['website']) : null]))) ?>
        </td>
    </tr>
</table>
<?php endif; ?>

<!-- Título y folio -->
<table class="head" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 50%; vertical-align: bottom;">
            <div class="title">COTIZACIÓN</div>
            <?php if ($quote['title']): ?><div style="font-size: 10pt; margin-top: 1mm;"><?= e($quote['title']) ?></div><?php endif; ?>
        </td>
        <td style="width: 50%;">
            <table class="folio-box card" style="width: 70mm;" align="right">
                <tr><td class="lbl">Folio</td><td style="font-weight: bold; font-size: 10pt;"><?= e($quote['folio']) ?><?= (int) $quote['version'] > 1 ? ' v' . (int) $quote['version'] : '' ?></td></tr>
                <tr><td class="lbl">Fecha</td><td><?= e($date($quote['issue_date'])) ?></td></tr>
                <tr><td class="lbl">Vigencia</td><td><?= e($date($quote['valid_until'])) ?></td></tr>
                <tr><td class="lbl">Moneda</td><td><?= $cur === 'USD' ? 'Dólares (USD)' : 'Pesos (MXN)' ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<!-- Cliente y vendedor -->
<table class="card" style="margin-bottom: 5mm;">
    <tr>
        <td style="width: 60%;">
            <div class="card-title">Cliente</div>
            <?php if ($client): ?>
                <div style="font-weight: bold; font-size: 10pt;"><?= e($client['company_name'] ?: $client['name']) ?></div>
                <?php if ($client['company_name']): ?><div>Atención: <?= e($client['name']) ?></div><?php endif; ?>
                <?php if ($client['rfc']): ?><div class="muted">RFC: <?= e($client['rfc']) ?></div><?php endif; ?>
                <?php if ($client['address']): ?><div class="muted"><?= nl2br(e($client['address'])) ?></div><?php endif; ?>
                <div class="muted"><?= e(implode(' · ', array_filter([$client['email'] ?? null, $client['phone'] ?? null]))) ?></div>
            <?php else: ?>
                <div class="muted">—</div>
            <?php endif; ?>
        </td>
        <td style="width: 40%; border-left: 0.3mm solid #e5e7eb;">
            <div class="card-title">Atendido por</div>
            <div style="font-weight: bold;"><?= e($seller['name']) ?></div>
            <div class="muted"><?= e($seller['email']) ?></div>
            <?php if ($quote['exchange_rate']): ?><div class="muted small" style="margin-top: 1.5mm;">Tipo de cambio: $<?= number_format((float) $quote['exchange_rate'], 4) ?> MXN/USD</div><?php endif; ?>
        </td>
    </tr>
</table>

<!-- Partidas -->
<table class="items" repeat_header="1">
    <thead>
        <tr>
            <th style="width: 6mm;">#</th>
            <?php if ($opts['show_images']): ?><th style="width: 16mm;"></th><?php endif; ?>
            <th>Descripción</th>
            <th class="num" style="width: 14mm;">Cant.</th>
            <th style="width: 15mm;">Unidad</th>
            <?php if ($opts['show_stock']): ?><th class="num" style="width: 14mm;">Exist.</th><?php endif; ?>
            <th class="num" style="width: 24mm;">P. unitario</th>
            <?php if ($hasDiscount): ?><th class="num" style="width: 20mm;">Desc.</th><?php endif; ?>
            <th class="num" style="width: 26mm;">Importe</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
        <?php if ($g['title'] !== null): ?>
        <tr class="section"><td colspan="<?= $cols - 1 ?>"><?= e($g['title']) ?></td><td class="num"><?= $m($g['total']) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($g['items'] as $it): ?>
        <tr>
            <td class="muted"><?= (int) $it['n'] ?></td>
            <?php if ($opts['show_images']): ?>
            <td><?php if ($it['image']): ?><img src="<?= e($it['image']) ?>" style="width: 14mm; height: 14mm;"><?php endif; ?></td>
            <?php endif; ?>
            <td>
                <?php if ($opts['show_brands'] && $it['type'] === 'product' && ($it['model'] || $it['brand'])): ?>
                    <span class="model"><?= e($it['model']) ?></span><?php if ($it['brand']): ?> <span class="brand">· <?= e($it['brand']) ?></span><?php endif; ?><br>
                <?php endif; ?>
                <?= nl2br(e($it['description'])) ?>
                <?php if ($it['notes']): ?><div class="notes"><?= nl2br(e($it['notes'])) ?></div><?php endif; ?>
            </td>
            <td class="num"><?= e($qtyFmt($it['qty'])) ?></td>
            <td><?= e($it['unit']) ?></td>
            <?php if ($opts['show_stock']): ?><td class="num"><?= $it['stock'] !== null ? (int) $it['stock'] : '—' ?></td><?php endif; ?>
            <td class="num"><?= $m($it['unit_price']) ?></td>
            <?php if ($hasDiscount): ?>
            <td class="num"><?php if ($it['discount'] > 0): ?>−<?= $m($it['discount']) ?><?php if ($it['discount_label']): ?><br><span class="small muted"><?= e($it['discount_label']) ?></span><?php endif; ?><?php endif; ?></td>
            <?php endif; ?>
            <td class="num" style="font-weight: bold;"><?= $m($it['total']) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Totales -->
<table style="margin-top: 4mm;">
    <tr>
        <td style="width: 55%; vertical-align: top; padding-right: 4mm;">
            <div class="block-title">Importe con letra</div>
            <div class="words">(<?= e($words) ?>)</div>
            <?php if (!$opts['show_tax']): ?><div class="small muted" style="margin-top: 2mm;">Precios con IVA incluido.</div><?php endif; ?>
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table class="totals">
                <?php if ($opts['show_tax'] || $totals['discount'] > 0): ?>
                <tr><td class="lbl">Subtotal</td><td class="val"><?= $m($totals['subtotal']) ?></td></tr>
                <?php endif; ?>
                <?php if ($totals['discount'] > 0): ?>
                <tr><td class="lbl">Descuento</td><td class="val">−<?= $m($totals['discount']) ?></td></tr>
                <?php endif; ?>
                <?php if ($opts['show_tax']): ?>
                <tr><td class="lbl">IVA <?= rtrim(rtrim(number_format($totals['vat_rate'], 2), '0'), '.') ?> %</td><td class="val"><?= $m($totals['tax']) ?></td></tr>
                <?php endif; ?>
                <tr class="grand"><td class="lbl" style="color: #ffffff;">Total <?= e($cur) ?></td><td class="val"><?= $m($totals['total']) ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<!-- Notas, condiciones y datos bancarios -->
<?php $hasNotes = trim((string) $quote['notes']) !== ''; $hasTerms = trim((string) $quote['terms']) !== ''; $hasBank = trim((string) $company['bank_details']) !== ''; ?>
<?php if ($hasNotes || $hasTerms || $hasBank): ?>
<table style="margin-top: 6mm;" autosize="1">
    <tr>
        <?php if ($hasTerms || $hasNotes): ?>
        <td style="vertical-align: top; padding-right: 4mm; width: <?= $hasBank ? '60%' : '100%' ?>;">
            <?php if ($hasTerms): ?><div class="block-title">Condiciones comerciales</div><div class="block"><?= nl2br(e($quote['terms'])) ?></div><?php endif; ?>
            <?php if ($hasNotes): ?><div class="block-title" style="margin-top: 3mm;">Notas</div><div class="block"><?= nl2br(e($quote['notes'])) ?></div><?php endif; ?>
        </td>
        <?php endif; ?>
        <?php if ($hasBank): ?>
        <td style="vertical-align: top; width: 40%;">
            <table style="border: 0.3mm solid #e5e7eb;"><tr><td style="padding: 3mm;">
                <div class="block-title">Datos para pago</div>
                <div class="block"><?= nl2br(e($company['bank_details'])) ?></div>
            </td></tr></table>
        </td>
        <?php endif; ?>
    </tr>
</table>
<?php endif; ?>

<!-- Firma -->
<table style="margin-top: 10mm; page-break-inside: avoid;">
    <tr>
        <td style="width: 60%;"></td>
        <td style="width: 40%; text-align: center;">
            <?php if ($signature): ?><img src="<?= e($signature) ?>" style="max-height: 20mm; max-width: 55mm;"><br><?php else: ?><div style="height: 16mm;"></div><?php endif; ?>
            <div style="border-top: 0.3mm solid #9ca3af; padding-top: 1.5mm; font-weight: bold;"><?= e($seller['name']) ?></div>
            <div class="small muted"><?= e($company['trade_name']) ?></div>
        </td>
    </tr>
</table>

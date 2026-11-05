<table style="width: 100%; font-size: 7pt; color: #6b7280; border-top: 0.2mm solid #e5e7eb;">
    <tr>
        <td style="width: 70%; padding-top: 1mm;"><?= e(trim((string) ($company['footer_notes'] ?? '')) !== '' ? mb_strimwidth(preg_replace('/\s+/', ' ', $company['footer_notes']), 0, 160, '…') : $company['trade_name']) ?></td>
        <td style="width: 30%; text-align: right; padding-top: 1mm;"><?= e($quote['folio']) ?> · Página {PAGENO} de {nbpg}</td>
    </tr>
</table>

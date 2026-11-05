<?php
declare(strict_types=1);

namespace App\Pdf;

use App\Core\Database;
use App\Core\View;
use App\Domain\Quotes\QuoteService;
use Mpdf\Mpdf;

/**
 * Genera el PDF de una cotización con mPDF. La misma plantilla (templates/pdf/quote.php)
 * se usa para la vista previa y para el archivo descargado.
 */
final class QuotePdfRenderer
{
    public function __construct(private array $company)
    {
    }

    public function render(array $quote): string
    {
        $data = $this->data($quote);
        $c = $this->company;

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'Letter',
            'margin_top' => (float) $c['pdf_margin_top'],
            'margin_right' => (float) $c['pdf_margin_right'],
            'margin_bottom' => (float) $c['pdf_margin_bottom'],
            'margin_left' => (float) $c['pdf_margin_left'],
            'margin_header' => 0,
            // El pie (folio y página) va justo arriba del margen inferior, para no encimarse con el pie del membrete
            'margin_footer' => max(4.0, (float) $c['pdf_margin_bottom'] - 9),
            'default_font' => 'dejavusanscondensed',
            'default_font_size' => 9,
            'tempDir' => storage_path('cache/mpdf'),
            'setAutoTopMargin' => false,
        ]);
        $mpdf->SetTitle('Cotización ' . $quote['folio']);
        $mpdf->SetAuthor((string) $c['trade_name']);
        $mpdf->SetCreator(app_name());

        $background = $this->letterhead($mpdf);
        $data['background'] = $background;

        $mark = \App\Domain\Quotes\QuoteWorkflow::watermark((string) $quote['status'], (bool) (int) ($c['require_quote_approval'] ?? 1));
        if ($mark !== null) {
            $mpdf->SetWatermarkText($mark, 0.08);
            $mpdf->showWatermarkText = true;
            $mpdf->watermark_font = 'dejavusanscondensed';
        }
        $mpdf->SetHTMLFooter(View::partial('pdf/footer', $data));
        $mpdf->WriteHTML(View::partial('pdf/quote', $data));
        return $mpdf->Output('', 'S');
    }

    /** Nombre de archivo: COT-2026-0001_Cliente.pdf */
    public static function filename(array $quote): string
    {
        $snap = json_decode((string) $quote['client_snapshot'], true) ?: [];
        $client = (string) ($snap['company_name'] ?? '') ?: (string) ($snap['name'] ?? '');
        $client = strtr($client, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $client = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $client) ?? '', '-');
        $folio = $quote['folio'] . ((int) $quote['version'] > 1 ? '-v' . (int) $quote['version'] : '');
        return $folio . ($client !== '' ? '_' . mb_substr($client, 0, 60) : '') . '.pdf';
    }

    /**
     * Membrete como fondo de todas las páginas. PDF: se intenta como plantilla vectorial;
     * si FPDI no puede leerlo (PDF comprimido ≥1.5) se rasteriza la primera página.
     * @return ?string ruta de imagen para usar como fondo CSS (null si no hay o es vectorial)
     */
    private function letterhead(Mpdf $mpdf): ?string
    {
        $rel = $this->company['letterhead_path'] ?? null;
        if (!$rel || !is_file(storage_path($rel))) {
            return null;
        }
        $abs = storage_path($rel);
        if (($this->company['letterhead_type'] ?? '') !== 'pdf') {
            return $abs;
        }
        try {
            // FPDI analiza el archivo al dibujar la página; setSourceFile() lo obliga a hacerlo ya,
            // para poder caer al respaldo si el PDF usa compresión que el parser libre no soporta
            $mpdf->setSourceFile($abs);
            $mpdf->SetDocTemplate($abs, true);
            return null;
        } catch (\Throwable) {
            $png = preg_replace('/\.pdf$/', '', $abs) . '-page1.png';
            if (!is_file($png) || filemtime($png) < filemtime($abs)) {
                $cmd = sprintf('pdftoppm -png -r 200 -f 1 -l 1 -singlefile %s %s 2>&1',
                    escapeshellarg($abs), escapeshellarg(preg_replace('/\.png$/', '', $png)));
                exec($cmd, $out, $code);
                if ($code !== 0 || !is_file($png)) {
                    error_log('No se pudo convertir el membrete PDF: ' . implode(' ', $out));
                    return null;
                }
            }
            return $png;
        }
    }

    private function data(array $quote): array
    {
        $c = $this->company;
        $opts = QuoteService::pdfOptions(json_decode((string) $quote['pdf_options'], true) ?: []);
        $vatFactor = 1 + (float) $quote['vat_rate'] / 100;
        // Sin desglose de IVA los importes se muestran con IVA incluido
        $k = $opts['show_tax'] ? 1.0 : $vatFactor;

        $sections = Database::fetchAll('SELECT * FROM quote_sections WHERE quote_id = ? ORDER BY position', [$quote['id']]);
        $rows = Database::fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position', [$quote['id']]);
        $groups = [0 => ['title' => null, 'items' => [], 'total' => 0.0]];
        foreach ($sections as $s) {
            $groups[(int) $s['id']] = ['title' => $s['title'], 'items' => [], 'total' => 0.0];
        }
        // Partidas en su sección (las de secciones inexistentes van a "sin sección"); primero las sin sección
        $byGroup = [];
        foreach ($rows as $r) {
            $byGroup[isset($groups[(int) $r['section_id']]) ? (int) $r['section_id'] : 0][] = $r;
        }
        $hasDiscount = false;
        $n = 0;
        foreach (array_keys($groups) as $gid) {
            foreach ($byGroup[$gid] ?? [] as $r) {
                $unit = (float) ($r['price_manual'] ?? $r['price_calculated'] ?? 0);
                $gross = round((float) $r['qty'] * $unit, 2);
                $total = (float) $r['line_total'];
                $discount = round($gross - $total, 2);
                if ($discount > 0.004) {
                    $hasDiscount = true;
                }
                $groups[$gid]['items'][] = [
                    'n' => ++$n,
                    'type' => $r['type'],
                    'model' => (string) $r['model'],
                    'brand' => (string) $r['brand'],
                    'description' => (string) $r['description'],
                    'notes' => (string) $r['notes'],
                    'image' => $opts['show_images'] && $r['image_url'] ? ImageCache::local($r['image_url']) : null,
                    'qty' => (float) $r['qty'],
                    'unit' => (string) $r['unit'],
                    'unit_price' => round($unit * $k, 2),
                    'discount' => round($discount * $k, 2),
                    'discount_label' => $r['discount_type'] === 'percent' && (float) $r['discount_value'] > 0
                        ? rtrim(rtrim(number_format((float) $r['discount_value'], 2), '0'), '.') . ' %' : null,
                    'total' => round($total * $k, 2),
                    'stock' => $r['stock'] !== null ? (int) $r['stock'] : null,
                    'sat_key' => (string) $r['sat_key'],
                ];
                $groups[$gid]['total'] += round($total * $k, 2);
            }
        }
        $groups = array_values(array_filter($groups, fn ($g) => $g['items'] || $g['title'] !== null));

        $seller = Database::fetch(
            'SELECT u.name, u.email, cu.display_name, cu.signature_path FROM users u
               LEFT JOIN company_user cu ON cu.user_id = u.id AND cu.company_id = ? WHERE u.id = ?',
            [$quote['company_id'], $quote['user_id']]
        );
        $signature = $seller['signature_path'] ?? null ?: ($c['signature_path'] ?? null);
        $file = fn (?string $rel) => $rel && is_file(storage_path($rel)) ? storage_path($rel) : null;

        $total = (float) $quote['total'];
        return [
            'company' => $c,
            'quote' => $quote,
            'client' => json_decode((string) $quote['client_snapshot'], true) ?: null,
            'seller' => ['name' => $seller ? ($seller['display_name'] ?: $seller['name']) : '', 'email' => $seller['email'] ?? ''],
            'groups' => $groups,
            'opts' => $opts,
            'hasDiscount' => $hasDiscount,
            'showSections' => count(array_filter($groups, fn ($g) => $g['title'] !== null)) > 0,
            'totals' => [
                'subtotal' => round((float) $quote['subtotal'] * $k, 2),
                'discount' => round((float) $quote['discount_total'] * $k, 2),
                'tax' => (float) $quote['tax_total'],
                'total' => $total,
                'vat_rate' => (float) $quote['vat_rate'],
            ],
            'words' => NumberToWords::money($total, $quote['currency']),
            'logo' => $file($c['logo_path'] ?? null),
            'signature' => $file($signature),
            'hasLetterhead' => !empty($c['letterhead_path']),
            'primary' => $c['color_primary'] ?: '#1E40AF',
            'secondary' => $c['color_secondary'] ?: '#64748B',
        ];
    }
}

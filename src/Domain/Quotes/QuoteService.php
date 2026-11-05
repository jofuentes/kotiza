<?php
declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Domain\FolioService;
use App\Pricing\CurrencyConverter;
use App\Pricing\ExchangeRateService;
use App\Pricing\PriceContext;
use App\Pricing\PriceEngine;
use App\Pricing\PriceResult;
use App\Pricing\PricingRule;
use App\Providers\CatalogService;
use App\Support\Validator;

/**
 * Reglas de negocio de las cotizaciones. El servidor es la autoridad:
 * costos y precios calculados nunca se toman del navegador.
 */
final class QuoteService
{
    public const UNITS = ['Pieza', 'Servicio', 'Metro', 'Rollo', 'Caja', 'Juego', 'Kit', 'Lote', 'Hora', 'Día', 'Mes'];

    public function __construct(private array $company)
    {
    }

    public static function forCurrentCompany(): self
    {
        return new self(Auth::company());
    }

    // ---------- Lectura y permisos ----------

    /** Cotización de la empresa activa; un vendedor solo accede a las suyas */
    public function find(int $id): array
    {
        $q = Database::fetch('SELECT * FROM quotes WHERE id = ? AND company_id = ?', [$id, $this->company['id']]);
        if (!$q || (!Auth::isAdmin() && (int) $q['user_id'] !== Auth::id())) {
            throw new HttpException(404);
        }
        return $q;
    }

    public static function isEditable(array $quote): bool
    {
        return $quote['status'] === 'draft';
    }

    public function canSeeCost(): bool
    {
        return Auth::isAdmin() || !(int) ($this->company['hide_costs_from_sellers'] ?? 0);
    }

    // ---------- Crear ----------

    public function create(?int $clientId, string $currency, string $title): int
    {
        if ($clientId !== null && !Database::value('SELECT 1 FROM clients WHERE id = ? AND company_id = ?', [$clientId, $this->company['id']])) {
            throw new \DomainException('El cliente no existe.');
        }
        $currency = in_array($currency, ['MXN', 'USD'], true) ? $currency : $this->company['default_currency'];
        $rate = ExchangeRateService::current($this->company);
        $days = (int) $this->company['default_validity_days'];

        $id = Database::insert('quotes', [
            'company_id' => $this->company['id'],
            'client_id' => $clientId,
            'user_id' => Auth::id(),
            'folio' => FolioService::next((int) $this->company['id']),
            'title' => $title !== '' ? mb_substr($title, 0, 200) : null,
            'issue_date' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime("+{$days} days")),
            'currency' => $currency,
            'exchange_rate' => $rate['rate'],
            'exchange_source' => $rate['source'],
            'vat_rate' => $this->company['vat_rate'],
            'terms' => $this->company['terms'],
            'notes' => $this->company['footer_notes'],
        ]);
        $this->snapshotClient($id);
        return $id;
    }

    // ---------- Agregar producto de proveedor ----------

    /** Agrega (y guarda) una partida con los datos y el costo actuales del proveedor */
    public function addProduct(array $quote, string $providerCode, string $productId, ?int $sectionId, float $qty = 1): array
    {
        $this->assertEditable($quote);
        $r = CatalogService::forCompany((int) $this->company['id'])->product($providerCode, $productId);
        $p = $r['product'];
        if (!$p) {
            throw new \DomainException($r['error'] ?? 'No se pudo obtener el producto.');
        }
        if ($p['cost'] === null) {
            throw new \DomainException('Este producto no tiene precio para tu cuenta' . ($p['note'] ? " ({$p['note']})" : '') . '. Agrégalo como partida manual si quieres cotizarlo.');
        }
        $price = $this->suggestPrice($quote, [
            'provider' => $providerCode, 'model' => (string) $p['model'], 'brand' => (string) $p['brand'],
            'category' => $p['category'] ?? null, 'cost' => (float) $p['cost'], 'currency' => (string) $p['currency'],
            'list_price' => $p['listPrice'] ?? null,
        ]);
        if ($sectionId !== null && !Database::value('SELECT 1 FROM quote_sections WHERE id = ? AND quote_id = ?', [$sectionId, $quote['id']])) {
            $sectionId = null;
        }
        $position = (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM quote_items WHERE quote_id = ?', [$quote['id']]);

        $itemId = Database::insert('quote_items', [
            'quote_id' => $quote['id'],
            'section_id' => $sectionId,
            'position' => $position,
            'type' => 'product',
            'provider_code' => $providerCode,
            'provider_product_id' => (string) $p['providerId'],
            'model' => mb_substr((string) $p['model'], 0, 120),
            'brand' => mb_substr((string) $p['brand'], 0, 120),
            'category' => isset($p['category']) ? mb_substr((string) $p['category'], 0, 190) : null,
            'sku' => $p['sku'] ?? null,
            'description' => (string) $p['title'],
            'image_url' => $p['images'][0] ?? null,
            'unit' => mb_substr((string) ($p['unit'] ?: 'Pieza'), 0, 40),
            'sat_key' => $p['satKey'] ?? null,
            'sat_unit' => $p['satUnit'] ?? null,
            'qty' => max(0.001, $qty),
            'cost' => $p['cost'],
            'cost_currency' => $p['currency'],
            'cost_includes_vat' => (int) ($p['costIncludesVat'] ?? false),
            'cost_fetched_at' => date('Y-m-d H:i:s', time() - (int) $r['age_minutes'] * 60),
            'list_price' => $p['listPrice'] ?? null,
            'stock' => isset($p['stock']) ? (int) $p['stock'] : null,
            'price_calculated' => $price->price,
            'price_rule' => mb_substr($price->label, 0, 200),
            'rule_discount' => $price->suggestedDiscount,
            // El descuento sugerido sobre lista se aplica como descuento visible
            'discount_type' => 'percent',
            'discount_value' => $price->suggestedDiscount,
        ]);
        $this->recalculate((int) $quote['id']);
        $this->updateApproval((int) $quote['id']);
        $row = Database::fetch('SELECT * FROM quote_items WHERE id = ?', [$itemId]);
        return ['item' => $this->itemState($row, $this->find((int) $quote['id'])), 'stale' => $r['stale'], 'warning' => $r['stale'] ? $r['error'] : null];
    }

    private ?PriceEngine $engine = null;

    private function engine(): PriceEngine
    {
        if ($this->engine === null) {
            $rules = array_map([PricingRule::class, 'fromRow'], Database::fetchAll(
                'SELECT * FROM pricing_rules WHERE company_id = ? AND is_active = 1 ORDER BY id', [$this->company['id']]
            ));
            $this->engine = new PriceEngine($rules, PricingRule::global($this->company));
        }
        return $this->engine;
    }

    /**
     * Precio sugerido en la moneda de la cotización.
     * @param array{provider:string, model:string, brand:string, category:?string, cost:float, currency:string, list_price:?float} $p
     */
    private function suggestPrice(array $quote, array $p): PriceResult
    {
        $rate = $quote['exchange_rate'] !== null ? (float) $quote['exchange_rate'] : null;
        $to = (string) $quote['currency'];
        $base = (string) $this->company['default_currency'];
        return $this->engine()->price(new PriceContext(
            provider: $p['provider'],
            model: $p['model'],
            brand: $p['brand'],
            category: $p['category'],
            cost: CurrencyConverter::convert($p['cost'], $p['currency'], $to, $rate),
            listPrice: $p['list_price'] !== null ? CurrencyConverter::convert((float) $p['list_price'], $p['currency'], $to, $rate) : null,
            fromBaseCurrency: fn (float $v) => $this->convertOrKeep($v, $base, $to, $rate),
        ));
    }

    // ---------- Guardar desde el editor ----------

    public function save(array $quote, array $in): void
    {
        $this->assertEditable($quote);
        $qid = (int) $quote['id'];

        $header = [
            'title' => trim((string) ($in['title'] ?? '')),
            'client_id' => ($in['client_id'] ?? '') === '' || $in['client_id'] === null ? '' : (string) $in['client_id'],
            'issue_date' => (string) ($in['issue_date'] ?? ''),
            'valid_until' => (string) ($in['valid_until'] ?? ''),
            'currency' => (string) ($in['currency'] ?? ''),
            'exchange_rate' => ($in['exchange_rate'] ?? '') === null ? '' : (string) ($in['exchange_rate'] ?? ''),
            'vat_rate' => (string) ($in['vat_rate'] ?? ''),
            'global_discount_type' => (string) ($in['global_discount_type'] ?? 'percent'),
            'global_discount_value' => (string) ($in['global_discount_value'] ?? '0'),
            'notes' => (string) ($in['notes'] ?? ''),
            'terms' => (string) ($in['terms'] ?? ''),
        ];
        $pdfOptions = self::pdfOptions(is_array($in['pdf_options'] ?? null) ? $in['pdf_options'] : []);
        $v = Validator::make($header, [
            'title' => 'max:200', 'client_id' => 'integer',
            'issue_date' => 'required|date', 'valid_until' => 'required|date',
            'currency' => 'required|in:MXN,USD', 'exchange_rate' => 'numeric|between:0.0001,1000',
            'vat_rate' => 'required|numeric|between:0,100',
            'global_discount_type' => 'required|in:percent,amount', 'global_discount_value' => 'numeric|between:0,999999999',
            'notes' => 'max:5000', 'terms' => 'max:10000',
        ], ['title' => 'Referencia', 'client_id' => 'Cliente', 'issue_date' => 'Fecha', 'valid_until' => 'Vigencia',
            'currency' => 'Moneda', 'exchange_rate' => 'Tipo de cambio', 'vat_rate' => 'IVA',
            'global_discount_type' => 'Tipo de descuento', 'global_discount_value' => 'Descuento global',
            'notes' => 'Notas', 'terms' => 'Condiciones']);
        $errors = $v->errors();
        if (!isset($errors['valid_until'], $errors['issue_date']) && $header['valid_until'] < $header['issue_date']) {
            $errors['valid_until'] = 'La vigencia no puede ser anterior a la fecha.';
        }
        if ($header['global_discount_type'] === 'percent' && (float) $header['global_discount_value'] > 100) {
            $errors['global_discount_value'] = 'El descuento global no puede pasar de 100 %.';
        }
        if ($header['client_id'] !== '' && !Database::value('SELECT 1 FROM clients WHERE id = ? AND company_id = ?', [$header['client_id'], $this->company['id']])) {
            $errors['client_id'] = 'El cliente no existe.';
        }

        // Secciones
        $existingSections = array_column(Database::fetchAll('SELECT id FROM quote_sections WHERE quote_id = ?', [$qid]), 'id');
        $sections = [];
        foreach (array_values((array) ($in['sections'] ?? [])) as $i => $s) {
            $title = trim((string) ($s['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 160) {
                $errors["sections.{$i}"] = 'Cada sección necesita un título (máx. 160 caracteres).';
            }
            $sections[] = ['key' => (string) ($s['key'] ?? ''), 'title' => $title];
        }

        // Partidas
        $existingItems = [];
        foreach (Database::fetchAll('SELECT * FROM quote_items WHERE quote_id = ?', [$qid]) as $row) {
            $existingItems[(int) $row['id']] = $row;
        }
        $sectionKeys = array_column($sections, 'key');
        $items = [];
        foreach (array_values((array) ($in['items'] ?? [])) as $i => $it) {
            $id = isset($it['id']) && $it['id'] !== null && $it['id'] !== '' ? (int) $it['id'] : null;
            $existing = $id !== null ? ($existingItems[$id] ?? null) : null;
            if ($id !== null && !$existing) {
                continue; // partida de otra cotización o ya eliminada
            }
            $type = $existing['type'] ?? 'manual';
            $data = [
                'description' => trim((string) ($it['description'] ?? '')),
                'unit' => trim((string) ($it['unit'] ?? 'Pieza')),
                'qty' => (string) ($it['qty'] ?? ''),
                'price_manual' => ($it['price_manual'] ?? '') === null ? '' : trim((string) ($it['price_manual'] ?? '')),
                'discount_type' => (string) ($it['discount_type'] ?? 'percent'),
                'discount_value' => trim((string) ($it['discount_value'] ?? '0')) ?: '0',
                'notes' => trim((string) ($it['notes'] ?? '')),
                'cost' => ($it['cost'] ?? '') === null ? '' : trim((string) ($it['cost'] ?? '')),
                'sat_key' => trim((string) ($it['sat_key'] ?? '')),
            ];
            $iv = Validator::make($data, [
                'description' => 'required|max:2000', 'unit' => 'required|max:40',
                'qty' => 'required|numeric|between:0.001,1000000',
                'price_manual' => ($type === 'manual' ? 'required|' : '') . 'numeric|between:0,999999999',
                'discount_type' => 'required|in:percent,amount', 'discount_value' => 'numeric|between:0,999999999',
                'notes' => 'max:2000', 'cost' => 'numeric|between:0,999999999', 'sat_key' => 'max:20',
            ], ['description' => 'Descripción', 'unit' => 'Unidad', 'qty' => 'Cantidad', 'price_manual' => 'Precio',
                'discount_type' => 'Tipo de descuento', 'discount_value' => 'Descuento', 'notes' => 'Notas',
                'cost' => 'Costo', 'sat_key' => 'Clave SAT']);
            foreach ($iv->errors() as $f => $msg) {
                $errors["items.{$i}.{$f}"] = $msg;
            }
            if ($data['discount_type'] === 'percent' && (float) $data['discount_value'] > 100) {
                $errors["items.{$i}.discount_value"] = 'El descuento no puede pasar de 100 %.';
            }
            $sectionKey = (string) ($it['section_key'] ?? '');
            $items[] = $data + ['id' => $id, 'type' => $type, 'section_key' => in_array($sectionKey, $sectionKeys, true) ? $sectionKey : ''];
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $newCurrency = $header['currency'];
        $newRate = $header['exchange_rate'] !== '' ? round((float) $header['exchange_rate'], 4) : null;
        $oldRate = $quote['exchange_rate'] !== null ? (float) $quote['exchange_rate'] : null;
        $currencyChanged = $newCurrency !== $quote['currency'];
        $rateChanged = $newRate !== $oldRate;
        if (($currencyChanged || $rateChanged) && $this->needsRate($existingItems, $newCurrency) && !$newRate) {
            throw new ValidationException(['exchange_rate' => 'Captura el tipo de cambio: hay partidas en otra moneda.']);
        }

        Database::transaction(function () use ($qid, $quote, $header, $sections, $items, $existingSections, $existingItems, $newCurrency, $newRate, $oldRate, $currencyChanged, $rateChanged, $pdfOptions) {
            Database::query('UPDATE quotes SET pdf_options = ? WHERE id = ?', [json_encode($pdfOptions), $qid]);
            $rateLabel = $quote['exchange_source'];
            if ($rateChanged) {
                $current = ExchangeRateService::current($this->company);
                $rateLabel = $newRate !== null && $current['rate'] !== null && abs($newRate - $current['rate']) < 0.00005
                    ? $current['source'] : ($newRate !== null ? 'Manual' : 'Sin tipo de cambio');
            }
            Database::query(
                'UPDATE quotes SET title = ?, client_id = ?, issue_date = ?, valid_until = ?, currency = ?, exchange_rate = ?, exchange_source = ?,
                        vat_rate = ?, global_discount_type = ?, global_discount_value = ?, notes = ?, terms = ? WHERE id = ?',
                [$header['title'] ?: null, $header['client_id'] ?: null, $header['issue_date'], $header['valid_until'], $newCurrency,
                 $newRate, $rateLabel, $header['vat_rate'], $header['global_discount_type'],
                 $currencyChanged && $header['global_discount_type'] === 'amount'
                     ? $this->convertOrKeep((float) $header['global_discount_value'], $quote['currency'], $newCurrency, $newRate ?? $oldRate)
                     : $header['global_discount_value'],
                 $header['notes'] ?: null, $header['terms'] ?: null, $qid]
            );

            // Secciones: crear/renombrar/ordenar y borrar las que ya no están
            $sectionIds = [];
            foreach ($sections as $pos => $s) {
                if (preg_match('/^id:(\d+)$/', $s['key'], $m) && in_array((int) $m[1], array_map('intval', $existingSections), true)) {
                    Database::query('UPDATE quote_sections SET title = ?, position = ? WHERE id = ?', [$s['title'], $pos + 1, (int) $m[1]]);
                    $sectionIds[$s['key']] = (int) $m[1];
                } else {
                    $sectionIds[$s['key']] = Database::insert('quote_sections', ['quote_id' => $qid, 'title' => $s['title'], 'position' => $pos + 1]);
                }
            }
            $keep = array_values($sectionIds);
            foreach ($existingSections as $sid) {
                if (!in_array((int) $sid, $keep, true)) {
                    Database::query('DELETE FROM quote_sections WHERE id = ?', [$sid]);
                }
            }

            // Partidas
            $keptItems = [];
            foreach ($items as $pos => $it) {
                $sectionId = $it['section_key'] !== '' ? $sectionIds[$it['section_key']] : null;
                $priceManual = $it['price_manual'] !== '' ? round((float) $it['price_manual'], 2) : null;
                $discount = round((float) $it['discount_value'], 2);
                $common = [
                    'section_id' => $sectionId, 'position' => $pos + 1,
                    'description' => $it['description'], 'unit' => $it['unit'], 'qty' => round((float) $it['qty'], 3),
                    'discount_type' => $it['discount_type'], 'notes' => $it['notes'] ?: null,
                ];
                if ($it['id'] !== null) {
                    $old = $existingItems[$it['id']];
                    // Al cambiar de moneda, los montos capturados se convierten
                    if ($currencyChanged) {
                        $priceManual = $priceManual !== null ? round($this->convertOrKeep($priceManual, $quote['currency'], $newCurrency, $newRate), 2) : null;
                        if ($it['discount_type'] === 'amount') {
                            $discount = round($this->convertOrKeep($discount, $quote['currency'], $newCurrency, $newRate), 2);
                        }
                    }
                    $fields = $common + ['price_manual' => $priceManual, 'discount_value' => $discount];
                    if ($old['type'] === 'product') {
                        if ($currencyChanged || $rateChanged) {
                            $quoteNow = ['currency' => $newCurrency, 'exchange_rate' => $newRate] + $quote;
                            $p = $this->suggestPrice($quoteNow, [
                                'provider' => (string) $old['provider_code'], 'model' => (string) $old['model'], 'brand' => (string) $old['brand'],
                                'category' => $old['category'], 'cost' => (float) $old['cost'], 'currency' => (string) $old['cost_currency'],
                                'list_price' => $old['list_price'] !== null ? (float) $old['list_price'] : null,
                            ]);
                            $fields['price_calculated'] = $p->price;
                            $fields['price_rule'] = mb_substr($p->label, 0, 200);
                        }
                    } else {
                        // Quien no ve costos no puede cambiarlos: se conserva el guardado
                        $cost = $this->canSeeCost()
                            ? ($it['cost'] !== '' ? (float) $it['cost'] : null)
                            : ($old['cost'] !== null ? (float) $old['cost'] : null);
                        if ($currencyChanged && $cost !== null) {
                            $cost = $this->convertOrKeep($cost, $quote['currency'], $newCurrency, $newRate);
                        }
                        $fields += ['cost' => $cost !== null ? round($cost, 4) : null, 'cost_currency' => $cost !== null ? $newCurrency : null,
                            'sat_key' => $it['sat_key'] ?: null];
                    }
                    $sets = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($fields)));
                    Database::query("UPDATE quote_items SET {$sets} WHERE id = :id AND quote_id = :qid", $fields + ['id' => $it['id'], 'qid' => $qid]);
                    $keptItems[] = $it['id'];
                } else {
                    $cost = $it['cost'] !== '' && $this->canSeeCost() ? round((float) $it['cost'], 4) : null;
                    $keptItems[] = Database::insert('quote_items', $common + [
                        'quote_id' => $qid, 'type' => 'manual', 'price_manual' => $priceManual ?? 0, 'discount_value' => $discount,
                        'cost' => $cost, 'cost_currency' => $cost !== null ? $newCurrency : null, 'sat_key' => $it['sat_key'] ?: null,
                    ]);
                }
            }
            $idList = $keptItems ? implode(',', array_map('intval', $keptItems)) : '0';
            Database::query("DELETE FROM quote_items WHERE quote_id = ? AND id NOT IN ({$idList})", [$qid]);
        });

        $this->snapshotClient($qid);
        $this->recalculate($qid);
        $this->updateApproval($qid);
    }

    // ---------- Límite de descuento del vendedor ----------

    /** Descuento máximo (%) del dueño de la cotización; null = sin límite */
    public function ownerMaxDiscount(array $quote): ?float
    {
        $m = Database::fetch('SELECT role, max_discount_pct FROM company_user WHERE company_id = ? AND user_id = ?',
            [$quote['company_id'], $quote['user_id']]);
        if (!$m || $m['role'] === 'admin' || $m['max_discount_pct'] === null) {
            return null;
        }
        return (float) $m['max_discount_pct'];
    }

    /**
     * Partidas/descuento global que exceden el límite del vendedor.
     * En productos cuenta todo lo que baje el importe respecto al precio calculado
     * (descuento o precio manual menor). El descuento sugerido por la regla es política de la
     * empresa: el límite del vendedor se suma a él.
     * @return string[]
     */
    public function discountExcess(int $quoteId, float $max): array
    {
        $quote = Database::fetch('SELECT * FROM quotes WHERE id = ?', [$quoteId]);
        $out = [];
        foreach (Database::fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position', [$quoteId]) as $n => $it) {
            $qty = (float) $it['qty'];
            if ($it['type'] === 'product' && (float) $it['price_calculated'] > 0) {
                $ref = $qty * (float) $it['price_calculated'];
                $allowed = min(100.0, $max + (float) $it['rule_discount']);
            } else {
                $ref = $qty * (float) ($it['price_manual'] ?? 0);
                $allowed = $max;
            }
            if ($ref <= 0) {
                continue;
            }
            $effective = (1 - (float) $it['line_total'] / $ref) * 100;
            if ($effective > $allowed + 0.01) {
                $out[] = sprintf('Partida %d (%s): %s de descuento (máx. %s)', $n + 1,
                    mb_strimwidth($it['model'] ?: $it['description'], 0, 40, '…'), PriceEngine::pct(round($effective, 2)), PriceEngine::pct($allowed));
            }
        }
        if ((float) $quote['subtotal'] > 0) {
            $g = (float) $quote['discount_total'] / (float) $quote['subtotal'] * 100;
            if ($g > $max + 0.01) {
                $out[] = sprintf('Descuento global: %s (máx. %s)', PriceEngine::pct(round($g, 2)), PriceEngine::pct($max));
            }
        }
        return $out;
    }

    /** Recalcula si la cotización requiere aprobación de un administrador */
    public function updateApproval(int $quoteId): void
    {
        $quote = Database::fetch('SELECT * FROM quotes WHERE id = ?', [$quoteId]);
        $max = $this->ownerMaxDiscount($quote);
        $excess = $max === null ? [] : $this->discountExcess($quoteId, $max);
        if (!$excess) {
            $status = 'none';
        } elseif (Auth::isAdmin()) {
            // Si un administrador guarda, los descuentos quedan aprobados por él
            $status = 'approved';
        } else {
            $status = 'pending';
        }
        Database::query(
            "UPDATE quotes SET approval_status = ?, approval_note = ?,
                    approved_by = IF(? = 'approved', ?, NULL), approved_at = IF(? = 'approved', NOW(), NULL) WHERE id = ?",
            [$status, $excess ? mb_substr(implode("\n", $excess), 0, 500) : null, $status, Auth::id(), $status, $quoteId]
        );
    }

    public function approve(array $quote): void
    {
        if (!Auth::isAdmin()) {
            throw new \DomainException('Solo un administrador puede aprobar descuentos.');
        }
        Database::query("UPDATE quotes SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?",
            [Auth::id(), $quote['id']]);
    }

    private function needsRate(array $items, string $currency): bool
    {
        foreach ($items as $it) {
            if ($it['type'] === 'product' && $it['cost_currency'] !== $currency) {
                return true;
            }
        }
        return false;
    }

    private function convertOrKeep(float $amount, string $from, string $to, ?float $rate): float
    {
        try {
            return CurrencyConverter::convert($amount, $from, $to, $rate);
        } catch (\DomainException) {
            return $amount;
        }
    }

    // ---------- Totales ----------

    /** Recalcula importes y totales guardados (autoridad del servidor) */
    public function recalculate(int $quoteId): array
    {
        $quote = Database::fetch('SELECT * FROM quotes WHERE id = ?', [$quoteId]);
        $rows = Database::fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position', [$quoteId]);
        $calc = QuoteCalculator::calculate(
            array_map(fn ($r) => $this->calcInput($r, $quote), $rows),
            $quote['global_discount_type'], (float) $quote['global_discount_value'], (float) $quote['vat_rate']
        );
        foreach ($rows as $k => $r) {
            if ((float) $r['line_total'] !== $calc['lines'][$k]['total']) {
                Database::query('UPDATE quote_items SET line_total = ? WHERE id = ?', [$calc['lines'][$k]['total'], $r['id']]);
            }
        }
        Database::query('UPDATE quotes SET subtotal = ?, discount_total = ?, tax_total = ?, total = ?, cost_total = ? WHERE id = ?',
            [$calc['subtotal'], $calc['discount_total'], $calc['tax_total'], $calc['total'], $calc['cost_total'], $quoteId]);
        return $calc;
    }

    private function calcInput(array $r, array $quote): array
    {
        $unitCost = null;
        if ($r['cost'] !== null) {
            try {
                $unitCost = CurrencyConverter::convert((float) $r['cost'], (string) ($r['cost_currency'] ?: $quote['currency']),
                    $quote['currency'], $quote['exchange_rate'] !== null ? (float) $quote['exchange_rate'] : null);
            } catch (\DomainException) {
                $unitCost = null;
            }
        }
        return [
            'qty' => $r['qty'],
            'unit_price' => $r['price_manual'] ?? $r['price_calculated'] ?? 0,
            'discount_type' => $r['discount_type'],
            'discount_value' => $r['discount_value'],
            'unit_cost' => $unitCost,
        ];
    }

    // ---------- Estado para el editor ----------

    public function editorState(array $quote): array
    {
        $sections = Database::fetchAll('SELECT * FROM quote_sections WHERE quote_id = ? ORDER BY position', [$quote['id']]);
        $rows = Database::fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position', [$quote['id']]);
        $client = $quote['client_id'] ? Database::fetch('SELECT * FROM clients WHERE id = ?', [$quote['client_id']]) : null;
        $seller = Database::fetch(
            'SELECT u.name, cu.display_name FROM users u LEFT JOIN company_user cu ON cu.user_id = u.id AND cu.company_id = ? WHERE u.id = ?',
            [$quote['company_id'], $quote['user_id']]
        );
        return [
            'id' => (int) $quote['id'],
            'folio' => $quote['folio'],
            'version' => (int) $quote['version'],
            'status' => $quote['status'],
            'editable' => self::isEditable($quote),
            'title' => (string) $quote['title'],
            'client_id' => $quote['client_id'] !== null ? (int) $quote['client_id'] : null,
            'client' => $client ? array_intersect_key($client, array_flip(['id', 'name', 'company_name', 'rfc', 'email', 'phone'])) : null,
            'seller' => $seller ? ($seller['display_name'] ?: $seller['name']) : '',
            'issue_date' => $quote['issue_date'],
            'valid_until' => $quote['valid_until'],
            'currency' => $quote['currency'],
            'exchange_rate' => $quote['exchange_rate'] !== null ? (float) $quote['exchange_rate'] : null,
            'exchange_source' => (string) $quote['exchange_source'],
            'vat_rate' => (float) $quote['vat_rate'],
            'global_discount_type' => $quote['global_discount_type'],
            'global_discount_value' => (float) $quote['global_discount_value'],
            'notes' => (string) $quote['notes'],
            'terms' => (string) $quote['terms'],
            'sections' => array_map(fn ($s) => ['key' => 'id:' . $s['id'], 'title' => $s['title']], $sections),
            'items' => array_map(fn ($r) => $this->itemState($r, $quote), $rows),
            'show_cost' => $this->canSeeCost(),
            'min_margin' => (float) $this->company['min_margin'],
            'max_discount' => $this->ownerMaxDiscount($quote),
            'is_admin' => Auth::isAdmin(),
            'approval_status' => $quote['approval_status'],
            'approval_note' => (string) $quote['approval_note'],
            'updated_at' => $quote['updated_at'],
            'pdf_options' => self::pdfOptions(json_decode((string) $quote['pdf_options'], true) ?: []),
        ];
    }

    private function itemState(array $r, array $quote): array
    {
        $in = $this->calcInput($r, $quote);
        $showCost = $this->canSeeCost();
        return [
            'id' => (int) $r['id'],
            'key' => 'id:' . $r['id'],
            'type' => $r['type'],
            'section_key' => $r['section_id'] ? 'id:' . $r['section_id'] : '',
            'provider' => $r['provider_code'],
            'provider_product_id' => $r['provider_product_id'],
            'model' => (string) $r['model'],
            'brand' => (string) $r['brand'],
            'sku' => $r['sku'],
            'description' => $r['description'],
            'image_url' => $r['image_url'],
            'unit' => $r['unit'],
            'sat_key' => (string) $r['sat_key'],
            'qty' => (float) $r['qty'],
            'price_calculated' => $r['price_calculated'] !== null ? (float) $r['price_calculated'] : null,
            'price_manual' => $r['price_manual'] !== null ? (float) $r['price_manual'] : null,
            'price_rule' => $r['price_rule'],
            'rule_discount' => (float) $r['rule_discount'],
            'list_price' => $r['list_price'] !== null ? (float) $r['list_price'] : null,
            'discount_type' => $r['discount_type'],
            'discount_value' => (float) $r['discount_value'],
            'notes' => (string) $r['notes'],
            // Costo: el original (para mostrar) y el unitario en la moneda de la cotización (para la utilidad)
            'cost' => $showCost && $r['cost'] !== null ? (float) $r['cost'] : null,
            'cost_currency' => $showCost ? $r['cost_currency'] : null,
            'cost_includes_vat' => (bool) $r['cost_includes_vat'],
            'unit_cost' => $showCost ? $in['unit_cost'] : null,
            'cost_fetched_at' => $r['cost_fetched_at'],
        ];
    }

    public const PDF_OPTIONS = [
        'show_images' => true,   // miniaturas de producto
        'show_brands' => true,   // marca y modelo
        'show_stock' => false,   // existencia al momento de cotizar
        'show_tax' => true,      // desglose de IVA (si no, precios con IVA incluido)
    ];

    /** Opciones del PDF normalizadas (solo claves conocidas, booleanas) */
    public static function pdfOptions(array $in): array
    {
        $out = [];
        foreach (self::PDF_OPTIONS as $k => $default) {
            $out[$k] = array_key_exists($k, $in) ? filter_var($in[$k], FILTER_VALIDATE_BOOLEAN) : $default;
        }
        return $out;
    }

    private function snapshotClient(int $quoteId): void
    {
        Database::query(
            "UPDATE quotes q LEFT JOIN clients c ON c.id = q.client_id
                SET q.client_snapshot = IF(c.id IS NULL, NULL, JSON_OBJECT('name', c.name, 'company_name', c.company_name, 'rfc', c.rfc,
                    'email', c.email, 'phone', c.phone, 'address', c.address))
              WHERE q.id = ?",
            [$quoteId]
        );
    }

    private function assertEditable(array $quote): void
    {
        if (!self::isEditable($quote)) {
            throw new \DomainException('Solo se pueden editar cotizaciones en borrador.');
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Pricing\ExchangeRateService;
use App\Pricing\PriceContext;
use App\Pricing\PriceEngine;
use App\Pricing\PricingRule;
use App\Providers\ProviderRegistry;
use App\Support\Validator;

/** Motor de precios: estrategia global, reglas, redondeo, tipo de cambio y margen mínimo */
final class PricingController extends Controller
{
    private const RULE_LABELS = [
        'scope' => 'Aplica a', 'scope_value' => 'Valor', 'provider_code' => 'Proveedor', 'method' => 'Método',
        'percent' => 'Porcentaje', 'list_discount' => 'Descuento sugerido', 'min_price' => 'Precio mínimo',
        'rounding' => 'Redondeo', 'rounding_value' => 'Múltiplo', 'notes' => 'Notas',
    ];

    public function index(Request $request): Response
    {
        $rules = Database::fetchAll(
            "SELECT * FROM pricing_rules WHERE company_id = ?
              ORDER BY FIELD(scope, 'product', 'brand', 'category', 'provider'), scope_value", [Auth::companyId()]
        );
        return $this->view('pricing/index', [
            'pageTitle' => 'Precios',
            'rules' => $rules,
            'providers' => array_map(fn ($c) => $c::name(), ProviderRegistry::all()),
            'rate' => ExchangeRateService::current(Auth::company()),
            'errors' => [],
        ]);
    }

    public function updateGlobal(Request $request): Response
    {
        $data = $request->only(['price_method', 'global_margin', 'list_discount', 'price_rounding', 'price_rounding_value',
            'min_margin', 'exchange_mode', 'exchange_manual', 'exchange_buffer']);
        $errors = Validator::make($data, [
            'price_method' => 'required|in:markup,margin,list',
            'global_margin' => 'required|numeric|between:0,1000',
            'list_discount' => 'required|numeric|between:0,100',
            'price_rounding' => 'required|in:none,multiple,integer,ending90',
            'price_rounding_value' => 'numeric|between:0.01,100000',
            'min_margin' => 'required|numeric|between:0,100',
            'exchange_mode' => 'required|in:provider,manual,provider_buffer',
            'exchange_manual' => 'numeric|between:0.0001,1000',
            'exchange_buffer' => 'numeric|between:-100,100',
        ], ['price_method' => 'Método', 'global_margin' => 'Porcentaje global', 'list_discount' => 'Descuento sugerido',
            'price_rounding' => 'Redondeo', 'price_rounding_value' => 'Múltiplo', 'min_margin' => 'Margen mínimo',
            'exchange_mode' => 'Tipo de cambio', 'exchange_manual' => 'Tipo de cambio manual', 'exchange_buffer' => 'Colchón'])->errors();
        if ($data['price_method'] === 'margin' && (float) $data['global_margin'] >= 100) {
            $errors['global_margin'] = 'Con margen sobre venta el porcentaje debe ser menor a 100.';
        }
        if ($data['price_rounding'] === 'multiple' && $data['price_rounding_value'] === '') {
            $errors['price_rounding_value'] = 'Indica el múltiplo (ej. 5 o 10).';
        }
        if ($data['exchange_mode'] === 'manual' && $data['exchange_manual'] === '') {
            $errors['exchange_manual'] = 'Captura el tipo de cambio manual.';
        }
        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            return $this->redirect('/precios');
        }
        Database::query(
            'UPDATE companies SET price_method = ?, global_margin = ?, list_discount = ?, price_rounding = ?, price_rounding_value = ?,
                    min_margin = ?, exchange_mode = ?, exchange_manual = ?, exchange_buffer = ? WHERE id = ?',
            [$data['price_method'], $data['global_margin'], $data['list_discount'], $data['price_rounding'],
             $data['price_rounding_value'] ?: null, $data['min_margin'], $data['exchange_mode'],
             $data['exchange_manual'] ?: null, $data['exchange_buffer'] ?: 0, Auth::companyId()]
        );
        Session::flash('success', 'Configuración de precios guardada. Aplica a las partidas que agregues desde ahora.');
        return $this->redirect('/precios');
    }

    public function storeRule(Request $request): Response
    {
        return $this->saveRule($request, null);
    }

    public function updateRule(Request $request): Response
    {
        return $this->saveRule($request, $this->rule((int) $request->params['id']));
    }

    public function destroyRule(Request $request): Response
    {
        $rule = $this->rule((int) $request->params['id']);
        Database::query('DELETE FROM pricing_rules WHERE id = ?', [$rule['id']]);
        Session::flash('success', 'Regla eliminada.');
        return $this->redirect('/precios');
    }

    /** Vista previa: qué regla y precio tocaría a un producto */
    public function preview(Request $request): Response
    {
        $company = Auth::company();
        $rules = array_map([PricingRule::class, 'fromRow'],
            Database::fetchAll('SELECT * FROM pricing_rules WHERE company_id = ? AND is_active = 1', [Auth::companyId()]));
        $engine = new PriceEngine($rules, PricingRule::global($company));
        $list = $request->str('list_price');
        $r = $engine->price(new PriceContext(
            provider: $request->str('provider'), model: $request->str('model'), brand: $request->str('brand'),
            category: $request->str('category'), cost: max(0, (float) $request->input('cost', 0)),
            listPrice: $list !== '' ? (float) $list : null,
        ));
        return Response::json(['price' => $r->price, 'label' => $r->label, 'suggested_discount' => $r->suggestedDiscount]);
    }

    private function saveRule(Request $request, ?array $rule): Response
    {
        $data = $request->only(['scope', 'scope_value', 'provider_code', 'method', 'percent', 'list_discount', 'min_price',
            'rounding', 'rounding_value', 'notes', 'is_active']);
        if ($data['scope'] === 'provider') {
            $data['provider_code'] = $data['scope_value'];
        }
        $errors = Validator::make($data, [
            'scope' => 'required|in:product,brand,category,provider',
            'scope_value' => 'required|max:190',
            'provider_code' => 'max:40',
            'method' => 'required|in:markup,margin,list',
            'percent' => 'required|numeric|between:0,1000',
            'list_discount' => 'numeric|between:0,100',
            'min_price' => 'numeric|between:0,999999999',
            'rounding' => 'required|in:none,multiple,integer,ending90',
            'rounding_value' => 'numeric|between:0.01,100000',
            'notes' => 'max:255',
            'is_active' => 'in:0,1',
        ], self::RULE_LABELS)->errors();
        if ($data['scope'] === 'provider' && !ProviderRegistry::get($data['scope_value'])) {
            $errors['scope_value'] = 'Proveedor desconocido.';
        }
        if ($data['provider_code'] !== '' && !ProviderRegistry::get($data['provider_code'])) {
            $errors['provider_code'] = 'Proveedor desconocido.';
        }
        if ($data['method'] === 'margin' && (float) $data['percent'] >= 100) {
            $errors['percent'] = 'Con margen sobre venta el porcentaje debe ser menor a 100.';
        }
        if ($data['rounding'] === 'multiple' && $data['rounding_value'] === '') {
            $errors['rounding_value'] = 'Indica el múltiplo (ej. 5 o 10).';
        }
        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            return $this->redirect('/precios' . ($rule ? '#regla-' . $rule['id'] : ''));
        }
        $row = [
            'scope' => $data['scope'],
            'scope_value' => $data['scope'] === 'product' ? mb_strtoupper($data['scope_value']) : $data['scope_value'],
            'provider_code' => $data['provider_code'] ?: null,
            'method' => $data['method'],
            'percent' => (float) $data['percent'],
            'list_discount' => (float) ($data['list_discount'] ?: 0),
            'min_price' => $data['min_price'] !== '' ? (float) $data['min_price'] : null,
            'rounding' => $data['rounding'],
            'rounding_value' => $data['rounding_value'] !== '' ? (float) $data['rounding_value'] : null,
            'notes' => $data['notes'] ?: null,
            'is_active' => $data['is_active'] === '0' ? 0 : 1,
        ];
        if ($rule) {
            $sets = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($row)));
            Database::query("UPDATE pricing_rules SET {$sets} WHERE id = :id", $row + ['id' => $rule['id']]);
            Session::flash('success', 'Regla actualizada.');
        } else {
            Database::insert('pricing_rules', $row + ['company_id' => Auth::companyId()]);
            Session::flash('success', 'Regla creada.');
        }
        return $this->redirect('/precios');
    }

    private function rule(int $id): array
    {
        return Database::fetch('SELECT * FROM pricing_rules WHERE id = ? AND company_id = ?', [$id, Auth::companyId()])
            ?? throw new HttpException(404);
    }
}

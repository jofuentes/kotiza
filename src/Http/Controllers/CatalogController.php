<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Providers\CatalogService;
use App\Providers\ProviderAccountRepository;
use App\Providers\ProviderRegistry;

/** Buscador unificado de productos en todos los proveedores activos. */
final class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $active = array_map(fn ($a) => ['code' => $a->providerCode, 'name' => ProviderRegistry::get($a->providerCode)::name()],
            ProviderAccountRepository::default()->active(Auth::companyId()));
        return $this->view('catalog/index', [
            'pageTitle' => 'Productos',
            'activeProviders' => $active,
            'showCost' => $this->canSeeCost(),
        ]);
    }

    public function search(Request $request): Response
    {
        $q = $request->str('q');
        if (mb_strlen($q) < 2) {
            return Response::json(['error' => 'Escribe al menos 2 caracteres.'], 422);
        }
        $codes = $request->input('providers');
        $codes = is_array($codes) ? array_values(array_filter($codes, 'is_string')) : null;
        $result = CatalogService::forCompany(Auth::companyId())->search($q, [
            'in_stock' => $request->input('in_stock') === '1',
            'page' => max(1, (int) $request->input('page', 1)),
        ], $codes ?: null);
        $result['products'] = array_map([$this, 'present'], $result['products']);
        return Response::json($result);
    }

    public function show(Request $request): Response
    {
        $code = (string) $request->params['code'];
        $id = (string) $request->params['id'];
        if (!ProviderRegistry::get($code)) {
            return Response::json(['error' => 'Proveedor desconocido.'], 404);
        }
        $r = CatalogService::forCompany(Auth::companyId())->product($code, $id, $request->input('refresh') === '1');
        if ($r['product']) {
            $r['product'] = $this->present($r['product']);
        }
        return Response::json($r, $r['product'] ? 200 : 404);
    }

    /** Quita el costo si el usuario no debe verlo y limpia la descripción HTML */
    private function present(array $p): array
    {
        if (!$this->canSeeCost()) {
            $p['cost'] = null;
        }
        if (!empty($p['description'])) {
            $p['description'] = self::sanitizeHtml($p['description']);
        }
        return $p;
    }

    private ?bool $canSeeCost = null;

    private function canSeeCost(): bool
    {
        return $this->canSeeCost ??= Auth::isAdmin() || !(int) (Auth::company()['hide_costs_from_sellers'] ?? 0);
    }

    /** HTML de ficha del proveedor reducido a etiquetas seguras de formato */
    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><ul><ol><li><b><strong><i><em><table><thead><tbody><tr><td><th><h3><h4><h5><span><div>');
        // Sin atributos (elimina on*, style, href, src…)
        return preg_replace('#<([a-z0-9]+)\s[^>]*>#i', '<$1>', $html) ?? '';
    }
}

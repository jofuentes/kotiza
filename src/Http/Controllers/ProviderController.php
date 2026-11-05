<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Providers\FieldDefinition;
use App\Providers\ProviderAccountRepository;
use App\Providers\ProviderFactory;
use App\Providers\ProviderRegistry;
use App\Support\Validator;

/** Sección Proveedores: credenciales cifradas, ajustes, prueba de conexión y log de llamadas. */
final class ProviderController extends Controller
{
    private ProviderAccountRepository $repo;

    public function __construct()
    {
        $this->repo = ProviderAccountRepository::default();
    }

    public function index(Request $request): Response
    {
        $rows = $this->repo->rows(Auth::companyId());
        $providers = [];
        foreach (ProviderRegistry::all() as $code => $class) {
            $row = $rows[$code] ?? null;
            $providers[] = [
                'code' => $code,
                'name' => $class::name(),
                'description' => $class::description(),
                'row' => $row,
                'configured' => count($this->repo->storedCredentialKeys($row)) >= count(array_filter($class::credentialFields(), fn ($f) => $f->required)),
            ];
        }
        usort($providers, fn ($a, $b) => [($a['row']['priority'] ?? 999), $a['name']] <=> [($b['row']['priority'] ?? 999), $b['name']]);
        return $this->view('providers/index', [
            'pageTitle' => 'Proveedores',
            'providers' => $providers,
            'cache' => Auth::company(),
        ]);
    }

    public function edit(Request $request): Response
    {
        [$code, $class] = $this->provider($request);
        $row = $this->repo->row(Auth::companyId(), $code);
        $settings = json_decode((string) ($row['settings'] ?? '{}'), true) ?: [];
        $old = [
            'is_active' => (string) (int) ($row['is_active'] ?? 1),
            'priority' => (string) ($row['priority'] ?? 10),
            'default_margin' => $row['default_margin'] ?? '',
        ];
        foreach ($class::settingFields() as $f) {
            $v = $settings[$f->key] ?? $f->default;
            $old['settings'][$f->key] = is_bool($v) ? (string) (int) $v : (string) $v;
        }
        return $this->form($code, $class, $row, $old, []);
    }

    public function update(Request $request): Response
    {
        [$code, $class] = $this->provider($request);
        $row = $this->repo->row(Auth::companyId(), $code);
        $stored = $this->repo->storedCredentialKeys($row);

        $data = $request->only(['is_active', 'priority', 'default_margin']);
        $rawSettings = (array) $request->input('settings', []);
        $rawCreds = (array) $request->input('credentials', []);

        $errors = Validator::make($data, [
            'is_active' => 'required|in:0,1',
            'priority' => 'required|integer|between:1,99',
            'default_margin' => 'numeric|between:0,1000',
        ], ['is_active' => 'Estado', 'priority' => 'Prioridad', 'default_margin' => 'Margen por defecto'])->errors();

        $settings = [];
        foreach ($class::settingFields() as $f) {
            $v = $rawSettings[$f->key] ?? null;
            $settings[$f->key] = match ($f->type) {
                'bool' => ($v === '1'),
                'number' => is_numeric($v) ? (float) $v : $f->default,
                'select' => isset($f->options[(string) $v]) ? (string) $v : $f->default,
                default => is_string($v) ? mb_substr(trim($v), 0, 500) : $f->default,
            };
        }

        $credentials = [];
        foreach ($class::credentialFields() as $f) {
            $v = $rawCreds[$f->key] ?? '';
            $v = is_string($v) ? trim($v) : '';
            if (mb_strlen($v) > 500) {
                $errors['credentials.' . $f->key] = "«{$f->label}» es demasiado largo.";
            }
            $credentials[$f->key] = $v;
            // Para activar se necesitan todas las credenciales obligatorias
            if ($f->required && $v === '' && !in_array($f->key, $stored, true) && $data['is_active'] === '1') {
                $errors['credentials.' . $f->key] = "Captura «{$f->label}» para poder activar el proveedor.";
            }
        }

        if ($errors) {
            $old = $data + ['settings' => array_map(fn ($v) => is_bool($v) ? (string) (int) $v : (string) $v, $settings)];
            return $this->form($code, $class, $row, $old, $errors);
        }

        $this->repo->save(Auth::companyId(), $code, [
            'is_active' => $data['is_active'],
            'priority' => $data['priority'],
            'default_margin' => $data['default_margin'] === '' || $data['default_margin'] === null ? null : $data['default_margin'],
            'settings' => $settings,
        ], $credentials);

        Session::flash('success', "Configuración de {$class::name()} guardada. Usa «Probar conexión» para verificarla.");
        return $this->redirect('/proveedores/' . $code);
    }

    public function test(Request $request): Response
    {
        [$code, $class] = $this->provider($request);
        $account = $this->repo->find(Auth::companyId(), $code);
        if (!$account || !$account->credentials) {
            return Response::json(['ok' => false, 'message' => 'Primero guarda las credenciales.']);
        }
        try {
            $result = ProviderFactory::default()->make($account)->testConnection();
        } catch (\Throwable $e) {
            error_log((string) $e);
            $result = new \App\Providers\ConnectionResult(false, 'Error inesperado: ' . $e->getMessage());
        }
        $this->repo->recordTest($account->id, $result);
        return Response::json(['ok' => $result->ok, 'message' => $result->message, 'details' => $result->details,
            'tested_at' => date('d/m/Y H:i')]);
    }

    public function logs(Request $request): Response
    {
        [$code, $class] = $this->provider($request);
        $row = $this->repo->row(Auth::companyId(), $code);
        $logs = $row ? Database::fetchAll(
            'SELECT * FROM provider_logs WHERE provider_account_id = ? ORDER BY id DESC LIMIT 200', [$row['id']]
        ) : [];
        $stats = $row ? Database::fetch(
            'SELECT COUNT(*) total, SUM(http_code BETWEEN 200 AND 299) ok, ROUND(AVG(duration_ms)) avg_ms
               FROM provider_logs WHERE provider_account_id = ? AND created_at >= NOW() - INTERVAL 1 DAY', [$row['id']]
        ) : null;
        return $this->view('providers/logs', ['pageTitle' => "Log de {$class::name()}", 'name' => $class::name(),
            'code' => $code, 'logs' => $logs, 'stats' => $stats]);
    }

    public function cache(Request $request): Response
    {
        $data = $request->only(['cache_price_minutes', 'cache_detail_hours']);
        $v = Validator::make($data, [
            'cache_price_minutes' => 'required|integer|between:0,10080',
            'cache_detail_hours' => 'required|integer|between:0,2160',
        ], ['cache_price_minutes' => 'Vigencia de precios', 'cache_detail_hours' => 'Vigencia de fichas']);
        if ($v->fails()) {
            Session::flash('error', implode(' ', $v->errors()));
        } else {
            Database::query('UPDATE companies SET cache_price_minutes = ?, cache_detail_hours = ? WHERE id = ?',
                [(int) $data['cache_price_minutes'], (int) $data['cache_detail_hours'], Auth::companyId()]);
            if ($request->input('clear') === '1') {
                Database::query('DELETE sc FROM search_cache sc JOIN provider_accounts pa ON pa.id = sc.provider_account_id WHERE pa.company_id = ?', [Auth::companyId()]);
                Database::query('DELETE pc FROM product_cache pc JOIN provider_accounts pa ON pa.id = pc.provider_account_id WHERE pa.company_id = ?', [Auth::companyId()]);
            }
            Session::flash('success', $request->input('clear') === '1' ? 'Caché vaciada y vigencias guardadas.' : 'Vigencia de caché guardada.');
        }
        return $this->redirect('/proveedores');
    }

    private function form(string $code, string $class, ?array $row, array $old, array $errors): Response
    {
        return $this->view('providers/edit', [
            'pageTitle' => $class::name(),
            'code' => $code,
            'name' => $class::name(),
            'description' => $class::description(),
            'credentialFields' => $class::credentialFields(),
            'settingFields' => $class::settingFields(),
            'storedKeys' => $this->repo->storedCredentialKeys($row),
            'row' => $row,
            'old' => $old,
            'errors' => $errors,
            'config' => method_exists($class, 'config') ? $class::config() : [],
        ]);
    }

    /** @return array{0:string, 1:class-string} */
    private function provider(Request $request): array
    {
        $code = (string) ($request->params['code'] ?? '');
        $class = ProviderRegistry::get($code) ?? throw new HttpException(404);
        return [$code, $class];
    }
}

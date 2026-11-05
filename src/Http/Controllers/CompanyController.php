<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\FolioService;
use App\Support\Upload;
use App\Support\UploadException;
use App\Support\Validator;

/** Perfil de la empresa activa y alta de empresas adicionales. */
final class CompanyController extends Controller
{
    private const LABELS = [
        'legal_name' => 'Razón social', 'trade_name' => 'Nombre comercial', 'rfc' => 'RFC',
        'tax_regime' => 'Régimen fiscal', 'address' => 'Dirección', 'phone' => 'Teléfonos',
        'email' => 'Correo', 'website' => 'Sitio web',
        'color_primary' => 'Color primario', 'color_secondary' => 'Color secundario',
        'pdf_margin_top' => 'Margen superior', 'pdf_margin_right' => 'Margen derecho',
        'pdf_margin_bottom' => 'Margen inferior', 'pdf_margin_left' => 'Margen izquierdo',
        'bank_details' => 'Datos bancarios', 'terms' => 'Condiciones comerciales', 'footer_notes' => 'Notas al pie',
        'default_validity_days' => 'Vigencia', 'folio_prefix' => 'Prefijo', 'folio_include_year' => 'Incluir año',
        'folio_reset_yearly' => 'Reiniciar cada año', 'folio_padding' => 'Dígitos', 'folio_next' => 'Siguiente número',
        'default_currency' => 'Moneda', 'vat_rate' => 'IVA', 'hide_costs_from_sellers' => 'Ocultar costos', 'require_quote_approval' => 'Requiere aprobación',
    ];

    private const RULES = [
        'legal_name' => 'required|max:200', 'trade_name' => 'required|max:200', 'rfc' => 'rfc',
        'tax_regime' => 'max:120', 'address' => 'max:1000', 'phone' => 'max:80',
        'email' => 'email|max:190', 'website' => 'url|max:190',
        'color_primary' => 'required|color', 'color_secondary' => 'required|color',
        'pdf_margin_top' => 'required|numeric|between:0,120', 'pdf_margin_right' => 'required|numeric|between:0,80',
        'pdf_margin_bottom' => 'required|numeric|between:0,120', 'pdf_margin_left' => 'required|numeric|between:0,80',
        'bank_details' => 'max:2000', 'terms' => 'max:5000', 'footer_notes' => 'max:2000',
        'default_validity_days' => 'required|integer|between:1,365',
        'folio_prefix' => 'max:20', 'folio_include_year' => 'required|in:0,1', 'folio_reset_yearly' => 'required|in:0,1',
        'folio_padding' => 'required|integer|between:1,8', 'folio_next' => 'required|integer|between:1,99999999',
        'default_currency' => 'required|in:MXN,USD', 'vat_rate' => 'required|numeric|between:0,100',
        'hide_costs_from_sellers' => 'required|in:0,1',
        'require_quote_approval' => 'required|in:0,1',
    ];

    private const FILES = [
        'logo' => ['column' => 'logo_path', 'dir' => 'logos', 'types' => Upload::IMAGE_SVG, 'max' => 2 * 1048576],
        'letterhead' => ['column' => 'letterhead_path', 'dir' => 'letterheads', 'types' => Upload::LETTERHEAD, 'max' => 10 * 1048576],
        'signature' => ['column' => 'signature_path', 'dir' => 'signatures', 'types' => Upload::IMAGE, 'max' => 2 * 1048576],
    ];

    public function edit(Request $request): Response
    {
        $company = Auth::company();
        return $this->form($company, [], 'general');
    }

    public function update(Request $request): Response
    {
        $company = Auth::company();
        $data = $request->only(array_keys(self::RULES));
        if ($data['website'] !== '' && $data['website'] !== null && !preg_match('#^https?://#i', (string) $data['website'])) {
            $data['website'] = 'https://' . $data['website'];
        }
        $data['folio_prefix'] = FolioService::normalizePrefix((string) $data['folio_prefix']);

        $v = Validator::make($data, self::RULES, self::LABELS);
        $errors = $v->errors();

        // Archivos: se validan y guardan antes de tocar la BD
        $newFiles = [];
        foreach (self::FILES as $field => $cfg) {
            if ($file = $request->file($field)) {
                try {
                    $newFiles[$field] = Upload::store($file, $cfg['dir'], (int) $company['id'], $cfg['types'], $cfg['max']);
                } catch (UploadException $e) {
                    $errors[$field] = $e->getMessage();
                }
            }
        }

        if ($errors) {
            array_map([Upload::class, 'delete'], $newFiles);
            return $this->form(array_merge($company, $data), $errors, (string) $request->input('_tab', 'general'));
        }

        $update = $data;
        foreach (['rfc', 'tax_regime', 'address', 'phone', 'email', 'website', 'bank_details', 'terms', 'footer_notes'] as $nullable) {
            $update[$nullable] = ($update[$nullable] ?? '') === '' ? null : $update[$nullable];
        }
        $update['rfc'] = $update['rfc'] !== null ? mb_strtoupper($update['rfc']) : null;
        $update['color_primary'] = strtoupper($data['color_primary']);
        $update['color_secondary'] = strtoupper($data['color_secondary']);
        // Si se fija manualmente el consecutivo, cuenta a partir del año en curso
        if ((int) $data['folio_next'] !== (int) $company['folio_next']) {
            $update['folio_year'] = (int) date('Y');
        }

        $obsolete = [];
        foreach (self::FILES as $field => $cfg) {
            $col = $cfg['column'];
            if (isset($newFiles[$field])) {
                $update[$col] = $newFiles[$field];
                $obsolete[] = $company[$col];
            } elseif ($request->input('remove_' . $field) === '1') {
                $update[$col] = null;
                $obsolete[] = $company[$col];
            }
        }
        if (array_key_exists('letterhead_path', $update)) {
            $update['letterhead_type'] = $update['letterhead_path'] === null ? null
                : (str_ends_with($update['letterhead_path'], '.pdf') ? 'pdf' : 'image');
        }

        $sets = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($update)));
        Database::query("UPDATE companies SET {$sets} WHERE id = :id", $update + ['id' => $company['id']]);
        array_map([Upload::class, 'delete'], array_filter($obsolete));

        Session::flash('success', 'Perfil de empresa actualizado.');
        return $this->redirect('/empresa?tab=' . urlencode((string) $request->input('_tab', 'general')));
    }

    /** Alta de una empresa adicional; quien la crea queda como administrador */
    public function create(Request $request): Response
    {
        return $this->view('companies/create', [
            'old' => ['default_currency' => 'MXN', 'vat_rate' => '16', 'folio_prefix' => 'COT', 'folio_next' => '1'],
            'errors' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $request->only(['trade_name', 'legal_name', 'rfc', 'default_currency', 'vat_rate', 'folio_prefix', 'folio_next']);
        $data['folio_prefix'] = FolioService::normalizePrefix((string) $data['folio_prefix']);
        $v = Validator::make($data, [
            'trade_name' => 'required|max:200', 'legal_name' => 'required|max:200', 'rfc' => 'rfc',
            'default_currency' => 'required|in:MXN,USD', 'vat_rate' => 'required|numeric|between:0,100',
            'folio_prefix' => 'max:20', 'folio_next' => 'required|integer|between:1,99999999',
        ], self::LABELS);
        if ($v->fails()) {
            return $this->view('companies/create', ['old' => $data, 'errors' => $v->errors()]);
        }

        $user = Auth::user();
        $companyId = Database::transaction(function () use ($data, $user) {
            $id = Database::insert('companies', [
                'trade_name' => $data['trade_name'],
                'legal_name' => $data['legal_name'],
                'rfc' => $data['rfc'] !== '' ? mb_strtoupper($data['rfc']) : null,
                'default_currency' => $data['default_currency'],
                'vat_rate' => $data['vat_rate'],
                'folio_prefix' => $data['folio_prefix'],
                'folio_next' => (int) $data['folio_next'],
                'folio_year' => (int) date('Y'),
            ]);
            Database::insert('company_user', [
                'company_id' => $id, 'user_id' => $user['id'], 'role' => 'admin', 'display_name' => $user['name'],
            ]);
            return $id;
        });

        Auth::selectCompany($companyId);
        Session::flash('success', "Empresa «{$data['trade_name']}» creada. Ya estás trabajando con ella: completa su perfil.");
        return $this->redirect('/empresa');
    }

    private function form(array $company, array $errors, string $tab): Response
    {
        $fileUrl = fn (?string $p) => $p ? url('/archivos/' . $p) : null;
        return $this->view('companies/edit', [
            'pageTitle' => 'Empresa',
            'profile' => $company,
            'errors' => $errors,
            'tab' => $errors ? $this->tabForErrors($errors, $tab) : (string) ($_GET['tab'] ?? $tab),
            'logoUrl' => $fileUrl($company['logo_path'] ?? null),
            'letterheadUrl' => $fileUrl($company['letterhead_path'] ?? null),
            'signatureUrl' => $fileUrl($company['signature_path'] ?? null),
        ]);
    }

    /** Abre la pestaña donde está el primer error */
    private function tabForErrors(array $errors, string $fallback): string
    {
        $map = [
            'general' => ['legal_name', 'trade_name', 'rfc', 'tax_regime', 'address', 'phone', 'email', 'website'],
            'branding' => ['logo', 'letterhead', 'color_primary', 'color_secondary', 'pdf_margin_top', 'pdf_margin_right', 'pdf_margin_bottom', 'pdf_margin_left'],
            'document' => ['bank_details', 'terms', 'footer_notes', 'default_validity_days', 'signature'],
            'folios' => ['folio_prefix', 'folio_include_year', 'folio_reset_yearly', 'folio_padding', 'folio_next', 'default_currency', 'vat_rate', 'hide_costs_from_sellers', 'require_quote_approval'],
        ];
        foreach ($map as $tab => $fields) {
            if (array_intersect($fields, array_keys($errors))) {
                return $tab;
            }
        }
        return $fallback;
    }
}

<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Quotes\QuoteService;
use App\Domain\Quotes\ConflictException;
use App\Domain\Quotes\ValidationException;
use App\Pdf\QuotePdfRenderer;
use App\Pricing\ExchangeRateService;
use App\Providers\ProviderAccountRepository;
use App\Providers\ProviderRegistry;

final class QuoteController extends Controller
{
    public const STATUSES = [
        'draft' => 'Borrador', 'sent' => 'Enviada', 'accepted' => 'Aceptada', 'rejected' => 'Rechazada', 'expired' => 'Vencida',
    ];

    public function index(Request $request): Response
    {
        $q = $request->str('q');
        $status = $request->str('status');
        $view = $request->str('vista');
        $where = ['q.company_id = :cid'];
        $params = ['cid' => Auth::companyId(), 'me' => Auth::id()];
        // Vendedores: las suyas y las compartidas con ellos. Administradores: todas.
        if ($view === 'mias') {
            $where[] = 'q.user_id = :uid';
            $params['uid'] = Auth::id();
        } elseif ($view === 'compartidas') {
            $where[] = 'qs.user_id IS NOT NULL';
        } elseif (!Auth::isAdmin()) {
            $where[] = '(q.user_id = :uid OR qs.user_id IS NOT NULL)';
            $params['uid'] = Auth::id();
        }
        if (isset(self::STATUSES[$status])) {
            $where[] = 'q.status = :status';
            $params['status'] = $status;
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(q.folio LIKE :q1 OR q.title LIKE :q2 OR c.name LIKE :q3 OR c.company_name LIKE :q4)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $quotes = Database::fetchAll(
            'SELECT q.id, q.folio, q.version, q.status, q.title, q.issue_date, q.valid_until, q.currency, q.total, q.user_id,
                    q.approval_status, c.name AS client_name, c.company_name AS client_company, u.name AS user_name,
                    qs.permission AS shared_permission,
                    (SELECT COUNT(*) FROM quote_shares x WHERE x.quote_id = q.id) AS shares_count
               FROM quotes q LEFT JOIN clients c ON c.id = q.client_id JOIN users u ON u.id = q.user_id
               LEFT JOIN quote_shares qs ON qs.quote_id = q.id AND qs.user_id = :me
              WHERE ' . implode(' AND ', $where) . ' ORDER BY q.id DESC LIMIT 300',
            $params
        );
        return $this->view('quotes/index', ['pageTitle' => 'Cotizaciones', 'quotes' => $quotes, 'q' => $q,
            'status' => $status, 'statuses' => self::STATUSES, 'vista' => $view]);
    }

    public function create(Request $request): Response
    {
        $client = null;
        if ($cid = (int) $request->input('cliente', 0)) {
            $client = Database::fetch('SELECT id, name, company_name, rfc, email, phone FROM clients WHERE id = ? AND company_id = ?',
                [$cid, Auth::companyId()]);
        }
        return $this->view('quotes/create', ['pageTitle' => 'Nueva cotización', 'client' => $client]);
    }

    public function store(Request $request): Response
    {
        $clientId = (int) $request->input('client_id', 0);
        try {
            $id = QuoteService::forCurrentCompany()->create($clientId ?: null, $request->str('currency'), $request->str('title'));
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/cotizaciones/nueva');
        }
        return $this->redirect('/cotizaciones/' . $id);
    }

    public function edit(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        $providers = array_map(fn ($a) => ['code' => $a->providerCode, 'name' => ProviderRegistry::get($a->providerCode)::name()],
            ProviderAccountRepository::default()->active(Auth::companyId()));
        return $this->view('quotes/edit', [
            'pageTitle' => $quote['folio'],
            'state' => $svc->editorState($quote),
            'providers' => $providers,
            'units' => QuoteService::UNITS,
            'statuses' => self::STATUSES,
        ]);
    }

    /** Guarda todo el editor (JSON) y devuelve el estado recalculado */
    public function save(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        try {
            $svc->save($quote, $request->all());
        } catch (ConflictException $e) {
            return Response::json(['error' => $e->getMessage(), 'conflict' => true], 409);
        } catch (ValidationException $e) {
            return Response::json(['error' => $e->getMessage(), 'errors' => $e->errors], 422);
        } catch (\DomainException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
        return Response::json(['state' => $svc->editorState($svc->find((int) $quote['id']))]);
    }

    /** Agrega un producto de proveedor (se guarda de inmediato con su costo actual) */
    public function addProduct(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        $provider = $request->str('provider');
        $productId = $request->str('product_id');
        if (!ProviderRegistry::get($provider) || $productId === '') {
            return Response::json(['error' => 'Producto inválido.'], 422);
        }
        $section = $request->str('section_key');
        $sectionId = preg_match('/^id:(\d+)$/', $section, $m) ? (int) $m[1] : null;
        try {
            $r = $svc->addProduct($quote, $provider, $productId, $sectionId, max(0.001, (float) $request->input('qty', 1)));
        } catch (\DomainException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
        return Response::json($r + ['state' => $svc->editorState($svc->find((int) $quote['id']))]);
    }

    /** Tipo de cambio actual según la configuración de la empresa */
    public function exchangeRate(Request $request): Response
    {
        QuoteService::forCurrentCompany()->find((int) $request->params['id']);
        return Response::json(ExchangeRateService::current(Auth::company()));
    }

    /** PDF de la cotización: en línea (vista previa) o descarga (?descargar=1) */
    public function pdf(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        $company = Auth::company();
        try {
            $pdf = (new QuotePdfRenderer($company))->render($quote);
        } catch (\Throwable $e) {
            error_log((string) $e);
            return new Response('<p style="font-family:sans-serif;padding:2rem">No se pudo generar el PDF: ' . e($e->getMessage()) . '</p>', 500);
        }
        $name = QuotePdfRenderer::filename($quote);
        $download = $request->input('descargar') === '1';
        if ($download) {
            // Copia del último PDF descargado
            $dir = storage_path('pdfs/' . (int) $company['id']);
            if (is_dir($dir) || @mkdir($dir, 0775, true)) {
                @file_put_contents($dir . '/' . $name, $pdf);
            }
        }
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"',
            'Cache-Control' => 'private, no-store',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /** Compartir con compañeros: [{user_id, permission: none|view|edit}] */
    public function share(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        try {
            $svc->setShares($quote, array_values(array_filter((array) $request->input('shares', []), 'is_array')));
        } catch (\DomainException $e) {
            return Response::json(['error' => $e->getMessage()], 403);
        }
        return Response::json(['state' => $svc->editorState($svc->find((int) $quote['id']))]);
    }

    public function approve(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        try {
            $svc->approve($quote);
        } catch (\DomainException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
        return Response::json(['state' => $svc->editorState($svc->find((int) $quote['id']))]);
    }

    public function destroy(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        if (!QuoteService::canManage($quote)) {
            Session::flash('error', 'Solo el creador o un administrador pueden eliminar esta cotización.');
            return $this->redirect('/cotizaciones/' . $quote['id']);
        }
        if ($quote['status'] !== 'draft') {
            Session::flash('error', 'Solo se pueden eliminar borradores.');
            return $this->redirect('/cotizaciones/' . $quote['id']);
        }
        Database::query('DELETE FROM quotes WHERE id = ?', [$quote['id']]);
        Session::flash('success', "Cotización {$quote['folio']} eliminada.");
        return $this->redirect('/cotizaciones');
    }
}

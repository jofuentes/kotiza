<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Quotes\QuoteService;
use App\Domain\Quotes\ValidationException;
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
        $where = ['q.company_id = :cid'];
        $params = ['cid' => Auth::companyId()];
        if (!Auth::isAdmin()) {
            $where[] = 'q.user_id = :uid';
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
            'SELECT q.id, q.folio, q.version, q.status, q.title, q.issue_date, q.valid_until, q.currency, q.total,
                    c.name AS client_name, c.company_name AS client_company, u.name AS user_name
               FROM quotes q LEFT JOIN clients c ON c.id = q.client_id JOIN users u ON u.id = q.user_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY q.id DESC LIMIT 300',
            $params
        );
        return $this->view('quotes/index', ['pageTitle' => 'Cotizaciones', 'quotes' => $quotes, 'q' => $q,
            'status' => $status, 'statuses' => self::STATUSES]);
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

    public function approve(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        $svc->approve($quote);
        return Response::json(['state' => $svc->editorState($svc->find((int) $quote['id']))]);
    }

    public function destroy(Request $request): Response
    {
        $svc = QuoteService::forCurrentCompany();
        $quote = $svc->find((int) $request->params['id']);
        if ($quote['status'] !== 'draft') {
            Session::flash('error', 'Solo se pueden eliminar borradores.');
            return $this->redirect('/cotizaciones/' . $quote['id']);
        }
        Database::query('DELETE FROM quotes WHERE id = ?', [$quote['id']]);
        Session::flash('success', "Cotización {$quote['folio']} eliminada.");
        return $this->redirect('/cotizaciones');
    }
}

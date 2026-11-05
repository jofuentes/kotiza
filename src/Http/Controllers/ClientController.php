<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/** Catálogo de clientes de la empresa activa */
final class ClientController extends Controller
{
    public const FIELDS = ['name', 'company_name', 'rfc', 'email', 'phone', 'address', 'notes'];
    private const LABELS = ['name' => 'Nombre', 'company_name' => 'Empresa', 'rfc' => 'RFC', 'email' => 'Correo',
        'phone' => 'Teléfono', 'address' => 'Dirección', 'notes' => 'Notas'];
    private const RULES = ['name' => 'required|max:160', 'company_name' => 'max:200', 'rfc' => 'rfc', 'email' => 'email|max:190',
        'phone' => 'max:80', 'address' => 'max:1000', 'notes' => 'max:2000'];

    public function index(Request $request): Response
    {
        $q = $request->str('q');
        [$where, $params] = $this->searchWhere($q);
        $clients = Database::fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM quotes x WHERE x.client_id = c.id) AS quotes_count
               FROM clients c WHERE {$where} ORDER BY c.name LIMIT 200", $params
        );
        return $this->view('clients/index', ['pageTitle' => 'Clientes', 'clients' => $clients, 'q' => $q]);
    }

    /** JSON para el selector de clientes */
    public function search(Request $request): Response
    {
        [$where, $params] = $this->searchWhere($request->str('q'));
        return Response::json(Database::fetchAll(
            "SELECT id, name, company_name, rfc, email, phone FROM clients c WHERE {$where} ORDER BY name LIMIT 20", $params
        ));
    }

    public function create(Request $request): Response
    {
        return $this->view('clients/form', ['pageTitle' => 'Nuevo cliente', 'client' => null, 'old' => [], 'errors' => []]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validated($request);
        if ($errors) {
            return $request->wantsJson()
                ? Response::json(['errors' => $errors], 422)
                : $this->view('clients/form', ['pageTitle' => 'Nuevo cliente', 'client' => null, 'old' => $data, 'errors' => $errors]);
        }
        $id = Database::insert('clients', $data + ['company_id' => Auth::companyId()]);
        if ($request->wantsJson()) {
            return Response::json(Database::fetch('SELECT id, name, company_name, rfc, email, phone FROM clients WHERE id = ?', [$id]), 201);
        }
        Session::flash('success', 'Cliente creado.');
        return $this->redirect('/clientes');
    }

    public function edit(Request $request): Response
    {
        $client = $this->client((int) $request->params['id']);
        return $this->view('clients/form', ['pageTitle' => 'Editar cliente', 'client' => $client, 'old' => $client, 'errors' => []]);
    }

    public function update(Request $request): Response
    {
        $client = $this->client((int) $request->params['id']);
        [$data, $errors] = $this->validated($request);
        if ($errors) {
            return $this->view('clients/form', ['pageTitle' => 'Editar cliente', 'client' => $client, 'old' => $data, 'errors' => $errors]);
        }
        $sets = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($data)));
        Database::query("UPDATE clients SET {$sets} WHERE id = :id", $data + ['id' => $client['id']]);
        Session::flash('success', 'Cliente actualizado.');
        return $this->redirect('/clientes');
    }

    public function destroy(Request $request): Response
    {
        $client = $this->client((int) $request->params['id']);
        if ((int) Database::value('SELECT COUNT(*) FROM quotes WHERE client_id = ?', [$client['id']]) > 0) {
            Session::flash('error', 'No se puede eliminar: el cliente tiene cotizaciones.');
        } else {
            Database::query('DELETE FROM clients WHERE id = ?', [$client['id']]);
            Session::flash('success', 'Cliente eliminado.');
        }
        return $this->redirect('/clientes');
    }

    private function validated(Request $request): array
    {
        $data = $request->only(self::FIELDS);
        $errors = Validator::make($data, self::RULES, self::LABELS)->errors();
        foreach ($data as $k => $v) {
            $data[$k] = $v === '' ? null : $v;
        }
        $data['rfc'] = $data['rfc'] !== null ? mb_strtoupper($data['rfc']) : null;
        $data['email'] = $data['email'] !== null ? mb_strtolower($data['email']) : null;
        return [$data, $errors];
    }

    private function searchWhere(string $q): array
    {
        $where = 'c.company_id = :cid';
        $params = ['cid' => Auth::companyId()];
        if ($q !== '') {
            $where .= ' AND (c.name LIKE :q1 OR c.company_name LIKE :q2 OR c.rfc LIKE :q3 OR c.email LIKE :q4)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        return [$where, $params];
    }

    private function client(int $id): array
    {
        return Database::fetch('SELECT * FROM clients WHERE id = ? AND company_id = ?', [$id, Auth::companyId()])
            ?? throw new HttpException(404);
    }
}

<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/** Asistente de primer arranque: crea al administrador y la primera empresa. */
final class SetupController extends Controller
{
    public const LABELS = [
        'name' => 'Nombre', 'email' => 'Correo', 'password' => 'Contraseña',
        'password_confirmation' => 'Confirmar contraseña',
        'trade_name' => 'Nombre comercial', 'legal_name' => 'Razón social', 'rfc' => 'RFC',
        'default_currency' => 'Moneda', 'vat_rate' => 'IVA', 'folio_prefix' => 'Prefijo de folio',
        'folio_next' => 'Siguiente folio',
    ];

    public function show(Request $request): Response
    {
        return $this->view('setup/wizard', [
            'old' => ['default_currency' => 'MXN', 'vat_rate' => '16', 'folio_prefix' => 'COT', 'folio_next' => '1'],
            'errors' => [],
        ], 'layouts/guest');
    }

    public function store(Request $request): Response
    {
        $data = $request->only(array_keys(self::LABELS));
        $v = Validator::make($data, [
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'password' => 'required|min:10|max:200',
            'password_confirmation' => 'required|same:password',
            'trade_name' => 'required|max:200',
            'legal_name' => 'required|max:200',
            'rfc' => 'rfc',
            'default_currency' => 'required|in:MXN,USD',
            'vat_rate' => 'required|numeric|between:0,100',
            'folio_prefix' => 'required|max:20',
            'folio_next' => 'required|integer|between:1,99999999',
        ], self::LABELS);

        if ($v->fails()) {
            unset($data['password'], $data['password_confirmation']);
            return $this->view('setup/wizard', ['old' => $data, 'errors' => $v->errors()], 'layouts/guest');
        }

        $user = Database::transaction(function () use ($data) {
            // Evita que dos peticiones simultáneas creen dos administradores
            Database::value('SELECT GET_LOCK("cotizador_setup", 10)');
            if ((int) Database::value('SELECT COUNT(*) FROM users') > 0) {
                return null;
            }
            $userId = Database::insert('users', [
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
            $companyId = Database::insert('companies', [
                'trade_name' => $data['trade_name'],
                'legal_name' => $data['legal_name'],
                'rfc' => $data['rfc'] !== '' ? mb_strtoupper($data['rfc']) : null,
                'default_currency' => $data['default_currency'],
                'vat_rate' => $data['vat_rate'],
                'folio_prefix' => mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['folio_prefix'])),
                'folio_next' => (int) $data['folio_next'],
                'folio_year' => (int) date('Y'),
            ]);
            Database::insert('company_user', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'role' => 'admin',
                'display_name' => $data['name'],
            ]);
            Database::value('SELECT RELEASE_LOCK("cotizador_setup")');
            return ['id' => $userId, 'company_id' => $companyId];
        });

        if ($user === null) {
            return $this->redirect('/login');
        }

        Auth::login($user);
        Auth::selectCompany($user['company_id']);
        Session::flash('success', '¡Listo! Tu cuenta y tu empresa quedaron creadas.');
        return $this->redirect('/');
    }
}

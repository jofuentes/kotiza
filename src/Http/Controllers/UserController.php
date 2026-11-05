<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Upload;
use App\Support\UploadException;
use App\Support\Validator;

/**
 * Usuarios de la empresa activa. Nombre, correo y contraseña son globales:
 * solo se editan aquí si el usuario no pertenece a otras empresas.
 */
final class UserController extends Controller
{
    private const LABELS = [
        'name' => 'Nombre', 'email' => 'Correo', 'password' => 'Contraseña',
        'password_confirmation' => 'Confirmar contraseña', 'role' => 'Rol',
        'display_name' => 'Nombre en cotizaciones', 'max_discount_pct' => 'Descuento máximo',
        'is_active' => 'Activo',
    ];

    public function index(Request $request): Response
    {
        $users = Database::fetchAll(
            'SELECT u.id, u.name, u.email, u.last_login_at, cu.role, cu.is_active, cu.max_discount_pct, cu.display_name,
                    (SELECT COUNT(*) FROM company_user x WHERE x.user_id = u.id) AS companies_count
               FROM company_user cu JOIN users u ON u.id = cu.user_id
              WHERE cu.company_id = ?
           ORDER BY cu.is_active DESC, u.name',
            [Auth::companyId()]
        );
        return $this->view('users/index', ['pageTitle' => 'Usuarios', 'users' => $users]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, ['role' => 'seller', 'is_active' => '1', 'max_discount_pct' => '10'], []);
    }

    public function store(Request $request): Response
    {
        $data = $request->only(['name', 'email', 'password', 'password_confirmation', 'role', 'display_name', 'max_discount_pct']);
        $data['email'] = mb_strtolower((string) $data['email']);
        $existing = $data['email'] !== ''
            ? Database::fetch('SELECT id, name FROM users WHERE email = ?', [$data['email']])
            : null;

        // Si el correo ya existe solo se agrega a esta empresa (no se piden nombre ni contraseña)
        $rules = [
            'email' => 'required|email|max:190', 'role' => 'required|in:admin,seller',
            'display_name' => 'max:120', 'max_discount_pct' => 'numeric|between:0,100',
        ];
        if (!$existing) {
            $rules += ['name' => 'required|max:120', 'password' => 'required|min:10|max:200', 'password_confirmation' => 'required|same:password'];
        }
        $v = Validator::make($data, $rules, self::LABELS);
        $errors = $v->errors();

        if ($existing && Database::value('SELECT 1 FROM company_user WHERE company_id = ? AND user_id = ?', [Auth::companyId(), $existing['id']])) {
            $errors['email'] = 'Ese usuario ya pertenece a esta empresa.';
        }

        $signature = null;
        if (!$errors && ($file = $request->file('signature'))) {
            try {
                $signature = Upload::store($file, 'signatures', Auth::companyId(), Upload::IMAGE, 2 * 1048576);
            } catch (UploadException $e) {
                $errors['signature'] = $e->getMessage();
            }
        }
        if ($errors) {
            unset($data['password'], $data['password_confirmation']);
            return $this->form(null, $data, $errors);
        }

        Database::transaction(function () use ($data, $existing, $signature) {
            $userId = $existing['id'] ?? Database::insert('users', [
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
            Database::insert('company_user', [
                'company_id' => Auth::companyId(),
                'user_id' => $userId,
                'role' => $data['role'],
                'display_name' => $data['display_name'] ?: ($existing['name'] ?? $data['name']),
                'max_discount_pct' => $data['max_discount_pct'] === '' ? null : $data['max_discount_pct'],
                'signature_path' => $signature,
            ]);
        });

        Session::flash('success', $existing
            ? "{$existing['name']} ya tenía cuenta: se agregó a esta empresa con su contraseña actual."
            : 'Usuario creado. Compártele su correo y contraseña para que inicie sesión.');
        return $this->redirect('/usuarios');
    }

    public function edit(Request $request): Response
    {
        $member = $this->member((int) $request->params['id']);
        return $this->form($member, $member, []);
    }

    public function update(Request $request): Response
    {
        $member = $this->member((int) $request->params['id']);
        $isSelf = (int) $member['id'] === Auth::id();
        $globalEditable = (int) $member['companies_count'] === 1;

        $data = $request->only(['name', 'email', 'password', 'password_confirmation', 'role', 'is_active', 'display_name', 'max_discount_pct']);
        $data['email'] = mb_strtolower((string) $data['email']);
        // Uno mismo no puede quitarse el rol de admin ni desactivarse
        if ($isSelf) {
            $data['role'] = $member['role'];
            $data['is_active'] = '1';
        }

        $rules = [
            'role' => 'required|in:admin,seller', 'is_active' => 'required|in:0,1',
            'display_name' => 'max:120', 'max_discount_pct' => 'numeric|between:0,100',
        ];
        if ($globalEditable) {
            $rules += ['name' => 'required|max:120', 'email' => 'required|email|max:190', 'password' => 'min:10|max:200'];
            if (($data['password'] ?? '') !== '') {
                $rules['password_confirmation'] = 'required|same:password';
            }
        }
        $errors = Validator::make($data, $rules, self::LABELS)->errors();

        if ($globalEditable && !isset($errors['email'])
            && Database::value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$data['email'], $member['id']])) {
            $errors['email'] = 'Ese correo ya lo usa otro usuario.';
        }
        $losesAdmin = $member['role'] === 'admin' && $member['is_active'] && ($data['role'] !== 'admin' || $data['is_active'] !== '1');
        if ($losesAdmin && $this->activeAdmins() <= 1) {
            $errors['role'] = 'La empresa debe conservar al menos un administrador activo.';
        }

        $signature = null;
        if (!$errors && ($file = $request->file('signature'))) {
            try {
                $signature = Upload::store($file, 'signatures', Auth::companyId(), Upload::IMAGE, 2 * 1048576);
            } catch (UploadException $e) {
                $errors['signature'] = $e->getMessage();
            }
        }
        if ($errors) {
            unset($data['password'], $data['password_confirmation']);
            return $this->form($member, array_merge($member, $data), $errors);
        }

        $membership = [
            'role' => $data['role'],
            'is_active' => (int) $data['is_active'],
            'display_name' => $data['display_name'] ?: null,
            'max_discount_pct' => $data['max_discount_pct'] === '' ? null : $data['max_discount_pct'],
        ];
        $oldSignature = null;
        if ($signature !== null || $request->input('remove_signature') === '1') {
            $membership['signature_path'] = $signature;
            $oldSignature = $member['signature_path'];
        }

        Database::transaction(function () use ($member, $membership, $data, $globalEditable) {
            $sets = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($membership)));
            Database::query(
                "UPDATE company_user SET {$sets} WHERE company_id = :cid AND user_id = :uid",
                $membership + ['cid' => Auth::companyId(), 'uid' => $member['id']]
            );
            if ($globalEditable) {
                Database::query('UPDATE users SET name = ?, email = ? WHERE id = ?', [$data['name'], $data['email'], $member['id']]);
                if (($data['password'] ?? '') !== '') {
                    Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($data['password'], PASSWORD_DEFAULT), $member['id']]);
                    Database::query('DELETE FROM login_attempts WHERE email = ?', [$data['email']]);
                }
            }
        });
        Upload::delete($oldSignature);
        Auth::refresh();

        Session::flash('success', 'Usuario actualizado.');
        return $this->redirect('/usuarios');
    }

    private function form(?array $member, array $old, array $errors): Response
    {
        return $this->view('users/form', [
            'pageTitle' => $member ? 'Editar usuario' : 'Nuevo usuario',
            'member' => $member,
            'old' => $old,
            'errors' => $errors,
            'isSelf' => $member && (int) $member['id'] === Auth::id(),
            'globalEditable' => !$member || (int) $member['companies_count'] === 1,
            'signatureUrl' => !empty($member['signature_path']) ? url('/archivos/' . $member['signature_path']) : null,
        ]);
    }

    private function member(int $userId): array
    {
        return Database::fetch(
            'SELECT u.id, u.name, u.email, u.last_login_at, cu.role, cu.is_active, cu.max_discount_pct,
                    cu.display_name, cu.signature_path,
                    (SELECT COUNT(*) FROM company_user x WHERE x.user_id = u.id) AS companies_count
               FROM company_user cu JOIN users u ON u.id = cu.user_id
              WHERE cu.company_id = ? AND u.id = ?',
            [Auth::companyId(), $userId]
        ) ?? throw new HttpException(404);
    }

    private function activeAdmins(): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM company_user WHERE company_id = ? AND role = 'admin' AND is_active = 1",
            [Auth::companyId()]
        );
    }
}

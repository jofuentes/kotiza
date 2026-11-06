<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Upload;
use App\Support\UploadException;
use App\Support\Validator;

/** Perfil propio: datos de acceso, contraseña, y nombre/firma en la empresa activa. */
final class ProfileController extends Controller
{
    private const LABELS = [
        'name' => 'Nombre', 'email' => 'Correo', 'current_password' => 'Contraseña actual',
        'password' => 'Contraseña nueva', 'password_confirmation' => 'Confirmar contraseña',
        'display_name' => 'Nombre en cotizaciones',
    ];

    public function edit(Request $request): Response
    {
        $m = Auth::membership();
        return $this->form(Auth::user() + ['display_name' => $m['display_name']], []);
    }

    public function update(Request $request): Response
    {
        $user = Database::fetch('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $m = Auth::membership();
        $data = $request->only(['name', 'email', 'current_password', 'password', 'password_confirmation', 'display_name']);
        $data['email'] = mb_strtolower((string) $data['email']);

        $changingEmail = $data['email'] !== $user['email'];
        $changingPassword = ($data['password'] ?? '') !== '';
        $rules = ['name' => 'required|max:120', 'email' => 'required|email|max:190', 'display_name' => 'max:120'];
        if ($changingPassword) {
            $rules += ['password' => 'min:10|max:200', 'password_confirmation' => 'required|same:password'];
        }
        $errors = Validator::make($data, $rules, self::LABELS)->errors();

        if (($changingEmail || $changingPassword) && !password_verify((string) $data['current_password'], $user['password_hash'])) {
            $errors['current_password'] = 'Para cambiar correo o contraseña escribe tu contraseña actual correcta.';
        }
        if ($changingEmail && !isset($errors['email'])
            && Database::value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$data['email'], $user['id']])) {
            $errors['email'] = 'Ese correo ya lo usa otro usuario.';
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
            unset($data['current_password'], $data['password'], $data['password_confirmation']);
            return $this->form($data, $errors);
        }

        Database::query('UPDATE users SET name = ?, email = ? WHERE id = ?', [$data['name'], $data['email'], $user['id']]);
        if ($changingPassword) {
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($data['password'], PASSWORD_DEFAULT), $user['id']]);
            Auth::invalidateSessions((int) $user['id']);
        }

        $membership = ['display_name' => $data['display_name'] ?: null];
        $oldSignature = null;
        if ($signature !== null || $request->input('remove_signature') === '1') {
            $membership['signature_path'] = $signature;
            $oldSignature = $m['signature_path'];
        }
        $sets = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($membership)));
        Database::query("UPDATE company_user SET {$sets} WHERE company_id = :cid AND user_id = :uid",
            $membership + ['cid' => Auth::companyId(), 'uid' => $user['id']]);
        Upload::delete($oldSignature);
        Auth::refresh();

        Session::flash('success', $changingPassword ? 'Perfil y contraseña actualizados.' : 'Perfil actualizado.');
        return $this->redirect('/perfil');
    }

    private function form(array $old, array $errors): Response
    {
        $m = Auth::membership();
        return $this->view('profile/edit', [
            'pageTitle' => 'Mi perfil',
            'old' => $old,
            'errors' => $errors,
            'signatureUrl' => !empty($m['signature_path']) ? url('/archivos/' . $m['signature_path']) : null,
        ]);
    }
}

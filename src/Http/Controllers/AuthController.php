<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LoginThrottle;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\PasswordResets;
use App\Mail\MailSettings;
use App\Support\Validator;

final class AuthController extends Controller
{
    /** Hash ficticio para que el tiempo de respuesta no revele si el correo existe */
    private const DUMMY_HASH = '$2y$10$ziw/gNugmRh74G9egQvRrufW1TwhxKAz4LeLeOBJ43okF.3t1gYpi';

    public function showLogin(Request $request): Response
    {
        return $this->loginView('', null);
    }

    public function login(Request $request): Response
    {
        $email = mb_strtolower($request->str('email'));
        $password = (string) $request->input('password', '');
        $ip = $request->ip();

        $fail = fn (string $msg) => $this->loginView($email, $msg);

        if ($email === '' || $password === '') {
            return $fail('Escribe tu correo y contraseña.');
        }

        $locked = LoginThrottle::lockedMinutes($email, $ip);
        if ($locked > 0) {
            return $fail("Demasiados intentos fallidos. Intenta de nuevo en {$locked} minuto(s).");
        }

        $user = Database::fetch('SELECT * FROM users WHERE email = ?', [$email]);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if (!$user || !$valid || !$user['is_active']) {
            LoginThrottle::record($email, $ip, false);
            return $fail($user && $valid ? 'Tu usuario está desactivado.' : 'Correo o contraseña incorrectos.');
        }

        $hasCompany = Database::value(
            'SELECT 1 FROM company_user cu JOIN companies c ON c.id = cu.company_id
              WHERE cu.user_id = ? AND cu.is_active = 1 AND c.is_active = 1 LIMIT 1',
            [$user['id']]
        );
        if (!$hasCompany) {
            return $fail('Tu usuario no tiene acceso activo a ninguna empresa. Contacta al administrador.');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_DEFAULT), $user['id'],
            ]);
        }

        LoginThrottle::record($email, $ip, true);
        $intended = Session::get('intended', '/');
        Auth::login($user);
        return $this->redirect(is_string($intended) && str_starts_with($intended, '/') ? $intended : '/');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return $this->redirect('/login');
    }

    public function showForgot(Request $request): Response
    {
        if (!MailSettings::available()) {
            return $this->redirect('/login');
        }
        return $this->view('auth/forgot-password', ['error' => null], 'layouts/guest');
    }

    public function forgot(Request $request): Response
    {
        if (!MailSettings::available()) {
            return $this->redirect('/login');
        }
        $email = mb_strtolower($request->str('email'));
        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            return $this->view('auth/forgot-password', ['error' => 'Escribe un correo válido.'], 'layouts/guest');
        }
        PasswordResets::request($email, $request->ip());
        // El mismo mensaje exista o no el correo
        Session::flash('success', 'Si ese correo tiene una cuenta activa, recibirás un enlace en unos minutos. Revisa también tu carpeta de spam.');
        return $this->redirect('/login');
    }

    public function showReset(Request $request): Response
    {
        $token = $request->str('token');
        return $this->view('auth/reset-password', [
            'token' => $token, 'valid' => PasswordResets::isValid($token), 'errors' => [],
        ], 'layouts/guest');
    }

    public function reset(Request $request): Response
    {
        $token = $request->str('token');
        $data = $request->only(['password', 'password_confirmation']);
        $errors = Validator::make($data, [
            'password' => 'required|min:10|max:200', 'password_confirmation' => 'required|same:password',
        ], ['password' => 'Contraseña nueva', 'password_confirmation' => 'Confirmar contraseña'])->errors();
        if ($errors) {
            return $this->view('auth/reset-password', [
                'token' => $token, 'valid' => PasswordResets::isValid($token), 'errors' => $errors,
            ], 'layouts/guest');
        }
        if (!PasswordResets::complete($token, (string) $data['password'])) {
            return $this->view('auth/reset-password', ['token' => $token, 'valid' => false, 'errors' => []], 'layouts/guest');
        }
        Session::flash('success', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
        return $this->redirect('/login');
    }

    private function loginView(string $email, ?string $error): Response
    {
        return $this->view('auth/login', [
            'email' => $email, 'error' => $error, 'canRecover' => MailSettings::available(),
        ], 'layouts/guest');
    }
}

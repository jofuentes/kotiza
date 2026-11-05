<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LoginThrottle;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthController extends Controller
{
    /** Hash ficticio para que el tiempo de respuesta no revele si el correo existe */
    private const DUMMY_HASH = '$2y$10$ziw/gNugmRh74G9egQvRrufW1TwhxKAz4LeLeOBJ43okF.3t1gYpi';

    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['email' => '', 'error' => null], 'layouts/guest');
    }

    public function login(Request $request): Response
    {
        $email = mb_strtolower($request->str('email'));
        $password = (string) $request->input('password', '');
        $ip = $request->ip();

        $fail = fn (string $msg) => $this->view('auth/login', ['email' => $email, 'error' => $msg], 'layouts/guest');

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
}

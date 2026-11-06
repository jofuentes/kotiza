<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Mail\MailMessage;
use App\Mail\MailSettings;
use App\Support\Validator;

/** Cuenta de correo con la que se envían los enlaces para recuperar contraseñas. */
final class MailController extends Controller
{
    private const LABELS = ['account' => 'Cuenta de Gmail', 'sender' => 'Nombre del remitente', 'password' => 'Contraseña de aplicación'];

    public function edit(Request $request): Response
    {
        $this->authorize();
        $current = MailSettings::current();
        return $this->form($current ?? ['account' => '', 'sender' => app_name()], [], $current !== null);
    }

    public function update(Request $request): Response
    {
        $this->authorize();
        $data = $request->only(['account', 'sender', 'password']);
        $data['account'] = mb_strtolower(trim((string) $data['account']));
        $data['sender'] = trim((string) $data['sender']);
        // Google muestra la contraseña de aplicación separada con espacios
        $password = (string) preg_replace('/\s+/', '', (string) $data['password']);
        $configured = MailSettings::available();

        $rules = ['account' => 'required|email|max:190', 'sender' => 'required|max:120'];
        if (!$configured) {
            $rules['password'] = 'required';
        }
        $errors = Validator::make($data, $rules, self::LABELS)->errors();

        if (!$errors) {
            try {
                $effective = $password !== '' ? $password : (string) MailSettings::storedPassword();
                MailSettings::mailerFor($data['account'], $data['sender'], $effective)->verify();
            } catch (\Throwable $e) {
                error_log('Correo: la comprobación falló: ' . $e->getMessage());
                $errors['password'] = 'No se pudo iniciar sesión en el correo. Revisa la cuenta y usa una contraseña de aplicación, no tu contraseña normal.';
            }
        }
        if ($errors) {
            unset($data['password']);
            return $this->form($data, $errors, $configured);
        }

        MailSettings::save($data['account'], $data['sender'], $effective);
        Session::flash('success', 'Conexión comprobada y cuenta guardada. Envíate un correo de prueba para confirmarlo.');
        return $this->redirect('/correo');
    }

    public function test(Request $request): Response
    {
        $this->authorize();
        $user = Auth::user();
        try {
            $mailer = MailSettings::mailer();
            if ($mailer === null) {
                throw new \RuntimeException('No hay cuenta configurada.');
            }
            $mailer->send((string) $user['email'], (string) $user['name'], MailMessage::test(app_name(), (string) $user['name']));
            Session::flash('success', "Correo de prueba enviado a {$user['email']}.");
        } catch (\Throwable $e) {
            error_log('Correo: la prueba falló: ' . $e->getMessage());
            Session::flash('error', 'No se pudo enviar el correo de prueba. Revisa la cuenta y la contraseña de aplicación.');
        }
        return $this->redirect('/correo');
    }

    public function destroy(Request $request): Response
    {
        $this->authorize();
        MailSettings::delete();
        Session::flash('success', 'Cuenta eliminada. La recuperación de contraseñas por correo quedó desactivada.');
        return $this->redirect('/correo');
    }

    private function authorize(): void
    {
        if (!Auth::isInstallationAdmin()) {
            throw new HttpException(403, 'Esta sección es solo para administradores de la primera empresa de la instalación.');
        }
    }

    private function form(array $old, array $errors, bool $configured): Response
    {
        return $this->view('mail/edit', [
            'pageTitle' => 'Correo',
            'old' => $old,
            'errors' => $errors,
            'configured' => $configured,
        ]);
    }
}

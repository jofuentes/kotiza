<?php
declare(strict_types=1);

namespace App\Mail;

/** Correo con versión de texto y HTML. */
final class MailMessage
{
    public function __construct(
        public readonly string $subject,
        public readonly string $text,
        public readonly string $html,
    ) {
    }

    /** @param string[] $paragraphs texto plano; se escapa para el HTML */
    public static function compose(string $appName, string $subject, string $greeting, array $paragraphs,
        ?string $buttonText = null, ?string $buttonUrl = null, ?string $note = null): self
    {
        $esc = fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $text = [$greeting, ''];
        foreach ($paragraphs as $p) {
            array_push($text, $p, '');
        }
        if ($buttonUrl !== null) {
            array_push($text, "{$buttonText}: {$buttonUrl}", '');
        }
        if ($note !== null) {
            array_push($text, $note, '');
        }
        $text[] = "— {$appName}";

        $body = '';
        foreach ($paragraphs as $p) {
            $body .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.5">' . $esc($p) . '</p>';
        }
        if ($buttonUrl !== null) {
            $body .= '<p style="margin:6px 0 18px"><a href="' . $esc($buttonUrl) . '" style="display:inline-block;padding:11px 20px;'
                . 'background:#1E40AF;border-radius:6px;color:#ffffff;font-size:15px;text-decoration:none">' . $esc((string) $buttonText) . '</a></p>';
        }
        if ($note !== null) {
            $body .= '<p style="margin:0;font-size:13px;line-height:1.5;color:#64748B">' . $esc($note) . '</p>';
        }
        $html = '<!doctype html><html lang="es"><body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
            . '<table role="presentation" width="100%" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px"><tr><td style="padding:24px 28px">'
            . '<p style="margin:0 0 18px;font-size:14px;font-weight:bold;color:#1E40AF">' . $esc($appName) . '</p>'
            . '<p style="margin:0 0 14px;font-size:15px">' . $esc($greeting) . '</p>' . $body
            . '</td></tr></table></body></html>';

        return new self("{$subject} · {$appName}", implode("\n", $text), $html);
    }

    public static function passwordReset(string $appName, string $userName, string $link, int $minutes): self
    {
        return self::compose(
            $appName,
            'Restablece tu contraseña',
            "Hola, {$userName}:",
            ['Recibimos una solicitud para restablecer tu contraseña. Usa este enlace para crear una nueva. '
                . "Solo funciona una vez y vence en {$minutes} minutos."],
            'Crear contraseña nueva',
            $link,
            'Si no la solicitaste, ignora este correo: tu contraseña seguirá igual.',
        );
    }

    public static function test(string $appName, string $userName): self
    {
        return self::compose(
            $appName,
            'Correo de prueba',
            "Hola, {$userName}:",
            ['Si recibiste este mensaje, el correo para recuperar contraseñas está bien configurado.'],
        );
    }
}

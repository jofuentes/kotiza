<?php
declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** Envío por SMTP. De forma predeterminada usa Gmail con una contraseña de aplicación. */
final class SmtpMailer implements Mailer
{
    public function __construct(
        private string $account,
        private string $password,
        private string $senderName,
        private string $host = 'smtp.gmail.com',
        private int $port = 465,
        /** ssl, tls (STARTTLS) o none */
        private string $encryption = 'ssl',
    ) {
    }

    public function verify(): void
    {
        $mail = $this->client();
        try {
            if (!$mail->smtpConnect()) {
                throw new \RuntimeException('No se pudo conectar con el servidor de correo.');
            }
        } catch (MailerException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        } finally {
            $mail->smtpClose();
        }
    }

    public function send(string $toEmail, string $toName, MailMessage $message): void
    {
        $mail = $this->client();
        try {
            $mail->setFrom($this->account, $this->senderName);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $message->subject;
            $mail->isHTML(true);
            $mail->Body = $message->html;
            $mail->AltBody = $message->text;
            $mail->send();
        } catch (MailerException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function client(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->Port = $this->port;
        $mail->SMTPAuth = true;
        $mail->Username = $this->account;
        $mail->Password = $this->password;
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = match ($this->encryption) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        return $mail;
    }
}

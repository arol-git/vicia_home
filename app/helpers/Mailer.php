<?php

namespace App\Helpers;

use App\Models\Setting;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoi d'e-mails via SMTP lorsque les paramètres sont configurés,
 * avec un repli sur mail() si aucun serveur SMTP n'est défini.
 */
class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $settings = Setting::all();
        $settings = self::withEnvironmentSettings($settings);
        $host = trim((string) ($settings['smtp_host'] ?? ''));

        if ($host === '') {
            app_log("[Mailer] E-mail non envoyé : serveur SMTP vide. À: $to | Sujet: $subject");
            return false;
        }

        if ($host !== '' && class_exists(PHPMailer::class)) {
            return self::sendSmtp($settings, $to, $subject, $body);
        }

        $fromEmail = trim((string) ($settings['smtp_from'] ?? '')) ?: 'no-reply@vicia-home.local';
        $fromName = trim((string) ($settings['smtp_from_name'] ?? '')) ?: config('app_name');
        $headers = "From: {$fromName} <{$fromEmail}>\r\nContent-Type: text/plain; charset=UTF-8";

        return mail($to, $subject, $body, $headers);
    }

    private static function sendSmtp(array $settings, string $to, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = trim((string) ($settings['smtp_host'] ?? ''));
            $mail->Port = (int) ($settings['smtp_port'] ?? 587);
            $mail->SMTPAuth = trim((string) ($settings['smtp_username'] ?? '')) !== '';
            $mail->Username = trim((string) ($settings['smtp_username'] ?? ''));
            $mail->Password = (string) ($settings['smtp_password'] ?? '');
            $mail->Timeout = 10;

            $encryption = trim((string) ($settings['smtp_encryption'] ?? 'tls'));
            if (in_array($encryption, ['tls', 'ssl'], true)) {
                $mail->SMTPSecure = $encryption;
            }

            $fromEmail = trim((string) ($settings['smtp_from'] ?? '')) ?: $mail->Username;
            $fromName = trim((string) ($settings['smtp_from_name'] ?? '')) ?: config('app_name');

            if ($fromEmail === '') {
                app_log('[Mailer] E-mail non envoyé : adresse d’expédition SMTP vide.');
                return false;
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $body;

            app_log("[Mailer] Tentative SMTP vers $to via {$mail->Host}:{$mail->Port}.");
            $sent = $mail->send();
            app_log("[NOTIFICATION] EMAIL utilisateur={$to} SUCCÈS | date=" . date('c'));

            return $sent;
        } catch (MailException $e) {
            app_log('[Mailer] Échec SMTP utilisateur=' . $to . ' : ' . $e->getMessage() . ' | date=' . date('c'));
            return false;
        }
    }

    private static function withEnvironmentSettings(array $settings): array
    {
        $environment = [
            'smtp_host' => getenv('SMTP_HOST') ?: '',
            'smtp_port' => getenv('SMTP_PORT') ?: '',
            'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: '',
            'smtp_username' => getenv('SMTP_USERNAME') ?: '',
            'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
            'smtp_from' => getenv('SMTP_FROM') ?: '',
            'smtp_from_name' => getenv('SMTP_FROM_NAME') ?: '',
        ];

        foreach ($environment as $key => $value) {
            if ($value !== '') {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }
}

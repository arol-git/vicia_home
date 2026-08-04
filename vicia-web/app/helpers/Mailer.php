<?php

namespace App\Helpers;

/**
 * Class Mailer
 *
 * Enveloppe minimale d'envoi d'e-mails. Utilise la fonction native
 * mail() de PHP, adaptée à un serveur Apache/Ubuntu disposant d'un
 * agent de transport local (sendmail/postfix) ou d'un relais SMTP
 * configuré au niveau système.
 *
 * En environnement de développement (APP_ENV != production), les
 * messages sont simplement journalisés dans storage/logs/app.log
 * plutôt qu'effectivement envoyés, afin de faciliter les tests.
 */
class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $fromSetting = config('app_name') . ' <no-reply@vicia-home.local>';

        if (config('env') !== 'production') {
            app_log("[Mailer] (mode développement — email non envoyé) À: $to | Sujet: $subject | Corps: $body");
            return true;
        }

        $headers = "From: $fromSetting\r\nContent-Type: text/plain; charset=UTF-8";

        return mail($to, $subject, $body, $headers);
    }
}

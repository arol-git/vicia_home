<?php

namespace Bot\Core;

use Bot\Core\Exceptions\ApiException;
use Bot\Core\Exceptions\BotException;
use Bot\Core\Exceptions\UnauthorizedException;
use Telegram\Bot\Api;

/**
 * Class ErrorHandler
 *
 * Système de gestion des erreurs du bot, à deux niveaux :
 *
 *   1. Un filet de sécurité global (registerGlobal()), installé au
 *      tout début du front controller, pour toute erreur PHP native
 *      ou exception échappant au bloc try/catch principal (erreur de
 *      bootstrap, par exemple). Il journalise et répond HTTP 200 à
 *      Telegram sans tenter d'envoyer de message — le contexte de
 *      conversation (chat_id) n'est pas nécessairement disponible à
 *      ce stade.
 *
 *   2. Un traitement contextualisé (handle()), utilisé par le front
 *      controller autour de Router::dispatch(), qui CONNAÎT le chat
 *      d'origine et peut donc répondre à l'utilisateur avec un
 *      message clair, tout en journalisant le détail technique.
 *
 * Principe constant : le message envoyé à l'utilisateur ne révèle
 * jamais de détail d'implémentation (nom de classe, requête SQL,
 * trace d'appel) — cette information reste dans les journaux.
 */
class ErrorHandler
{
    public static function registerGlobal(): void
    {
        set_exception_handler(function (\Throwable $e) {
            Logger::channel('bot')->critical('Exception non interceptée avant dispatch', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            http_response_code(200); // Telegram : toujours 200 pour éviter les répétitions du webhook
        });

        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            if (!(error_reporting() & $severity)) {
                return false; // erreur supprimée par @ ou par error_reporting() : ignorée, comportement standard
            }
            Logger::channel('bot')->error("Erreur PHP : $message", ['file' => $file, 'line' => $line]);
            return true;
        });
    }

    /**
     * Traite une exception survenue pendant le dispatch d'une requête
     * dont le contexte (chat, client Telegram) est connu : répond à
     * l'utilisateur avec un message adapté, journalise le détail
     * technique sur le canal approprié.
     */
    public static function handle(\Throwable $e, Request $request, Api $telegram): void
    {
        $response = new Response($telegram, $request);

        // Si l'erreur vient d'un bouton inline, Telegram attend quand
        // meme un accuse de reception. Sinon l'utilisateur voit le
        // bouton charger longtemps, comme si rien ne se passait.
        if ($request->isCallbackQuery()) {
            $response->answerCallback();
        }

        match (true) {
            $e instanceof UnauthorizedException => self::handleUnauthorized($e, $response),
            $e instanceof ApiException           => self::handleApiException($e, $response),
            $e instanceof BotException            => self::handleBotException($e, $response),
            default                                => self::handleUnexpected($e, $request, $response),
        };
    }

    private static function handleUnauthorized(UnauthorizedException $e, Response $response): void
    {
        Logger::channel('security')->warning($e->getMessage());
        $response->text('🔒 ' . $e->userMessage());
    }

    private static function handleApiException(ApiException $e, Response $response): void
    {
        Logger::channel('bot')->error('Erreur API Vicia Home : ' . $e->getMessage(), ['http_status' => $e->httpStatus()]);

        $message = match (true) {
            $e->isAuthError()  => "Votre session a expiré. Envoyez /start pour vous reconnecter.",
            $e->isForbidden()  => "Vous n'avez pas les droits nécessaires pour cette action.",
            default            => "La plateforme Vicia Home est momentanément indisponible. Merci de réessayer dans un instant.",
        };

        $response->text('⚠️ ' . $message);
    }

    private static function handleBotException(BotException $e, Response $response): void
    {
        Logger::channel('bot')->warning($e->getMessage());
        $response->text('⚠️ ' . $e->userMessage());
    }

    private static function handleUnexpected(\Throwable $e, Request $request, Response $response): void
    {
        Logger::channel('bot')->error('Exception inattendue', [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'chat_id'   => $request->chatId(),
        ]);

        $response->text("⚠️ Une erreur inattendue est survenue. L'équipe technique a été notifiée.");
    }
}

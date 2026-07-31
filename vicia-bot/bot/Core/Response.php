<?php

namespace Bot\Core;

use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Class Response
 *
 * Enveloppe les opérations d'envoi de l'API Telegram (texte, édition
 * de message, réponse à un callback, photo, document, PDF) derrière
 * une interface réduite et cohérente, utilisée par tous les
 * contrôleurs. Centralise également la gestion d'erreur : un envoi
 * Telegram raté est journalisé (canal "bot") et absorbé plutôt que de
 * remonter une exception — un échec d'accusé de réception ou de
 * message secondaire ne doit jamais faire échouer tout le traitement
 * de l'update en cours.
 */
class Response
{
    private bool $callbackAnswered = false;

    public function __construct(private readonly Api $telegram, private readonly Request $request)
    {
    }

    /**
     * Envoie un nouveau message texte dans le chat courant, avec un
     * clavier InlineKeyboard optionnel (tableau tel que retourné par
     * Bot\Services\KeyboardBuilder).
     */
    public function text(string $message, ?array $inlineKeyboard = null, string $parseMode = 'HTML'): void
    {
        $this->safeCall(function () use ($message, $inlineKeyboard, $parseMode) {
            $params = [
                'chat_id'    => $this->request->chatId(),
                'text'       => $message,
                'parse_mode' => $parseMode,
            ];
            if ($inlineKeyboard !== null) {
                $params['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
            }
            $this->telegram->sendMessage($params);
        }, 'sendMessage');
    }

    /**
     * Édite le message associé au callback_query en cours (mise à
     * jour d'un menu en place plutôt que renvoi d'un nouveau message
     * à chaque interaction — comportement attendu de tout menu
     * InlineKeyboard soigné).
     */
    public function edit(string $message, ?array $inlineKeyboard = null, string $parseMode = 'HTML'): void
    {
        $this->safeCall(function () use ($message, $inlineKeyboard, $parseMode) {
            $params = [
                'chat_id'    => $this->request->chatId(),
                'message_id' => $this->request->callbackMessageId(),
                'text'       => $message,
                'parse_mode' => $parseMode,
            ];
            if ($inlineKeyboard !== null) {
                $params['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
            }
            $this->telegram->editMessageText($params);
        }, 'editMessageText');
    }

    /**
     * Accuse réception d'un callback_query (fait disparaître le
     * spinner de chargement du bouton côté client Telegram), avec un
     * texte optionnel affiché en toast si $showAlert est vrai.
     */
    public function answerCallback(string $text = '', bool $showAlert = false): void
    {
        if ($this->callbackAnswered) {
            return;
        }

        $callbackId = $this->request->callbackQueryId();
        if (!$callbackId) {
            return;
        }

        $this->safeCall(function () use ($callbackId, $text, $showAlert) {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackId,
                'text'              => $text,
                'show_alert'        => $showAlert,
            ]);
        }, 'answerCallbackQuery');

        $this->callbackAnswered = true;
    }

    public function photo(string $filePath, string $caption = ''): void
    {
        $this->safeCall(function () use ($filePath, $caption) {
            $this->telegram->sendPhoto([
                'chat_id' => $this->request->chatId(),
                'photo'   => \Telegram\Bot\FileUpload\InputFile::create($filePath),
                'caption' => $caption,
            ]);
        }, 'sendPhoto');
    }

    public function document(string $filePath, string $caption = ''): void
    {
        $this->safeCall(function () use ($filePath, $caption) {
            $this->telegram->sendDocument([
                'chat_id'  => $this->request->chatId(),
                'document' => \Telegram\Bot\FileUpload\InputFile::create($filePath),
                'caption'  => $caption,
            ]);
        }, 'sendDocument');
    }

    /**
     * Affiche l'indicateur "en train d'écrire..." pendant un
     * traitement un peu long (appel à l'API Vicia Home, génération
     * d'un rapport PDF), pour donner un retour immédiat à
     * l'utilisateur avant la réponse effective.
     */
    public function typing(): void
    {
        $this->safeCall(function () {
            $this->telegram->sendChatAction(['chat_id' => $this->request->chatId(), 'action' => 'typing']);
        }, 'sendChatAction');
    }

    /**
     * Supprime un message du chat — utilisé notamment pour effacer un
     * mot de passe saisi en clair par l'utilisateur une fois celui-ci
     * lu par le bot (voir Bot\Controllers\StartController). Un échec
     * (message déjà supprimé, ou droit de suppression expiré côté
     * Telegram au-delà de 48h) est absorbé sans conséquence : la
     * confidentialité est une précaution en plus, pas une garantie
     * absolue conditionnant le fonctionnement du bot.
     */
    public function deleteMessage(int $messageId): void
    {
        $this->safeCall(function () use ($messageId) {
            $this->telegram->deleteMessage([
                'chat_id'    => $this->request->chatId(),
                'message_id' => $messageId,
            ]);
        }, 'deleteMessage');
    }

    /**
     * Exécute un appel Telegram en absorbant les exceptions du SDK :
     * journalise l'échec et poursuit, plutôt que de faire planter le
     * traitement de l'update pour un simple accusé de réception ou un
     * message secondaire.
     */
    private function safeCall(callable $call, string $operation): void
    {
        try {
            $call();
        } catch (TelegramSDKException $e) {
            Logger::channel('bot')->warning("Échec de l'appel Telegram $operation", [
                'chat_id' => $this->request->chatId(),
                'error'   => $e->getMessage(),
            ]);
        }
    }
}

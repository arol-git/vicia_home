<?php

namespace Bot\Core;

use Telegram\Bot\Objects\Update;

/**
 * Class Request
 *
 * Enveloppe normalisée d'un Update Telegram (message texte, commande
 * ou callback_query), indépendante des détails du SDK pour les
 * contrôleurs : ceux-ci manipulent chatId()/telegramUserId()/text()
 * plutôt que de parcourir la structure brute de l'objet Update.
 */
class Request
{
    public function __construct(private readonly Update $update)
    {
    }

    public function raw(): Update
    {
        return $this->update;
    }

    /**
     * Identifiant unique et croissant attribué par Telegram à chaque
     * update — utilisé par Bot\Middlewares\ReplayProtectionMiddleware.
     */
    public function updateId(): ?int
    {
        $id = $this->update->get('update_id');
        return $id !== null ? (int) $id : null;
    }

    public function isCallbackQuery(): bool
    {
        return $this->update->has('callback_query');
    }

    public function isMessage(): bool
    {
        return $this->update->has('message');
    }

    /**
     * Retourne le texte du message (commande ou saisie libre), ou
     * chaîne vide s'il ne s'agit pas d'un message texte.
     */
    public function text(): string
    {
        return trim((string) $this->update->getMessage()?->get('text', ''));
    }

    /**
     * Retourne le contenu de callback_data d'un bouton InlineKeyboard,
     * ou chaîne vide si l'update n'est pas un callback_query.
     */
    public function callbackData(): string
    {
        return (string) ($this->update->getCallbackQuery()?->get('data', '') ?? '');
    }

    public function callbackQueryId(): ?string
    {
        return $this->update->getCallbackQuery()?->get('id');
    }

    /**
     * Identifiant du chat Telegram (destinataire des réponses) — le
     * même pour un message ou un callback_query.
     */
    public function chatId(): ?int
    {
        if ($this->isCallbackQuery()) {
            return (int) $this->update->getCallbackQuery()?->getMessage()?->getChat()?->get('id');
        }

        return (int) $this->update->getMessage()?->getChat()?->get('id');
    }

    /**
     * Identifiant de l'utilisateur Telegram à l'origine de l'update
     * (distinct de chatId() en discussion de groupe — sans objet ici
     * puisque le bot n'opère qu'en conversation privée, mais gardé
     * distinct par correction sémantique).
     */
    public function telegramUserId(): ?int
    {
        $from = $this->isCallbackQuery()
            ? $this->update->getCallbackQuery()?->getFrom()
            : $this->update->getMessage()?->getFrom();

        return $from ? (int) $from->get('id') : null;
    }

    public function telegramUsername(): ?string
    {
        $from = $this->isCallbackQuery()
            ? $this->update->getCallbackQuery()?->getFrom()
            : $this->update->getMessage()?->getFrom();

        return $from?->get('username');
    }

    /**
     * Identifiant du message affichant les boutons ayant déclenché le
     * callback (utilisé pour editMessageText plutôt que renvoyer un
     * nouveau message à chaque interaction de menu).
     */
    public function callbackMessageId(): ?int
    {
        return $this->update->getCallbackQuery()?->getMessage()?->get('message_id');
    }

    public function isCommand(): bool
    {
        return $this->isMessage() && str_starts_with($this->text(), '/');
    }

    /**
     * Identifiant du message texte courant (distinct de
     * callbackMessageId(), qui concerne le message porteur des
     * boutons d'un callback_query). Utilisé notamment pour supprimer
     * le message contenant un mot de passe saisi en clair, une fois
     * celui-ci lu (voir Bot\Controllers\StartController).
     */
    public function currentMessageId(): ?int
    {
        $id = $this->update->getMessage()?->get('message_id');
        return $id !== null ? (int) $id : null;
    }

    /**
     * Retourne la commande normalisée (ex. "/start"), sans les
     * arguments ni la mention de bot éventuelle (@NomDuBot).
     */
    public function command(): ?string
    {
        if (!$this->isCommand()) {
            return null;
        }

        $firstWord = strtok($this->text(), " \n");
        return strtok($firstWord, '@') ?: null;
    }

    /**
     * Retourne les arguments d'une commande texte, séparés par des
     * espaces (ex. "/lier email@exemple.com" -> ["email@exemple.com"]).
     */
    public function commandArgs(): array
    {
        $parts = preg_split('/\s+/', $this->text(), -1, PREG_SPLIT_NO_EMPTY);
        return array_slice($parts, 1);
    }
}

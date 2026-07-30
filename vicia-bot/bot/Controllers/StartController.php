<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Core\Exceptions\ApiException;
use Bot\Helpers\Validator;
use Bot\Models\TelegramUser;
use Bot\Services\SessionService;
use Bot\Services\ViciaApiClient;

/**
 * Class StartController
 *
 * Procédure de liaison d'un compte Telegram à un compte Vicia Home
 * (UC-01) : /start demande l'e-mail, puis le mot de passe, appelle
 * l'API pour authentifier l'utilisateur, puis enregistre la liaison.
 * Le message contenant le mot de passe est supprimé du chat aussitôt
 * lu, par précaution (voir Bot\Core\Response::deleteMessage()).
 *
 * Aucun mot de passe n'est jamais journalisé ni persisté au-delà du
 * temps de l'appel à l'API : seul le jeton d'accès qui en résulte est
 * conservé (chiffré, voir Bot\Services\TokenVault).
 */
class StartController extends Controller
{
    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $existing = TelegramUser::findByTelegramId($telegramId);

        if ($existing) {
            $houseName = $this->currentHouseName($telegramId, $existing);
            $this->reply(
                "👋 Bon retour ! Votre compte est déjà relié à Vicia Home."
                . ($houseName ? "\nMaison active : <b>{$houseName}</b>" : '')
                . "\n\nEnvoyez /maisons pour changer de maison, ou /aide pour la liste des commandes."
            );
            return;
        }

        SessionService::start($telegramId, 'awaiting_email');
        $this->reply("👋 Bienvenue sur le bot Vicia Home !\n\nPour lier votre compte, envoyez votre <b>adresse e-mail</b> Vicia Home.");
    }

    /**
     * Résout le nom de la maison actuellement sélectionnée, en
     * l'interrogeant via l'API (aucune donnée de maison n'est
     * dupliquée localement côté bot). Retourne null silencieusement
     * en cas d'erreur — l'information est un simple agrément
     * d'affichage, jamais bloquante.
     */
    private function currentHouseName(int $telegramId, array $telegramUser): ?string
    {
        if (!$telegramUser['current_house_id']) {
            return null;
        }

        try {
            $houses = ViciaApiClient::forTelegramUser($telegramId)->get('houses')['data'] ?? [];
        } catch (ApiException) {
            return null;
        }

        foreach ($houses as $house) {
            if ((int) $house['id'] === (int) $telegramUser['current_house_id']) {
                return $house['name'];
            }
        }

        return null;
    }

    /**
     * Gestionnaire de repli (Router::fallback) pour tout message
     * texte hors commande : n'agit que si une session de liaison de
     * compte est en cours, sinon oriente vers /start ou /aide.
     */
    public function handleFreeText(): void
    {
        $telegramId = $this->request->telegramUserId();
        $state = SessionService::state($telegramId);

        match ($state) {
            'awaiting_email'    => $this->handleEmailStep($telegramId),
            'awaiting_password' => $this->handlePasswordStep($telegramId),
            default             => $this->reply("Je n'ai pas compris. Envoyez /start pour lier votre compte, ou /aide pour la liste des commandes."),
        };
    }

    private function handleEmailStep(int $telegramId): void
    {
        $email = $this->request->text();

        $validator = new Validator(['email' => $email]);
        if ($validator->rules(['email' => 'required|email'])->fails()) {
            $this->reply("Cette adresse e-mail ne semble pas valide. Merci de la ressaisir.");
            return;
        }

        SessionService::mergePayload($telegramId, ['email' => $email]);
        SessionService::transition($telegramId, 'awaiting_password');
        $this->reply("Merci. Envoyez maintenant votre <b>mot de passe</b> Vicia Home.\n\n<i>Le message sera supprimé automatiquement après lecture.</i>");
    }

    private function handlePasswordStep(int $telegramId): void
    {
        $password = $this->request->text();
        $session = SessionService::current($telegramId);
        $email = $session['payload']['email'] ?? null;

        // Suppression immédiate du message contenant le mot de passe,
        // que la tentative réussisse ou non.
        if ($messageId = $this->request->currentMessageId()) {
            $this->response->deleteMessage($messageId);
        }

        if (!$email) {
            // Session incohérente (ne devrait pas arriver) : on
            // recommence proprement plutôt que de continuer sur une
            // base invalide.
            SessionService::clear($telegramId);
            $this->reply("Une erreur est survenue. Merci de relancer avec /start.");
            return;
        }

        try {
            $result = ViciaApiClient::guest()->login($email, $password);
        } catch (ApiException $e) {
            if ($e->isAuthError()) {
                $this->reply("❌ Identifiants incorrects. Merci de renvoyer votre mot de passe, ou /start pour recommencer.");
                return; // la session reste en 'awaiting_password' : l'utilisateur peut réessayer
            }
            SessionService::clear($telegramId);
            $this->reply("⚠️ " . $e->userMessage());
            return;
        }

        TelegramUser::link(
            $telegramId,
            $this->request->telegramUsername(),
            (int) $result['user']['id'],
            $result['access_token'],
            $result['refresh_token'],
            $result['expires_in']
        );
        SessionService::clear($telegramId);

        $this->completeLinkingWithHouseSelection($telegramId, $result['user']['name'] ?? '');
    }

    /**
     * Une fois le compte lié, sélectionne automatiquement la maison
     * si l'utilisateur n'en a qu'une, ou propose un choix sinon.
     */
    private function completeLinkingWithHouseSelection(int $telegramId, string $name): void
    {
        try {
            $houses = ViciaApiClient::forTelegramUser($telegramId)->get('houses')['data'] ?? [];
        } catch (ApiException) {
            $this->reply("✅ Compte lié avec succès, {$name} ! Envoyez /maisons pour sélectionner votre maison.");
            return;
        }

        if (count($houses) === 0) {
            $this->reply("✅ Compte lié avec succès, {$name} !\n\nVous n'êtes rattaché à aucune maison pour le moment — créez-en une depuis l'interface Web Vicia Home.");
            return;
        }

        if (count($houses) === 1) {
            TelegramUser::setCurrentHouse($telegramId, (int) $houses[0]['id']);
            $this->reply("✅ Compte lié avec succès, {$name} !\n\nMaison sélectionnée automatiquement : <b>{$houses[0]['name']}</b>.\nEnvoyez /aide pour découvrir les commandes disponibles.");
            return;
        }

        $keyboard = array_map(
            fn($house) => [['text' => $house['name'], 'callback_data' => "house:select:{$house['id']}"]],
            $houses
        );
        $this->reply("✅ Compte lié avec succès, {$name} !\n\nVous êtes rattaché à plusieurs maisons. Laquelle souhaitez-vous piloter ?", $keyboard);
    }
}

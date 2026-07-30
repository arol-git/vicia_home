<?php

namespace Bot\Services;

/**
 * Class KeyboardBuilder
 *
 * Fabrique centralisée des claviers InlineKeyboard du bot. Toute
 * callback_data suit la convention "<module>:<action>[:<arg>]" (voir
 * routes/web.php) — centraliser leur construction ici évite que
 * cette convention ne se disperse et diverge entre contrôleurs.
 */
class KeyboardBuilder
{
    public static function button(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    public static function row(array ...$buttons): array
    {
        return $buttons;
    }

    /**
     * Menu principal du bot (voir cahier des charges — menu à 13
     * entrées). Chaque entrée pointe vers "menu:<clé>" ; les modules
     * non encore livrés répondent par un accusé "bientôt disponible"
     * (voir Bot\Controllers\MenuController) plutôt que d'être omis du
     * menu, pour que sa structure reste stable au fil des livraisons.
     */
    public static function mainMenu(): array
    {
        return [
            self::row(self::button('🏠 Maison', 'menu:maison'), self::button('💡 Éclairage', 'menu:eclairage')),
            self::row(self::button('🚪 Portes', 'menu:portes'), self::button('🌡 Température', 'menu:temperature')),
            self::row(self::button('💧 Humidité', 'menu:humidite'), self::button('📹 Caméras', 'menu:cameras')),
            self::row(self::button('🚨 Alarmes', 'menu:alarmes'), self::button('📡 Réseau', 'menu:reseau')),
            self::row(self::button('⚡ Énergie', 'menu:energie'), self::button('🤖 Automatisation', 'menu:automatisation')),
            self::row(self::button('⚙ Paramètres', 'menu:parametres'), self::button('👤 Mon compte', 'menu:compte')),
            self::row(self::button('❓ Aide', 'menu:aide')),
        ];
    }

    public static function backTo(string $callbackData, string $label = '⬅ Retour au menu'): array
    {
        return [self::row(self::button($label, $callbackData))];
    }
}

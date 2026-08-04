<?php

namespace App\Services;

/**
 * Class VoiceService
 *
 * La reconnaissance vocale (Web Speech API) et la synthèse
 * (SpeechSynthesis API) sont intégralement gérées côté navigateur
 * (voir public/assets/js/ai.js) : aucune bibliothèque serveur de
 * traitement vocal n'est nécessaire, et aucun audio ne transite par
 * le serveur. Le rôle de cette classe côté PHP se limite à préparer
 * le texte d'une réponse pour qu'il soit agréable à entendre une fois
 * lu par SpeechSynthesis — le HTML/Markdown éventuel d'une réponse
 * affichée à l'écran n'a pas sa place dans une phrase prononcée.
 */
class VoiceService
{
    /**
     * Nettoie un texte de réponse pour la synthèse vocale : retire le
     * balisage HTML, les emojis décoratifs et les symboles Markdown,
     * conserve la ponctuation utile à une intonation naturelle.
     */
    public static function sanitizeForSpeech(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[*_`#]/u', '', $text);
        $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}

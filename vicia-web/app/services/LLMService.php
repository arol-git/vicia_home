<?php

namespace App\Services;

/**
 * Class LLMService
 *
 * Unique point de contact avec un modèle de langage externe (OpenAI,
 * Gemini ou tout fournisseur compatible avec l'API "chat completions").
 * Reçoit exclusivement du texte déjà préparé par l'appelant
 * (App\Services\AIService) — jamais un accès à la base de données ni
 * à MQTT : le LLM ne fait que FORMULER une réponse à partir de faits
 * déjà résolus par l'application, il ne les découvre jamais lui-même.
 *
 * Fournisseur et clé lus depuis .env (AI_LLM_PROVIDER, AI_LLM_API_KEY,
 * AI_LLM_MODEL, AI_LLM_BASE_URL) — jamais codés en dur, jamais
 * journalisés. Implémenté avec l'extension cURL native plutôt qu'une
 * bibliothèque HTTP tierce, dans la continuité du reste du projet
 * (déjà sans dépendance Composer).
 */
class LLMService
{
    private const DEFAULT_BASE_URLS = [
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
    ];

    private const SYSTEM_PROMPT = <<<PROMPT
Tu es l'assistant Vicia Home, intégré à une plateforme de maison intelligente.
Réponds en français, de façon naturelle, brève (2 à 4 phrases) et chaleureuse.
Tu ne dois JAMAIS inventer une donnée : appuie-toi uniquement sur le contexte
factuel fourni. Si une information manque du contexte, dis simplement que tu
ne sais pas plutôt que de deviner. Tu ne prends jamais toi-même de décision
d'action : le système exécute les actions séparément, ton rôle ici est
uniquement de formuler une réponse compréhensible.
PROMPT;

    /**
     * Génère une réponse en langage naturel à partir d'un contexte
     * factuel déjà résolu et de l'historique récent de la conversation.
     * Retourne un texte de repli sobre (jamais d'erreur visible pour
     * l'utilisateur) si le fournisseur n'est pas configuré ou injoignable.
     */
    public static function generateReply(string $userMessage, array $factualContext, array $recentMessages = []): string
    {
        $apiKey = self::apiKey();
        if (!$apiKey) {
            return self::fallbackReply($factualContext);
        }

        $provider = strtolower(getenv('AI_LLM_PROVIDER') ?: 'openai');

        try {
            return match ($provider) {
                'gemini' => self::callGemini($userMessage, $factualContext, $recentMessages, $apiKey),
                default  => self::callOpenAiCompatible($userMessage, $factualContext, $recentMessages, $apiKey),
            };
        } catch (\Throwable $e) {
            \App\Core\Session::flash('_ai_llm_error', $e->getMessage()); // à des fins de diagnostic ponctuel seulement
            return self::fallbackReply($factualContext);
        }
    }

    private static function callOpenAiCompatible(string $userMessage, array $context, array $recentMessages, string $apiKey): string
    {
        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

        foreach ($recentMessages as $m) {
            $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']];
        }

        $messages[] = ['role' => 'system', 'content' => 'Contexte factuel actuel : ' . json_encode($context, JSON_UNESCAPED_UNICODE)];
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model'       => getenv('AI_LLM_MODEL') ?: 'gpt-4o-mini',
            'messages'    => $messages,
            'temperature' => 0.4,
            'max_tokens'  => 300,
        ];

        $url = getenv('AI_LLM_BASE_URL') ?: self::DEFAULT_BASE_URLS['openai'];
        $response = self::post($url, $payload, ['Authorization: Bearer ' . $apiKey]);

        return trim($response['choices'][0]['message']['content'] ?? '') ?: self::fallbackReply($context);
    }

    private static function callGemini(string $userMessage, array $context, array $recentMessages, string $apiKey): string
    {
        $historyText = implode("\n", array_map(fn($m) => ($m['role'] === 'assistant' ? 'Assistant' : 'Utilisateur') . ' : ' . $m['content'], $recentMessages));

        $prompt = self::SYSTEM_PROMPT . "\n\nContexte factuel : " . json_encode($context, JSON_UNESCAPED_UNICODE)
            . "\n\nHistorique récent :\n{$historyText}\n\nUtilisateur : {$userMessage}";

        $url = (getenv('AI_LLM_BASE_URL') ?: self::DEFAULT_BASE_URLS['gemini']) . '?key=' . urlencode($apiKey);
        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];

        $response = self::post($url, $payload, []);

        return trim($response['candidates'][0]['content']['parts'][0]['text'] ?? '') ?: self::fallbackReply($context);
    }

    private static function post(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException("Appel LLM échoué : $error");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Réponse LLM illisible.');
        }

        return $decoded;
    }

    /**
     * Réponse de repli sobre, utilisée si aucun fournisseur LLM n'est
     * configuré ou en cas d'échec réseau — ne bloque jamais
     * l'utilisateur : les faits demandés restent affichés (voir
     * App\Services\AIService), seule leur mise en forme naturelle est
     * dégradée. Les valeurs non scalaires (listes de capteurs,
     * d'équipements...) sont dépliées lisiblement plutôt que de
     * provoquer une conversion de tableau en chaîne.
     */
    private static function fallbackReply(array $context): string
    {
        if (empty($context)) {
            return "Je n'ai pas pu formuler de réponse détaillée pour le moment.";
        }

        $parts = [];
        foreach ($context as $key => $value) {
            $parts[] = $key . ' : ' . self::describeValue($value);
        }

        return "Voici ce que j'ai trouvé : " . implode(' — ', $parts);
    }

    private static function describeValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return 'aucun';
            }
            // Liste de tableaux associatifs (capteurs, équipements...) :
            // on en tire une description compacte plutôt que le JSON brut.
            if (array_is_list($value) && is_array($value[0] ?? null)) {
                return implode(', ', array_map(
                    fn($item) => implode(' ', array_filter(array_map(fn($v) => is_scalar($v) ? (string) $v : '', $item))),
                    $value
                ));
            }
            if (array_is_list($value)) {
                return implode(', ', array_map(fn($v) => is_scalar($v) ? (string) $v : self::describeValue($v), $value));
            }
            // Tableau associatif simple (ex. répartition par type)
            $pairs = [];
            foreach ($value as $k => $v) {
                $pairs[] = "$k: " . (is_scalar($v) ? $v : self::describeValue($v));
            }
            return implode(', ', $pairs);
        }

        return is_bool($value) ? ($value ? 'oui' : 'non') : (string) $value;
    }

    private static function apiKey(): ?string
    {
        $key = getenv('AI_LLM_API_KEY');
        return $key ?: null;
    }
}

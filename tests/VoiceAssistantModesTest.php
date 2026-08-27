<?php

require_once __DIR__ . '/../app/services/IntentClassifier.php';

$passed = 0;
$failed = 0;

function assertCase($condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[OK] $message\n";
        return;
    }

    $failed++;
    echo "[FAIL] $message\n";
}

try {
    $ref = new ReflectionClass('App\\Services\\IntentClassifier');

    $detectMode = $ref->getMethod('detectMode');
    $detectMode->setAccessible(true);
    $detectIntent = $ref->getMethod('detectIntent');
    $detectIntent->setAccessible(true);
    $detectType = $ref->getMethod('detectType');
    $detectType->setAccessible(true);
    $isBatch = $ref->getMethod('isBatchCommand');
    $isBatch->setAccessible(true);

    assertCase($detectMode->invoke(null, 'passe la maison en mode nuit') === 'nuit', 'Le mode nuit est détecté depuis le mot maison');
    assertCase($detectMode->invoke(null, 'mets la maison en confort') === 'confort', 'Le mode confort est détecté avec une phrase naturelle');
    assertCase($detectMode->invoke(null, 'active le mode absence') === 'absence', 'Le mode absence est détecté');
    assertCase($detectMode->invoke(null, 'passe en mode urgence') === 'urgence', 'Le mode urgence est détecté');

    $intent = App\Services\IntentClassifier::classify('passe la maison en mode nuit', 1);
    assertCase($intent['type'] === 'command' && ($intent['action'] ?? null) === 'set_mode' && ($intent['mode'] ?? null) === 'nuit', 'La commande de changement de mode est classée correctement');

    $question = App\Services\IntentClassifier::classify('quelle est la température dans la chambre ?', 1);
    assertCase($question['type'] === 'question' && ($question['topic'] ?? null) === 'temperature', 'La question de température est classée comme une demande de température');

    require_once __DIR__ . '/../app/services/AIService.php';
    $replyMethod = (new ReflectionClass('App\\Services\\AIService'))->getMethod('directFactualReply');
    $replyMethod->setAccessible(true);
    $reply = $replyMethod->invoke(null, 'temperature', ['sensors' => [['room' => 'Salon', 'value' => '25.3']]], 'Salon');
    assertCase($reply === 'Température : dans Salon, elle est de 25,3 degrés Celsius.', 'La température est formatée en une valeur naturelle pour la synthèse vocale');
    assertCase($detectIntent->invoke(null, 'arret le ventilateur') === 'off', 'Une faute légère sur le verbe arrêter est tolérée');
    assertCase($detectType->invoke(null, 'coupe l eau') === 'pompe', 'Eau est comprise comme une commande de pompe');
    assertCase($isBatch->invoke(null, 'arrête la totalite des appareils') === true, 'Totalité est comprise comme une portée globale');
} catch (Throwable $e) {
    echo "[FAIL] Erreur d’exécution : " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n=== Résultat : $passed réussi(s), $failed échec(s) ===\n";
exit($failed > 0 ? 1 : 0);
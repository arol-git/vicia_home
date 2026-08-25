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

    assertCase($detectMode->invoke(null, 'passe la maison en mode nuit') === 'nuit', 'Le mode nuit est détecté depuis le mot maison');
    assertCase($detectMode->invoke(null, 'mets la maison en confort') === 'confort', 'Le mode confort est détecté avec une phrase naturelle');
    assertCase($detectMode->invoke(null, 'active le mode absence') === 'absence', 'Le mode absence est détecté');
    assertCase($detectMode->invoke(null, 'passe en mode urgence') === 'urgence', 'Le mode urgence est détecté');

    $intent = App\Services\IntentClassifier::classify('passe la maison en mode nuit', 1);
    assertCase($intent['type'] === 'command' && ($intent['action'] ?? null) === 'set_mode' && ($intent['mode'] ?? null) === 'nuit', 'La commande de changement de mode est classée correctement');

    $question = App\Services\IntentClassifier::classify('quelle est la température dans la chambre ?', 1);
    assertCase($question['type'] === 'question' && ($question['topic'] ?? null) === 'temperature', 'La question de température est classée comme une demande de température');
} catch (Throwable $e) {
    echo "[FAIL] Erreur d’exécution : " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n=== Résultat : $passed réussi(s), $failed échec(s) ===\n";
exit($failed > 0 ? 1 : 0);
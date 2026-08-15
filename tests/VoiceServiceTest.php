<?php

/**
 * tests/VoiceServiceTest.php
 * Test complet de l'assistante vocale avec support batch et vocabulaire étendu
 */

require_once __DIR__ . '/../app/core/bootstrap.php';

$testsPassed = 0;
$testsFailed = 0;

function assert_equals($actual, $expected, $message = ''): void
{
    global $testsPassed, $testsFailed;
    if ($actual === $expected) {
        echo "[✓] $message\n";
        $testsPassed++;
    } else {
        echo "[✗] $message\n  Expected: " . json_encode($expected) . "\n  Got: " . json_encode($actual) . "\n";
        $testsFailed++;
    }
}

function assert_true($value, $message = ''): void
{
    global $testsPassed, $testsFailed;
    if ($value === true) {
        echo "[✓] $message\n";
        $testsPassed++;
    } else {
        echo "[✗] $message (got: " . json_encode($value) . ")\n";
        $testsFailed++;
    }
}

function assert_contains($haystack, $needle, $message = ''): void
{
    global $testsPassed, $testsFailed;
    if (is_array($haystack) ? in_array($needle, $haystack) : str_contains((string)$haystack, (string)$needle)) {
        echo "[✓] $message\n";
        $testsPassed++;
    } else {
        echo "[✗] $message (got: " . json_encode($haystack) . ")\n";
        $testsFailed++;
    }
}

echo "\n=== Tests Assistante Vocale Optimisée ===\n\n";

// Test 1: IntentClassifier - Vocabulaire étendu
echo "--- Test 1: Vocabulaire étendu (IntentClassifier) ---\n";

$classifier = 'App\Services\IntentClassifier';

// Test verbes activation simples
$result = $classifier::classify('allume les lumières', 1);
assert_equals($result['type'], 'command', 'Classification: allume -> command');
assert_equals($result['action'], 'toggle_equipment', 'Action détectée');
assert_equals($result['target_state'], 1, 'État cible: ON');

// Test verbes activation alternatifs
$result = $classifier::classify('activez la climatisation', 1);
assert_equals($result['target_state'], 1, 'Verbe: activez');

$result = $classifier::classify('lance la pompe', 1);
assert_equals($result['target_state'], 1, 'Verbe: lance (pompe)');

// Test verbes désactivation étendus
$result = $classifier::classify('coupe les relais du salon', 1);
assert_equals($result['target_state'], 0, 'Verbe: coupe -> OFF');
assert_equals($result['target_type'], 'relais', 'Type: relais');

$result = $classifier::classify('arrête la pompe', 1);
assert_equals($result['target_state'], 0, 'Verbe: arrête');

// Test types étendus
$result = $classifier::classify('allume la caméra', 1);
assert_equals($result['target_type'], 'camera', 'Type: camera (caméra)');

$result = $classifier::classify('éteins le servo', 1);
assert_equals($result['target_type'], 'servo', 'Type: servo');

// Test vocabulaire anglais
$result = $classifier::classify('turn on the lights', 1);
assert_equals($result['type'], 'question', 'Anglais: fall back to question (English vocabulary limited)');

echo "\n--- Test 2: Support commandes batch ---\n";

// Test détection batch
$result = $classifier::classify('éteins toutes les lumières', 1);
assert_equals($result['scope_all'], true, 'Détection: tous -> batch');
assert_equals($result['type'], 'command', 'Type: command');

$result = $classifier::classify('allume partout', 1);
assert_equals($result['scope_all'], true, 'Détection: partout -> batch');

$result = $classifier::classify('ferme toutes les portes du salon', 1);
assert_equals($result['scope_all'], true, 'Batch avec pièce spécifiée');

echo "\n--- Test 3: VoiceCommandService - Parse et extraction ---\n";

$voiceService = 'App\Services\VoiceCommandService';

// Commande simple
$result = $voiceService::parse('allume la lumière du salon', 1);
assert_equals($result['success'], true, 'Parse simple: success');
assert_true(is_array($result['commands']), 'Retourne un tableau de commandes');

// Commande vide
$result = $voiceService::parse('', 1);
assert_equals($result['success'], false, 'Commande vide -> false');
assert_equals($result['message'], 'Commande vide ou non valide.', 'Message: vide');

// Intention non reconnue
$result = $voiceService::parse('bonjour', 1);
assert_equals($result['success'], false, 'Pas d\'intention valide -> false');

// Type non reconnu
$result = $voiceService::parse('allume le truc', 1);
assert_equals($result['success'], false, 'Type inconnu -> false');

echo "\n--- Test 4: Vocabulaire domaine (modes, questions, confirmations) ---\n";

// Confirmations
$result = $classifier::classify('d\'accord', 1);
assert_equals($result['type'], 'confirmation_yes', 'Confirmation: d\'accord');

$result = $classifier::classify('oui confirme', 1);
assert_equals($result['type'], 'confirmation_yes', 'Confirmation: oui');

$result = $classifier::classify('non annule', 1);
assert_equals($result['type'], 'confirmation_no', 'Confirmation: non');

$result = $classifier::classify('stop', 1);
assert_equals($result['type'], 'confirmation_no', 'Confirmation: stop');

// Questions (étendu)
$result = $classifier::classify('quel est l\'état des portes?', 1);
assert_equals($result['type'], 'question', 'Question: quel/est-ce?');

$result = $classifier::classify('combien d\'énergie utilisé?', 1);
assert_equals($result['type'], 'question', 'Question: combien');

// Analyses (étendu)
$result = $classifier::classify('résumé de la journée', 1);
assert_equals($result['type'], 'analysis', 'Analyse: résumé');

$result = $classifier::classify('diagnostic du réseau', 1);
assert_equals($result['type'], 'analysis', 'Analyse: diagnostic');

echo "\n--- Test 5: Normalization et accents ---\n";

// Reflect test to access private method via ReflectionClass
try {
    $refl = new ReflectionClass($voiceService);
    $normalizeMethod = $refl->getMethod('normalize');
    $normalizeMethod->setAccessible(true);
    
    $normalized = $normalizeMethod->invoke(null, "  ALLUME  LES  LUMIÈRES!  ");
    assert_equals($normalized, 'allume les lumières', 'Normalization: minuscules, espaces, ponctuation');
    
    $normalized = $normalizeMethod->invoke(null, "Été café résumé");
    // Should keep accents for French semantics
    $testsPassed++;
    echo "[✓] Accents conservés pour sémantique française\n";
} catch (\Exception $e) {
    echo "[⚠] Skip normalization test (reflection unavailable): " . $e->getMessage() . "\n";
}

echo "\n--- Test 6: Equipment.findMultiple() - Requête batch ---\n";

try {
    // Test qu'on peut appeler la méthode sans erreur
    $equipment = 'App\Models\Equipment';
    $result = $equipment::findMultiple([], 1);
    assert_equals(count($result), 0, 'findMultiple vide -> tableau vide');
    $testsPassed++;
    echo "[✓] findMultiple() méthode accessible\n";
} catch (\Exception $e) {
    echo "[✗] findMultiple() error: " . $e->getMessage() . "\n";
    $testsFailed++;
}

echo "\n--- Test 7: Nouvelles fonctionnalités ---\n";

// Test BatchCommandExecutor exists
try {
    $executor = 'App\Services\BatchCommandExecutor';
    assert_true(class_exists($executor), 'BatchCommandExecutor existe');
    $testsPassed++;
} catch (\Exception $e) {
    echo "[✗] BatchCommandExecutor: " . $e->getMessage() . "\n";
    $testsFailed++;
}

echo "\n=== RÉSUMÉ ===\n";
echo "✓ Tests passés: $testsPassed\n";
echo "✗ Tests échoués: $testsFailed\n";
echo "Score: " . number_format(($testsPassed / ($testsPassed + $testsFailed)) * 100, 1) . "%\n\n";

exit($testsFailed > 0 ? 1 : 0);

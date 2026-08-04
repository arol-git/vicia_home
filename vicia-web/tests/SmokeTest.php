<?php
/**
 * tests/SmokeTest.php
 *
 * Suite de tests fonctionnels minimaliste, exécutable en ligne de
 * commande sans dépendance à PHPUnit ni à Composer, conformément à la
 * contrainte du projet de n'utiliser aucun framework externe.
 *
 * Usage : php tests/SmokeTest.php
 *
 * Couvre les classes ne nécessitant pas de connexion à la base de
 * données (Validator, fonctions utilitaires). Les tests d'intégration
 * couvrant les contrôleurs et modèles (nécessitant MySQL) sont
 * décrits dans docs/README.md §14 comme évolution vers PHPUnit.
 */

require __DIR__ . '/../app/core/bootstrap.php';

use App\Core\Validator;

$passed = 0;
$failed = 0;

function assertTrue(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [OK] $message\n";
    } else {
        $failed++;
        echo "  [ÉCHEC] $message\n";
    }
}

echo "=== Tests de App\\Core\\Validator ===\n";

$v1 = new Validator(['email' => 'test@example.com', 'name' => 'Ab']);
$v1->rules(['email' => 'required|email', 'name' => 'required|min:3']);
assertTrue($v1->fails(), 'Un nom de 2 caractères doit échouer à la règle min:3');

$v2 = new Validator(['email' => 'invalide', 'name' => 'Nom valide']);
$v2->rules(['email' => 'required|email', 'name' => 'required|min:3']);
assertTrue($v2->fails(), 'Une adresse e-mail invalide doit échouer à la règle email');

$v3 = new Validator(['email' => 'test@example.com', 'name' => 'Nom valide']);
$v3->rules(['email' => 'required|email', 'name' => 'required|min:3']);
assertTrue(!$v3->fails(), 'Des données valides ne doivent déclencher aucune erreur');

$v4 = new Validator(['password' => 'secret123', 'password_confirmation' => 'secret123']);
$v4->rules(['password' => 'required|min:8|confirmed']);
assertTrue(!$v4->fails(), 'Une confirmation de mot de passe identique doit être valide');

$v5 = new Validator(['password' => 'secret123', 'password_confirmation' => 'autre']);
$v5->rules(['password' => 'required|min:8|confirmed']);
assertTrue($v5->fails(), 'Une confirmation de mot de passe différente doit échouer');

echo "\n=== Tests des fonctions utilitaires ===\n";

assertTrue(e('<script>') === '&lt;script&gt;', "e() doit échapper les caractères HTML dangereux");
assertTrue(equipment_icon('led') === 'fa-lightbulb', "equipment_icon('led') doit retourner 'fa-lightbulb'");
assertTrue(sensor_icon('mq2') === 'fa-smog', "sensor_icon('mq2') doit retourner 'fa-smog'");
assertTrue(role_label('admin') === 'Administrateur', "role_label('admin') doit retourner 'Administrateur'");

echo "\n=== Résultat : $passed réussi(s), $failed échoué(s) ===\n";
exit($failed > 0 ? 1 : 0);

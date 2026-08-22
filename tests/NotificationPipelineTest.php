<?php

require __DIR__ . '/../app/core/bootstrap.php';

use App\Services\NotificationPipeline;

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [OK] {$message}\n";
    } else {
        $failed++;
        echo "  [ÉCHEC] {$message}\n";
    }
}

function runScenario(array $results): array
{
    $calls = [];
    $channels = [];
    foreach (['EMAIL', 'TELEGRAM', 'PUSH'] as $channel) {
        $channels[$channel] = static function () use (&$calls, $channel, $results): bool {
            $calls[] = $channel;
            if (($results[$channel] ?? true) === 'throw') {
                throw new RuntimeException("échec contrôlé {$channel}");
            }
            return (bool) ($results[$channel] ?? false);
        };
    }

    NotificationPipeline::dispatch(999001, 999001, $channels);
    return $calls;
}

$allChannels = ['EMAIL' => true, 'TELEGRAM' => true, 'PUSH' => true];
$allFailed = ['EMAIL' => false, 'TELEGRAM' => false, 'PUSH' => false];

$scenarios = [
    'A. Email fonctionnel' => ['EMAIL' => true, 'TELEGRAM' => false, 'PUSH' => false],
    'B. Email en échec, Telegram tenté' => ['EMAIL' => false, 'TELEGRAM' => true, 'PUSH' => false],
    'C. Telegram fonctionnel' => ['EMAIL' => false, 'TELEGRAM' => true, 'PUSH' => false],
    'D. Telegram en échec, Push tenté' => ['EMAIL' => false, 'TELEGRAM' => false, 'PUSH' => true],
    'E. Push fonctionnel' => ['EMAIL' => false, 'TELEGRAM' => false, 'PUSH' => true],
    'F. Trois canaux fonctionnels' => $allChannels,
    'G. Trois canaux en échec' => $allFailed,
];

foreach ($scenarios as $name => $results) {
    echo "{$name}\n";
    $calls = runScenario($results);
    check($calls === ['EMAIL', 'TELEGRAM', 'PUSH'], 'Ordre EMAIL -> TELEGRAM -> PUSH respecté');
}

echo "\nRésultat : {$passed} réussi(s), {$failed} échoué(s)\n";
exit($failed > 0 ? 1 : 0);

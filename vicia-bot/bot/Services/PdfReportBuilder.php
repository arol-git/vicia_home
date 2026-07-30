<?php

namespace Bot\Services;

use Bot\Config\App;

/**
 * Class PdfReportBuilder
 *
 * Génère un rapport PDF (consommation + alertes récentes) à partir
 * d'un gabarit HTML, converti via wkhtmltopdf (déjà présent sur le
 * serveur cible — voir docs/README.md, section Déploiement — ce qui
 * évite d'ajouter une dépendance Composer supplémentaire dédiée au
 * seul rendu PDF).
 */
class PdfReportBuilder
{
    /**
     * @return string Chemin du fichier PDF généré (storage/reports/)
     */
    public static function build(array $house, array $consumption, array $alerts): string
    {
        $html = self::renderHtml($house, $consumption, $alerts);

        $htmlPath = App::path('cache/report_' . uniqid() . '.html');
        $pdfPath = App::path('storage/reports/rapport_' . preg_replace('/[^a-z0-9]+/i', '-', $house['name']) . '_' . date('Y-m-d_His') . '.pdf');

        file_put_contents($htmlPath, $html);

        $cmd = sprintf(
            'wkhtmltopdf --quiet --page-size A4 %s %s 2>&1',
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );
        exec($cmd, $output, $exitCode);
        @unlink($htmlPath);

        if ($exitCode !== 0 || !file_exists($pdfPath)) {
            throw new \RuntimeException('Échec de la génération du rapport PDF : ' . implode("\n", $output));
        }

        return $pdfPath;
    }

    private static function renderHtml(array $house, array $consumption, array $alerts): string
    {
        $rows = '';
        foreach ($alerts as $a) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($a['created_at']),
                htmlspecialchars($a['severity']),
                htmlspecialchars($a['message'])
            );
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="3">Aucune alerte récente</td></tr>';
        }

        $houseName = htmlspecialchars($house['name']);
        $watts = htmlspecialchars((string) ($consumption['total_active_watts'] ?? 0));
        $kwh = htmlspecialchars((string) ($consumption['estimated_daily_kwh'] ?? 0));
        $date = date('d/m/Y H:i');

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body { font-family: sans-serif; color: #1b2430; }
h1 { color: #1b2a4a; font-size: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 16px; }
th, td { border: 1px solid #e4e8ef; padding: 8px; font-size: 12px; text-align: left; }
th { background: #f3f5f9; }
.kpi { display: inline-block; margin-right: 24px; }
.kpi b { font-size: 18px; color: #2f5fa8; }
</style></head><body>
<h1>Rapport Vicia Home — {$houseName}</h1>
<p>Généré le {$date}</p>
<div class="kpi">Puissance active<br><b>{$watts} W</b></div>
<div class="kpi">Estimation journalière<br><b>{$kwh} kWh</b></div>
<h2 style="font-size:14px;margin-top:24px;">Alertes récentes</h2>
<table><thead><tr><th>Date</th><th>Sévérité</th><th>Message</th></tr></thead><tbody>{$rows}</tbody></table>
</body></html>
HTML;
    }
}

# Consommation énergétique globale

VICIA HOME mesure la consommation de la maison entière avec un seul capteur énergétique. Le module ne répartit jamais l'énergie entre les lampes, prises ou autres équipements.

## Chemin des données

```text
Capteur global -> ESP32 -> MQTT -> mqtt/subscriber.php
-> TelemetryService -> sensors/sensor_readings
-> App\Models\Energy -> page Consommation
```

Le subscriber accepte notamment le topic `home/<maison>/energy/power`, ainsi que les topics de télémétrie existants. L'adresse est ensuite rapprochée du capteur enregistré pour la maison; aucune maison ou donnée n'est mélangée.

## Unités prises en charge

- `energy_power` ou unité `W`/`watt` : chaque valeur est une puissance instantanée. Le module calcule l'énergie entre deux relevés par intégration trapézoïdale, puis convertit les Wh en kWh.
- `energy_kwh` ou unité `kWh` : chaque valeur est un compteur cumulatif en kWh. Le module utilise la différence entre les relevés, y compris le dernier relevé précédant le mois sélectionné.
- `energy_consumption` avec unité `Wh` : même principe cumulatif, avec conversion des Wh en kWh.
- Toute autre unité énergétique reste indisponible plutôt que d'être interprétée arbitrairement.

Un mois avec un seul relevé de puissance ou un compteur sans relevé de référence n'affiche pas `0 kWh`; l'interface indique qu'il n'y a pas assez de données.

## Interface

La page `/consumption` affiche uniquement :

- le total global du mois sélectionné ;
- une seule courbe quotidienne en kWh ;
- les mois précédents et leur comparaison lorsque les deux mois ont des données ;
- une indication en baisse, stable ou hausse ;
- des conseils généraux d'économie d'énergie.

Aucune puissance individuelle par équipement n'est calculée ou affichée.

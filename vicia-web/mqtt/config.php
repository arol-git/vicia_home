<?php
/**
 * mqtt/config.php
 *
 * Paramètres de connexion au broker MQTT (Mosquitto), partagés par
 * le publisher, le subscriber et l'API. Reprend la configuration
 * centrale de config/config.php afin d'éviter toute duplication.
 */

return (require __DIR__ . '/../config/config.php')['mqtt'];

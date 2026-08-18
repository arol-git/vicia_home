<?php
/**
 * public/api/v1/index.php
 *
 * Proxy vers le vrai api/v1/
 * Permet de router les requêtes /api/v1/<resource> correctement
 */

// Redirige vers le vrai fichier api/index.php
require __DIR__ . '/../../../api/index.php';

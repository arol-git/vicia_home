<?php
/**
 * public/api/index.php
 *
 * Proxy vers le vrai api/index.php
 * Ceci est nécessaire car le serveur PHP pointe vers public/ comme racine.
 */

// Redirige vers le vrai fichier api/index.php
require __DIR__ . '/../../api/index.php';

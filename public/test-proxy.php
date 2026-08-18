<?php
echo "TEST FILE - If you see this, public/ is working correctly";
echo "\n\nFiles in public/api/v1/:\n";
$files = scandir(__DIR__ . '/api/v1', SCANDIR_SORT_ASCENDING);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "  - $file\n";
    }
}
?>

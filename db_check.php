<?php
require_once __DIR__ . '/includes/database.php';

header('Content-Type: text/plain; charset=utf-8');

if ($dbAvailable) {
    echo "DB_OK\n";
} else {
    echo "DB_FAIL\n";
    if (!empty($dbWarning)) {
        echo $dbWarning . "\n";
    }
}

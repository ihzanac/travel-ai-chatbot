<?php
header('Content-Type: text/plain; charset=utf-8');

echo 'extension_loaded: ' . (extension_loaded('mysqli') ? 'yes' : 'no') . "\n";
echo 'class_exists(mysqli): ' . (class_exists('mysqli') ? 'yes' : 'no') . "\n";
echo 'function_exists(mysqli_connect): ' . (function_exists('mysqli_connect') ? 'yes' : 'no') . "\n";
echo 'phpversion(mysqli): ' . (phpversion('mysqli') ?: '(none)') . "\n";
echo 'loaded_ini: ' . (php_ini_loaded_file() ?: '(none)') . "\n";
echo 'extension_dir: ' . (ini_get('extension_dir') ?: '(none)') . "\n";

$ext = get_loaded_extensions();
sort($ext);
echo "loaded_extensions:\n";
foreach ($ext as $e) {
    echo "- {$e}\n";
}


<?php

/**
 * PHP 8.5 deprecates PDO::MYSQL_ATTR_SSL_CA. Laravel 11 still ships that constant in
 * vendor stubs, and LoadConfiguration::getBaseConfiguration() always requires
 * vendor/laravel/framework/config/database.php during artisan boot (Cloud package:discover).
 */
$root = dirname(__DIR__);
$replacement = '(defined(\'Pdo\\Mysql::ATTR_SSL_CA\') ? \\Pdo\\Mysql::ATTR_SSL_CA : \\PDO::MYSQL_ATTR_SSL_CA)';

$targets = [
    $root.'/vendor/laravel/framework/config/database.php',
    $root.'/vendor/laravel/framework/src/Illuminate/Database/Schema/MySqlSchemaState.php',
];

foreach ($targets as $file) {
    if (! is_file($file)) {
        continue;
    }

    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }

    // Already patched (or Laravel 12+ style).
    if (str_contains($contents, "defined('Pdo\\Mysql::ATTR_SSL_CA')")
        || str_contains($contents, 'Pdo\\Mysql::ATTR_SSL_CA')
        || str_contains($contents, 'Mysql::ATTR_SSL_CA')) {
        continue;
    }

    if (! str_contains($contents, 'PDO::MYSQL_ATTR_SSL_CA')) {
        continue;
    }

    // Replace \PDO::… first so we don't double-prefix.
    $updated = str_replace('\\PDO::MYSQL_ATTR_SSL_CA', $replacement, $contents);
    $updated = str_replace('PDO::MYSQL_ATTR_SSL_CA', $replacement, $updated);

    if ($updated !== $contents) {
        file_put_contents($file, $updated);
        fwrite(STDOUT, 'Patched PHP 8.5 PDO SSL_CA usage in '.str_replace($root.DIRECTORY_SEPARATOR, '', $file).PHP_EOL);
    }
}

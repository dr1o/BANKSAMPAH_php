<?php

declare(strict_types=1);

/**
 * PSR-4 Autoloader untuk namespace BankSampah\
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'BankSampah\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'BankSampah' . DIRECTORY_SEPARATOR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

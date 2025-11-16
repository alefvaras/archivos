<?php
/**
 * Simple autoloader for PDF417 library without Composer
 * Uses PSR-4 autoloading for Le\PDF417 namespace
 */

spl_autoload_register(function ($class) {
    // Only handle Le\PDF417 classes
    if (strpos($class, 'Le\\PDF417\\') !== 0) {
        return;
    }

    // Convert namespace to file path
    $relativePath = str_replace('Le\\PDF417\\', '', $class);
    $relativePath = str_replace('\\', '/', $relativePath);

    $file = __DIR__ . '/pdf417/src/' . $relativePath . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

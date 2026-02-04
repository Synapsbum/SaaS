<?php

spl_autoload_register(function ($class) {

    // sadece phpseclib3 namespace
    if (strpos($class, 'phpseclib3\\') !== 0) {
        return;
    }

    $baseDir = __DIR__ . '/phpseclib/';
    $relativeClass = substr($class, strlen('phpseclib3\\'));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

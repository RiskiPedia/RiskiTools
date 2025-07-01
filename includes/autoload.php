<?php
spl_autoload_register(function ($class) {
    $prefix = 'MathParser\\';
    $baseDir = __DIR__ . '/math-parser/src/MathParser/';

    if (strncmp($prefix, $class, strlen($prefix)) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

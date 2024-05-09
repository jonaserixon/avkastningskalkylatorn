<?php

spl_autoload_register(function ($className) {
    $baseDir = __DIR__ . '/../';  // Går upp en nivå från nuvarande mapp

    $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);
    
    $file = $baseDir . $className . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

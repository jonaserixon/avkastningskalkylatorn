<?php

spl_autoload_register(function (string $className): void
{
    $baseDir = __DIR__ . '/../';

    $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);
    
    $file = $baseDir . $className . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

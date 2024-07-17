<?php declare(strict_types=1);

spl_autoload_register(function (string $className): void
{
    $baseDir = __DIR__ . '/';

    $prefix = 'Avk\\';

    if (strncmp($prefix, $className, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

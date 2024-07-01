<?php declare(strict_types=1);

spl_autoload_register(function (string $className): void
{
    $baseDir = __DIR__ . '/';

    $prefix = 'Avk\\';

    // Kontrollera om klassen använder prefixet 'Avk\'
    if (strncmp($prefix, $className, strlen($prefix)) !== 0) {
        // Om inte, använd en annan autoloader eller ignorera klassen
        return;
    }

    // Ta bort prefixet och ersätt namespaceavgränsare med directoryavgränsare
    $relativeClass = substr($className, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

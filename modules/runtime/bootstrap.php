<?php

declare(strict_types=1);

$runtimeRoot = __DIR__;

spl_autoload_register(static function (string $class) use ($runtimeRoot): void {
    $prefix = 'Eva\\ModuleRuntime\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $runtimeRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

return $runtimeRoot;

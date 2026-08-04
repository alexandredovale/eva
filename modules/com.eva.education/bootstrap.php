<?php

declare(strict_types=1);

$moduleRoot = __DIR__;

spl_autoload_register(static function (string $class) use ($moduleRoot): void {
    $prefix = 'EvaModule\\Education\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $moduleRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

return new EvaModule\Education\EducationModule();

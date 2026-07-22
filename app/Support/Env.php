<?php

declare(strict_types=1);

namespace Eva\Support;

final class Env
{
    /** @var array<string, string> */
    private static array $loadedValues = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));

            if ($name === '' || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)) {
                continue;
            }

            $loadedValue = self::unquote($value);

            if (array_key_exists($name, self::$loadedValues)) {
                if (self::$loadedValues[$name] === '' && $loadedValue !== '') {
                    self::$loadedValues[$name] = $loadedValue;
                }

                continue;
            }

            self::$loadedValues[$name] = $loadedValue;
        }
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, $_ENV) && is_scalar($_ENV[$name])) {
            return (string) $_ENV[$name];
        }

        if (array_key_exists($name, $_SERVER) && is_scalar($_SERVER[$name])) {
            return (string) $_SERVER[$name];
        }

        if (array_key_exists($name, self::$loadedValues)) {
            return self::$loadedValues[$name];
        }

        $value = getenv($name);

        return $value === false ? $default : $value;
    }

    public static function bool(string $name, bool $default = false): bool
    {
        $value = self::get($name);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function unquote(string $value): string
    {
        $length = strlen($value);

        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}

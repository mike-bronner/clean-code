<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests;

use Closure;

final class PregFailure
{
    private static ?string $function = null;

    private static ?Closure $matches = null;

    public static function arm(string $function, ?Closure $matches = null): void
    {
        self::$function = $function;
        self::$matches = $matches;
    }

    public static function disarm(): void
    {
        self::$function = null;
        self::$matches = null;
    }

    public static function armedFor(string $function, string $pattern): bool
    {
        if (self::$function !== $function) {
            return false;
        }

        return self::$matches === null || (self::$matches)($pattern) === true;
    }

    public static function during(string $function, Closure $body, ?Closure $matches = null): mixed
    {
        self::arm($function, $matches);

        try {
            return $body();
        } finally {
            self::disarm();
        }
    }
}

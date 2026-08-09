<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use JsonSerializable;
use function array_map;
use const PHP_EOL;

/**
 * A top-level docblock is not a statement.
 */
#[\AllowDynamicProperties]
final class UserPresenter implements JsonSerializable
{
    public const SEPARATOR = ', ';

    private string $prefix = 'user';

    public function present(User $user): string
    {
        $names = [];

        foreach ([$user] as $entry) {
            if ($entry->name === '') {
                continue;
            }

            $names[] = $entry->name;
        }

        return $this->prefix . implode(self::SEPARATOR, $names) . PHP_EOL;
    }

    public function jsonSerialize(): mixed
    {
        return array_map(
            static fn (string $name): string => trim($name),
            ['a', 'b']
        );
    }

    public function anonymous(): JsonSerializable
    {
        return new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return [];
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

final class NameTokens
{
    public const QUALIFIED = [
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    public function withoutNamespaceKeyword(array $token): string
    {
        if ($token['code'] === T_NAME_RELATIVE) {
            return substr($token['content'], strlen('namespace'));
        }

        return $token['content'];
    }

    public function lastSegment(string $name): string
    {
        $segments = explode('\\', $name);

        return end($segments);
    }
}

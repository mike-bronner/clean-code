<?php

declare(strict_types=1);

class ArrayAccessorsByReference
{
    public function readsInArgumentPositions(array $payload, string $subject): void
    {
        sort($payload['items']);
        preg_match('/x/', $subject, $payload['matches']);
        preg_match($payload['pattern'], $subject, $found);
        strtoupper($payload['label']);
    }
}

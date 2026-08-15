<?php

declare(strict_types=1);

namespace App\Sample;

use DateTimeImmutable;

/**
 * Doc-blocks and explanatory comments are not commented-out code, and an
 * import referenced only from a doc-block annotation is still a used import.
 *
 * @see https://mikebronner.dev/clean-code
 */
class Report
{
    /** @var array<int, string> free-form notes, keyed by position */
    private array $notes = [];

    /**
     * @param DateTimeImmutable $generatedAt when the report was produced
     */
    public function describe(mixed $generatedAt): string
    {
        // Prose that explains intent stays; only code-shaped comments go.
        return sprintf('Report with %d notes at %s.', count($this->notes), $generatedAt->format('c'));
    }
}

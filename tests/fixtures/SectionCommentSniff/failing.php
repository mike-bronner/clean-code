<?php

/**
 * Every shape the section-comment rule reports: a standalone comment inside a
 * function body, on its own line, at a statement boundary, with a further
 * statement after it in the same scope.
 */

declare(strict_types=1);

namespace Section\Failing;

class LabelledBlocks
{
    public function afterTheOpeningBrace(array $payload): array
    {
        // Validate the payload.
        $payload['valid'] = true;

        return $payload;
    }

    public function afterAStatement(array $payload): array
    {
        $payload['valid'] = true;

        // Build the response.
        $payload['response'] = [];

        return $payload;
    }

    public function afterANestedBlock(array $payload): array
    {
        if ($payload === []) {
            $payload['empty'] = true;
        }

        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function hashComment(array $payload): array
    {
        # Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function oneLineBlockComment(array $payload): array
    {
        /* Normalise the keys. */
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function commentRun(array $payload): array
    {
        // Normalise the keys, because the upstream feed mixes casing
        // and the comparison below is case-sensitive.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function runSplitByABlankLine(array $payload): array
    {
        // Normalise the keys.

        // Then compare them.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function separatedByBlankLines(array $payload): array
    {
        // Normalise the keys.


        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function insideAClosure(array $payload): callable
    {
        return function (array $extra) use ($payload): array {
            // Merge the extras.
            $merged = array_merge($payload, $extra);

            return $merged;
        };
    }

    public function insideANestedIf(array $payload): array
    {
        if ($payload !== []) {
            // Normalise the keys.
            $payload = array_change_key_case($payload);
        }

        return $payload;
    }

    public function insideAForeach(array $rows): array
    {
        $seen = [];

        foreach ($rows as $row) {
            // Record the row.
            $seen[] = $row;
        }

        return $seen;
    }

    public function insideATry(array $payload): array
    {
        try {
            // Normalise the keys.
            $payload = array_change_key_case($payload);
        } catch (\Throwable $exception) {
            $payload = [];
        }

        return $payload;
    }

    public function insideAnElseifAndAnElse(array $payload): array
    {
        if ($payload === []) {
            $payload['size'] = 'none';
        } elseif (count($payload) === 1) {
            // Record the single-entry case.
            $payload['size'] = 'one';
        } else {
            // Record every larger case.
            $payload['size'] = 'many';
        }

        return $payload;
    }

    public function insideAForAWhileAndADoBlock(array $rows): array
    {
        $seen = [];

        for ($index = 0; $index < count($rows); $index++) {
            // Record the row at this index.
            $seen[] = $rows[$index];
        }

        while (count($seen) < 2) {
            // Pad the result out to a fixed width.
            $seen[] = null;
        }

        do {
            // Trim one entry back off the padding.
            array_pop($seen);
        } while (count($seen) > 1);

        return $seen;
    }

    public function insideACatchAndAFinally(array $payload): array
    {
        try {
            $payload['tried'] = true;
        } catch (\Throwable $exception) {
            // Record the failure for the caller.
            $payload['failed'] = true;
        } finally {
            // Mark the attempt as finished either way.
            $payload['done'] = true;
        }

        return $payload;
    }

    public function followedByAControlStructure(array $payload): array
    {
        // Normalise the keys before comparing them.
        if ($payload !== []) {
            $payload = array_change_key_case($payload);
        }

        return $payload;
    }

    public function followedByAReturn(array $payload): array
    {
        $payload['seen'] = true;

        // Hand the payload back.
        return $payload;
    }

    public function beforeACaseLabel(string $key): int
    {
        switch ($key) {
            // Handle the keys the feed still spells the old way.
            case 'a':
                return 1;
            default:
                return 0;
        }
    }

    public function insideACaseBody(string $key, array $payload): array
    {
        switch ($key) {
            case 'a':
                // Normalise the keys.
                $payload = array_change_key_case($payload);

                break;
            default:
                // Leave the payload alone.
                $payload['untouched'] = true;

                break;
        }

        return $payload;
    }

    public function insideAnAlternativeSyntaxBlock(array $rows): array
    {
        $seen = [];

        foreach ($rows as $row):
            // Record the row.
            $seen[] = $row;
        endforeach;

        return $seen;
    }

    public function insideAnAnonymousClassMethod(): object
    {
        return new class {
            public function handle(array $payload): array
            {
                // Normalise the keys.
                $payload = array_change_key_case($payload);

                return $payload;
            }
        };
    }

    public function underADebtMarker(array $payload): array
    {
        // TODO: revisit the normalisation below.
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function underAFormatterDirective(array $payload): array
    {
        // @formatter:off
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function underADocblock(array $payload): array
    {
        /** @var array<string, mixed> $payload */
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function underAPhpcsAnnotation(array $payload): array
    {
        // phpcs:ignore Squiz.PHP.Eval
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function underAMultiLineBlockComment(array $payload): array
    {
        /* A block comment spanning
           two physical lines, so neither of its tokens is a label line. */
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function underACommentTrailingAStatement(array $payload): array
    {
        $payload['seen'] = true; // A note about the assignment it shares a line with.
        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }

    public function afterABareBlock(array $payload): array
    {
        {
            $payload['grouped'] = true;
        }

        // Normalise the keys.
        $payload = array_change_key_case($payload);

        return $payload;
    }
}

$atFileScope = function (array $payload): array {
    // Normalise the keys.
    $payload = array_change_key_case($payload);

    return $payload;
};

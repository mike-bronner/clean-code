<?php

/**
 * Every shape the section-comment rule must stay silent on, plus the compliant
 * form of the construct it registers on: a method whose blocks are already
 * extracted needs no labels at all.
 */

declare(strict_types=1);

namespace Section\Passing;

// A file-level comment followed by a statement: outside any function body.
$fileLevel = 1;

class AlreadyExtracted
{
    // A class-level comment between members, followed by a member.
    public int $count = 0;

    /**
     * A method docblock, followed by the method it documents.
     */
    public function handle(array $payload): array
    {
        $this->validate($payload);

        return $this->build($payload); // A trailing comment, sharing its line.
    }

    public function trailingBlockComment(array $payload): array
    {
        /* A one-line block comment sharing its line with the statement. */ $payload['seen'] = true;

        return $payload;
    }

    public function multiLineBlockComment(array $payload): array
    {
        /* A block comment spanning
           two physical lines, which the tokenizer splits into two comment
           tokens, neither of them a whole comment. */
        $payload['seen'] = true;

        return $payload;
    }

    public function lastInItsScope(array $payload): array
    {
        $payload['seen'] = true;

        return $payload;

        // Nothing but blank lines and the closing brace follow this comment.
    }

    public function lastInANestedScope(array $payload): array
    {
        if ($payload === []) {
            $payload['empty'] = true;

            // Nothing but the closing brace of the `if` follows this comment.
        }

        return $payload;
    }

    public function debtMarkers(array $payload): array
    {
        // TODO: extract the normalisation below into its own method.
        $payload['a'] = 1;

        // FIXME the ordering here.
        $payload['b'] = 2;

        // HACK around the upstream defect.
        $payload['c'] = 3;

        // XXX revisit once the API settles.
        $payload['d'] = 4;

        return $payload;
    }

    public function formatterDirectives(array $payload): array
    {
        // @formatter:off
        $payload['a'] = 1;

        // @formatter:on
        $payload['b'] = 2;

        // prettier-ignore
        $payload['c'] = 3;

        return $payload;
    }

    public function phpcsAnnotation(array $payload): array
    {
        // phpcs:ignore Squiz.PHP.Eval
        $payload['a'] = 1;

        return $payload;
    }

    public function insideAnArrayLiteral(): array
    {
        return [
            // An element label, not a statement label.
            'a' => 1,
            // A second one, after a comma rather than a semicolon.
            'b' => 2,
        ];
    }

    public function insideAnArgumentList(array $payload): array
    {
        return array_merge(
            $payload,
            // An argument label, not a statement label.
            ['seen' => true]
        );
    }

    public function insideAnAnonymousClass(): object
    {
        return new class {
            // A member label inside an anonymous class declared in a method.
            public int $held = 1;
        };
    }

    public function insideAMatchArmList(int $value): int
    {
        return match ($value) {
            // An arm label, not a statement label.
            1 => 10,
            default => 0,
        };
    }

    private function validate(array $payload): void
    {
        if ($payload === []) {
            throw new \InvalidArgumentException('Empty payload.');
        }
    }

    private function build(array $payload): array
    {
        return $payload + ['built' => true];
    }

    public function runLastInItsScope(array $payload): array
    {
        $payload['seen'] = true;

        return $payload;

        // Two comment lines, one run, and nothing but the closing brace of the
        // method after them.
    }

    public function runSplitByABlankLineLastInItsScope(array $payload): array
    {
        $payload['seen'] = true;

        return $payload;

        // A run whose lines are set apart by a blank one,

        // still with nothing but the closing brace after it.
    }

    public function insideAnArrowFunction(array $payload): callable
    {
        return fn (array $extra): array =>
            // An arrow function's body is one expression: nothing further can
            // follow this comment in its scope.
            array_merge($payload, $extra);
    }

    public function afterATernaryColon(array $payload): string
    {
        $label = $payload === [] ? 'empty' :
            // Not a label: a ternary arm follows a `:` that opens no block.
            'full';

        return $label;
    }

    public function formatterDirectiveWithTrailingText(array $payload): array
    {
        // @formatter:off for the block below
        $payload['a'] = 1;

        return $payload;
    }

    public function afterAClosuresBrace(): int
    {
        $value = (function (): int {
            return 1;
        }
            // Not a label: the expression holding the closure continues.
        )();

        return $value;
    }

    public function afterAnAnonymousClassBrace(): int
    {
        $value = (new class {
            public function held(): int
            {
                return 1;
            }
        }
            // Not a label: the expression holding the anonymous class continues.
        )->held();

        return $value;
    }

    public function insideAnArrowFunctionPastANestedBrace(): callable
    {
        return fn (): int => (new class {
            public function held(): int
            {
                return 1;
            }
        }
            // The same shape inside an arrow function, whose T_FN the tokenizer
            // keeps out of this comment's conditions altogether.
        )->held();
    }

    public function afterAMatchBrace(int $value): int
    {
        $mapped = match ($value) {
            1 => 10,
            default => 0,
        }
            // Not a label: the statement holding the `match` continues.
        ;

        return $mapped;
    }

    public function afterADoBlocksBrace(int $count): int
    {
        do {
            $count++;
        }
        // Not a label: the `while` that ends the loop follows.
        while ($count < 5);

        return $count;
    }

    public function beforeAnElse(array $payload): array
    {
        if ($payload === []) {
            $payload['empty'] = true;
        }
        // Not a label: the `else` continues the `if` above it.
        else {
            $payload['empty'] = false;
        }

        return $payload;
    }

    public function beforeAnElseif(array $payload): array
    {
        if ($payload === []) {
            $payload['size'] = 'none';
        }
        // Not a label: the `elseif` continues the `if` above it.
        elseif (count($payload) === 1) {
            $payload['size'] = 'one';
        }

        return $payload;
    }

    public function beforeACatch(array $payload): array
    {
        try {
            $payload['tried'] = true;
        }
        // Not a label: the `catch` continues the `try` above it.
        catch (\Throwable $exception) {
            $payload['tried'] = false;
        }

        return $payload;
    }

    public function beforeAFinally(array $payload): array
    {
        try {
            $payload['tried'] = true;
        } catch (\Throwable $exception) {
            $payload['tried'] = false;
        }
        // Not a label: the `finally` continues the `try` above it.
        finally {
            $payload['done'] = true;
        }

        return $payload;
    }
}

interface BodylessSignatures
{
    public function first(): void;

    // A comment between two bodyless signatures: no scope opens, nothing to
    // extract, and no error.

    public function second(): void;
}

abstract class AbstractSignatures
{
    abstract public function first(): void;

    // The same between two abstract signatures.

    abstract public function second(): void;
}

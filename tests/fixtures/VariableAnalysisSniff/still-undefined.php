<?php

declare(strict_types=1);

namespace App;

class StillUndefined
{
    public function namedByValue(string $pattern): int
    {
        return preg_match(pattern: $pattern, subject: $undefinedSubject);
    }

    public function readBeforeTheCall(string $pattern, string $line): array
    {
        $before = $matches;
        preg_match(pattern: $pattern, subject: $line, matches: $matches);

        return [$before, $matches];
    }

    public function otherArgumentInTheSameCall(string $line): array
    {
        preg_match(pattern: $undefinedPattern, subject: $line, matches: $matches);

        return $matches;
    }

    public function unknownParameterName(string $pattern, string $line): int
    {
        return preg_match(pattern: $pattern, subject: $line, unknown: $undefinedUnknown);
    }

    public function byReferenceNameOnAnotherFunction(string $line): string
    {
        return trim(string: $line, characters: $matches);
    }

    public function methodCallIsNotTheBuiltIn(object $parser, string $line): array
    {
        $parser->preg_match(subject: $line, matches: $matches);

        return $matches;
    }

    public function functionPhpDoesNotKnow(string $line): array
    {
        no_such_function(subject: $line, matches: $unknownFunctionMatches);

        return $unknownFunctionMatches;
    }

    public function byReferenceArgumentIsAnExpression(string $pattern, string $line): int
    {
        return preg_match(pattern: $pattern, subject: $line, matches: $notWhole['key']);
    }

    public function nestedArrowReadsNothingDefined(): callable
    {
        return fn ($a) => fn ($b) => $a + $b + $undefinedInArrow;
    }
}

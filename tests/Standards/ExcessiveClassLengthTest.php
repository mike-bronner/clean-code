<?php

declare(strict_types=1);

const EXCESSIVE_CLASS_LENGTH = 'CleanCode.Classes.ExcessiveClassLength';

const EXCESSIVE_CLASS_LENGTH_TOO_LONG = EXCESSIVE_CLASS_LENGTH . '.TooLong';

$excessiveClassLength = static function (int $minimum, bool $ignoreWhitespace = false): callable {
    return static function (object $sniff) use ($minimum, $ignoreWhitespace): void {
        $sniff->minimum = $minimum;
        $sniff->ignoreWhitespace = $ignoreWhitespace;
    };
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_CLASS_LENGTH);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it(
        'leaves interfaces, traits, enums, and anonymous classes alone whatever their length',
        function () use ($excessiveClassLength): void {
            $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'passing.php', $excessiveClassLength(10));

            expect($file->getErrors())->toBe([]);
        }
    );

it(
        'reports the one real class in the compliant fixture with PHPMD\'s own count',
        function () use ($excessiveClassLength): void {
            $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'passing.php', $excessiveClassLength(1));

            expect(violationTuples($file))->toBe([
                ['line' => 59, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
            ])->and(violationMessagesByLine($file->getErrors()))->toBe([
                59 => ['The class Invoice has 9 lines of code. Current threshold is 1. Avoid really long classes.'],
            ]);
        }
    );

it('flags a class of exactly the shipped 1000-line threshold', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
    ])->and(violationMessagesByLine($file->getErrors()))->toBe([
        11 => ['The class Colossus has 1000 lines of code. Current threshold is 1000. Avoid really long classes.'],
    ]);
});

it('treats the threshold as inclusive', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'boundaries.php', $excessiveClassLength(10));

    expect(violationMessagesByLine($file->getErrors()))->toBe([
        22 => ['The class AtThreshold has 10 lines of code. Current threshold is 10. Avoid really long classes.'],
        33 => ['The class OneOverThreshold has 11 lines of code. Current threshold is 10. Avoid really long classes.'],
    ]);
});

it(
        'reports at the declaration modifier, not the class keyword or the attribute above it',
        function () use ($excessiveClassLength): void {
            $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'declaration-start.php', $excessiveClassLength(1));

            expect(violationTuples($file))->toBe([
                ['line' => 16, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
                ['line' => 21, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
                ['line' => 28, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
                ['line' => 35, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
            ])->and(violationMessagesByLine($file->getErrors()))->toBe([
                16 => ['The class Alpha has 4 lines of code. Current threshold is 1. Avoid really long classes.'],
                21 => ['The class Beta has 6 lines of code. Current threshold is 1. Avoid really long classes.'],
                28 => ['The class Gamma has 6 lines of code. Current threshold is 1. Avoid really long classes.'],
                35 => ['The class Delta has 5 lines of code. Current threshold is 1. Avoid really long classes.'],
            ]);
        }
    );

it('counts comment and blank lines when ignoreWhitespace is off', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'whitespace.php', $excessiveClassLength(1));

    expect(violationMessagesByLine($file->getErrors()))->toBe([
        16 => ['The class Ledger has 20 lines of code. Current threshold is 1. Avoid really long classes.'],
        37 => ['The class Payment has 9 lines of code. Current threshold is 1. Avoid really long classes.'],
        47 => ['The class Factory has 12 lines of code. Current threshold is 1. Avoid really long classes.'],
    ]);
});

it('switches to executable lines when ignoreWhitespace is on', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'whitespace.php', $excessiveClassLength(1, true));

    expect(violationMessagesByLine($file->getErrors()))->toBe([
        16 => ['The class Ledger has 6 lines of code. Current threshold is 1. Avoid really long classes.'],
        37 => ['The class Payment has 3 lines of code. Current threshold is 1. Avoid really long classes.'],
        47 => ['The class Factory has 8 lines of code. Current threshold is 1. Avoid really long classes.'],
    ]);
});

it('gives an abstract method no executable lines', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'declaration-start.php', $excessiveClassLength(1, true));

    expect(violationMessagesByLine($file->getErrors()))->toBe([
        21 => ['The class Beta has 2 lines of code. Current threshold is 1. Avoid really long classes.'],
        28 => ['The class Gamma has 2 lines of code. Current threshold is 1. Avoid really long classes.'],
    ]);
});

it('undercounts a class whose brace pairing PHP_CodeSniffer gets wrong', function () use ($excessiveClassLength): void {
    $physical = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'tokenizer-limits.php', $excessiveClassLength(1));
    $executable = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'tokenizer-limits.php', $excessiveClassLength(1, true));

    $reported = static fn (int $lines): array => [
        17 => ["The class ClosureInPropertyFetch has {$lines} lines of code."
            . ' Current threshold is 1. Avoid really long classes.'],
    ];

    expect(violationMessagesByLine($physical->getErrors()))->toBe($reported(9))
        ->and(violationMessagesByLine($executable->getErrors()))->toBe($reported(2));
});

it('stays silent on a class the tokenizer never closed', function () use ($excessiveClassLength): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    });

    try {
        $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'unterminated.php', $excessiveClassLength(1));
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([])
        ->and($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports without offering a fix', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false])
        ->and($file->getFixableCount())->toBe(0);
});

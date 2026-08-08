<?php

/**
 * Tests the custom CleanCode.Classes.ExcessiveClassLength sniff (PHPMD
 * CodeSize: ExcessiveClassLength, #93). Fixtures live in
 * tests/fixtures/ExcessiveClassLengthSniff/.
 *
 * Every expected number below was taken from a live PHPMD 2.15.0 run over the
 * same fixture rather than derived from the sniff, so these assertions pin
 * PHPMD parity and not merely the sniff's own arithmetic. The runs are quoted
 * in docs/phpmd/codesize-excessiveclasslength.md.
 *
 * Most tests lower `minimum` through $configure the way a consuming ruleset
 * would: at the shipped default of 1000 a boundary fixture would have to be
 * three thousand-line classes. failing.php is the exception — it is a real
 * 1000-line class, so the threshold this package actually ships is exercised
 * too, by this file and by the contract sweep.
 */

declare(strict_types=1);

const EXCESSIVE_CLASS_LENGTH = 'CleanCode.Classes.ExcessiveClassLength';

const EXCESSIVE_CLASS_LENGTH_TOO_LONG = EXCESSIVE_CLASS_LENGTH . '.TooLong';

/**
 * Sets the sniff's properties the way a <properties> block in a ruleset would.
 */
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

/**
 * The discriminating half of the compliant fixture. Dropped to a threshold of
 * 10, every construct in passing.php is over it except the one real class,
 * which is 9 lines — so anything the sniff wrongly registered on would show up
 * here. PHPMD's rule is ClassAware: an interface (12 lines), a trait (16), an
 * enum (13), and an anonymous class (24) are all out of scope however long they
 * get. Live PHPMD 2.15.0 reports Invoice and nothing else on this fixture too.
 */
it(
    'leaves interfaces, traits, enums, and anonymous classes alone whatever their length',
    function () use ($excessiveClassLength): void {
        $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'passing.php', $excessiveClassLength(10));

        expect($file->getErrors())->toBe([]);
    }
);

/**
 * The same fixture at a threshold of 1, where silence would prove nothing:
 * exactly one class is reported, and its line and count are the ones live
 * PHPMD 2.15.0 reports for the same file.
 */
it(
    'reports the one real class in the compliant fixture with PHPMD\'s own count',
    function () use ($excessiveClassLength): void {
        $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'passing.php', $excessiveClassLength(1));

        expect(violationTuples($file))->toBe([
            ['line' => 59, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
        ])->and(violationMessagesByLine($file))->toBe([
            59 => ['The class Invoice has 9 lines of code. Current threshold is 1. Avoid really long classes.'],
        ]);
    }
);

/**
 * The shipped configuration, on a class that really is 1000 lines long.
 * PHPMD's rule returns early only when the length is *below* `minimum`, so a
 * class of exactly the threshold is already a violation — which is what this
 * fixture is: `class Colossus` on line 11, closing brace on line 1010. Live
 * PHPMD 2.15.0 with the stock codesize ruleset reports the identical line,
 * count, and threshold.
 */
it('flags a class of exactly the shipped 1000-line threshold', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
    ])->and(violationMessagesByLine($file))->toBe([
        11 => ['The class Colossus has 1000 lines of code. Current threshold is 1000. Avoid really long classes.'],
    ]);
});

/**
 * The threshold either side of the boundary, on three classes of 9, 10, and 11
 * lines at a threshold of 10. The middle one is the whole point: "minimum"
 * names the smallest reportable length, not the largest allowed one.
 */
it('treats the threshold as inclusive', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'boundaries.php', $excessiveClassLength(10));

    expect(violationMessagesByLine($file))->toBe([
        22 => ['The class AtThreshold has 10 lines of code. Current threshold is 10. Avoid really long classes.'],
        33 => ['The class OneOverThreshold has 11 lines of code. Current threshold is 10. Avoid really long classes.'],
    ]);
});

/**
 * PDepend takes a class's start line from the first of its
 * abstract/final/readonly modifiers, so that line is both where the violation
 * lands and where the count starts. The doc block and the `#[Deprecated]`
 * attribute above Alpha are not part of the class on either count, and Delta —
 * `abstract` on its own line above `class Delta` — is 5 lines rather than 4 for
 * exactly this reason. All four lines and counts match live PHPMD 2.15.0.
 */
it(
    'reports at the declaration modifier, not the class keyword or the attribute above it',
    function () use ($excessiveClassLength): void {
        $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'declaration-start.php', $excessiveClassLength(1));

        expect(violationTuples($file))->toBe([
            ['line' => 16, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
            ['line' => 21, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
            ['line' => 28, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
            ['line' => 35, 'column' => 1, 'source' => EXCESSIVE_CLASS_LENGTH_TOO_LONG],
        ])->and(violationMessagesByLine($file))->toBe([
            16 => ['The class Alpha has 4 lines of code. Current threshold is 1. Avoid really long classes.'],
            21 => ['The class Beta has 6 lines of code. Current threshold is 1. Avoid really long classes.'],
            28 => ['The class Gamma has 6 lines of code. Current threshold is 1. Avoid really long classes.'],
            35 => ['The class Delta has 5 lines of code. Current threshold is 1. Avoid really long classes.'],
        ]);
    }
);

/**
 * The default metric — PDepend's `loc` — is every physical line of the class,
 * comments and blank lines included. Ledger is 20 lines with 4 of them
 * comment-only or blank, Payment is 9, and Factory is 12.
 */
it('counts comment and blank lines when ignoreWhitespace is off', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'whitespace.php', $excessiveClassLength(1));

    expect(violationMessagesByLine($file))->toBe([
        16 => ['The class Ledger has 20 lines of code. Current threshold is 1. Avoid really long classes.'],
        37 => ['The class Payment has 9 lines of code. Current threshold is 1. Avoid really long classes.'],
        47 => ['The class Factory has 12 lines of code. Current threshold is 1. Avoid really long classes.'],
    ]);
});

/**
 * Turning ignoreWhitespace on is not "the same count without the blank lines":
 * PHPMD swaps `loc` for PDepend's `eloc`, which sums the non-comment lines
 * inside the bodies of the class's own methods and counts nothing else. Ledger
 * drops from 20 to 6 — losing its constant, its property, both signature lines,
 * the class braces, and every comment-only line — and Payment from 9 to 3.
 * Factory drops from 12 to 8, keeping the body of the anonymous class its
 * method returns: those lines are inside the enclosing method's token range,
 * and the anonymous class's own method is never counted a second time on top.
 * Every number comes from a live PHPMD 2.15.0 run with ignore-whitespace=true.
 */
it('switches to executable lines when ignoreWhitespace is on', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'whitespace.php', $excessiveClassLength(1, true));

    expect(violationMessagesByLine($file))->toBe([
        16 => ['The class Ledger has 6 lines of code. Current threshold is 1. Avoid really long classes.'],
        37 => ['The class Payment has 3 lines of code. Current threshold is 1. Avoid really long classes.'],
        47 => ['The class Factory has 8 lines of code. Current threshold is 1. Avoid really long classes.'],
    ]);
});

/**
 * An abstract method has no body, so under `eloc` it contributes nothing at
 * all. Alpha and Delta in declaration-start.php hold one abstract method each
 * and nothing else, which puts them at 0 executable lines — below even a
 * threshold of 1, so they fall silent while their concrete siblings are still
 * reported. PHPMD 2.15.0 with ignore-whitespace=true reports exactly this pair.
 */
it('gives an abstract method no executable lines', function () use ($excessiveClassLength): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'declaration-start.php', $excessiveClassLength(1, true));

    expect(violationMessagesByLine($file))->toBe([
        21 => ['The class Beta has 2 lines of code. Current threshold is 1. Avoid really long classes.'],
        28 => ['The class Gamma has 2 lines of code. Current threshold is 1. Avoid really long classes.'],
    ]);
});

/**
 * The one shape where the sniff and PHPMD disagree, pinned rather than claimed
 * away. A closure inside a curly-brace property fetch in a `foreach` header
 * makes PHP_CodeSniffer's tokenizer close the class's scope — and the enclosing
 * method's — one brace early, so the sniff measures 9 lines where PHPMD 2.15.0
 * measures 10, and 2 executable lines where PHPMD measures 5. The class is
 * still reported, at the right line, and undercounting can only ever suppress a
 * report, never invent one. Rewriting PHPCS's brace pairing to close the gap
 * would be a far larger risk than the gap itself.
 */
it('undercounts a class whose brace pairing PHP_CodeSniffer gets wrong', function () use ($excessiveClassLength): void {
    $physical = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'tokenizer-limits.php', $excessiveClassLength(1));
    $executable = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'tokenizer-limits.php', $excessiveClassLength(1, true));

    $reported = static fn (int $lines): array => [
        17 => ["The class ClosureInPropertyFetch has {$lines} lines of code."
            . ' Current threshold is 1. Avoid really long classes.'],
    ];

    expect(violationMessagesByLine($physical))->toBe($reported(9))
        ->and(violationMessagesByLine($executable))->toBe($reported(2));
});

/**
 * An unterminated class body leaves PHP_CodeSniffer with no scope_closer for
 * the class, so there is no end line to measure from. The sniff's scope guard
 * is the only thing between that and a read of a key that is not there:
 * without it the analysis dies on the missing index rather than reporting
 * nothing. A threshold of 1 is used so silence cannot come from the length.
 */
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

/**
 * PHPMD has no fixer for this rule and neither does the sniff: splitting an
 * over-long class is a design decision, not a mechanical rewrite.
 */
it('reports without offering a fix', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_LENGTH, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false])
        ->and($file->getFixableCount())->toBe(0);
});

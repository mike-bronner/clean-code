<?php

declare(strict_types=1);

use PHP_CodeSniffer\Ruleset;

const SHORT_METHOD_NAME = 'CleanCode.Naming.ShortMethodName';

const SHORT_METHOD_NAME_TOO_SHORT = SHORT_METHOD_NAME . '.TooShort';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SHORT_METHOD_NAME);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every short declaration at the name token', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 21, 'column' => 10, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 27, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 32, 'column' => 30, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 34, 'column' => 28, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 38, 'column' => 22, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 44, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 51, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 55, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 63, 'column' => 18, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 71, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 78, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
    ]);
});

it('reports without offering a fix', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'failing.php');

    expect($file->getErrorCount())->toBe(11)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

it('names the declaration and the threshold in the message', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'failing.php');

    $messages = $file->getErrors()[55][21];

    expect($messages[0]['message'])
        ->toBe('Avoid using short method names like a(). The configured minimum method name length is 3.');
});

it('passes a name exactly at the minimum and fails one character shorter', function (): void {
    $atMinimum = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 3;
        }
    );

    $oneAbove = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect($atMinimum->getErrors())->toBe([])
        ->and(array_keys($oneAbove->getErrors()))->toContain(77);
});

it('measures the name in bytes, as PHPMD does', function (): void {
    $atDefault = analyzeFixture(SHORT_METHOD_NAME, 'passing.php');

    $raised = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect(array_keys($atDefault->getErrors()))->not->toContain(83)
        ->and(array_keys($raised->getErrors()))->toContain(83);
});

it('reports magic methods below the threshold, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 12;
        }
    );

    expect(array_keys($file->getErrors()))
        ->toContain(57)
        ->toContain(61)
        ->toContain(66)
        ->toContain(70);
});

it('never reports an unnamed declaration', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 40;
        }
    );

    expect(array_keys($file->getErrors()))->not->toContain(109, 112);
});

it('never reports a name in the exceptions list', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ct';
        }
    );

    expect(array_keys($file->getErrors()))
        ->not->toContain(32)
        ->not->toContain(51)
        ->toContain(34);
});

it('does not trim the exceptions list, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ct, st';
        }
    );

    expect(array_keys($file->getErrors()))
        ->toContain(34)
        ->not->toContain(32);
});

it('matches exceptions case-sensitively, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'CT';
        }
    );

    expect(array_keys($file->getErrors()))->toContain(32, 51);
});

it('receives null from PHPCS for an empty ruleset property', function (): void {
    [, $ruleset] = buildRuleset([SHORT_METHOD_NAME], true);
    $sniffClass = $ruleset->sniffCodes[SHORT_METHOD_NAME];

    $ruleset->setSniffProperty($sniffClass, 'minimum', ['scope' => 'sniff', 'value' => '']);

    expect($ruleset->sniffs[$sniffClass]->minimum)->toBeNull();
})->skip(
    method_exists(Ruleset::class, 'setSniffProperty') === false,
    'This PHPCS release does not expose setSniffProperty().'
);

it('falls back to the default minimum when the configured one is unusable', function (mixed $configured): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff) use ($configured): void {
            $sniff->minimum = $configured;
        }
    );

    expect($file->getErrorCount())->toBe(11);
})->with([
    'null (PHPCS empty property)' => [null],
    'empty string' => [''],
    'non-numeric' => ['abc'],
    'zero' => ['0'],
    'negative' => ['-1'],
    'float-ish' => ['2.5'],
]);

it('honours a numeric string threshold from a ruleset', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = '4';
        }
    );

    expect(array_keys($file->getErrors()))->toContain(77, 83);
});

it('refuses to name a declaration it cannot bound', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'malformed-declaration.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
    ]);
});

it('reports anonymous-class methods that PHPMD cannot see', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 29, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 39, 'column' => 29, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
    ]);
});

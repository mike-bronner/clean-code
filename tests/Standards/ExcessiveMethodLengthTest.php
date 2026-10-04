<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const EXCESSIVE_METHOD_LENGTH = 'CleanCode.Functions.ExcessiveMethodLength';

const EXCESSIVE_METHOD_LENGTH_ERROR = EXCESSIVE_METHOD_LENGTH . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_METHOD_LENGTH);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every declaration at or over the default threshold', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 115, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 216, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 318, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports the measured line count and threshold', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'failing.php');

    $messages = array_map(
            static fn (array $columns): string => reset($columns)[0]['message'],
            $file->getErrors()
        );

    expect(array_values($messages))->toBe([
        'The method exactlyAtTheThreshold() has 100 lines of code, and the threshold is 100; '
            . 'a declaration this long is doing several jobs, so extract each one into its own '
            . 'method (see resources/boost/guidelines/codesize-excessivemethodlength.md)',
        'The method modifiersOnTheirOwnLines() has 100 lines of code, and the threshold is 100; '
            . 'a declaration this long is doing several jobs, so extract each one into its own '
            . 'method (see resources/boost/guidelines/codesize-excessivemethodlength.md)',
        'The method padOutWithBlanksAndComments() has 100 lines of code, and the threshold is 100; '
            . 'a declaration this long is doing several jobs, so extract each one into its own '
            . 'method (see resources/boost/guidelines/codesize-excessivemethodlength.md)',
        'The function standaloneFunctionThatRunsLong() has 100 lines of code, and the threshold '
            . 'is 100; a declaration this long is doing several jobs, so extract each one into '
            . 'its own method (see resources/boost/guidelines/codesize-excessivemethodlength.md)',
    ]);
});

it('offers no fix for any violation', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false, false, false, false])
        ->and($file->getFixableCount())->toBe(0);
});

it('honours a lowered minimum', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'configured.php',
            static function (object $sniff): void {
                $sniff->minimum = 14;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

it('treats a declaration of exactly minimum lines as too long', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'configured.php',
            static function (object $sniff): void {
                $sniff->minimum = 15;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

it('starts the span at the first modifier when a comment sits between two', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'configured.php',
            static function (object $sniff): void {
                $sniff->minimum = 6;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 34, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 70, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);

    expect($file->getErrors()[70][5][0]['message'])->toContain('has 7 lines of code');
});

it('counts only executable lines when ignoreWhitespace is set', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'failing.php',
            static function (object $sniff): void {
                $sniff->ignoreWhitespace = true;
                $sniff->minimum = 99;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 318, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

it('excludes comments and the signature from the executable count', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'configured.php',
            static function (object $sniff): void {
                $sniff->ignoreWhitespace = true;
                $sniff->minimum = 11;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

it('counts every line a multi-line construct spans', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'configured.php',
            static function (object $sniff): void {
                $sniff->ignoreWhitespace = true;
                $sniff->minimum = 7;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 34, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

it('scores a bodiless declaration zero executable lines', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'passing.php',
            static function (object $sniff): void {
                $sniff->ignoreWhitespace = true;
                $sniff->minimum = 1;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 29, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 129, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 136, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 143, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 150, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

it('measures a bodiless declaration as one line under the default metric', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'passing.php',
            static function (object $sniff): void {
                $sniff->minimum = 1;
            }
        );

    $bodiless = array_filter(
            violationTuples($file),
            static fn (array $tuple): bool => in_array($tuple['line'], [160, 165], true)
        );

    expect(array_values($bodiless))->toBe([
        ['line' => 160, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 165, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);

    $messages = $file->getErrors();

    expect($messages[160][5][0]['message'])->toContain('has 1 lines of code')
        ->and($messages[165][5][0]['message'])->toContain('has 1 lines of code');
});

it('scores a declaration with no body and no terminator as one line', function (): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'malformed.php',
            static function (object $sniff): void {
                $sniff->minimum = 5;
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 22, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

it('falls back to the default threshold on an unusable minimum', function (string $minimum): void {
    $file = analyzeFixture(
            EXCESSIVE_METHOD_LENGTH,
            'configured.php',
            static function (object $sniff) use ($minimum): void {
                $sniff->minimum = $minimum;
            }
        );

    expect($file->getErrors())->toBe([]);
})->with([
    'not a number' => ['abc'],
    'empty' => [''],
    'zero' => ['0'],
    'negative' => ['-5'],
]);

it('accepts both properties from a ruleset file', function (): void {
    $standard = sys_get_temp_dir() . '/' . uniqid('cleancode-ruleset-', true) . '.xml';
    $sniffPath = cleanCodeRoot() . '/CleanCode/Sniffs/Functions/ExcessiveMethodLengthSniff.php';

    file_put_contents($standard, <<<XML
        <?xml version="1.0"?>
        <ruleset name="ExcessiveMethodLengthFromXml">
            <rule ref="{$sniffPath}">
                <properties>
                    <property name="minimum" value="14"/>
                    <property name="ignoreWhitespace" value="false"/>
                </properties>
            </rule>
        </ruleset>
        XML);

    $config = new ConfigDouble(['--standard=' . $standard]);
    $config->cache = false;
    $config->setConfigData('installed_paths', '', true);

    $file = new LocalFile(
            fixturePath('ExcessiveMethodLengthSniff', 'configured.php'),
            new Ruleset($config),
            $config
        );
    $file->process();

    unlink($standard);

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

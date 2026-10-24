<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const MAGIC_NUMBERS = 'CleanCode.Naming.DisallowMagicNumbers';

const MAGIC_NUMBERS_WARNING = MAGIC_NUMBERS . '.Found';

$applicationFixture = static fn (string $fixture, ?callable $configure = null): LocalFile => analyzeWithSniffs(
        [MAGIC_NUMBERS],
        stageFixtureOutsideTests(fixturePath('DisallowMagicNumbersSniff', $fixture), 'app'),
        $configure
    );

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MAGIC_NUMBERS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags a bare literal in every use context', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 7, 'column' => 27, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 11, 'column' => 26, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 13, 'column' => 21, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 14, 'column' => 36, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 17, 'column' => 24, 'source' => MAGIC_NUMBERS_WARNING],
        ]);
});

it('names the literal in the warning message', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'failing.php')->getWarnings();

    expect($warnings[13][21][0]['message'])->toContain('42');
});

it('polices a callable body while exempting its parameter defaults', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'callable-bodies.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        5 => [MAGIC_NUMBERS_WARNING],
        8 => [MAGIC_NUMBERS_WARNING],
        14 => [MAGIC_NUMBERS_WARNING],
    ]);
});

it('reads every numeric base when matching the ignore list', function () use ($applicationFixture): void {
    $file = $applicationFixture('numeric-bases.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        12 => [MAGIC_NUMBERS_WARNING],
        13 => [MAGIC_NUMBERS_WARNING],
        14 => [MAGIC_NUMBERS_WARNING],
        15 => [MAGIC_NUMBERS_WARNING],
        16 => [MAGIC_NUMBERS_WARNING],
        17 => [MAGIC_NUMBERS_WARNING],
    ]);
});

it('attaches a negating minus to the literal but not a subtracting one', function () use (
    $applicationFixture,
): void {
    $warnings = $applicationFixture('negative-numbers.php')->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([
        7 => [MAGIC_NUMBERS_WARNING, MAGIC_NUMBERS_WARNING],
        8 => [MAGIC_NUMBERS_WARNING],
    ])
        ->and($warnings[7][15][0]['message'])->toContain('number 100 ')
        ->and($warnings[7][21][0]['message'])->toContain('number 5 ')
        ->and($warnings[8][13][0]['message'])->toContain('number -7 ');
});

it('exposes a configurable ignore list', function () use ($applicationFixture): void {
    expect(violationSourcesByLine($applicationFixture('configured.php')->getWarnings()))->toBe([
        5 => [MAGIC_NUMBERS_WARNING],
        6 => [MAGIC_NUMBERS_WARNING],
    ]);

    $configured = $applicationFixture(
            'configured.php',
            static function (object $sniff): void {
                $sniff->ignoredNumbers = ['1000'];
            }
        );

    expect(violationSourcesByLine($configured->getWarnings()))->toBe([
        6 => [MAGIC_NUMBERS_WARNING],
    ]);
});

it('reports warnings rather than errors', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(5);
});

it('offers no fixer', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'failing.php')->getWarnings();

    $fixable = [];

    foreach ($warnings as $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                $fixable[] = $message['fixable'];
            }
        }
    }

    expect($fixable)->toBe([false, false, false, false, false]);
});

it('names a protected typed property, not a constant, in the message', function (): void {
    $message = analyzeFixture(MAGIC_NUMBERS, 'failing.php')->getWarnings()[13][21][0]['message'];

    expect($message)->toContain('protected typed property')
        ->not->toContain('constant');
});

it('exempts a literal assigned whole to a named variable or property in a test file', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'named-values.php');

    expect(violationCountsByLine($file->getWarnings()))->toBe([
        7 => 2,
        8 => 1,
        9 => 1,
        10 => 1,
        16 => 1,
        17 => 1,
        18 => 1,
        19 => 1,
        20 => 1,
    ]);
});

it('reports a named value in application code', function () use ($applicationFixture): void {
    $file = $applicationFixture('named-values.php');

    expect(violationCountsByLine($file->getWarnings()))->toBe([
        6 => 1,
        7 => 2,
        8 => 1,
        9 => 1,
        10 => 1,
        14 => 1,
        15 => 1,
        16 => 1,
        17 => 1,
        18 => 1,
        19 => 1,
        20 => 1,
    ]);
});

it('matches a test file by name as well as by directory', function (): void {
    $staged = stageGeneratedFixture(
            'NamedValuesTest.php',
            (string) file_get_contents(fixturePath('DisallowMagicNumbersSniff', 'named-values.php'))
        );

    expect(array_keys(analyzeWithSniffs([MAGIC_NUMBERS], $staged)->getWarnings()))
        ->toBe([7, 8, 9, 10, 16, 17, 18, 19, 20]);
});

it('takes the test-file patterns from the sniff property', function (): void {
    $assigned = analyzeFixture(MAGIC_NUMBERS, 'named-values.php', static function (object $sniff): void {
        $sniff->testFilePatterns = ['*/production/*'];
    });

    $configured = analyzeFixtureWithRulesetProperties(MAGIC_NUMBERS, 'named-values.php', [
        'testFilePatterns' => ['*/production/*'],
    ]);

    expect(array_keys($assigned->getWarnings()))->toBe([6, 7, 8, 9, 10, 14, 15, 16, 17, 18, 19, 20])
        ->and(array_keys($configured->getWarnings()))->toBe([6, 7, 8, 9, 10, 14, 15, 16, 17, 18, 19, 20]);
});

it('ships the same test-file patterns as the other test-aware sniffs', function (): void {
    expect(sniffInstance(MAGIC_NUMBERS)->testFilePatterns)->toBe(['*/tests/*', '*/Tests/*', '*Test.php']);
});

it('shows a protected typed property, not a constant, as the compliant example', function (): void {
    $guideline = (string) file_get_contents(
            cleanCodeRoot() . '/resources/boost/skills/clean-code/references/naming-semantic-naming-principles.md'
        );
    preg_match('/## Compliant\s+```php\n(.*?)```/s', $guideline, $compliant);

    expect($compliant[1] ?? '')
        ->toContain('protected int $maxClassesPerStudent = 7;')
        ->not->toContain('const');
});

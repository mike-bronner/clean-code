<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const NO_FORMATTER_DIRECTIVES = 'CleanCode.CodeStyle.NoFormatterDirectives';

const NO_FORMATTER_DIRECTIVES_TUPLES = [
    ['line' => 3, 'column' => 1],
    ['line' => 5, 'column' => 1],
    ['line' => 7, 'column' => 1],
    ['line' => 10, 'column' => 1],
    ['line' => 14, 'column' => 1],
    ['line' => 19, 'column' => 5],
    ['line' => 23, 'column' => 4],
    ['line' => 24, 'column' => 4],
    ['line' => 28, 'column' => 5],
    ['line' => 32, 'column' => 11],
    ['line' => 39, 'column' => 9],
    ['line' => 41, 'column' => 1],
];

const NO_FORMATTER_DIRECTIVES_MESSAGES = [
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:on must not be committed; correct style by hand instead',
    'Auto-formatter directive prettier-ignore must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:on must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:on must not be committed; correct style by hand instead',
    'Auto-formatter directive prettier-ignore must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive prettier-ignore must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
];

$analyzeThroughRulesetFile = static function (string $ruleset, string $fixture): LocalFile {
    $directory = sniffFixtureDirectory(NO_FORMATTER_DIRECTIVES);

    $config = new ConfigDouble(['--standard=' . fixturePath($directory, $ruleset)]);
    $config->cache = false;

    $file = new LocalFile(fixturePath($directory, $fixture), new Ruleset($config), $config);
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_FORMATTER_DIRECTIVES);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every directive comment at its own line and column', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    $expected = [];

    foreach (NO_FORMATTER_DIRECTIVES_TUPLES as $tuple) {
        $expected[] = $tuple + ['source' => NO_FORMATTER_DIRECTIVES . '.Found'];
    }

    expect(violationTuples($file))->toBe($expected);
});

it('names the directive each comment carries', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect(violationMessages($file))->toBe(NO_FORMATTER_DIRECTIVES_MESSAGES);
});

it('reports a comment carrying two directives once', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->toHaveKey(41)
        ->and(violationCountsByLine($file->getErrors())[41])->toBe(1);
});

it('reports errors rather than warnings', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect($file->getErrorCount())->toBe(count(NO_FORMATTER_DIRECTIVES_TUPLES))
        ->and($file->getWarnings())->toBe([])
        ->and($file->getWarningCount())->toBe(0);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(NO_FORMATTER_DIRECTIVES_TUPLES), false));
});

it('flags nothing once the shipped directives leave the configured list', function (): void {
    $file = analyzeFixtureWithProperty(
        NO_FORMATTER_DIRECTIVES,
        'failing.php',
        'directives',
        ['@fmt:off']
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('takes both element-node property spellings from a real ruleset file', function () use (
    $analyzeThroughRulesetFile
): void {
    $file = $analyzeThroughRulesetFile('custom-directives.xml', 'passing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 39, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 57, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
    ])->and(violationMessages($file))->toBe([
        'Auto-formatter directive formatter:off must not be committed; correct style by hand instead',
        'Auto-formatter directive @FMT:OFF must not be committed; correct style by hand instead',
    ]);
});

it('trims a configured directive before matching it', function (): void {
    $file = analyzeFixtureWithProperty(
        NO_FORMATTER_DIRECTIVES,
        'failing.php',
        'directives',
        ['  @formatter:off  ']
    );

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 10, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 19, 'column' => 5, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 28, 'column' => 5, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 39, 'column' => 9, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 41, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
    ]);
});

it('ignores a configured directive that is empty once trimmed', function (): void {
    $file = analyzeFixtureWithProperty(
        NO_FORMATTER_DIRECTIVES,
        'failing.php',
        'directives',
        ['', '   ']
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('stays silent on this package\'s own source', function (): void {
    $root = cleanCodeRoot();
    $files = [];

    foreach ([$root . '/CleanCode', $root . '/tests'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $isFixture = str_contains($path, '/fixtures/');

            if ($file->isFile() === true && $file->getExtension() === 'php' && $isFixture === false) {
                $files[] = $path;
            }
        }
    }

    expect($files)->not->toBeEmpty();

    $offenders = [];

    foreach ($files as $path) {
        $errors = analyzeWithSniffs([NO_FORMATTER_DIRECTIVES], $path)->getErrors();

        foreach (array_keys($errors) as $line) {
            $offenders[] = substr($path, strlen($root) + 1) . ':' . $line;
        }
    }

    expect($offenders)->toBe([]);
});

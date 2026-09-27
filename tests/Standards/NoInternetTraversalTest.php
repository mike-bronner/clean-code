<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Util\Tokens;

const NO_INTERNET_TRAVERSAL = 'CleanCode.Testing.NoInternetTraversal';

const NO_INTERNET_TRAVERSAL_WARNING = NO_INTERNET_TRAVERSAL . '.Found';

const NO_INTERNET_TRAVERSAL_FIXTURES = 'NoInternetTraversalSniff';

const NO_INTERNET_TRAVERSAL_SUITE = 'tests/Feature';

$featurePath = static fn (string $fixture): string => stageFixtureOutsideTests(
    fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, $fixture),
    NO_INTERNET_TRAVERSAL_SUITE
);

$featureRun = static fn (string $fixture): LocalFile => analyzeWithSniffs(
    [NO_INTERNET_TRAVERSAL],
    $featurePath($fixture)
);

$expectedWarnings = static fn (): array => array_map(
    static fn (array $position): array => [
        'line' => $position[0],
        'column' => $position[1],
        'source' => NO_INTERNET_TRAVERSAL_WARNING,
    ],
    [
        [8, 11],
        [9, 1],
        [10, 11],
        [11, 11],
        [12, 12],
        [13, 10],
        [14, 9],
        [15, 10],
        [16, 11],
        [17, 12],
        [18, 17],
        [19, 15],
        [20, 16],
        [21, 18],
        [22, 19],
        [23, 13],
        [24, 14],
        [25, 11],
        [26, 11],
        [27, 12],
        [30, 11],
        [33, 17],
    ]
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_INTERNET_TRAVERSAL);
});

it('produces no violations on the compliant fixture', function () use ($featureRun): void {
    $file = $featureRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('says nothing about source it cannot read to the end of', function () use ($featureRun): void {
    $file = $featureRun('malformed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function () use (
    $featureRun,
    $expectedWarnings
): void {
    $file = $featureRun('failing.php');

    expect(warningTuples($file))->toBe($expectedWarnings());
});

it('names the offending primitive in the warning message', function () use ($featureRun): void {
    $warnings = $featureRun('failing.php')->getWarnings();

    expect($warnings[8][11][0]['message'])->toStartWith('curl_init() traverses the internet')
        ->and($warnings[13][10][0]['message'])->toStartWith('CURL_EXEC() traverses the internet')
        ->and($warnings[14][9][0]['message'])->toStartWith('file_get_contents() traverses')
        ->and($warnings[20][16][0]['message'])->toStartWith('new GuzzleHttp\Client traverses')
        ->and($warnings[8][11][0]['message'])->toContain('Http facade')
        ->and($warnings[8][11][0]['message'])->toContain('docs/standards/testing-test-suites.md');
});

it('inspects nothing outside a feature test', function () use ($featureRun): void {
    $inRepo = analyzeWithSniffs(
        [NO_INTERNET_TRAVERSAL],
        fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php')
    );

    $outsideSuite = analyzeWithSniffs(
        [NO_INTERNET_TRAVERSAL],
        stageFixtureOutsideTests(fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php'))
    );

    expect($inRepo->getWarnings())->toBe([])
        ->and($inRepo->getErrors())->toBe([])
        ->and($outsideSuite->getWarnings())->toBe([])
        ->and($outsideSuite->getErrors())->toBe([])
        ->and($featureRun('failing.php')->getWarnings())->toHaveCount(22);
});

it('exposes a configurable feature-test pattern list', function () use ($expectedWarnings): void {
    $staged = stageFixtureOutsideTests(
        fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php'),
        'suites/Acceptance'
    );

    $default = analyzeWithSniffs([NO_INTERNET_TRAVERSAL], $staged);

    $configured = analyzeWithSniffs(
        [NO_INTERNET_TRAVERSAL],
        $staged,
        static function (object $sniff) use ($staged): void {
            $sniff->featureTestPatterns = ['*/' . basename(dirname($staged)) . '/*'];
        }
    );

    expect($default->getWarnings())->toBe([])
        ->and(warningTuples($configured))->toBe($expectedWarnings());
});

it('honours inline suppression', function () use ($featureRun): void {
    $file = $featureRun('suppressed.php');

    expect(warningTuples($file))->toBe([[
        'line' => 15,
        'column' => 13,
        'source' => NO_INTERNET_TRAVERSAL_WARNING,
    ]]);
});

it('reports detection-only warnings', function () use ($featureRun): void {
    $file = $featureRun('failing.php');

    expect($file->getWarningCount())->toBe(22)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('accounts for every string token PHPCS defines', function (): void {
    $path = cleanCodeRoot() . '/CleanCode/Sniffs/Testing/NoInternetTraversalSniff.php';
    $source = (string) file_get_contents($path);
    $declaration = strpos($source, 'private const STRING_TOKENS = [');

    expect($declaration)->not->toBeFalse('the constant is still declared under that name');

    $opening = (int) $declaration;
    $closing = (int) strpos($source, '];', $opening);
    $body = substr($source, $opening, $closing - $opening);

    preg_match_all('/^\s+(T_[A-Z_0-9]+),$/m', $body, $entries);

    $family = array_map(
        static fn (int|string $code): string => is_int($code) === true
            ? (string) token_name($code)
            : (string) preg_replace('/^PHPCS_/', '', $code),
        array_values(Tokens::$stringTokens)
    );

    sort($family);
    $accounted = $entries[1];
    sort($accounted);

    expect($accounted)->toBe($family);
});

it('accounts for every heredoc token PHPCS defines', function (): void {
    $path = cleanCodeRoot() . '/CleanCode/Sniffs/Testing/NoInternetTraversalSniff.php';
    $source = (string) file_get_contents($path);
    $accounted = [];

    foreach (['HEREDOC_OPENERS', 'HEREDOC_BODIES'] as $constant) {
        $declaration = strpos($source, 'private const ' . $constant . ' = [');

        expect($declaration)->not->toBeFalse($constant . ' is still declared under that name');

        $opening = (int) $declaration;
        $closing = (int) strpos($source, '];', $opening);

        preg_match_all('/^\s+(T_[A-Z_0-9]+),$/m', substr($source, $opening, $closing - $opening), $entries);

        $accounted = array_merge($accounted, $entries[1]);
    }

    $closers = ['T_END_HEREDOC', 'T_END_NOWDOC'];

    $whole = array_map(
        static fn (int|string $code): string => is_int($code) === true
            ? (string) token_name($code)
            : (string) preg_replace('/^PHPCS_/', '', $code),
        array_values(Tokens::$heredocTokens)
    );

    $family = array_values(array_diff($whole, $closers));

    sort($family);
    sort($accounted);

    expect($accounted)->toBe($family)
        ->and(array_intersect($closers, $whole))
        ->toHaveCount(2, 'the closing tokens the scope_closer stands in for are still the two named here');
});

it('sees a qualified class name in the pre-8.0 spelling', function (): void {
    $file = parseFixture(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php');
    $tokens = $file->getTokens();
    $codes = [];

    foreach ($tokens as $token) {
        if ($token['line'] === 21) {
            $codes[] = $token['type'];
        }
    }

    expect($codes)->toContain('T_NS_SEPARATOR')
        ->and($codes)->toContain('T_STRING')
        ->and($codes)->not->toContain('T_NAME_FULLY_QUALIFIED');
});

it('reports the violation end to end through the installed package', function () use (
    $featurePath
): void {
    $staged = installedSniffRun(NO_INTERNET_TRAVERSAL, $featurePath('failing.php'));
    $inRepo = installedSniffRun(
        NO_INTERNET_TRAVERSAL,
        fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php')
    );
    $passing = installedSniffRun(NO_INTERNET_TRAVERSAL, $featurePath('passing.php'));

    expect(array_column($staged['messages'], 'source'))->toHaveCount(22)
        ->each->toBe(NO_INTERNET_TRAVERSAL_WARNING)
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(1)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});

<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const SUPERGLOBALS = 'CleanCode.Controversial.Superglobals';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SUPERGLOBALS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every superglobal access at its own position', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    $positions = array_map(
            static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
            violationTuples($file)
        );

    expect($positions)->toBe([
        [13, 9],
        [14, 9],
        [15, 9],
        [16, 9],
        [17, 9],
        [18, 9],
        [19, 9],
        [20, 9],
        [21, 9],
        [28, 9],
        [29, 9],
        [30, 9],
        [31, 9],
        [32, 9],
        [33, 9],
        [34, 9],
        [42, 17],
        [43, 19],
        [44, 27],
        [44, 27],
        [45, 30],
        [47, 1],
        [48, 1],
        [56, 30],
        [57, 33],
        [67, 20],
    ]);
});

it('flags every name PHPMD carries, aliases included', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    $named = [];

    foreach ($file->getErrors() as $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $named[] = explode(' ', $violation['message'])[1];
            }
        }
    }

    $named = array_values(array_unique($named));
    sort($named);

    expect($named)->toBe([
        '$GLOBALS',
        '$HTTP_COOKIE_VARS',
        '$HTTP_ENV_VARS',
        '$HTTP_GET_VARS',
        '$HTTP_POST_FILES',
        '$HTTP_POST_VARS',
        '$HTTP_SERVER_VARS',
        '$HTTP_SESSION_VARS',
        '$_COOKIE',
        '$_ENV',
        '$_FILES',
        '$_GET',
        '$_POST',
        '$_REQUEST',
        '$_SERVER',
        '$_SESSION',
    ]);
});

it('ignores an escaped superglobal beside a live one', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');
    $messages = array_column($file->getErrors()[45][30], 'message');

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toStartWith('Superglobal $_COOKIE ');
});

it('counts backslash pairs when deciding whether an interpolation is live', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'escape-pairs.php');

    $reported = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $reported[] = [$line, explode(' ', $violation['message'])[1]];
            }
        }
    }

    expect($reported)->toBe([
        [42, '$_POST'],
        [48, '$_COOKIE'],
        [59, '$_REQUEST'],
    ]);
});

it('flags a plain parameter as it flags the same parameter in a function', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'parameters.php');

    $reported = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $column => $violations) {
            foreach ($violations as $violation) {
                $reported[] = [$line, $column, explode(' ', $violation['message'])[1]];
            }
        }
    }

    expect($reported)->toBe([
        [61, 39, '$HTTP_GET_VARS'],
        [63, 16, '$HTTP_GET_VARS'],
        [71, 41, '$HTTP_ENV_VARS'],
        [82, 28, '$HTTP_COOKIE_VARS'],
        [84, 12, '$HTTP_COOKIE_VARS'],
    ]);
});

it('stays silent on a promoted parameter', function (): void {
    $fixture = file(fixturePath(sniffFixtureDirectory(SUPERGLOBALS), 'parameters.php'));
    $promotedLines = array_keys(array_filter(
            $fixture,
            static fn (string $line): bool => str_contains($line, 'public $HTTP_POST_VARS = [],')
            || str_contains($line, 'protected array $HTTP_SERVER_VARS = []')
        ));

    expect($promotedLines)->toHaveCount(2);

    $file = analyzeFixture(SUPERGLOBALS, 'parameters.php');
    $reportedLines = array_column(violationTuples($file), 'line');

    foreach ($promotedLines as $promotedLine) {
        expect($reportedLines)->not->toContain($promotedLine + 1);
    }
});

it('reports every violation under one message code', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    $sources = array_unique(array_column(violationTuples($file), 'source'));

    expect($sources)->toBe([SUPERGLOBALS . '.Found']);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports the shapes PHPMD misses', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'divergences.php');

    $positions = array_map(
            static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
            violationTuples($file)
        );

    expect($positions)->toBe([
        [18, 19],
        [19, 20],
        [21, 11],
        [22, 25],
        [34, 12],
    ]);
});

it('stays silent on a static property access', function (): void {
    $fixture = file(fixturePath(sniffFixtureDirectory(SUPERGLOBALS), 'divergences.php'));
    $staticAccessLines = array_keys(array_filter(
            $fixture,
            static fn (string $line): bool => str_contains($line, 'StaticHolder::$_POST')
        ));

    expect($staticAccessLines)->toHaveCount(1);

    $staticAccessLine = ($staticAccessLines[0] + 1);
    $file = analyzeFixture(SUPERGLOBALS, 'divergences.php');

    expect(array_column(violationTuples($file), 'line'))->not->toContain($staticAccessLine);
});

it('measures the vendor candidates the comparison table rejects', function (): void {
    $reportedLines = static function (string $standard, string $source, string $fixture): array {
        $file = analyzeWithStandard($standard, fixturePath(sniffFixtureDirectory(SUPERGLOBALS), $fixture));

        $lines = array_keys(array_filter(
                allViolationSourcesByLine($file),
                static fn (array $sources): bool => in_array($source, $sources, true)
            ));

        sort($lines);

        return $lines;
    };

    $slevomat = 'SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable'
        . '.DisallowedSuperGlobalVariable';
    $generic = 'Generic.PHP.DisallowRequestSuperglobal.Found';

    $slevomatLines = $reportedLines('SlevomatCodingStandard', $slevomat, 'failing.php');
    $violations = violationTuples(analyzeFixture(SUPERGLOBALS, 'failing.php'));

    $fixture = file(fixturePath(sniffFixtureDirectory(SUPERGLOBALS), 'failing.php'));
    $missed = array_filter(
            array_column($violations, 'line'),
            static fn (int $line): bool => in_array($line, $slevomatLines, true) === false
        );
    $aliases = array_filter(
            $missed,
            static fn (int $line): bool => str_contains($fixture[$line - 1], '$HTTP_')
        );

    expect($slevomatLines)->toBe([13, 14, 15, 16, 17, 18, 19, 20, 21, 56, 57, 67])
        ->and($reportedLines('SlevomatCodingStandard', $slevomat, 'passing.php'))->toHaveCount(2)
        ->and($reportedLines('Generic', $generic, 'failing.php'))->toBe([20])
        ->and($violations)->toHaveCount(26)
        ->and($aliases)->toHaveCount(7)
        ->and(array_diff($missed, $aliases))->toHaveCount(7);
});

it('reports nothing from a string whose interpolations cannot be read', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(SUPERGLOBALS, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
                'preg_match_all',
                static fn (): array => violationSourcesByLine(analyzeFixture(SUPERGLOBALS, 'failing.php')->getErrors()),
                static fn (string $pattern): bool => str_contains($pattern, '(?P<name>')
            );
    });

    expect(array_keys($expected))->toContain(42)
        ->and(array_keys($degraded))->not->toContain(42)
        ->and($diagnostics)->toBe([]);
});

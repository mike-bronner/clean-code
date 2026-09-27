<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const ACTION_METHOD_RETURN = 'CleanCode.Naming.ActionMethodReturn';

const ACTION_METHOD_RETURN_WARNING = ACTION_METHOD_RETURN . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ACTION_METHOD_RETURN);
});

it('passes the standard it belongs to', function (): void {
    $run = installedPhpcsRun(
        'CleanCode',
        cleanCodeRoot() . '/CleanCode/Sniffs/Naming/ActionMethodReturnSniff.php'
    );

    expect(array_column($run['messages'], 'source'))->toBe([])
        ->and($run['status'])->toBe(0);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every action method that returns a value in the failing fixture', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 12, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 21, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 30, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 40, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 48, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 58, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 70, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 78, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 86, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 96, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 104, 'column' => 28, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 112, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 122, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 135, 'column' => 30, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 143, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 148, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 158, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 168, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 181, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 186, 'column' => 30, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 191, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 200, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 220, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 225, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

it('is answered by no nearby vendor standard', function (): void {
    $candidates = [
        'PEAR.NamingConventions.ValidFunctionName',
        'PSR1.Methods.CamelCapsMethodName',
        'SlevomatCodingStandard.TypeHints.ReturnTypeHint',
        'Squiz.NamingConventions.ValidFunctionName',
    ];
    $path = fixturePath('ActionMethodReturnSniff', 'failing.php');
    $reported = [];

    foreach (['PEAR', 'PSR12', 'SlevomatCodingStandard', 'Squiz'] as $standard) {
        foreach (allViolationSourcesByLine(analyzeWithStandard($standard, $path)) as $line => $sources) {
            foreach ($sources as $source) {
                foreach ($candidates as $candidate) {
                    if (str_starts_with($source, $candidate . '.') === true) {
                        $reported[$candidate][$line] = $line;
                    }
                }
            }
        }
    }

    ksort($reported);
    $reported = array_map('array_values', $reported);

    expect($reported)->toBe([
        'PEAR.NamingConventions.ValidFunctionName' => [86],
        'PSR1.Methods.CamelCapsMethodName' => [86],
        'SlevomatCodingStandard.TypeHints.ReturnTypeHint' => [48, 58, 70, 122, 148, 200, 220, 225],
        'Squiz.NamingConventions.ValidFunctionName' => [86],
    ]);
});

it('names the method and the matched verb in the message', function (): void {
    $warnings = analyzeFixture(ACTION_METHOD_RETURN, 'failing.php')->getWarnings();

    expect($warnings[12][21][0]['message'])
        ->toContain('setReference()')
        ->toContain('"set"');
});

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'failing.php');

    expect($file->getWarningCount())->toBe(24)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

it('exempts a fluent interface by default', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'fluent.php');

    expect(warningTuples($file))->toBe([]);
});

it('flags a fluent interface when allowFluentInterface is off', function (): void {
    $file = analyzeFixtureWithProperty(ACTION_METHOD_RETURN, 'fluent.php', 'allowFluentInterface', false);

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 25, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 32, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 39, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 46, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 58, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 69, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 81, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

it('widens to fluent declarations only when allowFluentInterface is off', function (): void {
    $file = analyzeFixtureWithProperty(ACTION_METHOD_RETURN, 'passing.php', 'allowFluentInterface', false);

    expect(warningTuples($file))->toBe([
        ['line' => 92, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 99, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 106, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 118, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 179, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 191, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 240, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

it('turns the fluent exemption off from a ruleset property', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        ACTION_METHOD_RETURN,
        'fluent.php',
        ['allowFluentInterface' => 'false']
    );

    expect(warningTuples($file))->toHaveCount(8);
});

it('ignores verbs outside the configured prefix list', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'prefixes.php');

    expect(warningTuples($file))->toBe([]);
});

it('flags configured verbs when actionPrefixes is retuned', function (): void {
    $file = analyzeFixtureWithProperty(
        ACTION_METHOD_RETURN,
        'prefixes.php',
        'actionPrefixes',
        ['archive', 'publish']
    );

    expect(warningTuples($file))->toBe([
        ['line' => 15, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 20, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

it('matches nothing for an empty verb in the configured list', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        ACTION_METHOD_RETURN,
        'passing.php',
        ['actionPrefixes[]' => 'set,,save']
    );

    expect(warningTuples($file))->toBe([]);
});

it('ships the suggested prefix list and the exemption switched on', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = new $ruleset->sniffCodes[ACTION_METHOD_RETURN]();

    expect($sniff->actionPrefixes)->toBe([
        'add',
        'apply',
        'attach',
        'clear',
        'delete',
        'detach',
        'post',
        'remove',
        'reset',
        'save',
        'send',
        'set',
        'store',
        'update',
    ])
        ->and($sniff->allowFluentInterface)->toBeTrue();
});

it('reads a return type that cannot be normalised as written', function (): void {
    $expected = allViolationSourcesByLine(analyzeFixture(ACTION_METHOD_RETURN, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_replace',
            static fn (): array => allViolationSourcesByLine(analyzeFixture(ACTION_METHOD_RETURN, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/\s+/'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});

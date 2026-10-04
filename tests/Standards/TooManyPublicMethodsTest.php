<?php

declare(strict_types=1);

const TOO_MANY_PUBLIC_METHODS = 'CleanCode.Classes.TooManyPublicMethods';

const TOO_MANY_PUBLIC_METHODS_ERROR = TOO_MANY_PUBLIC_METHODS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_PUBLIC_METHODS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every class past the threshold in the failing fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 32, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 51, 'column' => 10, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 69, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 88, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ]);
});

it('names the class, the count, and the threshold in the message', function (): void {
    $errors = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'failing.php')->getErrors();

    expect($errors[13][1][0]['message'])
        ->toContain('The class ElevenPublicMethods has 11 public methods')
        ->toContain('under 10');
});

it('reports detection-only errors', function (): void {
    $file = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports at PHPMD\'s default threshold of ten', function (): void {
    $file = analyzeFixtureWithRulesetProperties(TOO_MANY_PUBLIC_METHODS, 'configured.php', []);

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ]);
});

it('applies a lowered maxmethods given as a ruleset string', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            TOO_MANY_PUBLIC_METHODS,
            'configured.php',
            ['maxmethods' => '3']
        );

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 55, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ]);
});

it('applies the narrower ignore pattern phpmd.org documents', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            TOO_MANY_PUBLIC_METHODS,
            'configured.php',
            ['ignorepattern' => '(^(set|get))i']
        );

    $errors = $file->getErrors();

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ])->and($errors[34][1][0]['message'])->toContain('has 12 public methods');
});

it('exempts nothing when the ignore pattern is emptied by a ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(TOO_MANY_PUBLIC_METHODS, 'configured.php', ['ignorepattern' => '']);

    $errors = $file->getErrors();

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ])->and($errors[34][1][0]['message'])->toContain('has 14 public methods');
});

it('falls back to the default threshold when maxmethods is emptied by a ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(TOO_MANY_PUBLIC_METHODS, 'configured.php', ['maxmethods' => '']);

    $errors = $file->getErrors();

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ])->and($errors[14][1][0]['message'])->toContain('under 10');
});

it('exempts nothing when the ignore pattern is malformed', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    }, E_WARNING);

    try {
        $file = analyzeFixtureWithRulesetProperties(
                TOO_MANY_PUBLIC_METHODS,
                'configured.php',
                ['ignorepattern' => 'not-a-pattern']
            );
    } finally {
        restore_error_handler();
    }

    $errors = $file->getErrors();

    expect($raised)->not->toBeEmpty()
        ->and($raised[0])->toContain('Delimiter must not be alphanumeric')
        ->and(violationTuples($file))->toBe([
            ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
            ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ])
        ->and($errors[34][1][0]['message'])->toContain('has 14 public methods');
});

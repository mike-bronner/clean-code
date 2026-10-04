<?php

declare(strict_types=1);

const TOO_MANY_METHODS = 'CleanCode.CodeSize.TooManyMethods';

const TOO_MANY_METHODS_ERROR = TOO_MANY_METHODS . '.MaxExceeded';

const TOO_MANY_METHODS_PATTERN_ERROR = TOO_MANY_METHODS . '.InvalidIgnorePattern';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_METHODS);
});

it('carries PHPMD\'s defaults through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TOO_MANY_METHODS]];

    expect($sniff->maxmethods)->toBe(25)
        ->and($sniff->ignorepattern)->toBe('(^(set|get|is|has|with))i');
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags a class one method past the maximum on its declaration', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_ERROR],
    ])->and($file->getWarnings())->toBe([]);
});

it('names the class and the counts in the message', function (): void {
    $errors = analyzeFixture(TOO_MANY_METHODS, 'failing.php')->getErrors();

    expect($errors[3][1][0]['message'])
        ->toContain('OneOverTheMaximum')
        ->toContain('26')
        ->toContain('25');
});

it('excludes case-insensitive prefix matches from the count', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'prefix-matching.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);

    $counted = analyzeFixture(
            TOO_MANY_METHODS,
            'prefix-matching.php',
            static function (object $sniff): void {
                $sniff->maxmethods = 0;
            }
        );

    expect(violationTuples($counted))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_ERROR],
    ])->and($counted->getErrors()[3][1][0]['message'])
        ->toContain('declares 25 counted methods');
});

it('counts only the methods the class declares itself', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'nested.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('exposes a configurable maximum', function (): void {
    expect(violationTuples(analyzeFixture(TOO_MANY_METHODS, 'configured.php')))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_ERROR],
    ]);

    $raised = analyzeFixture(
            TOO_MANY_METHODS,
            'configured.php',
            static function (object $sniff): void {
                $sniff->maxmethods = 26;
            }
        );

    expect($raised->getErrors())->toBe([]);
});

it('exposes a configurable ignore pattern', function (): void {
    $ignored = analyzeFixture(
            TOO_MANY_METHODS,
            'configured.php',
            static function (object $sniff): void {
                $sniff->ignorepattern = '(^handle)i';
            }
        );

    expect($ignored->getErrors())->toBe([]);
});

it('reports an unusable ignore pattern instead of miscounting', function (): void {
    $file = analyzeFixture(
            TOO_MANY_METHODS,
            'broken-pattern.php',
            static function (object $sniff): void {
                $sniff->ignorepattern = '(^(set|get';
            }
        );

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_PATTERN_ERROR],
    ]);
});

it('emits no PHP warning while rejecting the pattern', function (): void {
    $diagnostics = [];

    set_error_handler(static function (int $errno, string $message) use (&$diagnostics): bool {
        $diagnostics[] = $message;

        return true;
    });

    try {
        analyzeFixture(
                TOO_MANY_METHODS,
                'broken-pattern.php',
                static function (object $sniff): void {
                    $sniff->ignorepattern = '(^(set|get';
                }
            );
    } finally {
        restore_error_handler();
    }

    expect($diagnostics)->toBe([]);
});

it('restores the error handler it installed', function (): void {
    $before = set_error_handler(null);
    restore_error_handler();

    analyzeFixture(TOO_MANY_METHODS, 'passing.php');

    $after = set_error_handler(null);
    restore_error_handler();

    expect($after)->toBe($before);
});

it('passes over a half-written class', function (string $fixture): void {
    $file = analyzeFixture(TOO_MANY_METHODS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with(['truncated.php', 'nameless.php']);

it('reports detection-only errors', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'failing.php');

    expect($file->getErrorCount())->toBe(1)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

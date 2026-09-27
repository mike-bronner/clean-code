<?php

declare(strict_types=1);

const EXCESSIVE_PUBLIC_COUNT = 'CleanCode.Metrics.ExcessivePublicCount';

const EXCESSIVE_PUBLIC_COUNT_ERROR = 'CleanCode.Metrics.ExcessivePublicCount.Found';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_PUBLIC_COUNT);
});

it('flags a class or trait whose public surface reaches the threshold', function (): void {
    expect(violationTuples(analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'failing.php')))->toBe([
        ['line' => 14, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 198, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 308, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

it('names the type, its public count, and the threshold', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'failing.php')->getErrors();

    expect($errors[14][1][0]['message'])->toBe(
        'The class AtTheThreshold has 45 public methods and attributes.'
            . ' Consider reducing the number of public items to less than 45'
    );
    expect($errors[308][1][0]['message'])->toBe(
        'The trait WideTrait has 45 public methods and attributes.'
            . ' Consider reducing the number of public items to less than 45'
    );
});

it('treats the threshold as inclusive', function (): void {
    expect(violationTuples(analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'boundaries.php')))->toBe([
        ['line' => 193, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 377, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

it('counts only the public members a class or trait declares itself', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'passing.php', static function (object $sniff): void {
        $sniff->minimum = 44;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 196, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 1063, 'column' => 20, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

it('reports the public surface PDepend fails to model', function (): void {
    expect(violationTuples(analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'divergences.php')))->toBe([
        ['line' => 15, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 68, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 128, 'column' => 20, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

it('names an anonymous class by its kind', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'divergences.php')->getErrors();

    expect($errors[128][20][0]['message'])->toBe(
        'The anonymous class has 45 public methods and attributes.'
            . ' Consider reducing the number of public items to less than 45'
    );
});

it('reports at a lowered minimum', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'configured.php', static function (object $sniff): void {
        $sniff->minimum = 3;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

it('stays silent above the largest public surface in the file', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'configured.php', static function (object $sniff): void {
        $sniff->minimum = 4;
    });

    expect(violationTuples($file))->toBe([]);
});

it('accepts a minimum supplied as a string, the way a ruleset supplies it', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'configured.php', static function (object $sniff): void {
        $sniff->minimum = '3';
    });

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

it('stays silent on an unterminated declaration without raising a PHP error', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    });

    try {
        $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'unclosed-class.php');
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([]);
    expect(violationTuples($file))->toBe([]);
});

it('offers no fixer', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false, false, false]);
    expect($file->getFixableCount())->toBe(0);
});

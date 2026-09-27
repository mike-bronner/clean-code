<?php

declare(strict_types=1);

const EXCESSIVE_CLASS_COMPLEXITY = 'CleanCode.Metrics.ExcessiveClassComplexity';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_CLASS_COMPLEXITY);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('measures each class in the compliant fixture exactly', function (): void {
    $errors = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'passing.php',
        static function (object $sniff): void {
            $sniff->maximum = 0;
        }
    )->getErrors();

    expect($errors[20][1][0]['message'])
        ->toContain('Class AtOneBelowTheMaximum has a weighted method count of 49,')
        ->and($errors[82][1][0]['message'])
        ->toContain('Class UncountedConstructs has a weighted method count of 4,')
        ->and($errors[164][10][0]['message'])
        ->toContain('Class AbstractMethods has a weighted method count of 4,')
        ->and($errors[181][1][0]['message'])
        ->toContain('Class KeywordBooleanOperators has a weighted method count of 8,')
        ->and($errors[209][1][0]['message'])
        ->toContain('Class InlineFunctionBodies has a weighted method count of 3,')
        ->and($errors[237][1][0]['message'])
        ->toContain('Class AnonymousClassArguments has a weighted method count of 5,')
        ->and($errors[289][1][0]['message'])
        ->toContain('Class MemberDefaults has a weighted method count of 3,')
        ->and($errors[327][1][0]['message'])
        ->toContain('Class BareBlocks has a weighted method count of 2,')
        ->and(array_keys($errors))->toBe([20, 82, 164, 181, 209, 237, 289, 327]);
});

it('flags each over-threshold class at its declaration', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
        ['line' => 77, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports the measured weighted method count and the maximum', function (): void {
    $errors = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'failing.php')->getErrors();

    expect($errors[14][1][0]['message'])
        ->toBe(
            'Class AtExactlyTheMaximum has a weighted method count of 50, at or above the '
                . 'configured maximum of 50; split it into smaller classes (see '
                . 'docs/phpmd/codesize-excessiveclasscomplexity.md)'
        )
        ->and($errors[77][1][0]['message'])
        ->toBe(
            'Class WellAboveTheMaximum has a weighted method count of 63, at or above the '
                . 'configured maximum of 50; split it into smaller classes (see '
                . 'docs/phpmd/codesize-excessiveclasscomplexity.md)'
        );
});

it('stays silent on every declaration that is not a named class', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'not-a-class.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('measures each class in the not-a-class fixture exactly', function (): void {
    $errors = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'not-a-class.php',
        static function (object $sniff): void {
            $sniff->maximum = 0;
        }
    )->getErrors();

    expect($errors[45][1][0]['message'])
        ->toContain('Class UsesHugeTrait has a weighted method count of 0,')
        ->and($errors[135][1][0]['message'])
        ->toContain('Class HoldsHugeAnonymousClass has a weighted method count of 1,')
        ->and($errors[169][1][0]['message'])
        ->toContain('Class HoldsHugeNestedFunction has a weighted method count of 1,')
        ->and(array_keys($errors))->toBe([45, 135, 169]);
});

it('passes over a class the tokenizer never closed', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'truncated.php',
        static function (object $sniff): void {
            $sniff->maximum = 0;
        }
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports at the configured maximum and stays silent one above it', function (): void {
    $atThreshold = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = 13;
        }
    );

    $aboveThreshold = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = 14;
        }
    );

    expect(violationTuples($atThreshold))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ])->and($aboveThreshold->getErrors())->toBe([]);
});

it('accepts the maximum as the string a ruleset supplies', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = '13';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ]);
});

it('over-reports rather than falling silent on an unreadable maximum', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = 'forty';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ]);
});

it('defaults the maximum to PHPMD\'s own 50', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[EXCESSIVE_CLASS_COMPLEXITY]];

    expect($sniff->maximum)->toBe(50);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});

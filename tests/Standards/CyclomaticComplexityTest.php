<?php

declare(strict_types=1);

pest()->group('arch');

const CYCLOMATIC_COMPLEXITY = 'CleanCode.Metrics.CyclomaticComplexity';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CYCLOMATIC_COMPLEXITY);
});

it('reports the violation end to end through the installed package', function (string $standard): void {
    $violations = installedPhpcsViolations(
            $standard,
            fixturePath('CyclomaticComplexitySniff', 'failing.php'),
            CYCLOMATIC_COMPLEXITY . '.Found'
        );

    expect(array_column($violations, 'line'))->toBe([46, 93, 110, 152, 210])
        ->and($violations[0]['message'])
        ->toContain('atExactlyTheReportLevel() has a cyclomatic complexity of 10')
        ->and($violations[1]['message'])
        ->toContain('booleanOperatorChain() has a cyclomatic complexity of 12')
        ->and($violations[2]['message'])
        ->toContain('mergesItsClosure() has a cyclomatic complexity of 11')
        ->and($violations[3]['message'])
        ->toContain('deeplyNested() has a cyclomatic complexity of 19')
        ->and($violations[4]['message'])
        ->toContain('heavyStandalone() has a cyclomatic complexity of 10');
})->with([
    'the ruleset path' => fn (): string => cleanCodeRoot() . '/CleanCode/ruleset.xml',
    'the standard by name' => 'CleanCode',
]);

it('stays silent on its compliant fixture through the installed package', function (): void {
    $run = installedSniffFixtureRun(CYCLOMATIC_COMPLEXITY, 'passing.php');

    expect($run['messages'])->toBe([])
        ->and($run['status'])->toBe(0);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('measures each declaration in the compliant fixture exactly', function (): void {
    $file = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'passing.php', 'reportLevel', 1);

    expect(measuredComplexities($file))->toBe([
        'method describe()' => 1,
        'method baseline()' => 1,
        'method drawn()' => 1,
        'method atOneBelowTheReportLevel()' => 9,
        'method booleanChainRemoved()' => 2,
        'method uncounted()' => 2,
        'method elseAndDefaultOnly()' => 2,
        'method nestedInsideArms()' => 3,
        'method withDefault()' => 3,
        'method stackedLabels()' => 4,
        'method wordForms()' => 3,
        'method ternaries()' => 4,
        'method everyLoopAndCatch()' => 7,
        'method elseIfSpellings()' => 4,
        'method hostsInlineFunctions()' => 5,
        'method hostsNamedFunction()' => 2,
        'function nested()' => 9,
        'method makes()' => 3,
        'method __construct()' => 1,
        'method inner()' => 3,
        'method measure()' => 1,
        'method withBareBlock()' => 2,
        'method fromTrait()' => 2,
        'method label()' => 2,
        'function standalone()' => 3,
    ]);
});

it('flags each over-level declaration at its declaration line', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 46, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 93, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 110, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 152, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 210, 'column' => 1, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports the measured complexity and the report level', function (): void {
    $errors = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'failing.php')->getErrors();

    expect($errors[46][12][0]['message'])
        ->toBe(
            'The method atExactlyTheReportLevel() has a cyclomatic complexity of 10, reaching '
                . 'the report level of 10; break it into smaller declarations '
                . '(see resources/boost/guidelines/codesize-cyclomaticcomplexity.md)'
        )
        ->and($errors[93][12][0]['message'])
        ->toContain('The method booleanOperatorChain() has a cyclomatic complexity of 12,')
        ->and($errors[110][12][0]['message'])
        ->toContain('The method mergesItsClosure() has a cyclomatic complexity of 11,')
        ->and($errors[152][12][0]['message'])
        ->toContain('The method deeplyNested() has a cyclomatic complexity of 19,')
        ->and($errors[210][1][0]['message'])
        ->toContain('The function heavyStandalone() has a cyclomatic complexity of 10,');
});

it('reports an anonymous class method PHPMD passes over', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 20, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ])->and(measuredComplexities($file))->toBe(['method heavy()' => 11]);
});

it('reports at the configured level and stays silent one above it', function (): void {
    $atLevel = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'configured.php', 'reportLevel', 5);
    $aboveLevel = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'configured.php', 'reportLevel', 6);

    expect(violationTuples($atLevel))->toBe([
        ['line' => 16, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ])->and($aboveLevel->getErrors())->toBe([]);
});

it('accepts the report level as the string a ruleset supplies', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            CYCLOMATIC_COMPLEXITY,
            'configured.php',
            ['reportLevel' => '5']
        );

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ]);
});

it('falls back to the default level on an unusable configured value', function (mixed $value): void {
    $failing = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'failing.php', 'reportLevel', $value);
    $configured = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'configured.php', 'reportLevel', $value);

    expect(violationTuples($failing))->toHaveCount(5)
        ->and($configured->getErrors())->toBe([]);
})->with([['ten'], [''], [null], ['0'], ['-1']]);

it('survives an empty report-level property from a ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            CYCLOMATIC_COMPLEXITY,
            'configured.php',
            ['reportLevel' => '']
        );

    expect($file->getErrors())->toBe([]);
});

it('defaults the report level to PHPMD\'s own 10', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[CYCLOMATIC_COMPLEXITY]];

    expect($sniff->reportLevel)->toBe(10);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('matches PHPMD on a real source file in this repository', function (): void {
    $file = analyzeWithSniffs(
            [CYCLOMATIC_COMPLEXITY],
            cleanCodeRoot() . '/CleanCode/Sniffs/Functions/DisallowBooleanArgumentFlagSniff.php',
            static function (object $sniff): void {
                $sniff->reportLevel = 1;
            }
        );

    expect(measuredComplexities($file))->toBe([
        'method register()' => 1,
        'method process()' => 7,
        'method isIgnoredName()' => 3,
        'method isExceptedClass()' => 1,
        'method enclosingClassName()' => 3,
        'method describe()' => 1,
        'method isBooleanFlag()' => 3,
        'method isBooleanType()' => 1,
        'method isBooleanDefault()' => 1,
    ]);
});

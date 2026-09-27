<?php

declare(strict_types=1);

const REDUNDANT_NAMESPACE_SUFFIX = 'CleanCode.Naming.RedundantNamespaceSuffix';

const REDUNDANT_NAMESPACE_SUFFIX_FOUND = REDUNDANT_NAMESPACE_SUFFIX . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REDUNDANT_NAMESPACE_SUFFIX);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every redundant suffix at its own line', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 6, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 12, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 18, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 24, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 30, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 36, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 42, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 48, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 54, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 58, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 62, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 69, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 75, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 81, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
        ['line' => 87, 'column' => 5, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
    ]);
});

it('names the repeated segment, the suffix, and the kind of declaration', function (): void {
    $messages = violationMessages(analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php'));

    expect($messages[0])->toContain('Class BillingService repeats its own Services namespace segment')
        ->and($messages[0])->toContain('drop the redundant "Service" suffix')
        ->and($messages[4])->toContain('Class RevenueAnalysis repeats its own Analyses namespace segment')
        ->and($messages[4])->toContain('drop the redundant "Analysis" suffix')
        ->and($messages[8])->toContain('Interface RefundablePayment')
        ->and($messages[9])->toContain('Trait RecordsPayment')
        ->and($messages[10])->toContain('Enum CapturedPayment')
        ->and($messages[14])->toContain('Class Movie repeats its own Movies namespace segment')
        ->and($messages[14])->toContain('drop the redundant "Movie" suffix');
});

it('reads a file-scoped namespace, not only a braced block', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'file-scoped-namespace.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 1, 'source' => REDUNDANT_NAMESPACE_SUFFIX_FOUND],
    ])->and(violationMessages($file)[0])
        ->toContain('Class BillingService repeats its own Services namespace segment');
});

it('reports the deepest matching ancestor, not only the immediate parent', function (): void {
    $messages = violationMessages(analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php'));

    expect($messages[5])->toContain('Class TokenController repeats its own Controllers namespace segment');
});

it('reports a declaration once, against its deepest matching ancestor', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->each->toBe(1)
        ->and(violationMessages($file)[13])
        ->toContain('Class BillingService repeats its own Service namespace segment');
});

it('reports detection-only errors', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php');

    expect($file->getErrorCount())->toBe(15)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('passes over a declaration keyword with no name', function (): void {
    $file = analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('measures the Slevomat naming sniffs as unaffected by the namespace', function (): void {
    $file = analyzeWithStandard(
        'SlevomatCodingStandard',
        fixturePath(sniffFixtureDirectory(REDUNDANT_NAMESPACE_SUFFIX), 'vendor-superfluous-naming.php')
    );

    $superfluous = [];

    foreach (allViolationSourcesByLine($file) as $line => $sources) {
        foreach ($sources as $source) {
            if (str_starts_with($source, 'SlevomatCodingStandard.Classes.Superfluous')) {
                $superfluous[$line][] = $source;
            }
        }
    }

    $interface = 'SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix';
    $trait = 'SlevomatCodingStandard.Classes.SuperfluousTraitNaming.SuperfluousSuffix';
    $abstract = 'SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix';
    $exception = 'SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix';
    $error = 'SlevomatCodingStandard.Classes.SuperfluousErrorNaming.SuperfluousSuffix';

    $reported = violationTuples(analyzeFixture(REDUNDANT_NAMESPACE_SUFFIX, 'vendor-superfluous-naming.php'));

    expect($superfluous)->toBe([
        14 => [$interface],
        18 => [$trait],
        22 => [$abstract],
        26 => [$exception],
        30 => [$error],
        40 => [$interface],
        44 => [$trait],
        48 => [$abstract],
        52 => [$exception],
        56 => [$error],
    ])->and(array_column($reported, 'line'))->toBe([10]);
});

it('leaves the package own source alone', function (): void {
    $paths = glob(cleanCodeRoot() . '/CleanCode/*/*/*.php');

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $file = analyzeWithSniffs([REDUNDANT_NAMESPACE_SUFFIX], $path);

        expect($file->numTokens)->toBeGreaterThan(0)
            ->and($file->getErrors())->toBe([])
            ->and($file->getWarnings())->toBe([]);
    }
});

it('reports the violation end to end through the installed package', function (): void {
    expect(installedSniffFixtureRun(REDUNDANT_NAMESPACE_SUFFIX, 'failing.php')['status'])->toBe(1)
        ->and(installedSniffFixtureRun(REDUNDANT_NAMESPACE_SUFFIX, 'passing.php')['status'])->toBe(0);
});

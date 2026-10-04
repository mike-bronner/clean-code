<?php

declare(strict_types=1);

const TEST_SUITE_NAMESPACE = 'CleanCode.Testing.TestSuiteNamespace';

const TEST_SUITE_NAMESPACE_DIRECTORY = TEST_SUITE_NAMESPACE . '.DirectoryMismatch';

const TEST_SUITE_NAMESPACE_NAMESPACE = TEST_SUITE_NAMESPACE . '.NamespaceMismatch';

const TEST_SUITE_UNIT_FIXTURES = 'tests/Unit/';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TEST_SUITE_NAMESPACE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every suite namespace with no matching directory', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 11, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 17, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 23, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 32, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 42, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 51, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 61, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 69, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);
});

it('flags a test under a suite directory whose namespace disagrees', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, TEST_SUITE_UNIT_FIXTURES . 'suite-mismatch.php');

    expect(warningTuples($file))->toBe([
        ['line' => 8, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_NAMESPACE],
        ['line' => 15, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_NAMESPACE],
    ]);
});

it('leaves a test whose namespace matches its suite directory alone', function (string $fixture): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'the unit suite' => TEST_SUITE_UNIT_FIXTURES . 'suite-compliant.php',
    'the feature suite' => 'tests/Feature/suite-compliant.php',
]);

it('does not anchor the test root on an ancestor directory', function (): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, 'tests/Feature/tests/Unit/nested-root.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reads the suite directly below the root, not anywhere below it', function (string $fixture): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a suite name deeper in the path' => 'tests/Support/Unit/deep-path-suite.php',
    'a suite name deeper in the namespace' => 'tests/Support/deep-namespace-suite.php',
]);

it('leaves a non-test declaration under a suite directory alone', function (string $fixture): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, TEST_SUITE_UNIT_FIXTURES . $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a shared support helper' => 'support-helper.php',
    'a trait' => 'test-trait.php',
    'an interface' => 'test-interface.php',
    'an abstract test case' => 'abstract-test-case.php',
    'a class with no declared namespace' => 'no-namespace.php',
    'a business-domain namespace spelling a suite name' => 'business-domain-namespace.php',
]);

it('reports against the configured suite segments', function (): void {
    $fixture = 'tests/Contract/custom-suite.php';

    $shipped = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect(warningTuples($shipped))->toBe([
        ['line' => 12, 'column' => 1, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);

    $configured = analyzeFixtureWithRulesetProperties(
            TEST_SUITE_NAMESPACE,
            $fixture,
            ['suiteSegments' => ['Contract', 'Unit']]
        );

    expect(warningTuples($configured))->toBe([
        ['line' => 12, 'column' => 1, 'source' => TEST_SUITE_NAMESPACE_NAMESPACE],
    ]);
});

it('matches the configured test root case-insensitively', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            TEST_SUITE_NAMESPACE,
            'failing.php',
            ['testRoot' => 'TESTS']
        );

    expect(warningTuples($file))->toBe([
        ['line' => 11, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 17, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 23, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 32, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 42, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 51, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 61, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
        ['line' => 69, 'column' => 5, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);
});

it('marks no violation fixable', function (string $fixture, int $expected): void {
    $file = analyzeFixture(TEST_SUITE_NAMESPACE, $fixture);

    expect($file->getWarningCount())->toBe($expected)
        ->and($file->getFixableCount())->toBe(0);
})->with([
    ['failing.php', 8],
    [TEST_SUITE_UNIT_FIXTURES . 'suite-mismatch.php', 2],
]);

it('says nothing when the file has no path to compare against', function (): void {
    $source = <<<'PHP'
        <?php

        namespace Tests\Unit;

        class CalculatorTest
        {
        }
        PHP;

    $onDisk = analyzeWithSniffs(
            [TEST_SUITE_NAMESPACE],
            stageSourceOutsideTests($source, 'CalculatorTest.php')
        );

    expect(warningTuples($onDisk))->toBe([
        ['line' => 5, 'column' => 1, 'source' => TEST_SUITE_NAMESPACE_DIRECTORY],
    ]);

    $piped = analyzeStdinSource([TEST_SUITE_NAMESPACE], $source);

    expect($piped->getErrors())->toBe([])
        ->and($piped->getWarnings())->toBe([]);
});

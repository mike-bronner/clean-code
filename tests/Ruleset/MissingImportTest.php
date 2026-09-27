<?php

declare(strict_types=1);

const REFERENCE_USED_NAMES_ONLY = 'SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly';

const REFERENCE_VIA_FQN = REFERENCE_USED_NAMES_ONLY . '.ReferenceViaFullyQualifiedName';

const REFERENCE_VIA_FQN_WITHOUT_NAMESPACE = REFERENCE_USED_NAMES_ONLY
    . '.ReferenceViaFullyQualifiedNameWithoutNamespace';

const THROWABLE_ONLY = 'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly';

const FAILING_VIOLATIONS = [
    ['line' => 9, 'column' => 9, 'source' => REFERENCE_VIA_FQN],
    ['line' => 11, 'column' => 29, 'source' => REFERENCE_VIA_FQN],
    ['line' => 13, 'column' => 20, 'source' => REFERENCE_VIA_FQN],
    ['line' => 16, 'column' => 32, 'source' => REFERENCE_VIA_FQN],
    ['line' => 18, 'column' => 20, 'source' => REFERENCE_VIA_FQN],
    ['line' => 23, 'column' => 16, 'source' => REFERENCE_VIA_FQN],
    ['line' => 29, 'column' => 23, 'source' => REFERENCE_VIA_FQN],
    ['line' => 30, 'column' => 18, 'source' => REFERENCE_VIA_FQN],
];

const TRAIT_USE_VIOLATION = ['line' => 9, 'column' => 9, 'source' => REFERENCE_VIA_FQN];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFERENCE_USED_NAMES_ONLY);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each fully qualified reference at its own line and column', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect(violationTuples($file))->toBe(FAILING_VIOLATIONS);
});

it('flags a fully qualified trait use and leaves an imported one alone', function (): void {
    $failing = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect(violationTuples($failing))->toContain(TRAIT_USE_VIOLATION);

    $traitLine = trim(explode("\n", (string) file_get_contents(
        fixturePath('ReferenceUsedNamesOnlySniff', 'failing.php')
    ))[TRAIT_USE_VIOLATION['line'] - 1]);

    expect($traitLine)->toBe('use \App\Billing\Support\Loggable;');

    $passing = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'passing.php');

    expect(violationTuples($passing))->toBe([]);
});

it('imports a fully qualified trait use when it fixes the failing fixture', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    $fixed = autofixedContents($file);

    expect($fixed)->toContain('use App\Billing\Support\Loggable;')
        ->and($fixed)->toContain('    use Loggable;')
        ->and($fixed)->not->toContain('use \App\Billing\Support\Loggable;');
});

it('reports fully qualified references as errors rather than warnings', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect($file->getErrorCount())->toBe(count(FAILING_VIOLATIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('silences global-namespace classes when ignore-global is switched on', function (): void {
    $file = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'failing.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalClasses = true;
        }
    );

    expect(violationTuples($file))->toBe([
        TRAIT_USE_VIOLATION,
        ['line' => 16, 'column' => 32, 'source' => REFERENCE_VIA_FQN],
        ['line' => 18, 'column' => 20, 'source' => REFERENCE_VIA_FQN],
        ['line' => 23, 'column' => 16, 'source' => REFERENCE_VIA_FQN],
    ]);
});

it('flags a fully qualified reference in a file with no namespace', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'no-namespace.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 29, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
        ['line' => 9, 'column' => 20, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
    ]);

    $skipped = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'no-namespace.php',
        static function (object $sniff): void {
            $sniff->allowWhenNoNamespace = false;
        }
    );

    expect($skipped->getErrors())->toBe([]);
});

it('keeps reporting namespace-less files even with ignore-global switched on', function (): void {
    $file = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'no-namespace.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalClasses = true;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 29, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
        ['line' => 9, 'column' => 20, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
    ]);
});

it('allows fully qualified global functions and constants, and would report them without that', function (): void {
    $file = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'passing.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalFunctions = false;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 42, 'column' => 16, 'source' => REFERENCE_VIA_FQN],
    ]);

    $constants = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'passing.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalConstants = false;
        }
    );

    expect(violationTuples($constants))->toBe([
        ['line' => 42, 'column' => 51, 'source' => REFERENCE_VIA_FQN],
    ]);
});

it('flags the divergences from PHPMD exactly where they are recorded', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 16, 'source' => REFERENCE_VIA_FQN],
        ['line' => 20, 'column' => 17, 'source' => REFERENCE_VIA_FQN],
    ]);
});

it('offers a fix for every violation and rewrites the failing fixture', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect($file->getFixableCount())->toBe(count(FAILING_VIOLATIONS))
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(FAILING_VIOLATIONS), true));

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ReferenceUsedNamesOnlySniff', 'autofixed.php')));
});

it('leaves no violation behind on its own fixed output', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'autofixed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports a caught general exception through both rules at once', function (): void {
    $file = analyzeWithMasterRuleset(
        fixturePath('ReferenceUsedNamesOnlySniff', 'throwable-interaction.php')
    );

    $sources = violationSourcesByLine($file->getErrors());

    expect($sources[13] ?? [])->toContain(THROWABLE_ONLY . '.ReferencedGeneralException')
        ->and($sources[13] ?? [])->toContain(REFERENCE_VIA_FQN);
});

it('converges on an imported Throwable when the whole ruleset is fixed', function (): void {
    $file = analyzeWithMasterRuleset(
        fixturePath('ReferenceUsedNamesOnlySniff', 'throwable-interaction.php')
    );

    $fixed = autofixedContents($file);

    expect($fixed)->toContain('use Throwable;')
        ->and($fixed)->toContain('catch (Throwable $exception)')
        ->and($fixed)->not->toContain('\Throwable')
        ->and($fixed)->not->toContain('\Exception');
});

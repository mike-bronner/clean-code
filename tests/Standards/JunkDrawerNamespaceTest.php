<?php

declare(strict_types=1);

const JUNK_DRAWER_NAMESPACE = 'CleanCode.ClearCode.JunkDrawerNamespace';

const JUNK_DRAWER_NAMESPACE_WARNING = JUNK_DRAWER_NAMESPACE . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(JUNK_DRAWER_NAMESPACE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            5 => [JUNK_DRAWER_NAMESPACE_WARNING],
            8 => [JUNK_DRAWER_NAMESPACE_WARNING],
            11 => [JUNK_DRAWER_NAMESPACE_WARNING],
            14 => [JUNK_DRAWER_NAMESPACE_WARNING],
            17 => [JUNK_DRAWER_NAMESPACE_WARNING],
            20 => [JUNK_DRAWER_NAMESPACE_WARNING],
            23 => [JUNK_DRAWER_NAMESPACE_WARNING],
            26 => [JUNK_DRAWER_NAMESPACE_WARNING],
            29 => [JUNK_DRAWER_NAMESPACE_WARNING],
            32 => [JUNK_DRAWER_NAMESPACE_WARNING, JUNK_DRAWER_NAMESPACE_WARNING],
        ]);
});

it('reports at warning severity, never error', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(11)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports at the namespace name rather than the keyword', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 5, 'column' => 11, 'source' => JUNK_DRAWER_NAMESPACE_WARNING])
        ->toContain(['line' => 29, 'column' => 11, 'source' => JUNK_DRAWER_NAMESPACE_WARNING])
        ->and(array_keys($file->getWarnings()[32]))->toBe([11]);
});

it('names the namespace and the offending segment in the message', function (): void {
    $warnings = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php')->getWarnings();

    expect($warnings[5][11][0]['message'])
        ->toContain('Namespace App\\Helpers')
        ->toContain('the "Helpers" segment')
        ->toContain('Group related classes into a domain namespace')
        ->toContain('docs/standards/clear-code-encapsulate-related-classes-in-a-domain.md')
        ->and($warnings[23][11][0]['message'])->toContain('the "helpers" segment')
        ->and($warnings[26][11][0]['message'])->toContain('the "UTILS" segment')
        ->and($warnings[32][11][0]['message'])->toContain('the "Helpers" segment')
        ->and($warnings[32][11][1]['message'])->toContain('the "Utils" segment')
        ->and($warnings[32][11][1]['message'])->toContain('Namespace App\\Helpers\\Utils');
});

it('exposes a configurable discouraged-segment list', function (): void {
    $shipped = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'configured.php');

    expect(array_keys($shipped->getWarnings()))->toBe([5]);

    $replaced = analyzeFixture(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        static function (object $sniff): void {
            $sniff->discouragedSegments = ['Widgets'];
        }
    );

    expect(array_keys($replaced->getWarnings()))->toBe([8]);

    $extended = analyzeFixture(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        static function (object $sniff): void {
            $sniff->discouragedSegments = ['Helpers', 'Widgets'];
        }
    );

    expect(array_keys($extended->getWarnings()))->toBe([5, 8]);

    $emptied = analyzeFixture(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        static function (object $sniff): void {
            $sniff->discouragedSegments = [];
        }
    );

    expect($emptied->getWarnings())->toBe([]);
});

it('accepts the list from a consuming ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        ['discouragedSegments' => ['Helpers', 'Widgets']]
    );

    expect(array_keys($file->getWarnings()))->toBe([5, 8]);
});

it('flags a semicolon-terminated declaration', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'unbraced.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 5, 'column' => 11, 'source' => JUNK_DRAWER_NAMESPACE_WARNING],
        ])
        ->and($file->getWarnings()[5][11][0]['message'])->toContain('Namespace App\\Helpers');
});

it('stays silent on a namespace declaration with no name after it', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'unterminated-namespace.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

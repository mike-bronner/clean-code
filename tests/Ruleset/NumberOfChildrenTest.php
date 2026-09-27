<?php

declare(strict_types=1);

const ORDINAL_INDEX_SNIFF = 'CleanCode.Metrics.NumberOfChildren';

const ORDINAL_INDEX_DIAGNOSTIC = ORDINAL_INDEX_SNIFF . '.OrdinalIndex';

const ORDINAL_INDEX_FOUND = ORDINAL_INDEX_SNIFF . '.Found';

$stageOrdinalIndexProject = static function (): string {
    $children = implode("\n\n", array_map(
        static fn (int $index): string => "class Child{$index} extends Base\n{\n}",
        range(1, 15)
    ));

    return dirname(stageProjectOutsideTests([
        'Base.php' => "<?php\n\nnamespace Fixture\\Ordinal;\n\nclass Base\n{\n}\n\n" . $children . "\n",
    ]));
};

$runThrowawayPhpcs = static function (string $binary, array $arguments): array {
    [$stdout, , $status] = runOutsidePackage(implode(' ', array_map(
        'escapeshellarg',
        array_merge([PHP_BINARY, $binary], $arguments)
    )));

    return [$stdout, $status];
};

$reportedSources = static function (string $json): array {
    $sources = [];

    foreach ((json_decode($json, true)['files'] ?? []) as $file) {
        foreach ($file['messages'] as $message) {
            $sources[] = $message['source'];
        }
    }

    return $sources;
};

it('merges the ordinal diagnostic to severity 0', function (): void {
    $ruleset = buildRulesetForStandard(cleanCodeRoot() . '/CleanCode/ruleset.xml');

    expect($ruleset->ruleset[ORDINAL_INDEX_DIAGNOSTIC]['severity'] ?? null)->toBe(0);
});

it('leaves the sniff itself reporting', function (): void {
    $ruleset = buildRulesetForStandard(cleanCodeRoot() . '/CleanCode/ruleset.xml');

    expect($ruleset->sniffCodes)->toHaveKey(ORDINAL_INDEX_SNIFF)
        ->and($ruleset->ruleset[ORDINAL_INDEX_SNIFF]['severity'] ?? null)->toBeNull()
        ->and($ruleset->ruleset[ORDINAL_INDEX_FOUND]['severity'] ?? null)->toBeNull();
});

it('keeps the diagnostic silent when a consumer tunes the sniff', function (): void {
    $standard = stagingDirectory() . '/tuned.xml';

    file_put_contents($standard, implode("\n", [
        '<?xml version="1.0"?>',
        '<ruleset name="Tuned">',
        '    <description>The consumer example from docs/phpmd/design-numberofchildren.md.</description>',
        '    <rule ref="' . cleanCodeRoot() . '/CleanCode/ruleset.xml"/>',
        '    <rule ref="' . ORDINAL_INDEX_SNIFF . '">',
        '        <properties>',
        '            <property name="minimum" value="8"/>',
        '        </properties>',
        '    </rule>',
        '</ruleset>',
        '',
    ]));

    $ruleset = buildRulesetForStandard($standard);

    expect($ruleset->ruleset[ORDINAL_INDEX_DIAGNOSTIC]['severity'] ?? null)->toBe(0);
});

it('stays silent on an ordinary run against an install carrying a persisted config value', function () use (
    $stageOrdinalIndexProject,
    $runThrowawayPhpcs,
    $reportedSources
): void {
    $binary = stageThrowawayPhpcsInstall();
    $project = $stageOrdinalIndexProject();
    $shared = cleanCodeRoot() . '/vendor/squizlabs/php_codesniffer/CodeSniffer.conf';
    $before = is_file($shared) === true ? hash_file('sha256', $shared) : null;

    $runThrowawayPhpcs($binary, [
        '--config-set',
        'installed_paths',
        implode(',', installedStandardPaths()),
    ]);
    $runThrowawayPhpcs($binary, ['--config-set', 'cleancode_ordinal_index_diagnostic', '1']);

    [$shown] = $runThrowawayPhpcs($binary, ['--config-show']);

    [$ordinary] = $runThrowawayPhpcs($binary, [
        '--standard=' . cleanCodeRoot() . '/CleanCode/ruleset.xml',
        '--sniffs=' . ORDINAL_INDEX_SNIFF,
        '--report=json',
        '--no-cache',
        $project,
    ]);
    [$restored] = $runThrowawayPhpcs($binary, [
        '--standard=' . stageOrdinalDiagnosticRuleset(),
        '--sniffs=' . ORDINAL_INDEX_SNIFF,
        '--report=json',
        '--no-cache',
        $project,
    ]);

    expect($shown)->toContain('cleancode_ordinal_index_diagnostic: 1')
        ->and($reportedSources($ordinary))->toBe([ORDINAL_INDEX_FOUND])
        ->and($reportedSources($restored))->toBe([ORDINAL_INDEX_FOUND, ORDINAL_INDEX_DIAGNOSTIC])
        ->and(is_file($shared) === true ? hash_file('sha256', $shared) : null)->toBe($before);
});

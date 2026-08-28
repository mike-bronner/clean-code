<?php

/**
 * The CleanCode.Metrics.NumberOfChildren.OrdinalIndex backstop *as the shipped
 * rulesets declare it* (#378). The sniff's own behaviour lives in
 * tests/Standards/NumberOfChildrenTest.php; what is asserted here is the
 * shipped severity, which is what decides whether a consumer running
 * `phpcs --standard=rules.xml` can be shown a diagnostic they never asked for.
 *
 * OrdinalIndex is instrumentation. It exists so the performance test in the
 * Standards file can read the sniff's own counters back out of a real phpcs
 * subprocess, which is the only channel that run has. The sniff gates it on the
 * cleancode_ordinal_index_diagnostic config value, and that gate is not enough
 * on its own: PHPCS merges the runtime-set flag, a ruleset `config` element and
 * the persisting config-set command into one store, and the sniff cannot tell
 * them apart. A stale config-set on a shared install — a CI runner, a monorepo,
 * a cached Docker layer — therefore leaks warnings into every later ordinary
 * run against that install, on every file holding a class declaration, until
 * someone runs config-delete. It was reproduced live under PR #377.
 *
 * The severity-0 declaration in CleanCode/ruleset.xml closes that off. These
 * tests hold both halves of it: that the declaration survives into the ruleset
 * PHPCS actually merges, by either route a consumer can load the package, and
 * that a real install carrying a real persisted config value stays quiet
 * anyway.
 */

declare(strict_types=1);

const ORDINAL_INDEX_SNIFF = 'CleanCode.Metrics.NumberOfChildren';

const ORDINAL_INDEX_DIAGNOSTIC = ORDINAL_INDEX_SNIFF . '.OrdinalIndex';

const ORDINAL_INDEX_FOUND = ORDINAL_INDEX_SNIFF . '.Found';

/**
 * A project whose Base class is over the shipped threshold of 15 children, so
 * an ordinary run has something to report either way. Without a real violation
 * a silent report proves nothing: a run that failed to load the standard at all
 * looks exactly like a run whose backstop worked.
 *
 * @return string the directory to hand PHPCS
 *
 * @var callable(): string
 */
$stageOrdinalIndexProject = static function (): string {
    $children = implode("\n\n", array_map(
        static fn (int $index): string => "class Child{$index} extends Base\n{\n}",
        range(1, 15)
    ));

    return dirname(stageProjectOutsideTests([
        'Base.php' => "<?php\n\nnamespace Fixture\\Ordinal;\n\nclass Base\n{\n}\n\n" . $children . "\n",
    ]));
};

/**
 * Runs a staged throwaway phpcs install and returns [stdout, exit status].
 *
 * @param array<int, string> $arguments
 *
 * @return array{0: string, 1: int}
 *
 * @var callable(string, array<int, string>): array{0: string, 1: int}
 */
$runThrowawayPhpcs = static function (string $binary, array $arguments): array {
    [$stdout, , $status] = runOutsidePackage(implode(' ', array_map(
        'escapeshellarg',
        array_merge([PHP_BINARY, $binary], $arguments)
    )));

    return [$stdout, $status];
};

/**
 * Every message source a JSON report carries, in report order.
 *
 * @return array<int, string>
 *
 * @var callable(string): array<int, string>
 */
$reportedSources = static function (string $json): array {
    $sources = [];

    foreach ((json_decode($json, true)['files'] ?? []) as $file) {
        foreach ($file['messages'] as $message) {
            $sources[] = $message['source'];
        }
    }

    return $sources;
};

/**
 * The declaration has to survive into the ruleset PHPCS *merges*, which is not
 * the same claim as the string being present in a file. Reading it back off
 * Ruleset::$ruleset is what tells the two apart: a rules.xml that re-declared a
 * nonzero severity after its rule ref would leave CleanCode/ruleset.xml
 * untouched and still reopen the hazard on the file every consumer installs.
 *
 * Both entry points are checked because both are reachable. rules.xml is the
 * master ruleset, and CleanCode/ruleset.xml is independently loadable — it
 * carries the sniffs, so a consumer may point a standard argument straight at
 * it — which is why the declaration lives in the latter and not the former.
 */
it('merges the ordinal diagnostic to severity 0', function (string $standard): void {
    $ruleset = buildRulesetForStandard(cleanCodeRoot() . '/' . $standard);

    expect($ruleset->ruleset[ORDINAL_INDEX_DIAGNOSTIC]['severity'] ?? null)->toBe(0);
})->with([
    'rules.xml',
    'CleanCode/ruleset.xml',
]);

/**
 * The backstop must silence the one message code and nothing else. Severity is
 * declared per code, so zeroing the sniff instead of the message would take
 * NumberOfChildren.Found down with it — the rule this sniff exists for, and the
 * PHPMD rule docs/phpmd/design-numberofchildren.md promises to cover.
 *
 * `Found` carries no severity of its own, so it reports at PHPCS's default of
 * 5. Asserting the absence of a severity key is the honest form of that: any
 * value written here, 5 included, would be a second place for the shipped
 * behaviour to drift from PHPCS's default.
 */
it('leaves the sniff itself reporting', function (string $standard): void {
    $ruleset = buildRulesetForStandard(cleanCodeRoot() . '/' . $standard);

    expect($ruleset->sniffCodes)->toHaveKey(ORDINAL_INDEX_SNIFF)
        ->and($ruleset->ruleset[ORDINAL_INDEX_SNIFF]['severity'] ?? null)->toBeNull()
        ->and($ruleset->ruleset[ORDINAL_INDEX_FOUND]['severity'] ?? null)->toBeNull();
})->with([
    'rules.xml',
    'CleanCode/ruleset.xml',
]);

/**
 * docs/phpmd/design-numberofchildren.md tells a consumer to tune this rule by
 * referencing the sniff and setting `minimum`. That reference names the sniff,
 * not the message code, and PHPCS only reopens an excluded code when the code
 * itself is referenced — so the documented example must not hand the diagnostic
 * back by accident. Pinned here because the doc is the thing that would be
 * wrong, and nothing else would catch it.
 */
it('keeps the diagnostic silent when a consumer tunes the sniff', function (): void {
    $standard = stagingDirectory() . '/tuned.xml';

    file_put_contents($standard, implode("\n", [
        '<?xml version="1.0"?>',
        '<ruleset name="Tuned">',
        '    <description>The consumer example from docs/phpmd/design-numberofchildren.md.</description>',
        '    <rule ref="' . cleanCodeRoot() . '/rules.xml"/>',
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

/**
 * The hazard itself, from a consumer's vantage point and end to end.
 *
 * Everything above reads a merged ruleset in this process. None of it exercises
 * the route the issue is actually about: `phpcs --config-set`, which persists
 * the gate value into the install's own CodeSniffer.conf and so arms every
 * later run against that install — including runs that pass no flags at all.
 *
 * That route cannot be driven against vendor/ without writing the shared config
 * file this suite's ConfigDouble convention exists to keep it off, and leaving
 * behind precisely the stale value under test. PHPCS resolves that path from
 * its own __DIR__ with no environment override, so the install is copied
 * instead and the copy is what gets written. Cleanup is the afterEach purge in
 * tests/Pest.php, which runs whether or not the assertions below hold.
 *
 * Three assertions carry the weight, and the first and last are what stop the
 * middle one passing vacuously:
 *
 * - config-show proves the value really is persisted in that install and
 *   readable by it. A config-set that silently failed would produce the same
 *   empty ordinal report as a working backstop.
 * - The ordinary run reports Found and no OrdinalIndex. This is the claim.
 * - The same install, same persisted value, no flags added, loading a consumer
 *   ruleset that restores the severity, does report OrdinalIndex. That proves
 *   the persisted value reaches the sniff and that the ruleset declaration is
 *   the only thing suppressing it — so deleting the backstop reddens the middle
 *   assertion rather than quietly changing nothing.
 *
 * The real CodeSniffer.conf is hashed either side of all of it, because a test
 * about not leaking persisted config is the last place to leak persisted
 * config.
 */
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
        implode(',', [
            cleanCodeRoot() . '/vendor/sirbrillig/phpcs-variable-analysis',
            cleanCodeRoot() . '/vendor/slevomat/coding-standard',
        ]),
    ]);
    $runThrowawayPhpcs($binary, ['--config-set', 'cleancode_ordinal_index_diagnostic', '1']);

    [$shown] = $runThrowawayPhpcs($binary, ['--config-show']);

    [$ordinary] = $runThrowawayPhpcs($binary, [
        '--standard=' . cleanCodeRoot() . '/rules.xml',
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

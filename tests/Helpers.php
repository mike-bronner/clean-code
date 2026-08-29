<?php

/**
 * Pest helper functions for driving PHP_CodeSniffer against the fixtures in
 * tests/fixtures/.
 *
 * Every helper builds the *master* ruleset (rules.xml) and then narrows it,
 * rather than restricting PHPCS via $config->sniffs. The distinction is
 * load-bearing: under PHP_CODESNIFFER_IN_TESTS a $config->sniffs restriction
 * makes Ruleset skip parsing rules.xml altogether, which is what pulls the
 * custom CleanCode sniffs in and what applies the <properties> configured
 * there. Narrowing $ruleset->sniffs after the parse keeps both.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Sniffs\WhiteSpace\PassiveOperatorSpacingSniff;
use MikeBronner\CleanCode\Support\ParameterDeclaration;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;
use PHP_CodeSniffer\Tests\ConfigDouble;

/**
 * Absolute path to the package root.
 */
function cleanCodeRoot(): string
{
    return dirname(__DIR__);
}

/**
 * Absolute path to a fixture, given its directory under tests/fixtures/.
 */
function fixturePath(string $directory, string $fixture): string
{
    return __DIR__ . '/fixtures/' . $directory . '/' . $fixture;
}

/**
 * Maps a sniff code to its fixture directory: the sniff's class short name.
 * `CleanCode.Arrays.ArrayAccessors` => `ArrayAccessorsSniff`.
 */
function sniffFixtureDirectory(string $sniffCode): string
{
    $segments = explode('.', $sniffCode);

    return end($segments) . 'Sniff';
}

/**
 * Every third-party standard rules.xml references, as an absolute path.
 *
 * Composer registers these in CodeSniffer.conf relative to the PHP_CodeSniffer
 * install it wrote them for, and absolute is what the callers here need: the
 * throwaway install in tests/Ruleset/NumberOfChildrenTest.php is a copy at an
 * unrelated path, where a relative entry points nowhere. Kept in one place
 * because a missing entry does not fail loudly — it makes the referenced sniffs
 * fail to resolve and takes the whole ruleset parse down with it, which is a
 * confusing failure to meet twice.
 *
 * tests/Ruleset/UndefinedVariableTest.php deliberately lists one of these on
 * its own rather than calling this: its subject is VariableAnalysis without
 * rules.xml, so the shorter list is the point of the test, not a copy of this
 * one that drifted.
 *
 * @return array<int, string>
 */
function installedStandardPaths(): array
{
    return [
        cleanCodeRoot() . '/vendor/sirbrillig/phpcs-variable-analysis',
        cleanCodeRoot() . '/vendor/slevomat/coding-standard',
    ];
}

/**
 * Puts the third-party standards' installed paths back into PHPCS's config.
 *
 * ConfigDouble blanks CodeSniffer.conf, where Composer registers them, and
 * every ruleset built here references at least one of those standards. In
 * memory only; CodeSniffer.conf on disk is never written.
 */
function restoreInstalledPaths(): void
{
    Config::setConfigData('installed_paths', implode(',', installedStandardPaths()), true);
}

/**
 * A freshly built master ruleset plus the config it was built from.
 *
 * Results are memoised per cache key so rules.xml is parsed once per distinct
 * sniff selection rather than once per fixture. Each entry is built
 * immediately after its own ConfigDouble construction, which resets PHPCS's
 * static Config state — so no entry can inherit defaults latched by a
 * ruleset built earlier in the run (PSR12's tab width being the classic one,
 * since it changes how indentation is tokenised).
 *
 * @param array<int, string> $sniffCodes Sniff codes to narrow to; empty for the whole ruleset.
 *
 * @return array{Config, Ruleset}
 */
function buildRuleset(array $sniffCodes = [], bool $fresh = false): array
{
    static $cache = [];

    $key = $sniffCodes === [] ? '*master*' : implode('|', $sniffCodes);

    if ($fresh === false && isset($cache[$key]) === true) {
        return $cache[$key];
    }

    // restoreInstalledPaths() puts back what ConfigDouble blanks, and has to
    // run before the rules.xml parse. The explicit argv stops Config falling
    // back to parsing the live $_SERVER['argv'] as PHPCS flags, which would
    // leak the test runner's own arguments in.
    $config = new ConfigDouble(['--standard=' . cleanCodeRoot() . '/rules.xml']);
    $config->cache = false;

    restoreInstalledPaths();

    $ruleset = new Ruleset($config);

    if ($sniffCodes !== []) {
        $isolated = [];

        foreach ($sniffCodes as $code) {
            $class = $ruleset->sniffCodes[$code];
            $isolated[$class] = $ruleset->sniffs[$class];
        }

        $ruleset->sniffs = $isolated;
        $ruleset->populateTokenListeners();
    }

    $built = [$config, $ruleset];

    if ($fresh === false) {
        $cache[$key] = $built;
    }

    return $built;
}

/**
 * Tokenises a fixture without running a single sniff over it.
 *
 * The helper classes under CleanCode/Helpers/ read the token stream and report
 * on it rather than adding violations, so their tests need a parsed file and
 * nothing else. Driving them through a sniff instead would only be able to
 * observe them through that sniff's own filtering.
 */
function parseFixture(string $directory, string $fixture): LocalFile
{
    [$config, $ruleset] = buildRuleset();

    $file = new LocalFile(fixturePath($directory, $fixture), $ruleset, $config);
    $file->parse();

    return $file;
}

/**
 * Every T_STRING in a parsed file whose content starts with $prefix, mapped to
 * FunctionCalls' verdict for each of its occurrences in source order.
 *
 * A name can appear more than once — an imported one shows up in its own `use`
 * statement as well as at the call site — so the verdicts are a list rather
 * than a single value, and a test pins every occurrence.
 *
 * @return array<string, array<int, bool>>
 */
function globalFunctionCallVerdicts(LocalFile $file, string $prefix): array
{
    $verdicts = [];

    foreach ($file->getTokens() as $pointer => $token) {
        if ($token['code'] !== T_STRING || str_starts_with($token['content'], $prefix) === false) {
            continue;
        }

        $verdicts[$token['content']][] = FunctionCalls::isGlobalFunctionCall($file, $pointer);
    }

    return $verdicts;
}

/**
 * Processes a file through the whole master ruleset, every sniff active.
 */
function analyzeWithMasterRuleset(string $path): LocalFile
{
    [$config, $ruleset] = buildRuleset();

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

/**
 * Processes a file through a whole third-party standard, by name, outside the
 * master ruleset.
 *
 * rules.xml references vendor sniffs one at a time, never a whole category, so
 * narrowing the master ruleset can only ever report on the sniffs already
 * wired in — it cannot answer "does anything in this vendor standard cover
 * this?", which is the question a new custom sniff has to settle before it is
 * written, and the one a vendor upgrade can quietly change the answer to.
 *
 * Memoised per standard, each ruleset built immediately after its own
 * ConfigDouble, for the reason given on buildRuleset().
 */
function analyzeWithStandard(string $standard, string $path): LocalFile
{
    static $cache = [];

    if (isset($cache[$standard]) === false) {
        $config = new ConfigDouble(['--standard=' . $standard]);
        $config->cache = false;

        restoreInstalledPaths();

        $cache[$standard] = [$config, new Ruleset($config)];
    }

    [$config, $ruleset] = $cache[$standard];

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

/**
 * Processes a file through a ruleset narrowed to the given sniff codes.
 *
 * $configure, when given, receives the first isolated sniff instance so a test
 * can set its public properties the way a consuming ruleset would. Supplying
 * it forces a fresh ruleset, so a mutated sniff can never leak into a later
 * test through the memoisation above.
 *
 * @param array<int, string>          $sniffCodes
 * @param callable(object): void|null $configure
 */
function analyzeWithSniffs(array $sniffCodes, string $path, ?callable $configure = null): LocalFile
{
    [$config, $ruleset] = buildRuleset($sniffCodes, $configure !== null);

    if ($configure !== null) {
        $configure($ruleset->sniffs[$ruleset->sniffCodes[$sniffCodes[0]]]);
    }

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

/**
 * Processes source through a ruleset narrowed to the given sniff codes, as
 * piped input with no path — PHPCS reports the file name as STDIN.
 *
 * A sniff that reads the file's location has to say nothing when there is no
 * location to read, and that behaviour cannot be reached through a fixture on
 * disk: every fixture has a path.
 *
 * @param array<int, string> $sniffCodes
 */
function analyzeStdinSource(array $sniffCodes, string $source): DummyFile
{
    [$config, $ruleset] = buildRuleset($sniffCodes);

    $file = new DummyFile($source, $ruleset, $config);
    $file->process();

    return $file;
}

/**
 * The sniff instance a ruleset narrowed to one sniff code holds.
 *
 * buildRuleset() memoises the ruleset per sniff-code key, so this is the very
 * instance analyzeStdinSource() and analyzeFixture() drive for that code — which
 * is what lets a test read the counters a sniff keeps about its own per-stream
 * caches around a run it made through those helpers.
 */
function sniffInstance(string $sniffCode): object
{
    [, $ruleset] = buildRuleset([$sniffCode]);

    return $ruleset->sniffs[$ruleset->sniffCodes[$sniffCode]];
}

/**
 * What each of a sniff's cache counters moved by between two readings.
 *
 * The counters are cumulative for the life of the sniff instance, and
 * buildRuleset() hands every test in a file the same instance, so a total is
 * the whole file's history rather than one run's. A delta around a single run
 * is the only reading that describes that run.
 *
 * @param array<string, int> $before
 * @param array<string, int> $after
 *
 * @return array<string, int>
 */
function cacheCountsDelta(array $before, array $after): array
{
    $counted = [];

    foreach ($after as $counter => $count) {
        $counted[$counter] = $count - $before[$counter];
    }

    return $counted;
}

/**
 * Processes a fixture through a ruleset narrowed to one sniff, resolving the
 * fixture directory from the sniff code.
 *
 * @param callable(object): void|null $configure
 */
function analyzeFixture(string $sniffCode, string $fixture, ?callable $configure = null): LocalFile
{
    return analyzeWithSniffs(
        [$sniffCode],
        fixturePath(sniffFixtureDirectory($sniffCode), $fixture),
        $configure
    );
}

/**
 * Processes one file of a fixture *project* through a ruleset narrowed to one
 * sniff, with PHPCS configured as though it had been invoked on the project
 * directory.
 *
 * Every helper above leaves Config::$files empty, because every one of them
 * analyses a single file handed over by path. That is indistinguishable, to a
 * sniff, from `phpcs one-file.php` — which is the right harness until a rule's
 * answer depends on the *other* files in the run, as
 * CleanCode.Metrics.NumberOfChildren's does: a parent's children are declared
 * in files of their own, and a run that does not contain them has none to
 * count.
 *
 * Setting $paths on the config is what a consumer's `phpcs src/` does, and it is
 * the only difference from analyzeWithSniffs(). The ruleset is always built
 * fresh: the config carries the run's paths now, so a memoised one would hand a
 * later test the earlier test's codebase.
 *
 * $paths is a directory, or a list of paths for a run narrowed to particular
 * files — the latter is how a cross-file count is attributed to one file at a
 * time, by running the same subject against one contributor at a time.
 *
 * @param array<int, string>|string   $paths
 * @param callable(object): void|null $configure
 */
function analyzeProjectFixture(
    string $sniffCode,
    array|string $paths,
    string $file,
    ?callable $configure = null
): LocalFile {
    [$config, $ruleset] = buildRuleset([$sniffCode], true);

    $config->files = (array) $paths;

    if ($configure !== null) {
        $configure($ruleset->sniffs[$ruleset->sniffCodes[$sniffCode]]);
    }

    $analyzed = new LocalFile($file, $ruleset, $config);
    $analyzed->process();

    return $analyzed;
}

/**
 * Processes a fixture through a ruleset narrowed to one sniff, with a single
 * public property set the way a consuming ruleset's <properties> would set it.
 * The shorthand for the common case of exercising one configurable threshold.
 */
function analyzeFixtureWithProperty(
    string $sniffCode,
    string $fixture,
    string $property,
    mixed $value
): LocalFile {
    return analyzeFixture($sniffCode, $fixture, static function (object $sniff) use ($property, $value): void {
        $sniff->{$property} = $value;
    });
}

/**
 * Processes a fixture through a ruleset narrowed to one sniff, with that
 * sniff's properties set the way a *consuming ruleset* sets them — through
 * Ruleset::setSniffProperty(), with string values, exactly as parsing a
 * `<property>` element does.
 *
 * The `$configure` callback analyzeFixture() takes is the other half of this
 * pair, and the two are not interchangeable. That callback assigns to the
 * property directly, so it always hands over a correctly typed PHP value; the
 * XML path first trims the value and turns an empty string into `null`, which
 * is what decides whether an empty `<property>` element configures a sniff or
 * aborts the whole ruleset parse with a TypeError. Reach for this one when the
 * assertion is about how a consumer's ruleset reaches the sniff, and for
 * `$configure` when it is about what the sniff does with a value it already
 * holds.
 *
 * Always builds a fresh ruleset, so a configured sniff can never leak into a
 * later test through buildRuleset()'s memoisation.
 *
 * @param array<string, string> $properties Property name => value, as written in XML.
 */
function analyzeFixtureWithRulesetProperties(
    string $sniffCode,
    string $fixture,
    array $properties
): LocalFile {
    [$config, $ruleset] = buildRuleset([$sniffCode], true);
    $sniffClass = $ruleset->sniffCodes[$sniffCode];

    foreach ($properties as $name => $value) {
        $ruleset->setSniffProperty($sniffClass, $name, ['scope' => 'sniff', 'value' => $value]);
    }

    $file = new LocalFile(
        fixturePath(sniffFixtureDirectory($sniffCode), $fixture),
        $ruleset,
        $config
    );
    $file->process();

    return $file;
}

/**
 * Processes a fixture through a ruleset narrowed to a *group* of sniffs that
 * together implement one configured standard, from tests/fixtures/_rulesets/.
 *
 * @param array<int, string> $sniffCodes
 */
function analyzeRulesetFixture(array $sniffCodes, string $directory, string $fixture): LocalFile
{
    return analyzeWithSniffs($sniffCodes, fixturePath('_rulesets/' . $directory, $fixture));
}

/**
 * Processes a file through the master ruleset with the named message codes'
 * `<exclude>`s lifted — the shipped configuration minus only the excludes under
 * test.
 *
 * The control half of CONTRIBUTING.md's "pin both halves" rule for excludes.
 * PHPCS implements `<exclude name="Foo.Bar.Baz"/>` by setting that code's
 * severity to 0 (Ruleset::processRule()), so restoring the default severity of
 * 5 is precisely "the same ruleset without that exclude" — and it keeps the
 * rule's configured `<properties>` live, which rebuilding the rule from XML
 * here would not: a transcript of rules.xml's properties drifts the moment
 * rules.xml changes, and a control run under different properties says nothing
 * about the exclude.
 *
 * A code that rules.xml does not actually exclude fails closed rather than
 * quietly passing: raising the severity of a code already reporting at 5
 * changes nothing, so the paired "silent through rules.xml" assertion is what
 * reddens.
 *
 * Always builds a fresh ruleset, so lifted excludes can never leak into a later
 * test through buildRuleset()'s memoisation.
 *
 * @param array<int, string> $sniffCodes    Sniffs to narrow the run to.
 * @param array<int, string> $excludedCodes Message codes whose exclude is lifted.
 */
function analyzeWithoutExcludes(array $sniffCodes, array $excludedCodes, string $path): LocalFile
{
    [$config, $ruleset] = buildRuleset($sniffCodes, true);

    foreach ($excludedCodes as $code) {
        $ruleset->ruleset[$code]['severity'] = 5;
    }

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

/**
 * Processes a fixture through a *consumer* ruleset — one that references
 * rules.xml and then overrides a sniff's properties in XML, exactly as a
 * consuming project's own ruleset does.
 *
 * Distinct from analyzeFixture()'s $configure callback, and deliberately so.
 * The callback assigns the property directly, so it hands over whatever PHP
 * type the test wrote; PHPCS's XML path always hands over a *string*, because
 * Ruleset::setSniffProperty() ends in `$sniffObject->$name = $value;` with no
 * cast. A sniff whose threshold property carries a native `int` type therefore
 * passes every callback-driven test and still dies with an uncaught TypeError
 * the moment a real consumer configures it. Only this route exercises that.
 *
 * An array value is written out as a `type="array"` property with one
 * `<element>` per entry — the spelling PHPCS documents for a list property, and
 * a different parse path from the scalar `value=""` attribute above. A sniff
 * with an `array` property type needs that route specifically: the attribute
 * form hands over a string and dies with the same TypeError described above.
 *
 * @param array<string, string|array<string>> $properties Property name => value, as written in XML.
 */
function analyzeWithConfiguredRuleset(
    string $sniffCode,
    string $fixture,
    array $properties
): LocalFile {
    $lines = [];

    foreach ($properties as $name => $value) {
        if (is_array($value) === false) {
            $lines[] = '            <property name="' . $name . '" value="' . $value . '"/>';

            continue;
        }

        $lines[] = '            <property name="' . $name . '" type="array">';

        foreach ($value as $element) {
            $lines[] = '                <element value="' . $element . '"/>';
        }

        $lines[] = '            </property>';
    }

    $standard = sys_get_temp_dir() . '/' . uniqid('cleancode-ruleset-', true) . '.xml';
    file_put_contents($standard, implode("\n", [
        '<?xml version="1.0"?>',
        '<ruleset name="Consumer">',
        '    <description>Consumer ruleset built by the test suite.</description>',
        '    <rule ref="' . cleanCodeRoot() . '/rules.xml"/>',
        '    <rule ref="' . $sniffCode . '">',
        '        <properties>',
        implode("\n", $lines),
        '        </properties>',
        '    </rule>',
        '</ruleset>',
        '',
    ]));

    // Mirrors buildRuleset(): ConfigDouble blanks CodeSniffer.conf, so the
    // third-party standards rules.xml references have to be restored in memory
    // before the parse or the whole ruleset fails to resolve.
    $config = new ConfigDouble(['--standard=' . $standard]);
    $config->cache = false;

    restoreInstalledPaths();

    $ruleset = new Ruleset($config);
    unlink($standard);

    $class = $ruleset->sniffCodes[$sniffCode];
    $ruleset->sniffs = [$class => $ruleset->sniffs[$class]];
    $ruleset->populateTokenListeners();

    $file = new LocalFile(fixturePath(sniffFixtureDirectory($sniffCode), $fixture), $ruleset, $config);
    $file->process();

    return $file;
}

/**
 * Runs the *installed* phpcs binary out of process and returns the violations
 * it reports for one sniff, as a list of `['line' => int, 'message' => string]`
 * in report order.
 *
 * Every other helper above drives PHPCS in process through ConfigDouble, which
 * blanks the CodeSniffer.conf Composer wrote at install time and has
 * restoreInstalledPaths() put the standards back by hand. That is the right
 * harness for asserting what a sniff measures, but it cannot answer whether the
 * *shipped* package works: the scaffolding supplies the registration a consumer
 * gets from Composer, so a package that never registered itself would pass all
 * the same. This helper uses none of it — it executes vendor/bin/phpcs the way
 * a consumer does, reading the real CodeSniffer.conf.
 *
 * $standard is passed to `--standard` verbatim, so it takes either a ruleset
 * file's path (rules.xml, the file a consumer points at) or an installed
 * standard's name (CleanCode). The run happens from a working directory
 * *outside* the package, which is what keeps those two distinct: PHPCS resolves
 * `--standard=CleanCode` against the working directory first, so run from the
 * package root the name would find ./CleanCode/ruleset.xml as a plain relative
 * path and prove nothing about the package being installed at all. Measured,
 * not assumed — from the package root the name still resolves with the
 * package's installed_paths entry deleted; from outside it does not.
 *
 * Nothing narrows the run to one sniff, because narrowing is what a consumer
 * does not do; $sniffCode filters the report afterwards instead.
 *
 * Every way this can fail to measure anything throws rather than returning an
 * empty list: a missing binary, a process that will not start, output that is
 * not the expected JSON report, or a report naming other than exactly the one
 * file asked about. An assertion of "no violations" must never be satisfiable
 * by a run that never happened.
 *
 * @return array<int, array{line: int, message: string}>
 */
function installedPhpcsViolations(string $standard, string $path, string $sniffCode): array
{
    $report = installedPhpcsReport($standard, $path);
    $violations = [];

    foreach ($report as $message) {
        if ($message['source'] === $sniffCode) {
            $violations[] = ['line' => (int) $message['line'], 'message' => (string) $message['message']];
        }
    }

    return $violations;
}

/**
 * The messages the installed phpcs reports for $path, unfiltered.
 *
 * @return array<int, array<string, mixed>>
 */
function installedPhpcsReport(string $standard, string $path): array
{
    return installedPhpcsRun($standard, $path)['messages'];
}

/**
 * The same run as installedPhpcsReport(), with the process exit status kept
 * alongside the messages, and with room for extra phpcs flags.
 *
 * The status is what separates "phpcs looked and found nothing" from "phpcs
 * never got as far as looking". PHP_CodeSniffer 3.13.6's Runner::runPHPCS()
 * returns 0 when nothing was reported, 1 when something was and none of it is
 * fixable, and 2 when something was and some of it is fixable; a
 * DeepExitException — an uninstalled standard, a bad flag — returns 3 instead.
 * Severity plays no part: an error-only run and a warning-only run both exit 1
 * when nothing in them is fixable. So a caller asserting an *exact* status
 * cannot be satisfied by the 3 a broken install exits with, which a "non-zero"
 * check would swallow.
 *
 * Exit 3 also produces no JSON, so the report guards below fire first and the
 * status never has to carry that case alone.
 *
 * @param array<int, string> $extraArguments Flags inserted before --report=json.
 *
 * @return array{status: int, messages: array<int, array<string, mixed>>}
 */
function installedPhpcsRun(string $standard, string $path, array $extraArguments = []): array
{
    $binary = cleanCodeRoot() . '/vendor/bin/phpcs';

    if (is_file($binary) === false) {
        throw new RuntimeException("the installed phpcs binary is missing at {$binary}; run composer install");
    }

    $arguments = array_merge(
        [PHP_BINARY, $binary, '--standard=' . $standard],
        $extraArguments,
        ['--report=json', '--no-cache', $path]
    );
    [$stdout, $stderr, $status] = runOutsidePackage(implode(' ', array_map('escapeshellarg', $arguments)));

    $decoded = json_decode($stdout, true);
    $files = is_array($decoded) === true ? $decoded['files'] ?? null : null;

    if (is_array($files) === false) {
        throw new RuntimeException("phpcs produced no JSON report for {$path}; stdout: {$stdout} stderr: {$stderr}");
    }

    if (count($files) !== 1) {
        throw new RuntimeException('phpcs reported on ' . count($files) . " files, expected only {$path}");
    }

    return ['status' => $status, 'messages' => reset($files)['messages'] ?? []];
}

/**
 * One sniff's end-to-end verdict from the installed package: the messages the
 * shipped binary reported for it, and the status the process exited with.
 *
 * --standard points at rules.xml, the file the README tells a consumer to
 * point at, so the run carries that ruleset's <properties> and its
 * <include-pattern>/<exclude-pattern> path scoping — measured, not assumed: a
 * fixture staged inside src/ reports 14 CleanCode.Files.NoProceduralCode errors
 * through this route and the same fixture read at its in-repo path reports
 * none.
 *
 * --sniffs narrows the run to $sniffCode, which installedPhpcsReport()'s own
 * callers deliberately do not do. The difference is what each is asserting.
 * Those tests ask what a consumer's whole run says, so narrowing would change
 * the question. This one has to attribute a *status* to one sniff, and a status
 * is a property of the run: with every sibling standard active, a passing
 * fixture that trips any other rule exits non-zero, and a warning-only sniff's
 * failing fixture exits 2 rather than 1 as soon as some other sniff finds
 * something fixable in it. Narrowing is what makes the pass/fail land on the
 * sniff named. It costs nothing in reach — the standard is still resolved from
 * the installed package, and rules.xml is still parsed in full.
 *
 * @return array{status: int, messages: array<int, array<string, mixed>>}
 */
function installedSniffRun(string $sniffCode, string $path): array
{
    return installedPhpcsRun(cleanCodeRoot() . '/rules.xml', $path, ['--sniffs=' . $sniffCode]);
}

/**
 * installedSniffRun() against one of the sniff's own fixtures, resolving the
 * fixture directory the way the in-process harness does — through
 * sniffFixtureDirectory(), so the shipped-binary test can never point at a
 * different fixture than its in-process sibling.
 *
 * @return array{status: int, messages: array<int, array<string, mixed>>}
 */
function installedSniffFixtureRun(string $sniffCode, string $fixture): array
{
    return installedSniffRun($sniffCode, fixturePath(sniffFixtureDirectory($sniffCode), $fixture));
}

/**
 * Runs $command from a working directory outside the package and returns its
 * stdout, stderr, and exit status.
 *
 * The status is last because it was added after the two callers that take only
 * the first two; see installedPhpcsRun() for what it is worth reading.
 *
 * @return array{0: string, 1: string, 2: int}
 */
function runOutsidePackage(string $command): array
{
    $pipes = [];
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, sys_get_temp_dir());

    if (is_resource($process) === false) {
        throw new RuntimeException("could not start: {$command}");
    }

    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return [$stdout, $stderr, proc_close($process)];
}

/**
 * Runs the fixer over an already-processed file and returns the result.
 */
function autofixedContents(LocalFile $file): string
{
    $file->fixer->fixFile();

    return $file->fixer->getContents();
}

/**
 * Every token PHP_CodeSniffer's tokenizer failed to classify in $source, as
 * `line:content` strings — the signature of source that PHP itself accepts but
 * PHPCS cannot read.
 *
 * The two are not the same language. A binary-string prefix on an interpolating
 * double-quoted string (`B"Hi {$name}"`) is the known case: `php -l` passes, and
 * PHPCS types the `B"` opener T_NONE and then folds the rest of the statement —
 * and the source after it — into one bogus string token, so every sniff
 * downstream reads live code as string body. A fixer that emits such a shape
 * corrupts the file for the next pass while looking correct to every
 * content-comparing assertion.
 *
 * Whitespace-only T_NONE tokens are excluded: PHPCS uses that code for ordinary
 * inter-token filler, and only a non-empty one marks unclassified source.
 *
 * @return array<int, string>
 */
function unclassifiedTokens(string $source): array
{
    [$config, $ruleset] = buildRuleset();

    $file = new DummyFile($source, $ruleset, $config);
    $file->parse();

    $faults = [];

    foreach ($file->getTokens() as $token) {
        if ($token['type'] === 'T_NONE' && trim($token['content']) !== '') {
            $faults[] = $token['line'] . ':' . trim($token['content']);
        }
    }

    return $faults;
}

/**
 * Collapses PHPCS's line => column => violations structure to a map of
 * line number => list of violation source codes.
 *
 * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
 *
 * @return array<int, array<int, string>>
 */
function violationSourcesByLine(array $messages): array
{
    $sources = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $sources[$line][] = $violation['source'];
            }
        }
    }

    ksort($sources);

    return $sources;
}

/**
 * Collapses PHPCS's line => column => violations structure to line => count.
 *
 * @param array<int, array<int, array<int, mixed>>> $messages
 *
 * @return array<int, int>
 */
function violationCountsByLine(array $messages): array
{
    $counts = [];

    foreach ($messages as $line => $columns) {
        $counts[$line] = array_sum(array_map('count', $columns));
    }

    ksort($counts);

    return $counts;
}

/**
 * Collapses PHPCS's line => column => violations structure to a map of
 * line number => list of rendered violation messages, for the assertions that
 * are about what a diagnostic *says* rather than where it lands.
 *
 * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
 *
 * @return array<int, array<int, string>>
 */
function violationMessagesByLine(array $messages): array
{
    $rendered = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $rendered[$line][] = $violation['message'];
            }
        }
    }

    ksort($rendered);

    return $rendered;
}

/**
 * Flattens a processed file's errors into an ordered list of
 * line/column/source tuples for exact assertion.
 *
 * @return array<int, array{line: int, column: int, source: string}>
 */
function violationTuples(LocalFile $file): array
{
    return tuplesFromMessages($file->getErrors());
}

/**
 * The warning-side counterpart of violationTuples(), for the sniffs that report
 * warnings rather than errors.
 *
 * Deliberately a second function rather than widening violationTuples() to
 * cover both: several tests here exist to pin a rule at a *particular*
 * severity — tests/Ruleset/EvalExpressionTest.php asserts Squiz.PHP.Eval was
 * raised from its built-in warning to an error — and a helper that flattened
 * errors and warnings together would let a regression back to a warning pass
 * those tests unnoticed.
 *
 * @return array<int, array{line: int, column: int, source: string}>
 */
function warningTuples(LocalFile $file): array
{
    return tuplesFromMessages($file->getWarnings());
}

/**
 * Flattens one of PHP_CodeSniffer's nested line => column => messages
 * structures into an ordered list of line/column/source tuples.
 *
 * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
 *
 * @return array<int, array{line: int, column: int, source: string}>
 */
function tuplesFromMessages(array $messages): array
{
    $flat = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $column => $lineMessages) {
            foreach ($lineMessages as $message) {
                $flat[] = ['line' => $line, 'column' => $column, 'source' => $message['source']];
            }
        }
    }

    usort($flat, static fn (array $a, array $b): int => [$a['line'], $a['column']] <=> [$b['line'], $b['column']]);

    return $flat;
}

/**
 * Flattens both errors and warnings to line => sorted violation source codes.
 *
 * @return array<int, array<int, string>>
 */
function allViolationSourcesByLine(LocalFile $file): array
{
    $map = [];

    foreach ([$file->getErrors(), $file->getWarnings()] as $violations) {
        foreach ($violations as $line => $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $map[$line][] = $message['source'];
                }
            }
        }
    }

    ksort($map);
    array_walk($map, static function (array &$sources): void {
        sort($sources);
    });

    return $map;
}

/**
 * Every violation message on a processed file's errors, in line order, so a
 * test can assert what a message *says* — the metric a threshold sniff counted,
 * the name it resolved — rather than only that it was raised.
 *
 * @return array<int, string>
 */
function violationMessages(LocalFile $file): array
{
    $messages = [];

    foreach ($file->getErrors() as $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $messages[] = $violation['message'];
            }
        }
    }

    return $messages;
}

/**
 * Every `fixable` flag on a processed file's errors, so a test can assert a
 * rule is detection-only without reaching into PHPCS's nested structure.
 *
 * Errors only, like violationTuples(). A warning-reporting sniff therefore
 * always yields an empty list here whatever its fixability — assert
 * $file->getFixableCount(), which counts both, for those.
 *
 * @return array<int, bool>
 */
function violationFixableFlags(LocalFile $file): array
{
    $flags = [];

    foreach ($file->getErrors() as $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                $flags[] = $message['fixable'];
            }
        }
    }

    return $flags;
}

/**
 * The sorted, de-duplicated lines carrying a violation the fixer would rewrite.
 *
 * The line-level counterpart of violationFixableFlags(), for a partial fixer
 * whose contract is *which* lines it will act on. A count cannot express that:
 * a line that loses fixability and another that gains it leave the total
 * unmoved, so a sniff whose fixer silently relocated would still pass.
 *
 * Takes the messages array rather than the file, so a caller can ask the same
 * question of getWarnings() as of getErrors().
 *
 * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
 *
 * @return array<int, int>
 */
function violationFixableLines(array $messages): array
{
    $lines = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                if ($violation['fixable'] === true) {
                    $lines[$line] = $line;
                }
            }
        }
    }

    ksort($lines);

    return array_values($lines);
}

/**
 * Writes source to a file outside the repository and returns its path, for a
 * case that varies one detail of a view or too large a body to keep on disk.
 * Staged paths are purged after each test by tests/Pest.php.
 */
function stageSource(string $source, string $filename = 'view.blade.php'): string
{
    // Its own directory, because purgeStagedFixtures() removes the parent.
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-source-', true);

    if (mkdir($directory, 0700) === false) {
        throw new RuntimeException("could not stage a source file in {$directory}");
    }

    $path = $directory . '/' . $filename;
    stagedFixtures($path);
    file_put_contents($path, $source);

    return $path;
}

/**
 * Copies a fixture to a directory outside the repository and returns the new
 * path. Two sniffs are scoped by path in rules.xml, and PHPCS decides the
 * scoping from the file's path alone — so this is what lets either of them see
 * its own fixtures at all.
 *
 * $subdirectory nests the copy below the staging root, so a path-scoped sniff
 * can be driven against a path that matches its rule and against one that does
 * not. CleanCode.Models.DisallowExternalPersistenceCalls needs only "anywhere
 * outside tests/" and passes nothing; CleanCode.Files.NoProceduralCode is
 * restricted to src/ and app/, so its tests stage into (and outside) those.
 */
function stageFixtureOutsideTests(string $path, string $subdirectory = ''): string
{
    $directory = stagingDirectory($subdirectory);
    $staged = $directory . '/' . basename($path);
    copy($path, $staged);

    return $staged;
}

/**
 * Writes generated source to a staged fixture and returns its path, for the
 * cases where a fixture's *size* is the point — committing thousands of
 * mechanical lines would bury the one thing the test is about.
 */
function stageGeneratedFixture(string $filename, string $contents): string
{
    $staged = stagingDirectory() . '/' . $filename;

    if (file_put_contents($staged, $contents) === false) {
        throw new RuntimeException("could not write a generated fixture to {$staged}");
    }

    return $staged;
}

/**
 * A fresh directory outside the repository, tracked by whichever staging
 * helper called for it. $subdirectory nests the returned path below the
 * staging root, so a path-scoped sniff can be driven against a path that
 * matches its rule and against one that does not.
 */
function stagingDirectory(string $subdirectory = ''): string
{
    $root = sys_get_temp_dir() . '/' . uniqid('cleancode-fixture-', true);
    $directory = $subdirectory === '' ? $root : $root . '/' . $subdirectory;

    if (mkdir($directory, 0700, true) === false) {
        throw new RuntimeException("could not stage a fixture in {$directory}");
    }

    stagedFixtures($root);

    return $directory;
}

/**
 * Builds a small project outside the repository from a map of
 * `<path relative to the project root> => <file contents>`, and returns the
 * absolute path of the first entry.
 *
 * For the sniffs that read the *filesystem* rather than one file's tokens, and
 * whose subject is the directory name itself: a directory called `Od*d` or
 * `Foo[Bar]` is legal on the platforms this package is tested on but not on
 * Windows, so committing one under tests/fixtures/ would break a checkout
 * rather than exercise a rule. Staging it at run time keeps the name where it
 * has to be — on disk, in a real path handed to PHPCS — without putting it in
 * the tree.
 *
 * @param array<string, string> $files
 */
function stageProjectOutsideTests(array $files): string
{
    $root = stagingDirectory();

    foreach ($files as $relativePath => $contents) {
        $path = $root . '/' . $relativePath;
        $directory = dirname($path);

        if (is_dir($directory) === false && mkdir($directory, 0700, true) === false) {
            throw new RuntimeException("could not stage a project directory at {$directory}");
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("could not stage a project file at {$path}");
        }
    }

    return $root . '/' . array_key_first($files);
}

/**
 * Copies PHP_CodeSniffer into a staging root and returns the path of the copy's
 * own phpcs entry point, so a test can persist config into *that* install's
 * CodeSniffer.conf rather than the real one.
 *
 * Config::setConfigData() writes to `dirname(__DIR__) . '/CodeSniffer.conf'`
 * resolved from its own src/ directory, and PHPCS reads no environment
 * variable that redirects it. Giving PHPCS a different install to write into is
 * therefore the only way to exercise the persisted config-set route honestly.
 * The alternative — letting a test run config-set against vendor/ — writes the
 * shared file ConfigDouble exists to keep this suite off, and would leave in
 * the developer's working copy exactly the stale value #378 is about.
 *
 * A copy rather than a tree of symlinks: PHPCS derives that path from __DIR__,
 * which resolves symlinks and would lead straight back to the real install.
 * Two entries are skipped. The package's own tests/ directory is a third of the
 * bytes and nothing a phpcs subprocess loads reaches it. Its CodeSniffer.conf
 * is skipped so the copy starts with no config at all: inheriting the real one
 * would carry over Composer's relative installed_paths, which resolve against
 * the install directory and so point nowhere from a staging root.
 *
 * The staging root is registered for the afterEach purge, so the copy is
 * removed even when the test that asked for it fails part way through.
 */
function stageThrowawayPhpcsInstall(): string
{
    $vendor = stagingDirectory('vendor');
    $source = cleanCodeRoot() . '/vendor/squizlabs/php_codesniffer';
    $install = $vendor . '/squizlabs/php_codesniffer';

    if (mkdir($install, 0700, true) === false) {
        throw new RuntimeException("could not stage a PHP_CodeSniffer install at {$install}");
    }

    $entries = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        // getSubPathname() is the path below $source, so these two match the
        // top-level entries only and leave any same-named descendant alone.
        static fn (SplFileInfo $entry, string $key, RecursiveDirectoryIterator $directory): bool
            => in_array($directory->getSubPathname(), ['tests', 'CodeSniffer.conf'], true) === false
    );
    $offset = strlen($source) + 1;

    foreach (new RecursiveIteratorIterator($entries, RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $target = $install . '/' . substr($entry->getPathname(), $offset);

        if ($entry->isDir() === true) {
            if (mkdir($target, 0700, true) === false) {
                throw new RuntimeException("could not stage {$target}");
            }

            continue;
        }

        if (copy($entry->getPathname(), $target) === false) {
            throw new RuntimeException("could not stage {$target}");
        }
    }

    // PHPCS looks two directories above its own for a Composer autoloader, and
    // needs to find one: the CleanCode sniffs are PSR-4 classes, and
    // CleanCode/Support/BackportedTokens.php is a Composer `files` entry that
    // no other loader reads. A shim returning the real ClassLoader satisfies
    // the `instanceof ClassLoader` check in the package's own autoload.php
    // without a symlink into the real install. Teardown is no longer a reason
    // to avoid one: removeStagedDirectory() unlinks a symlink rather than
    // descending through it since #380.
    file_put_contents(
        $vendor . '/autoload.php',
        '<?php' . "\n\n" . 'return require ' . var_export(cleanCodeRoot() . '/vendor/autoload.php', true) . ';' . "\n"
    );

    return $install . '/bin/phpcs';
}

/**
 * The Ruleset PHPCS builds for an arbitrary standard, for a test whose subject
 * is what the shipped rulesets *declare* rather than what a sniff does.
 *
 * buildRuleset() answers the same question for rules.xml alone. This one takes
 * the path because CleanCode/ruleset.xml is independently loadable — a consumer
 * may point a standard argument straight at it — so a rule declared for
 * consumers has two entry points to hold at, not one.
 *
 * Not memoised: the callers compare whole rulesets, and a shared instance
 * across standards is the one thing that would make that comparison vacuous.
 */
function buildRulesetForStandard(string $standard): Ruleset
{
    // Mirrors buildRuleset(): ConfigDouble blanks CodeSniffer.conf, so the
    // installed standards have to be put back before the parse reads them.
    $config = new ConfigDouble(['--standard=' . $standard]);
    $config->cache = false;

    restoreInstalledPaths();

    return new Ruleset($config);
}

/**
 * Stages a consumer-style ruleset that loads rules.xml and then gives
 * CleanCode.Metrics.NumberOfChildren.OrdinalIndex back a reporting severity,
 * and returns its path.
 *
 * CleanCode/ruleset.xml ships that message code at severity 0, because a config
 * value alone cannot keep an instrumentation diagnostic away from a consumer
 * (#378). The suppression is total by design and covers the runtime-set route
 * as well, so the one test that reads the sniff's counters out of a real run's
 * report has to ask for them back the way a consumer would. PHPCS applies rules
 * in document order, so the declaration below wins over the one it inherits.
 *
 * This is therefore both the compensating override for that test and the live
 * proof that the escape hatch CleanCode/ruleset.xml documents actually works —
 * a consumer who does want the numbers is not locked out.
 *
 * Staged in a root of its own rather than beside the fixtures, because the
 * caller hands PHPCS a whole directory to scan and a ruleset is not a file
 * under test.
 */
function stageOrdinalDiagnosticRuleset(): string
{
    $standard = stagingDirectory() . '/ordinal-diagnostic.xml';

    file_put_contents($standard, implode("\n", [
        '<?xml version="1.0"?>',
        '<ruleset name="OrdinalIndexDiagnostic">',
        '    <description>Consumer ruleset built by the test suite.</description>',
        '    <rule ref="' . cleanCodeRoot() . '/rules.xml"/>',
        '    <rule ref="CleanCode.Metrics.NumberOfChildren.OrdinalIndex">',
        '        <severity>5</severity>',
        '    </rule>',
        '</ruleset>',
        '',
    ]));

    return $standard;
}

/**
 * Writes $source to a file named $filename in a directory outside the
 * repository and returns the path, so a test can compare a sniff's verdict on
 * the same bytes at a real path and with no path at all.
 */
function stageSourceOutsideTests(string $source, string $filename): string
{
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-source-', true);

    if (mkdir($directory, 0700) === false) {
        throw new RuntimeException("could not stage a source file in {$directory}");
    }

    stagedFixtures($directory);

    $staged = $directory . '/' . $filename;
    file_put_contents($staged, $source);

    return $staged;
}

/**
 * Tracks the staging roots created so far, and returns them for cleanup when
 * called with no argument.
 *
 * @return array<int, string>
 */
function stagedFixtures(?string $add = null): array
{
    static $roots = [];

    if ($add !== null) {
        $roots[] = $add;

        return $roots;
    }

    $tracked = $roots;
    $roots = [];

    return $tracked;
}

/**
 * Removes every fixture staged outside the repository so far, roots and all.
 */
function purgeStagedFixtures(): void
{
    foreach (stagedFixtures() as $root) {
        removeStagedDirectory($root);
    }
}

/**
 * Removes a staging root and everything below it. Recursive because a staged
 * fixture may sit in a nested directory the sniff's path scoping requires.
 *
 * A symlink is unlinked, never descended into, at the root and at every entry
 * below it alike. is_dir() answers for a link's target rather than the link, so
 * without that guard a staged link to a real directory would turn this teardown
 * into a recursive delete of the target's contents, outside the staging root it
 * was asked to remove (#380). The unlink is unconditional, never gated on the
 * target still existing: a dangling link has no contents to keep either way,
 * and leaving one behind fails the closing rmdir() below — or, when the staging
 * root is itself the dangling link, returns before there is an rmdir() to fail
 * and leaks the link with no diagnostic at all.
 */
function removeStagedDirectory(string $directory): void
{
    if (is_link($directory) === true) {
        unlink($directory);

        return;
    }

    if (is_dir($directory) === false) {
        return;
    }

    foreach (array_diff((array) scandir($directory), ['.', '..']) as $entry) {
        $path = $directory . '/' . $entry;

        if (is_link($path) === true) {
            unlink($path);

            continue;
        }

        if (is_dir($path) === true) {
            removeStagedDirectory($path);

            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}

/**
 * The four helpers below serve tests/Helpers/RemoveStagedDirectoryTest.php,
 * which is the one test that reads this file rather than a class under
 * CleanCode/. They live here because a Pest test file that declares functions
 * and runs tests both fails the PSR-12 side-effects rule composer lint applies.
 */

/**
 * A real directory, with one file in it, outside every staging root — the thing
 * a symlinked fixture would point at. Deliberately not registered through
 * stagedFixtures(): the purge under test must have no claim on it, or its
 * survival proves nothing.
 */
function directoryOutsideEveryStagingRoot(): string
{
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-outside-', true);

    if (mkdir($directory, 0700, true) === false) {
        throw new RuntimeException("could not create {$directory}");
    }

    file_put_contents($directory . '/keep.php', '<?php' . "\n");

    return $directory;
}

/**
 * Removes the directory above, without going through the function under test.
 */
function removeDirectoryOutsideEveryStagingRoot(string $directory): void
{
    unlink($directory . '/keep.php');
    rmdir($directory);
}

/**
 * Creates a symlink, or reports the failure where it happened rather than as
 * three puzzling assertions later. Checked for the same reason the mkdir()
 * and copy() calls above are.
 */
function stageSymlink(string $target, string $link): void
{
    if (symlink($target, $link) === false) {
        throw new RuntimeException("could not stage a symlink at {$link}");
    }
}

/**
 * Runs $callback with every PHP diagnostic it raises collected and returned
 * rather than reported, so "the call raises no warning" is an assertion a test
 * makes rather than a property of the suite's error handling.
 *
 * @param Closure(): void $callback
 *
 * @return array<int, string>
 */
function diagnosticsRaisedBy(Closure $callback): array
{
    $diagnostics = [];

    set_error_handler(static function (int $severity, string $message) use (&$diagnostics): bool {
        $diagnostics[] = $message;

        return true;
    });

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $diagnostics;
}

/**
 * Reads the complexity CleanCode.Metrics.CyclomaticComplexity measured back out
 * of each of its reports, keyed by the declaration the message names
 * ("method process()", "function nested()"), in report order.
 *
 * Reading the number rather than only the presence of a report is what makes a
 * single counting rule discriminating: a test asserting which declarations were
 * reported holds just as well against a sniff that measures every one of them
 * wrongly and still lands above the level. A report whose message does not
 * carry a measurement is skipped rather than guessed at.
 *
 * @return array<string, int>
 */
function measuredComplexities(LocalFile $file): array
{
    $measured = [];

    foreach (violationMessages($file) as $message) {
        if (preg_match('/^The (\S+ \S+\(\)) has a cyclomatic complexity of (\d+),/', $message, $matches) === 1) {
            $measured[$matches[1]] = (int) $matches[2];
        }
    }

    return $measured;
}

/**
 * Reads the nesting level CleanCode.Metrics.MethodNestingLevel measured back out
 * of each of its reports, keyed by the line it reported on.
 *
 * The counterpart of measuredComplexities() above, and there for the same
 * reason: a test asserting only *where* the sniff reported holds just as well
 * against one that measures every level wrongly and still lands over the limit,
 * and the level is the whole content of the diagnostic. A report whose message
 * carries no level is skipped rather than guessed at.
 *
 * One entry per line, which is safe only next to an assertion that pins the
 * reports themselves — violationTuples() — since a second report on a line
 * would overwrite the first here.
 *
 * @return array<int, int>
 */
function reportedNestingLevels(LocalFile $file): array
{
    $levels = [];

    foreach (violationMessagesByLine($file->getErrors()) as $line => $messages) {
        foreach ($messages as $message) {
            if (preg_match('/^Method nesting level \((\d+)\) exceeds/', $message, $matches) === 1) {
                $levels[$line] = (int) $matches[1];
            }
        }
    }

    return $levels;
}

/**
 * Executes a fixture in an isolated scope and returns the variables it
 * defined, so a fixer's before/after string values can be compared directly.
 *
 * @return array<string, mixed>
 */
function evaluateFixtureVariables(string $path): array
{
    $load = static function (string $mikeBronnerFixturePath): array {
        require $mikeBronnerFixturePath;

        $variables = get_defined_vars();
        unset($variables['mikeBronnerFixturePath']);

        return $variables;
    };

    return $load($path);
}

/**
 * Source for a file of $depth brace-less single-branch `if`s nested inside one
 * another, followed by one qualifying if/elseif chain.
 *
 * Generated rather than committed because the depth is the whole point: this is
 * what the mapping-array sniff's nesting-scale test measures against, and
 * thousands of mechanical lines in tests/fixtures/ would bury it. Returned with
 * the line its chain heads on, which follows from the depth.
 *
 * @return array{0: string, 1: int}
 */
function nestedChainFixture(int $depth): array
{
    $indent = str_repeat(' ', 8);
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'final class DeepNesting', '{'];
    $lines[] = '    public function nested(int $code): int';
    $lines[] = '    {';

    for ($level = 0; $level < $depth; $level++) {
        $lines[] = $indent . 'if ($code === ' . $level . ')';
    }

    $lines[] = $indent . 'return 0;';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    public function chain(int $code): int';
    $lines[] = '    {';

    $chainLine = count($lines) + 1;

    $lines = array_merge($lines, [
        $indent . 'if ($code === 1) {',
        $indent . '    return 1;',
        $indent . '} elseif ($code === 2) {',
        $indent . '    return 2;',
        $indent . '} else {',
        $indent . '    return 3;',
        $indent . '}',
        '    }',
        '}',
        '',
    ]);

    return [implode("\n", $lines), $chainLine];
}

/**
 * Source for one callable holding a left-associative chain of $links short
 * ternaries — `$a ?: $a ?: $a …` — which needs no parentheses and is ordinary
 * valid PHP.
 *
 * Generated rather than committed for the same reason as nestedChainFixture():
 * the length is the whole point, and it is what the NPath sniff's linearity test
 * measures against. Each link is a place the sniff has to find the end of an
 * else-branch, so a scan that runs to the end of the statement every time makes
 * the walk quadratic in $links.
 */
function ternaryChainFixture(int $links): string
{
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'function chainedTernaries($a)', '{'];
    $lines[] = '    $value = $a' . str_repeat(' ?: $a', $links) . ';';
    $lines[] = '';
    $lines[] = '    return $value;';
    $lines[] = '}';
    $lines[] = '';

    return implode("\n", $lines);
}

/**
 * Source for one callable holding $branches sequential independent `if`s.
 *
 * NPath multiplies statements in sequence and each of these is worth 2, so the
 * measurement is 2 ** $branches — which passes PHP_INT_MAX at 63 branches from
 * a few kilobytes of entirely ordinary code. That is what the NPath sniff's
 * saturation test drives, and generating it keeps the arithmetic legible where
 * sixty-odd committed fixture blocks would not be.
 */
function sequentialBranchFixture(int $branches): string
{
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'function manyBranches(int $a): int', '{'];

    for ($branch = 0; $branch < $branches; $branch++) {
        $lines[] = '    if ($a === ' . $branch . ') {';
        $lines[] = '        $a++;';
        $lines[] = '    }';
        $lines[] = '';
    }

    $lines[] = '    return $a;';
    $lines[] = '}';
    $lines[] = '';

    return implode("\n", $lines);
}

/**
 * Collapses CleanCode.Metrics.NPathComplexity's messages into `callable name =>
 * measured NPath`, which is what every measurement assertion above reads.
 *
 * The counterpart of measuredComplexities() for the cyclomatic sniff, and named
 * apart from it because the two parse different messages: this one keys on the
 * bare callable name, that one on the declaration phrase the message names.
 *
 * The value is parsed back out of the message because the message is the only
 * place the sniff publishes it. Anchoring on the name as well as the number
 * means a measurement landing on the wrong callable cannot satisfy an
 * expectation meant for another.
 *
 * @return array<string, int>
 */
function measuredNPathComplexities(LocalFile $file): array
{
    $measured = [];

    foreach (violationMessages($file) as $message) {
        $matched = preg_match(
            '/The (?:function|method) ([A-Za-z_0-9]+)\(\) has an NPath complexity of (\d+)/',
            $message,
            $matches
        );

        if ($matched === 1) {
            $measured[$matches[1]] = (int) $matches[2];
        }
    }

    return $measured;
}

/**
 * Analyses a class written as a source string — the whole of it, without a PHP
 * open tag — and hands back the parsed file, for a test that reads tokens
 * rather than violations. See tests/Support/ParameterDeclarationTest.php.
 */
function parameterDeclarationFile(string $source): File
{
    return analyzeStdinSource(
        ['CleanCode.Metrics.TooManyFields'],
        "<?php\n\ndeclare(strict_types=1);\n\n" . $source . "\n"
    );
}

/**
 * The pointer to the $occurrence'th T_VARIABLE written as $name, counting from
 * one.
 *
 * Throws rather than returning false when there is no such occurrence, so a
 * source edited out from under an expectation cannot leave it silently
 * asserting against token 0.
 */
function parameterDeclarationPointer(File $file, string $name, int $occurrence = 1): int
{
    $seen = 0;

    foreach ($file->getTokens() as $ptr => $token) {
        if ($token['code'] !== T_VARIABLE || $token['content'] !== $name) {
            continue;
        }

        $seen++;

        if ($seen === $occurrence) {
            return $ptr;
        }
    }

    throw new RuntimeException($name . ' is written fewer than ' . $occurrence . ' times in the analysed source');
}

/**
 * Both answers for one occurrence of $name, as
 * [isPlainParameter, isPromotedParameter].
 *
 * @return array<int, bool>
 */
function parameterDeclarationAnswers(File $file, string $name, int $occurrence = 1): array
{
    $ptr = parameterDeclarationPointer($file, $name, $occurrence);

    return [
        ParameterDeclaration::isPlainParameter($file, $ptr),
        ParameterDeclaration::isPromotedParameter($file, $ptr),
    ];
}

/**
 * The set CleanCode.WhiteSpace.PassiveOperatorSpacing uses to decide a `+`/`-`
 * is a unary sign, read off the real class through reflection so the divergence
 * tests compare live behaviour rather than a transcription of it.
 *
 * @return array<int|string, int|string>
 */
function passiveNonOperandTokens(): array
{
    $method = new ReflectionMethod(PassiveOperatorSpacingSniff::class, 'nonOperandTokens');
    $method->setAccessible(true);

    return $method->invoke(new PassiveOperatorSpacingSniff());
}

/**
 * The same set as Squiz.WhiteSpace.OperatorSpacing computes it — the baseline
 * both CleanCode.Operators.BinaryOperatorSpacing and the passive sniff are
 * measured against. register() is what populates it, so it must run first.
 *
 * @return array<int|string, int|string>
 */
function squizNonOperandTokens(): array
{
    $sniff = new OperatorSpacingSniff();
    $sniff->register();

    $property = new ReflectionProperty($sniff, 'nonOperandTokens');
    $property->setAccessible(true);

    return $property->getValue($sniff) ?? [];
}

/**
 * The T_* token names listed in a class constant, read out of the source that
 * declares it.
 *
 * Lets a test assert against the enumeration a sniff actually uses rather than
 * against a copy of it kept alongside, which is the whole point: a copy drifts
 * silently, and an enumeration a test only restates is an enumeration nothing
 * checks. Reading it needs no Reflection, which this package's own
 * CleanCode.Testing.NoReflectionAccess forbids in tests — PHP_CodeSniffer
 * tokenizes the file and the names are read off the tokens.
 *
 * Every T_* name between the constant's own name and the semicolon ending its
 * declaration. A constant that cannot be found yields an empty list, so a
 * caller asserting completeness reddens rather than passing on nothing.
 *
 * @param array<int, string> $sniffCodes
 *
 * @return array<int, string>
 */
function tokenNamesInConstant(string $path, string $constant, array $sniffCodes): array
{
    $tokens = analyzeWithSniffs($sniffCodes, $path)->getTokens();
    $names = [];
    $reading = false;

    foreach ($tokens as $token) {
        if ($token['code'] === T_STRING && $token['content'] === $constant) {
            $reading = true;

            continue;
        }

        if ($reading === false) {
            continue;
        }

        if ($token['code'] === T_SEMICOLON) {
            break;
        }

        if ($token['code'] === T_STRING && str_starts_with($token['content'], 'T_') === true) {
            $names[] = $token['content'];
        }
    }

    return $names;
}

/**
 * Processes every file in a directory through a ruleset narrowed to the given
 * sniff codes, as one PHPCS run over that directory.
 *
 * Every other helper here drives a single LocalFile, which is the whole of what
 * a per-file sniff can see. CleanCode.Metrics.DepthOfInheritance is the one
 * sniff whose answer depends on the *set* of files being analysed — it resolves
 * a class's parents against PHPCS's own FileList — so a fixture directory, not
 * a fixture file, is the unit its behaviour has to be asserted against.
 *
 * The config is built with the directory as its path argument, exactly as
 * `phpcs <directory>` does, because $config->files is what FileList expands and
 * what the sniff reads. Never memoised: the path argument is part of what makes
 * a run, so two directories must not share a config.
 *
 * @param array<int, string> $sniffCodes
 *
 * @return array<string, LocalFile> The processed files, keyed by basename.
 */
function analyzeFileset(array $sniffCodes, string $directory): array
{
    // Mirrors buildRuleset(): ConfigDouble blanks CodeSniffer.conf, so the
    // installed paths have to be restored before the rules.xml parse.
    $config = new ConfigDouble(['--standard=' . cleanCodeRoot() . '/rules.xml', $directory]);
    $config->cache = false;

    restoreInstalledPaths();

    $ruleset = new Ruleset($config);

    if ($sniffCodes !== []) {
        $isolated = [];

        foreach ($sniffCodes as $code) {
            $class = $ruleset->sniffCodes[$code];
            $isolated[$class] = $ruleset->sniffs[$class];
        }

        $ruleset->sniffs = $isolated;
        $ruleset->populateTokenListeners();
    }

    $list = new FileList($config, $ruleset);
    $files = [];

    for ($list->rewind(); $list->valid() === true; $list->next()) {
        $path = $list->key();
        $file = new LocalFile($path, $ruleset, $config);
        $file->process();
        $files[basename($path)] = $file;
    }

    ksort($files);

    return $files;
}

/**
 * Loads the shim twice in a fresh interpreter and reports what it saw.
 *
 * Diagnostics are collected rather than displayed: PHP writes a warning to
 * stdout under the CLI's default display_errors, where it would land in the
 * middle of the JSON this reads back.
 *
 * @return array{diagnostics: array<int, string>, first: array<string, mixed>, second: array<string, mixed>}
 */
function backportedTokensDoubleLoad(): array
{
    $shim = var_export(cleanCodeRoot() . '/CleanCode/Support/BackportedTokens.php', true);
    $script = <<<PHP
    \$diagnostics = [];
    set_error_handler(static function (int \$number, string \$message) use (&\$diagnostics): bool {
        \$diagnostics[] = \$message;

        return true;
    });
    require {$shim};
    \$first = ['T_VOID_CAST' => T_VOID_CAST, 'T_PIPE' => T_PIPE];
    require {$shim};
    echo json_encode([
        'diagnostics' => \$diagnostics,
        'first' => \$first,
        'second' => ['T_VOID_CAST' => T_VOID_CAST, 'T_PIPE' => T_PIPE],
    ]);
    PHP;

    [$stdout, $stderr] = runOutsidePackage(
        implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-d', 'error_reporting=-1', '-r', $script]))
    );
    $decoded = json_decode($stdout, true);

    if (is_array($decoded) === false) {
        throw new RuntimeException("the shim subprocess produced no JSON; stdout: {$stdout} stderr: {$stderr}");
    }

    return $decoded;
}

/**
 * Runs $body and returns both its result and every PHP diagnostic raised while
 * it ran, as `[$result, $diagnostics]`.
 *
 * This is what makes #376's `preg_match_all()` guards testable. A failed read
 * leaves $matches untouched — see tests/PregOverrides.php for the two failure
 * modes and which one is reproduced — so a guard that is deleted does not
 * change the verdict the sniff reports: it reads an offset off null on the way
 * to the same answer. The read itself is the difference, and PHP announces it.
 *
 * Deprecations are dropped rather than collected. They are a property of the
 * runtime the suite happens to run on (PHP 8.5 deprecates several reflection
 * calls this package's own dependencies make), not of the code under test, so
 * collecting them would make every assertion here fail on a runtime upgrade
 * that changed nothing.
 *
 * @template T
 *
 * @param Closure(): T $body
 *
 * @return array{T, array<int, string>}
 */
function withPhpDiagnostics(Closure $body): array
{
    $diagnostics = [];

    set_error_handler(static function (int $severity, string $message) use (&$diagnostics): bool {
        if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true) === false) {
            $diagnostics[] = $message;
        }

        return true;
    });

    try {
        $result = $body();
    } finally {
        restore_error_handler();
    }

    return [$result, $diagnostics];
}

/**
 * A decoded phpcs JSON report carrying one entry per given path => error count.
 *
 * Used by tests/Contract/DogfoodBaselineTest.php to drive the #229 dogfood
 * comparison from synthetic input. Running real phpcs there would measure
 * whatever the tree looked like that day, and could not construct the equality
 * and absence cases the ratchet's boundaries live on at all.
 *
 * @param array<string, int> $files Repo-relative path => error count.
 *
 * @return array<string, mixed>
 */
function dogfoodReport(array $files, string $root): array
{
    $entries = [];

    foreach ($files as $path => $errors) {
        $entries[$root . '/' . $path] = ['errors' => $errors, 'warnings' => 0, 'messages' => []];
    }

    return ['totals' => ['errors' => array_sum($files), 'warnings' => 0], 'files' => $entries];
}

/**
 * The repository root, resolved, as the dogfood comparison expects to be given
 * it.
 */
function dogfoodRoot(): string
{
    return (string) realpath(__DIR__ . '/..');
}

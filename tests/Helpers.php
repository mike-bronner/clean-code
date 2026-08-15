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

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
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
 * Puts the third-party standards' installed paths back into PHPCS's config.
 *
 * ConfigDouble blanks CodeSniffer.conf, where Composer registers them, and
 * every ruleset built here references at least one of those standards. Every
 * standard a ruleset depends on has to be listed — a missing entry does not
 * fail loudly, it makes the referenced sniffs fail to resolve and takes the
 * whole ruleset parse down with it. In memory only; CodeSniffer.conf on disk
 * is never written.
 */
function restoreInstalledPaths(): void
{
    Config::setConfigData(
        'installed_paths',
        implode(',', [
            cleanCodeRoot() . '/vendor/sirbrillig/phpcs-variable-analysis',
            cleanCodeRoot() . '/vendor/slevomat/coding-standard',
        ]),
        true
    );
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
 * @param array<string, string> $properties Property name => value, as written in XML.
 */
function analyzeWithConfiguredRuleset(
    string $sniffCode,
    string $fixture,
    array $properties
): LocalFile {
    $lines = [];

    foreach ($properties as $name => $value) {
        $lines[] = '            <property name="' . $name . '" value="' . $value . '"/>';
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

    Config::setConfigData(
        'installed_paths',
        implode(',', [
            cleanCodeRoot() . '/vendor/sirbrillig/phpcs-variable-analysis',
            cleanCodeRoot() . '/vendor/slevomat/coding-standard',
        ]),
        true
    );

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
 */
function removeStagedDirectory(string $directory): void
{
    if (is_dir($directory) === false) {
        return;
    }

    foreach (array_diff((array) scandir($directory), ['.', '..']) as $entry) {
        $path = $directory . '/' . $entry;

        if (is_dir($path) === true) {
            removeStagedDirectory($path);

            continue;
        }

        unlink($path);
    }

    rmdir($directory);
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

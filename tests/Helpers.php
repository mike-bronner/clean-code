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

    // ConfigDouble blanks CodeSniffer.conf, where Composer registers the
    // third-party standards' installed paths; the master ruleset references
    // them, so restore the paths (in memory only) before the rules.xml parse.
    // Every standard rules.xml depends on has to be listed — a missing entry
    // does not fail loudly, it makes the referenced sniffs fail to resolve and
    // takes the whole rules.xml parse down with it. The explicit argv also
    // stops Config falling back to parsing the live $_SERVER['argv'] as PHPCS
    // flags, which would leak the test runner's own arguments in.
    $config = new ConfigDouble(['--standard=' . cleanCodeRoot() . '/rules.xml']);
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
    $root = sys_get_temp_dir() . '/' . uniqid('cleancode-fixture-', true);
    $directory = $subdirectory === '' ? $root : $root . '/' . $subdirectory;

    if (mkdir($directory, 0700, true) === false) {
        throw new RuntimeException("could not stage a fixture in {$directory}");
    }

    stagedFixtures($root);

    $staged = $directory . '/' . basename($path);
    copy($path, $staged);

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

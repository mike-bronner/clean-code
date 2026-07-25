<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use MikeBronner\CleanCode\Sniffs\Collections\OnlyUseCollectionMethodsSniff;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests the custom CleanCode.Collections.OnlyUseCollectionMethods sniff
 * (Collections: Only Use Collection Methods, #28). Fixtures live in
 * Fixtures/OnlyUseCollectionMethodsSniff/ beside this file: separate passing
 * and failing files, and separate autofix-before/autofix-after files.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */
class OnlyUseCollectionMethodsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Collections.OnlyUseCollectionMethods';

    private const FIXTURE_DIR = '/Fixtures/OnlyUseCollectionMethodsSniff/';

    /**
     * Every violation carries the same code, so only the message distinguishes
     * one mapping from another. Matching it against this pattern is what turns
     * the sniff's function => method table into something the suite verifies.
     */
    private const MESSAGE_PATTERN = '/^Use the Collection method (\w+)\(\) instead of'
        . ' the generic PHP function (\w+)\(\) on a Collection$/';

    public function testSniffIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * A `use function … as count;` import rebinds the name for the whole file,
     * so an unqualified `count($collection)` is the import rather than the
     * builtin the sniff maps — and `count` is one of only two functions the
     * fixer rewrites, so trusting the name alone turns a working call into a
     * silently different answer.
     *
     * Both directions are pinned from one fixture: the two shadowed calls stay
     * silent, and the fully-qualified `\count()` on line 33 is the builtin
     * again and stays reported *and* fixable. Asserting only the silence would
     * pass just as well if the sniff stopped reporting the file altogether.
     */
    public function testImportedFunctionsShadowTheBuiltinButQualifiedCallsDoNot(): void
    {
        $file = $this->processFixture('imported-function.inc');

        $this->assertSame([33 => [self::SNIFF_CODE . '.Found']], $this->sourcesByLine($file->getErrors()));
        $this->assertSame([33], $this->fixableLines($file->getErrors()));
    }

    public function testEveryViolationIsFlaggedAtItsOwnLineWithTheExpectedCode(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                13 => [self::SNIFF_CODE . '.Found'],
                14 => [self::SNIFF_CODE . '.Found'],
                15 => [self::SNIFF_CODE . '.Found'],
                16 => [self::SNIFF_CODE . '.Found'],
                17 => [self::SNIFF_CODE . '.Found'],
                18 => [self::SNIFF_CODE . '.Found'],
                19 => [self::SNIFF_CODE . '.Found'],
                20 => [self::SNIFF_CODE . '.Found'],
                31 => [self::SNIFF_CODE . '.Found'],
                32 => [self::SNIFF_CODE . '.Found'],
                33 => [self::SNIFF_CODE . '.Found'],
                40 => [self::SNIFF_CODE . '.Found'],
                41 => [self::SNIFF_CODE . '.Found'],
                42 => [self::SNIFF_CODE . '.Found'],
                49 => [self::SNIFF_CODE . '.Found'],
                50 => [self::SNIFF_CODE . '.Found'],
                58 => [self::SNIFF_CODE . '.Found'],
                67 => [self::SNIFF_CODE . '.Found'],
                68 => [self::SNIFF_CODE . '.Found'],
                69 => [self::SNIFF_CODE . '.Found'],
                70 => [self::SNIFF_CODE . '.Found'],
                71 => [self::SNIFF_CODE . '.Found'],
                81 => [self::SNIFF_CODE . '.Found'],
                82 => [self::SNIFF_CODE . '.Found'],
                92 => [self::SNIFF_CODE . '.Found'],
                93 => [self::SNIFF_CODE . '.Found'],
                103 => [self::SNIFF_CODE . '.Found'],
                104 => [self::SNIFF_CODE . '.Found'],
                121 => [self::SNIFF_CODE . '.Found'],
                122 => [self::SNIFF_CODE . '.Found'],
                146 => [self::SNIFF_CODE . '.Found'],
                157 => [self::SNIFF_CODE . '.Found'],
                166 => [self::SNIFF_CODE . '.Found'],
                180 => [self::SNIFF_CODE . '.Found'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * The violation code is the same for all seventeen mapped functions, so
     * the message is the only thing that proves the sniff named the right
     * replacement. Every mapping in GENERIC_FUNCTIONS appears below.
     */
    public function testEveryViolationNamesTheCollectionMethodThatReplacesTheFunction(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                13 => 'array_map() => map()',
                14 => 'array_filter() => filter()',
                15 => 'array_reduce() => reduce()',
                16 => 'array_keys() => keys()',
                17 => 'array_values() => values()',
                18 => 'count() => count()',
                19 => 'in_array() => contains()',
                20 => 'implode() => implode()',
                31 => 'array_sum() => sum()',
                32 => 'array_slice() => slice()',
                33 => 'array_unique() => unique()',
                40 => 'count() => count()',
                41 => 'count() => count()',
                42 => 'array_merge() => merge()',
                49 => 'count() => count()',
                50 => 'array_values() => values()',
                58 => 'count() => count()',
                67 => 'array_diff() => diff()',
                68 => 'array_intersect() => intersect()',
                69 => 'array_key_exists() => has()',
                70 => 'array_search() => search()',
                71 => 'join() => implode()',
                81 => 'count() => count()',
                82 => 'count() => count()',
                92 => 'count() => count()',
                93 => 'count() => count()',
                103 => 'count() => count()',
                104 => 'count() => count()',
                121 => 'count() => count()',
                122 => 'array_sum() => sum()',
                146 => 'count() => count()',
                157 => 'count() => count()',
                166 => 'count() => count()',
                180 => 'count() => count()',
            ],
            $this->mappingsByLine($file->getErrors())
        );
    }

    /**
     * Fixability is asserted line by line rather than as a total, because the
     * two conditions that withhold it are invisible in a count: a call is
     * fixable only when it is a single-argument 1:1 swap (count(), array_sum())
     * *and* its receiver is a Collection the tokens prove outright.
     *
     * That second condition is what keeps the fixer away from TERMINAL_METHODS.
     * Lines 103-104 chain off a Collection, so the sniff types them by asking
     * whether the chain's last method is on that hand-curated list — fine for a
     * report, not something to rewrite source on. They must report and stay
     * unfixable; if they ever appear below, an incomplete list can fatal a
     * codebase again.
     *
     * Lines 121-122 are the same collapse applied to by-reference mutation: the
     * receiver was handed bare to a call that may carry a `&$parameter`, so it
     * is proven at its assignment but not at the call site. Reported, never
     * rewritten.
     */
    public function testOnlyProvablyTypedSingleArgumentCallsAreFixable(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(34, $file->getErrorCount());
        $this->assertSame(
            [18, 31, 49, 81, 82, 92, 93, 146, 157, 166, 180],
            $this->fixableLines($file->getErrors())
        );
    }

    /**
     * TERMINAL_METHODS decides whether a chain is still a Collection, and it
     * fails in the dangerous direction: a method missing from it is assumed to
     * return a Collection, so every omission is a false positive. It is also a
     * hand-curated mirror of a framework API that changes without this package,
     * which is how random() slipped in unnoticed.
     *
     * The fixer no longer consults it (see
     * testOnlyProvablyTypedSingleArgumentCallsAreFixable), so an omission now
     * costs a warning rather than a rewrite — but only five of its sixty
     * entries have behavioural coverage, and without this the other fifty-five
     * could be deleted with the suite still green. Pinning the key set makes
     * every edit to the constant a deliberate, reviewed one.
     */
    public function testTerminalMethodsListIsPinned(): void
    {
        $keys = array_keys($this->terminalMethods());

        $this->assertSame(
            [
                'after', 'all', 'average', 'avg', 'before', 'contains', 'containsoneitem', 'containsstrict', 'count',
                'doesntcontain', 'every', 'find', 'first', 'firstorfail', 'firstwhere', 'get', 'getiterator',
                'getorput', 'has', 'hasany', 'implode', 'isempty', 'isnotempty', 'join', 'jsonserialize', 'last',
                'max', 'median', 'min', 'mode', 'modelkeys', 'offsetexists', 'offsetget', 'offsetset', 'offsetunset',
                'percentage', 'pipe', 'pipeinto', 'pipethrough', 'pop', 'pull', 'random', 'reduce', 'reducespread',
                'reducewithkeys', 'search', 'shift', 'sole', 'some', 'sum', 'toarray', 'tojson', 'toquery', 'unless',
                'unlessempty', 'unlessnotempty', 'value', 'when', 'whenempty', 'whennotempty',
            ],
            $keys
        );
    }

    /**
     * Lookups lower-case the method name before checking the list, so an entry
     * carrying a capital can never match. Keeping the list sorted is what makes
     * a missing entry visible to the next person auditing it against the
     * framework.
     */
    public function testTerminalMethodsAreLowerCasedAndSorted(): void
    {
        $keys = array_keys($this->terminalMethods());
        $sorted = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys);
        $this->assertSame(array_map('strtolower', $keys), $keys);
    }

    public function testAutoFixProducesTheExpectedOutput(): void
    {
        $file = $this->processFixture('autofix-before.inc');
        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . self::FIXTURE_DIR . 'autofix-after.inc',
            $file->fixer->getContents()
        );
    }

    /**
     * @return array<string, string>
     */
    private function terminalMethods(): array
    {
        $sniff = new ReflectionClass(OnlyUseCollectionMethodsSniff::class);

        return $sniff->getConstant('TERMINAL_METHODS');
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test after the full ruleset has loaded it.
        // A $config->sniffs restriction cannot be used: under
        // PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip parsing rules.xml,
        // which is what pulls the custom CleanCode sniffs in.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile(__DIR__ . self::FIXTURE_DIR . $fixture, $ruleset, $config);
        $file->process();

        return $file;
    }

    private function createConfig(): ConfigDouble
    {
        $config = new ConfigDouble();
        $config->cache = false;
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];

        // ConfigDouble blanks CodeSniffer.conf, where Composer registers
        // Slevomat's installed path; the master ruleset references Slevomat,
        // so restore it (in memory only) for the rules.xml parse.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }

    /**
     * Collapses PHPCS's line => column => violations structure to a map of
     * line number => list of violation source codes.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, array<int, string>>
     */
    private function sourcesByLine(array $messages): array
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
     * The sorted, de-duplicated lines carrying a violation PHPCBF would rewrite.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, int>
     */
    private function fixableLines(array $messages): array
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
     * Collapses the same structure to a map of line number => the
     * "generic() => method()" pair the violation's message actually names, so
     * a wrong substitution in the mapping table cannot pass unnoticed. A
     * message that does not match the expected shape fails outright rather
     * than being reported as an empty pair.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, string>
     */
    private function mappingsByLine(array $messages): array
    {
        $mappings = [];

        foreach ($messages as $line => $columns) {
            foreach ($columns as $violations) {
                foreach ($violations as $violation) {
                    $this->assertMatchesRegularExpression(self::MESSAGE_PATTERN, $violation['message']);
                    preg_match(self::MESSAGE_PATTERN, $violation['message'], $matches);

                    $mappings[$line] = $matches[2] . '() => ' . $matches[1] . '()';
                }
            }
        }

        ksort($mappings);

        return $mappings;
    }
}

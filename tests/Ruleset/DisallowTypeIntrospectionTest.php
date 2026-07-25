<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the custom CleanCode.Classes.DisallowTypeIntrospection
 * sniff as wired into the master rules.xml (Classes: Introspection / Type
 * Casting, issue #73). Fixtures live in
 * Fixtures/DisallowTypeIntrospectionSniff/ beside this file.
 *
 * The sniff is detection-only, so there is no auto-fix assertion — instead the
 * tests prove every reported violation is non-fixable.
 */
class DisallowTypeIntrospectionTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Classes.DisallowTypeIntrospection';

    private const FIXTURE_DIR = '/Fixtures/DisallowTypeIntrospectionSniff/';

    public function testRuleIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantFileProducesNoViolations(): void
    {
        $file = $this->processFixture('compliant.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testInstanceofDrivingABranchIsFlaggedAtItsOwnLineAndColumn(): void
    {
        $file = $this->processFixture('violations.inc');
        $code = self::SNIFF_CODE . '.InstanceOf';

        // if (11), elseif (13), ternary (22), match arms (28, and both
        // conditions of the multi-condition arm on 29), case label (37),
        // while (48), nested inside an if condition (59), and a ternary whose
        // condition wraps the check in a call (68). Then the branches that sit
        // *inside* a callback, which a callback bounding the search must still
        // report — pairing each branching construct with both callback forms:
        // if+closure (83), ternary+arrow fn (93) and ternary+closure (168),
        // match arm+closure (100) and match arm+arrow fn (175), match
        // subject+arrow fn (187), and case label+closure (110). The four
        // absent pairings are not omissions: `if`/`elseif`/`while` and
        // `switch`/`case` are statements, and an arrow function's body is a
        // single expression, so PHP rejects that source at parse time — a
        // match subject (187) is the only condition parenthesis an arrow
        // function can hold. Finally a plain branch after every one of those
        // callbacks has closed (125), plus a callback that closes earlier
        // within the very same if (138) and ternary (150) condition — none of
        // which a boundary may swallow.
        $this->assertSame(
            [
                ['line' => 11, 'column' => 20, 'source' => $code],
                ['line' => 13, 'column' => 26, 'source' => $code],
                ['line' => 22, 'column' => 23, 'source' => $code],
                ['line' => 28, 'column' => 20, 'source' => $code],
                ['line' => 29, 'column' => 20, 'source' => $code],
                ['line' => 29, 'column' => 50, 'source' => $code],
                ['line' => 37, 'column' => 25, 'source' => $code],
                ['line' => 48, 'column' => 23, 'source' => $code],
                ['line' => 59, 'column' => 29, 'source' => $code],
                ['line' => 68, 'column' => 35, 'source' => $code],
                ['line' => 83, 'column' => 24, 'source' => $code],
                ['line' => 93, 'column' => 53, 'source' => $code],
                ['line' => 100, 'column' => 24, 'source' => $code],
                ['line' => 110, 'column' => 29, 'source' => $code],
                ['line' => 125, 'column' => 20, 'source' => $code],
                ['line' => 138, 'column' => 78, 'source' => $code],
                ['line' => 150, 'column' => 81, 'source' => $code],
                ['line' => 168, 'column' => 27, 'source' => $code],
                ['line' => 175, 'column' => 20, 'source' => $code],
                ['line' => 187, 'column' => 60, 'source' => $code],
            ],
            $this->violations($file)
        );
    }

    public function testIntrospectionFunctionsDrivingABranchAreFlaggedAtTheirOwnLineAndColumn(): void
    {
        $file = $this->processFixture('introspection-functions.inc');
        $code = self::SNIFF_CODE . '.IntrospectionFunction';

        // get_class in an if (11), get_debug_type in a ternary (20), gettype
        // as a switch subject (25), is_a/is_subclass_of in match arms (36,
        // 37), a root-namespaced \get_class() (44), an upper-cased call (53),
        // is_subclass_of in a while (64), gettype in a case label (76), and a
        // variadic unpack (92) — which shares the `...` of the first-class
        // callable syntax but, having an argument after it, really does call.
        $this->assertSame(
            [
                ['line' => 11, 'column' => 13, 'source' => $code],
                ['line' => 20, 'column' => 16, 'source' => $code],
                ['line' => 25, 'column' => 17, 'source' => $code],
                ['line' => 36, 'column' => 13, 'source' => $code],
                ['line' => 37, 'column' => 13, 'source' => $code],
                ['line' => 44, 'column' => 14, 'source' => $code],
                ['line' => 53, 'column' => 13, 'source' => $code],
                ['line' => 64, 'column' => 16, 'source' => $code],
                ['line' => 76, 'column' => 18, 'source' => $code],
                ['line' => 92, 'column' => 13, 'source' => $code],
            ],
            $this->violations($file)
        );
    }

    public function testTheOffendingCallIsNamedInTheMessage(): void
    {
        $file = $this->processFixture('introspection-functions.inc');

        $this->assertStringContainsString('get_class()', $file->getErrors()[11][13][0]['message']);
        $this->assertStringContainsString('is_subclass_of()', $file->getErrors()[37][13][0]['message']);
    }

    public function testIntrospectionThatDoesNotDecideABranchIsNotFlagged(): void
    {
        $file = $this->processFixture('non-conditional.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testAnImportedNameIsNotTheGlobalIntrospectionFunction(): void
    {
        $file = $this->processFixture('shadowed-by-import.inc');
        $code = self::SNIFF_CODE . '.IntrospectionFunction';

        // The four `use function` imports — plain (23), aliased (35), and both
        // members of a braced group, one of them aliased (46, 50) — rebind the
        // name, so those calls reach the imported function and are silent. Only
        // the two controls remain: an introspection function this file does not
        // import (64), and a root-qualified `\get_class()` (77), which is the
        // global function whatever the bare name resolves to.
        $this->assertSame(
            [
                ['line' => 64, 'column' => 13, 'source' => $code],
                ['line' => 77, 'column' => 14, 'source' => $code],
            ],
            $this->violations($file)
        );
    }

    public function testANameDeclaredAsAFunctionInTheFileIsNotTheGlobalOne(): void
    {
        $file = $this->processFixture('shadowed-by-declaration.inc');
        $code = self::SNIFF_CODE . '.IntrospectionFunction';

        // The file declares its own `get_class()` (12), so the bare call at 24
        // resolves to that and is silent. The three controls still report: a
        // root-qualified `\get_class()` (37), a name declared only as a *method*
        // (51) — which an unqualified call never reaches, so it shadows nothing
        // — and a name the file does not declare at all (69).
        $this->assertSame(
            [
                ['line' => 37, 'column' => 14, 'source' => $code],
                ['line' => 51, 'column' => 13, 'source' => $code],
                ['line' => 69, 'column' => 13, 'source' => $code],
            ],
            $this->violations($file)
        );
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        $fixtures = [
            'violations.inc',
            'introspection-functions.inc',
            'shadowed-by-import.inc',
            'shadowed-by-declaration.inc',
        ];

        foreach ($fixtures as $fixture) {
            $file = $this->processFixture($fixture);

            $this->assertGreaterThan(0, $file->getErrorCount(), $fixture . ' must report violations');
            $this->assertSame(0, $file->getFixableCount(), $fixture . ' violations must be detection-only');
        }
    }

    /**
     * Flattens a processed file's errors into an ordered list of
     * line/column/source tuples for exact assertion.
     *
     * @return array<int, array{line: int, column: int, source: string}>
     */
    private function violations(LocalFile $file): array
    {
        $flat = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $column => $messages) {
                foreach ($messages as $message) {
                    $flat[] = ['line' => $line, 'column' => $column, 'source' => $message['source']];
                }
            }
        }

        usort($flat, static fn (array $a, array $b): int => [$a['line'], $a['column']] <=> [$b['line'], $b['column']]);

        return $flat;
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test. A $config->sniffs restriction cannot
        // be used here: under PHP_CODESNIFFER_IN_TESTS it makes Ruleset skip
        // parsing rules.xml, which is what pulls the custom CleanCode sniffs
        // in. populateTokenListeners() re-applies the narrowed listener map.
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

        // ConfigDouble blanks CodeSniffer.conf, which is where Composer
        // registers Slevomat's installed path — restore it (in memory only)
        // so the ruleset can resolve the SlevomatCodingStandard sniffs.
        ConfigDouble::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        return $config;
    }
}

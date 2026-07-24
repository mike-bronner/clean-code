<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Methods.NoNullArguments sniff (Methods: No Null
 * Arguments, #71). Fixtures live in Fixtures/NoNullArgumentsSniff/ beside this
 * file: separate passing and failing files, separate autofix-before/after
 * files, and namespaces.inc for resolution across namespace blocks.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */
class NoNullArgumentsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Methods.NoNullArguments';

    private const VIOLATION = self::SNIFF_CODE . '.PositionalNull';

    private const FIXTURE_DIR = '/Fixtures/NoNullArgumentsSniff/';

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

    public function testEveryViolationIsFlaggedAtItsOwnLineWithTheExpectedCode(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                // Attribute arguments are constructor arguments — including
                // the second attribute of a group.
                23 => [self::VIOLATION],
                30 => [self::VIOLATION, self::VIOLATION],
                // $this-> method call, plain and nullsafe.
                69 => [self::VIOLATION],
                70 => [self::VIOLATION],
                // ClassName::, self:: and static:: static calls.
                73 => [self::VIOLATION],
                74 => [self::VIOLATION],
                77 => [self::VIOLATION],
                // new ClassName() and new self() constructor calls.
                80 => [self::VIOLATION],
                81 => [self::VIOLATION],
                // two null arguments in one call — one violation each
                84 => [self::VIOLATION, self::VIOLATION],
                // null before a further positional argument
                88 => [self::VIOLATION],
                // null before an argument that cannot be named
                93 => [self::VIOLATION],
                96 => [self::VIOLATION],
                100 => [self::VIOLATION],
                // Calls dispatched against the runtime class.
                132 => [self::VIOLATION],
                135 => [self::VIOLATION],
                138 => [self::VIOLATION],
                // ...and the declarations dispatch cannot divert.
                142 => [self::VIOLATION],
                146 => [self::VIOLATION],
                149 => [self::VIOLATION],
                150 => [self::VIOLATION],
                // `self::` and `new self()` in the same extendable class.
                155 => [self::VIOLATION],
                156 => [self::VIOLATION],
                196 => [self::VIOLATION],
                // Trait methods, which the using class may replace — reached
                // through `$this->` and through `self::`/`new self()` alike.
                213 => [self::VIOLATION],
                214 => [self::VIOLATION],
                215 => [self::VIOLATION],
                222 => [self::VIOLATION],
                223 => [self::VIOLATION],
                // `self` inside an anonymous class nested in a trait names
                // that anonymous class, not the trait.
                251 => [self::VIOLATION],
                // Enum and anonymous class — neither can be extended.
                270 => [self::VIOLATION],
                287 => [self::VIOLATION],
                // standalone function call
                302 => [self::VIOLATION],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * The fixer writes the resolved declaration's parameter name into the
     * source, so it may only run when this file proves that declaration is the
     * one the call reaches. Every violation is reported either way.
     */
    public function testEachViolationIsFixableOnlyWhereTheRewriteIsProvablySafe(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                // An attribute names the class it instantiates outright.
                23 => [true],
                30 => [true, true],
                // $this-> inside a final class: no subclass can exist to
                // divert the dispatch.
                69 => [true],
                70 => [true],
                // Early-bound: ClassName::, self::, new ClassName, new self —
                // and static:: contained by the same final class.
                73 => [true],
                74 => [true],
                77 => [true],
                80 => [true],
                81 => [true],
                84 => [true, true],
                88 => [true],
                // Unfixable for an unrelated reason: a later argument in the
                // call cannot be named.
                93 => [false],
                96 => [false],
                100 => [false],
                // Late-bound in an extendable class — $this->, static:: and
                // new static(). An override may rename the parameter, so the
                // violation is reported unfixed.
                132 => [false],
                135 => [false],
                138 => [false],
                // A private method is resolved in the scope that declares it,
                // so `$this->` reaches this one...
                142 => [true],
                // ...but `static::` binds to the subclass before it checks
                // visibility, so `private` does not protect it there.
                146 => [false],
                // A final method — and a final constructor behind
                // `new static()` — cannot be overridden at all.
                149 => [true],
                150 => [true],
                // `self` is not late-bound: it names the class it is written
                // in, so it reaches these declarations even though a subclass
                // may override them.
                155 => [true],
                156 => [true],
                196 => [true],
                // A trait's methods are copied into the using class, which may
                // replace any of them: public, private and final alike.
                213 => [false],
                214 => [false],
                215 => [false],
                // `self` inside a trait names the *using* class, so it is no
                // more provable than `$this->` is — rewriting either would
                // write the trait's parameter name into a call the using
                // class's own declaration answers.
                222 => [false],
                223 => [false],
                // The enclosing trait does not make this unfixable: `self`
                // names the anonymous class the call is written in, and
                // nothing can extend that.
                251 => [true],
                // An enum cannot be extended and an anonymous class has no
                // name to extend, so neither can be subclassed.
                270 => [true],
                287 => [true],
                // A namespace-level function is early-bound.
                302 => [true],
            ],
            $this->fixableByLine($file->getErrors())
        );

        $this->assertSame(35, $file->getErrorCount());
        $this->assertSame(23, $file->getFixableCount());
    }

    /**
     * @testWith [93]
     *           [96]
     *           [100]
     */
    public function testTheUnfixableViolationExplainsWhy(int $line): void
    {
        $message = $this->firstErrorOnLine($this->processFixture('failing.inc'), $line);

        $this->assertFalse($message['fixable']);
        $this->assertStringContainsString('cannot be fixed automatically', $message['message']);
        $this->assertStringContainsString('cannot be named', $message['message']);
    }

    /**
     * @testWith [132]
     *           [135]
     *           [138]
     *           [146]
     *           [213]
     *           [214]
     *           [215]
     *           [222]
     *           [223]
     */
    public function testTheLateBoundViolationExplainsWhy(int $line): void
    {
        $message = $this->firstErrorOnLine($this->processFixture('failing.inc'), $line);

        $this->assertFalse($message['fixable']);
        $this->assertStringContainsString('cannot be fixed automatically', $message['message']);
        $this->assertStringContainsString('dispatched against the runtime class', $message['message']);
    }

    public function testViolationMessageNamesTheParameterToUse(): void
    {
        $message = $this->firstErrorOnLine($this->processFixture('failing.inc'), 302);

        $this->assertTrue($message['fixable']);
        $this->assertStringContainsString('$retries', $message['message']);
        $this->assertStringContainsString('retries: null', $message['message']);
    }

    /**
     * A short name is unique only within its namespace block, so a declaration
     * in another block belongs to a different symbol and must not be borrowed.
     * Only the two calls in the first block resolve to an optional parameter;
     * the identical-looking calls in the second reach declarations whose
     * parameter is required.
     */
    public function testResolutionStopsAtTheNamespaceBoundary(): void
    {
        $file = $this->processFixture('namespaces.inc');

        $this->assertSame(
            [
                27 => [self::VIOLATION],
                28 => [self::VIOLATION],
            ],
            $this->sourcesByLine($file->getErrors())
        );
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
     * Re-running the sniff over its own output must surface nothing new: every
     * violation the fixer touched is gone, and the only reports left are the
     * ones it deliberately declined to fix — the trailing-spread call on line
     * 30, the late-bound call on line 54, and the two `self`-in-a-trait calls
     * on lines 75 and 76.
     */
    public function testTheFixedOutputIsCompliantExceptWhereTheFixerDeclined(): void
    {
        $file = $this->processFixture('autofix-after.inc');

        $this->assertSame(
            [
                30 => [self::VIOLATION],
                54 => [self::VIOLATION],
                75 => [self::VIOLATION],
                76 => [self::VIOLATION],
            ],
            $this->sourcesByLine($file->getErrors())
        );
        $this->assertSame(0, $file->getFixableCount());
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
     * Returns the first violation reported on $line.
     *
     * @return array<string, mixed>
     */
    private function firstErrorOnLine(LocalFile $file, int $line): array
    {
        $errors = $file->getErrors();

        $this->assertArrayHasKey($line, $errors);

        return array_values(array_values($errors[$line])[0])[0];
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
        return $this->collapseByLine($messages, 'source');
    }

    /**
     * Collapses the same structure to a map of line number => list of the
     * violations' fixable flags.
     *
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, array<int, bool>>
     */
    private function fixableByLine(array $messages): array
    {
        return $this->collapseByLine($messages, 'fixable');
    }

    /**
     * @param array<int, array<int, array<int, array<string, mixed>>>> $messages
     *
     * @return array<int, array<int, mixed>>
     */
    private function collapseByLine(array $messages, string $key): array
    {
        $collapsed = [];

        foreach ($messages as $line => $columns) {
            foreach ($columns as $violations) {
                foreach ($violations as $violation) {
                    $collapsed[$line][] = $violation[$key];
                }
            }
        }

        ksort($collapsed);

        return $collapsed;
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Naming.ModelNamingConventions sniff (Models:
 * Naming Conventions, #44). Fixtures live in
 * Fixtures/ModelNamingConventionsSniff/ beside this file.
 *
 * The rule is report-only — renaming an identifier or rewriting a legacy
 * accessor is never safe for a fixer — so there are no autofix-before /
 * autofix-after fixtures; testViolationsAreNotAutoFixable pins that decision.
 *
 * The sniff is isolated from the rest of the master ruleset so these
 * assertions stay stable as sibling standards land in rules.xml.
 */
class ModelNamingConventionsTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Naming.ModelNamingConventions';

    private const FIXTURE_DIR = '/Fixtures/ModelNamingConventionsSniff/';

    public function testSniffIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    public function testCompliantModelProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Every rule the standard defines, each flagged at its own declaration line
     * under its own code so a consumer can tune them individually.
     */
    public function testEveryViolationIsFlaggedAtItsLineUnderItsOwnCode(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(
            [
                // Boolean property not phrased as a yes/no question — the
                // third only borrows the letters of `is` ($isolated).
                13 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                15 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                19 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                // Boolean method not phrased as a yes/no question — the second
                // only borrows the letters of `can` (candidate()).
                21 => [self::SNIFF_CODE . '.BooleanMethodPrefix'],
                27 => [self::SNIFF_CODE . '.BooleanMethodPrefix'],
                // Single-model return without the `find` prefix — the second
                // writes its nullability as `User|null` rather than `?User`.
                32 => [self::SNIFF_CODE . '.FindMethodPrefix'],
                37 => [self::SNIFF_CODE . '.FindMethodPrefix'],
                // `find`-prefixed but silent about the model it returns.
                42 => [self::SNIFF_CODE . '.FindModelName'],
                // Collection return without the `get` prefix.
                47 => [self::SNIFF_CODE . '.GetMethodPrefix'],
                // Legacy accessor style, read and write halves alike.
                52 => [self::SNIFF_CODE . '.LegacyAttributeAccessor'],
                57 => [self::SNIFF_CODE . '.LegacyAttributeAccessor'],
                // Root-anchored return type that does resolve into a Models
                // namespace — fully qualified is not an exemption.
                64 => [self::SNIFF_CODE . '.FindMethodPrefix'],
                // Qualified name whose first segment resolves through an
                // import: App\Domain\Models\Account.
                71 => [self::SNIFF_CODE . '.FindMethodPrefix'],
                // Promoted properties, flagged on their own parameter line;
                // the plain $title parameter beside them declares nothing.
                80 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                81 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
            ],
            $this->sourcesByLine($file->getErrors())
        );

        $this->assertSame([], $file->getWarnings());
    }

    /**
     * A model that lives outside a `Models` namespace is still recognised
     * through the Eloquent base class it extends — directly (`Model`) and
     * through an aliased import (`Authenticatable`).
     */
    public function testModelIsRecognisedByItsEloquentBaseClass(): void
    {
        $file = $this->processFixture('model-by-base-class.inc');

        $this->assertSame(
            [
                16 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                18 => [self::SNIFF_CODE . '.BooleanMethodPrefix'],
                26 => [self::SNIFF_CODE . '.BooleanMethodPrefix'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * The naming rules describe how a model exposes data; a plain service class
     * carrying the very same declarations must go unreported.
     */
    public function testNonModelClassIsLeftAlone(): void
    {
        $file = $this->processFixture('not-a-model.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * The declarations that carry no usable signal — untyped properties
     * (promoted or not), non-boolean promoted properties, plain parameters,
     * missing or union return types, `array` and other builtins, magic methods,
     * Eloquent's own override points, group-imported relation types, non-model
     * classes from other namespaces, root-anchored (fully qualified) return
     * types that resolve outside a Models namespace, and locals inside a method
     * body — must all stay silent rather than guess.
     */
    public function testAmbiguousAndExemptDeclarationsAreSkipped(): void
    {
        $file = $this->processFixture('edge-cases.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * An abstract method ends at a semicolon rather than a body, so its
     * parameters sit at what looks like class-body level. They must not be
     * read as model properties (`bool $strict` is not a `$strict` property),
     * and the walk over the class body has to pick up again after them — which
     * the reported `expired()` on the far side proves.
     *
     * Two guards hold this contract: the declaration skip in endOfMethod()
     * keeps the parameters out of member discovery, and the catch in
     * processProperty() absorbs the exception PHPCS raises if one ever reaches
     * it anyway. Breaking either alone leaves this assertion green; breaking
     * both makes it error, which is the point — a consumer's PHPCS run must
     * never abort on an abstract model method.
     */
    public function testAbstractMethodParametersAreNotReadAsProperties(): void
    {
        $file = $this->processFixture('abstract-model.inc');

        $this->assertSame(
            [20 => [self::SNIFF_CODE . '.BooleanMethodPrefix']],
            $this->sourcesByLine($file->getErrors())
        );
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertGreaterThan(0, $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());
    }

    private function processFixture(string $fixture): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test after the full ruleset has loaded it —
        // see NotOperatorSpacingTest for why $config->sniffs cannot be used.
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
}

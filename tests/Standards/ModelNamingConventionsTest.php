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
     * through the Eloquent base class it extends. Each class in the fixture
     * spells its base differently — a plain import, an aliased one
     * (`Model as EloquentModel`), the conventional `Authenticatable` alias of
     * `Illuminate\Foundation\Auth\User`, and a fully qualified name — and none
     * of them can be matched on the name as written.
     */
    public function testModelIsRecognisedByItsEloquentBaseClass(): void
    {
        $file = $this->processFixture('model-by-base-class.inc');

        $this->assertSame(
            [
                // Plain import of the Eloquent base.
                20 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                22 => [self::SNIFF_CODE . '.BooleanMethodPrefix'],
                // Base imported under an alias: only resolution reaches
                // Illuminate\Database\Eloquent\Model.
                34 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                36 => [self::SNIFF_CODE . '.BooleanMethodPrefix'],
                // `Authenticatable` is an alias, not a class name — it resolves
                // to Illuminate\Foundation\Auth\User, whose short name is
                // `User`, so neither the alias nor the resolved short name
                // identifies it. Only the fully qualified name does.
                50 => [self::SNIFF_CODE . '.BooleanMethodPrefix'],
                // Fully qualified base, no import at all.
                62 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * The naming rules describe how a model exposes data; a plain service class
     * carrying the very same declarations must go unreported — and so must a
     * class extending something merely *called* `Model`, which the fixture
     * aliases onto a plain value object. Projects owning their own `Model` do
     * exactly that, so the base name as written proves nothing.
     */
    public function testNonModelClassIsLeftAlone(): void
    {
        $file = $this->processFixture('not-a-model.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * An unimported `Model` resolves against the enclosing namespace, so it is
     * the project's own base class and not Eloquent's — reaching Eloquent's
     * requires a `use` statement or a leading backslash.
     */
    public function testUnimportedBaseClassIsNotEloquentsModel(): void
    {
        $file = $this->processFixture('unimported-base.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Every name the sniff interprets goes through the import map first. Read
     * as written, none of these three violations exists: `CollectionAlias` is
     * not in the collection list, and `findLedgerById()` appears to name the
     * very model it returns.
     */
    public function testAliasedTypesAreResolvedBeforeTheyAreInterpreted(): void
    {
        $file = $this->processFixture('aliased-imports.inc');

        $this->assertSame(
            [
                // Resolves to Illuminate\Support\Collection.
                24 => [self::SNIFF_CODE . '.GetMethodPrefix'],
                // Resolves to App\Domain\Models\Account.
                31 => [self::SNIFF_CODE . '.FindMethodPrefix'],
                // Named after the alias rather than the model behind it.
                39 => [self::SNIFF_CODE . '.FindModelName'],
            ],
            $this->sourcesByLine($file->getErrors())
        );
    }

    /**
     * The costly direction: correct code that the same blindness would report.
     * A method named after the model it really returns must not be told to
     * rename itself after a file-local alias.
     */
    public function testCorrectNamesBehindAliasedTypesAreNotReported(): void
    {
        $file = $this->processFixture('aliased-imports-passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * `use function` / `use const` import from PHP's separate symbol tables and
     * never name a type, so they must not enter the class import map — where
     * they would resolve a return type under the imported namespace and report
     * correct code. The marker appears in three shapes, all covered by the
     * fixture: on an individual item of a mixed group, before a group prefix,
     * and on a plain statement — and it marks a constant as readily as a
     * function, so the fixture carries both keywords in the shapes that can
     * express them.
     *
     * Every one of those imports is *used* as a return type. That is what makes
     * this assertion depend on the screen: an unused import would leave the
     * const half of the check free to be deleted with the suite still green.
     *
     * Screening them must not cost the class item beside them, which the
     * silent, correctly named `findUserById(): User` proves is still imported.
     */
    public function testSymbolImportsNeverEnterTheClassImportMap(): void
    {
        $file = $this->processFixture('group-use-mixed.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * PHP identifies class names, `use` aliases and method names
     * case-insensitively, so the sniff has to as well: a name spelled
     * differently from the symbol it refers to is still that symbol. Matched on
     * source casing, none of these violations exists — the aliased base class
     * resolves to nothing and takes its whole class dark with it, the
     * acronym-drifted return type is not the imported model, and neither legacy
     * accessor ends in `Attribute`.
     */
    public function testCaseMismatchedSymbolsAreStillIdentified(): void
    {
        $file = $this->processFixture('case-mismatched-symbols.inc');

        $this->assertSame(
            [
                // The class is a model only through `extends eloquentmodel`,
                // the mis-cased spelling of an aliased Eloquent base — so this
                // property is reported only if that resolved.
                24 => [self::SNIFF_CODE . '.BooleanPropertyPrefix'],
                // `ApiToken` is the imported `APIToken`: a model, and this
                // method lacks the `find` prefix.
                32 => [self::SNIFF_CODE . '.FindMethodPrefix'],
                // Identified case-insensitively, judged case-sensitively: the
                // model is spelled `APIToken`, so `findApiToken` does not name
                // it. The judging half must stay strict.
                43 => [self::SNIFF_CODE . '.FindModelName'],
                // Live legacy accessors — Eloquent finds them through
                // method_exists(), which folds case.
                57 => [self::SNIFF_CODE . '.LegacyAttributeAccessor'],
                62 => [self::SNIFF_CODE . '.LegacyAttributeAccessor'],
            ],
            $this->sourcesByLine($file->getErrors())
        );

        $this->assertSame([], $file->getWarnings());
    }

    /**
     * The costly direction of the same invariant. Under a `Models` namespace a
     * mis-cased alias that misses the import map falls through to
     * namespace-qualification, manufacturing a name with a `Models` segment —
     * so the sniff reads a value object as a model and a collection as a single
     * instance, and reports correct code. The mis-cased override is worse than
     * noise: acting on the advice breaks the override.
     */
    public function testCaseMismatchedSymbolsDoNotInventViolations(): void
    {
        $file = $this->processFixture('case-mismatched-symbols-passing.inc');

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

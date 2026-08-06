<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Models.RequireLazyLoadingPrevention sniff
 * (Models: Eager Loading, #74/#154). Fixtures live in
 * Fixtures/RequireLazyLoadingPreventionSniff/ beside this file.
 *
 * The sniff is an absence check: a watched provider class that never enables
 * Laravel's lazy-loading safety check earns one warning on its class
 * declaration. That shape makes a silent sniff indistinguishable from a
 * satisfied one, so every "no violations" assertion here is paired with a
 * fixture that does warn — the negative alone would pass just as well against
 * a sniff that never fires.
 *
 * The rule is detection-only: writing the call into a service provider is a
 * change to application bootstrapping, not a formatting fix, so there are no
 * autofix fixtures.
 *
 * rules.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in rules.xml.
 */
class RequireLazyLoadingPreventionTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Models.RequireLazyLoadingPrevention';

    private const WARNING_CODE = self::SNIFF_CODE . '.Missing';

    private const FIXTURE_DIR = '/Fixtures/RequireLazyLoadingPreventionSniff/';

    public function testSniffIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    /**
     * The canonical provider stays silent, and so does everything the sniff
     * must not police:
     *
     * - lines 3-14, `AppServiceProvider` calling
     *   `Model::preventLazyLoading(! $this->app->isProduction());` in `boot()`
     *   — the shape the standard's doc recommends.
     * - lines 16-22, `RouteServiceProvider` — a sibling provider with no
     *   safety check at all. Every Laravel app and package ships several of
     *   these, and none is expected to enable the check, which is why the
     *   sniff keys off the class name rather than `extends ServiceProvider`.
     * - lines 24-27, a model carrying a populated `$with` property. That is a
     *   violation of the *other* slice of this standard (#153) and must not be
     *   reported by this sniff, whose subject is the provider alone.
     */
    public function testAProviderThatEnablesTheSafetyCheckProducesNoViolations(): void
    {
        $file = $this->processFixture('passing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * `Model::shouldBeStrict()` turns preventLazyLoading() on as part of
     * Laravel's strict mode, so a provider using it has enabled the check and
     * must not be flagged. This pins the second entry of the accepted-method
     * list; dropping it from the sniff turns this fixture into a violation.
     */
    public function testStrictModeSatisfiesTheCheck(): void
    {
        $file = $this->processFixture('strict-mode.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * `boot()` delegating to a private `configureModels()` helper is a common
     * provider idiom, and the check is switched on either way. The sniff
     * therefore accepts the call anywhere in the class body; a version that
     * searched only `boot()`'s scope would report this file.
     */
    public function testTheCallIsAcceptedAnywhereInTheClassBody(): void
    {
        $file = $this->processFixture('delegated.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * A provider with a real `boot()` that never enables the check earns
     * exactly one warning, reported on the class declaration (line 3) rather
     * than on any single statement — the defect is the absence of a call, so
     * it has no line of its own.
     */
    public function testAProviderWithoutTheSafetyCheckIsFlaggedOnItsClassDeclaration(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame(
            [3 => [self::WARNING_CODE]],
            $this->sourcesByLine($file->getWarnings())
        );
    }

    /**
     * The message names the class that is missing the call, so a report over a
     * whole application says which provider to open.
     */
    public function testTheWarningMessageNamesTheProviderClass(): void
    {
        $warnings = $this->processFixture('failing.inc')->getWarnings();

        $this->assertStringContainsString('AppServiceProvider', $warnings[3][1][0]['message']);
    }

    /**
     * Four shapes that mention the method without calling it statically, all
     * in one provider that must still be reported. Each pins one condition of
     * the detection, and a sniff missing any of them would fall silent here:
     *
     * - line 7, the call commented out — a T_COMMENT, never a T_STRING.
     * - line 8, the name inside a string literal.
     * - line 9, `$this->preventLazyLoading()` — an object-operator call. The
     *   Laravel API is static only, so the operator before the name has to be
     *   `::`.
     * - line 10, `preventLazyLoading()` as a plain function call — no operator
     *   before the name at all.
     * - line 11, `Model::preventLazyLoading` with no argument list — a
     *   constant fetch, not a call, which is why the token after the name has
     *   to open a parenthesis.
     */
    public function testShapesThatOnlyMentionTheMethodDoNotSatisfyTheCheck(): void
    {
        $file = $this->processFixture('near-miss.inc');

        $this->assertSame(
            [3 => [self::WARNING_CODE]],
            $this->sourcesByLine($file->getWarnings())
        );
    }

    /**
     * The search is bounded by the provider's own class body. Here a *second*
     * class in the same file (line 11) does enable the check while the watched
     * one (line 3) does not, so the watched class is still reported. A
     * file-wide search would report nothing.
     */
    public function testTheSearchIsScopedToTheWatchedClassBody(): void
    {
        $file = $this->processFixture('scoped.inc');

        $this->assertSame(
            [3 => [self::WARNING_CODE]],
            $this->sourcesByLine($file->getWarnings())
        );
    }

    /**
     * The watched class names are a public sniff property, so an application
     * that boots the check from another provider can point the sniff at it.
     * One fixture pins both directions: `ModelServiceProvider` without the
     * call is silent under the shipped default and reported once the list
     * names it. A property that was ignored would leave both runs identical
     * and fail the second assertion.
     *
     * The configured name is spelled in lower case against a PascalCase
     * class, because PHP class names are case-insensitive and a consuming
     * ruleset should not have to match the declaration's casing.
     */
    public function testTheWatchedProviderListIsConfigurable(): void
    {
        $shipped = $this->processFixture('configured.inc');

        $this->assertSame([], $shipped->getWarnings());

        $configured = $this->processFixture(
            'configured.inc',
            static function (object $sniff): void {
                $sniff->serviceProviderClasses = ['modelserviceprovider'];
            }
        );

        $this->assertSame(
            [3 => [self::WARNING_CODE]],
            $this->sourcesByLine($configured->getWarnings())
        );
    }

    /**
     * PHP_CodeSniffer tokenizes files mid-edit, so the fixture ends with
     * `Model::preventLazyLoading` and nothing after it — no argument list, no
     * closing braces. The sniff has to pass over the half-written call rather
     * than fall over or accept it, and the class is reported because the check
     * genuinely is not enabled yet.
     *
     * With no token of any kind after the name, the lookahead for the opening
     * parenthesis returns false. The `$afterPtr !== false` guard that reads as
     * what handles this is in fact defensive only, and removing it changes no
     * result: PHP resolves `$tokens[false]` to `$tokens[0]`, the open tag,
     * which fails the parenthesis comparison anyway. It is kept for saying so
     * outright instead of leaning on that coercion. Stated here because no
     * fixture can pin it — this test covers the truncated call, not the guard.
     */
    public function testATruncatedCallIsHandledWithoutFallingOver(): void
    {
        $file = $this->processFixture('truncated.inc');

        $this->assertSame(
            [3 => [self::WARNING_CODE]],
            $this->sourcesByLine($file->getWarnings())
        );
    }

    /**
     * A `class` keyword with no name after it — the other half-written shape
     * PHPCS hands a sniff mid-edit. There is no class name to match against
     * the watched list, so the sniff passes over the file rather than falling
     * over on it. This is what reaches the `$name === null` guard; anonymous
     * classes cannot, because PHPCS gives them their own T_ANON_CLASS token,
     * which this sniff never registers for.
     */
    public function testAClassKeywordWithNoNameIsPassedOver(): void
    {
        $file = $this->processFixture('nameless.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * Pins the detection-only decision: enabling the safety check is a change
     * to how the application boots, so no violation is auto-fixable.
     */
    public function testViolationsAreDetectionOnlyWarnings(): void
    {
        $file = $this->processFixture('failing.inc');

        $this->assertSame(1, $file->getWarningCount());
        $this->assertSame(0, $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());
    }

    /**
     * $configure, when given, receives the isolated sniff instance so a test
     * can set its public properties the way a consuming ruleset would.
     *
     * @param callable(object): void|null $configure
     */
    private function processFixture(string $fixture, ?callable $configure = null): LocalFile
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

        if ($configure !== null) {
            $configure($ruleset->sniffs[$sniffClass]);
        }

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
}

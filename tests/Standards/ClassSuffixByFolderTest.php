<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom CleanCode.Classes.ClassSuffixByFolder sniff (Classes: Class
 * Naming, #27). Fixtures live in Fixtures/ClassSuffixByFolderSniff/ beside this
 * file, split into passing/ and failing/ trees.
 *
 * This sniff reads the file's *path*, so each fixture has to sit at a path that
 * mirrors a real Laravel layout — hence the `app/…` directories inside the
 * passing/ and failing/ trees rather than flat fixture files.
 *
 * The rule is report-only — renaming a class means rewriting every reference to
 * it, which a token-based fixer cannot follow — so there are no
 * autofix-before/autofix-after fixtures; testViolationsAreNotAutoFixable pins
 * that decision.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */
class ClassSuffixByFolderTest extends TestCase
{
    private const SNIFF_CODE = 'CleanCode.Classes.ClassSuffixByFolder';

    private const FIXTURE_DIR = '/Fixtures/ClassSuffixByFolderSniff/';

    /**
     * Every folder category the standard distinguishes, with a compliantly
     * named class: the no-suffix folders (app/ itself, app/Models,
     * app/Livewire), the Http folders (bare, flat, nested), app/Livewire/Forms,
     * a generic base folder (flat and nested), and the folder-name
     * singularisation cases (plural, ies-plural, already-singular, double-s).
     *
     * @return array<string, array{0: string}>
     */
    public static function compliantFixtureProvider(): array
    {
        $fixtures = [
            'directly in app/, no base folder to derive from' => 'app/Bootstrapper.inc',
            'app/Models takes no suffix' => 'app/Models/User.inc',
            'app/Livewire takes no suffix' => 'app/Livewire/Counter.inc',
            'app/Livewire/Forms takes the Form suffix' => 'app/Livewire/Forms/LoginForm.inc',
            'directly in app/Http, no segment below it' => 'app/Http/Kernel.inc',
            'app/Http suffix comes from the segment below Http' => 'app/Http/Controllers/UserController.inc',
            'nested app/Http folders keep their category suffix' => 'app/Http/Controllers/Api/TokenController.inc',
            'already-singular folder name is used as-is' => 'app/Http/Middleware/AuthenticateMiddleware.inc',
            'generic base folder, singularised' => 'app/Services/PaymentService.inc',
            'nested generic folder still uses the base folder' => 'app/Services/Billing/StripeService.inc',
            'ies-plural folder singularises to y' => 'app/Policies/UserPolicy.inc',
            'double-s folder name is not trimmed' => 'app/Access/PortalAccess.inc',
            'Form suffix is allowed where the folder derives it' => 'app/Forms/ContactForm.inc',
            'anonymous class has no name to judge' => 'app/Services/AnonymousFactoryService.inc',
            'the last app segment is the application folder' => 'app/app/Models/User.inc',
        ];

        return array_map(static fn (string $path): array => ['passing/' . $path], $fixtures);
    }

    /**
     * A misnamed class in each folder category, with the violation codes it must
     * produce. Keyed by fixture so a failure names the case.
     *
     * @return array<string, array{0: string, 1: array<int, array<int, string>>}>
     */
    public static function violatingFixtureProvider(): array
    {
        return [
            'generic base folder is missing its suffix' => [
                'failing/app/Services/Payment.inc',
                [7 => [self::SNIFF_CODE . '.MissingSuffix']],
            ],
            'ies-plural folder is not itself a valid suffix' => [
                'failing/app/Policies/UserPolicies.inc',
                [8 => [self::SNIFF_CODE . '.MissingSuffix']],
            ],
            'app/Http class is missing its category suffix' => [
                'failing/app/Http/Controllers/UserThing.inc',
                [7 => [self::SNIFF_CODE . '.MissingSuffix']],
            ],
            'app/Livewire/Forms class is missing the Form suffix' => [
                'failing/app/Livewire/Forms/Login.inc',
                [7 => [self::SNIFF_CODE . '.MissingSuffix']],
            ],
            'Form-suffixed class in a no-suffix folder is misplaced' => [
                'failing/app/Models/UserForm.inc',
                [9 => [self::SNIFF_CODE . '.MisplacedFormClass']],
            ],
            'Form-suffixed class in app/Livewire belongs one folder down' => [
                'failing/app/Livewire/LoginForm.inc',
                [9 => [self::SNIFF_CODE . '.MisplacedFormClass']],
            ],
            'missing suffix and misplaced Form are reported independently' => [
                'failing/app/Services/PaymentForm.inc',
                [
                    9 => [
                        self::SNIFF_CODE . '.MissingSuffix',
                        self::SNIFF_CODE . '.MisplacedFormClass',
                    ],
                ],
            ],
        ];
    }

    public function testSniffIsRegisteredInMasterRuleset(): void
    {
        $ruleset = new Ruleset($this->createConfig());

        $this->assertArrayHasKey(self::SNIFF_CODE, $ruleset->sniffCodes);
    }

    /**
     * @dataProvider compliantFixtureProvider
     */
    public function testCompliantClassProducesNoViolations(string $fixture): void
    {
        $file = $this->processFixture($fixture);

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * @dataProvider violatingFixtureProvider
     *
     * @param array<int, array<int, string>> $expected
     */
    public function testMisnamedClassIsFlaggedAtItsDeclarationLine(
        string $fixture,
        array $expected
    ): void {
        $file = $this->processFixture($fixture);

        $this->assertSame($expected, $this->sourcesByLine($file->getErrors()));
        $this->assertSame([], $file->getWarnings());
    }

    /**
     * The standard is scoped to a Laravel application folder. The fixture is
     * processed from a temporary copy so the assertion cannot be broken by a
     * checkout whose own path happens to contain an `app` directory.
     */
    public function testClassOutsideAnAppFolderIsIgnored(): void
    {
        $source = __DIR__ . self::FIXTURE_DIR . 'outside-app/src/Services/Payment.inc';
        $directory = sys_get_temp_dir() . '/clean-code-outside-' . bin2hex(random_bytes(8)) . '/src/Services';

        $this->assertTrue(mkdir($directory, 0777, true));
        $this->assertNotFalse(copy($source, $directory . '/Payment.inc'));

        try {
            $file = $this->process($directory . '/Payment.inc');

            $this->assertSame([], $file->getErrors());
            $this->assertSame([], $file->getWarnings());
        } finally {
            unlink($directory . '/Payment.inc');
            rmdir($directory);
            rmdir(dirname($directory));
            rmdir(dirname($directory, 2));
        }
    }

    /**
     * A `class` keyword with no name token after it still tokenises as T_CLASS.
     * There is no name to judge, so the sniff must report nothing instead of
     * crashing on a work-in-progress file.
     */
    public function testNamelessClassDeclarationIsIgnored(): void
    {
        $file = $this->processFixture('unnamed/app/Services/Truncated.inc');

        $this->assertSame([], $file->getErrors());
        $this->assertSame([], $file->getWarnings());
    }

    public function testViolationsAreNotAutoFixable(): void
    {
        $file = $this->processFixture('failing/app/Services/PaymentForm.inc');

        $this->assertSame(2, $file->getErrorCount());
        $this->assertSame(0, $file->getFixableCount());
    }

    private function processFixture(string $fixture): LocalFile
    {
        return $this->process(__DIR__ . self::FIXTURE_DIR . $fixture);
    }

    private function process(string $path): LocalFile
    {
        $config = $this->createConfig();
        $ruleset = new Ruleset($config);

        // Isolate the sniff under test after the full ruleset has loaded it —
        // see NotOperatorSpacingTest for why $config->sniffs cannot be used.
        $sniffClass = $ruleset->sniffCodes[self::SNIFF_CODE];
        $ruleset->sniffs = [$sniffClass => $ruleset->sniffs[$sniffClass]];
        $ruleset->populateTokenListeners();

        $file = new LocalFile($path, $ruleset, $config);
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

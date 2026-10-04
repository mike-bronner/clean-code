<?php

declare(strict_types=1);

use PHP_CodeSniffer\Exceptions\RuntimeException;

const DISALLOW_REPOSITORY_CLASSES = 'CleanCode.Pattern.DisallowRepositoryClasses';

const DISALLOW_REPOSITORY_CLASSES_WARNING = DISALLOW_REPOSITORY_CLASSES . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_REPOSITORY_CLASSES);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            6 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            10 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            14 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            18 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            22 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            26 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            32 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            38 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            44 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            50 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
            56 => [DISALLOW_REPOSITORY_CLASSES_WARNING],
        ]);
});

it('reports at warning severity, never error', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(11)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports at the declaration keyword', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 6, 'column' => 5, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING])
        ->toContain(['line' => 32, 'column' => 5, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING]);
});

it('names the declaration and the matched half in the message', function (): void {
    $warnings = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'failing.php')->getWarnings();

    expect($warnings[6][5][0]['message'])
        ->toContain('Class UserRepository names itself a repository')
        ->toContain('move the persistence behaviour onto the model')
        ->toContain('resources/boost/guidelines/pattern-repository.md')
        ->toContain('resources/boost/guidelines/models-persistence-methods-repository-pattern.md')
        ->and($warnings[10][5][0]['message'])->toContain('Interface OrderRepositoryInterface')
        ->and($warnings[14][5][0]['message'])->toContain('Trait ArchiveRepository')
        ->and($warnings[18][5][0]['message'])->toContain('Enum LedgerRepository')
        ->and($warnings[26][5][0]['message'])->toContain('Class userrepository')
        ->and($warnings[32][5][0]['message'])
        ->toContain('Class UserFinder is declared in the App\\Repositories namespace')
        ->and($warnings[44][5][0]['message'])->toContain('the App\\repositories namespace')
        ->and($warnings[50][5][0]['message'])
        ->toContain('Class OrderRepository names itself a repository')
        ->not->toContain('is declared in the');
});

it('reads the enclosing namespace, not the nearest namespace token', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'unbraced.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 17, 'column' => 1, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING],
        ])
        ->and($file->getWarnings()[17][1][0]['message'])
        ->toContain('Class LedgerRepository names itself a repository');
});

it('flags by name in the global namespace', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'no-namespace.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 9, 'column' => 1, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING],
        ]);
});

it('stays silent on a declaration with no name after it', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'unterminated-declaration.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('accounts for every declaration name PHPCS defines', function (): void {
    $path = cleanCodeRoot() . '/CleanCode/Sniffs/Pattern/DisallowRepositoryClassesSniff.php';
    $probe = analyzeStdinSource([DISALLOW_REPOSITORY_CLASSES], "<?php\n\necho 'repository';\n");
    $echo = array_keys(array_filter(
            $probe->getTokens(),
            static fn (array $token): bool => $token['code'] === T_ECHO
        ));

    expect($echo)->toHaveCount(1, 'the probe token is where the source puts it');

    $family = [];

    try {
        $probe->getDeclarationName($echo[0]);
    } catch (RuntimeException $exception) {
        preg_match_all('/\bT_[A-Z_0-9]+\b/', $exception->getMessage(), $names);

        $family = array_values(array_diff($names[0], ['T_ECHO']));
    }

    expect($family)->not->toBeEmpty('getDeclarationName() still names its accepted token types');

    sort($family);

    $included = tokenNamesInConstant($path, 'DECLARATION_KEYWORDS', [DISALLOW_REPOSITORY_CLASSES]);
    $excluded = tokenNamesInConstant($path, 'NON_DECLARATION_KEYWORDS', [DISALLOW_REPOSITORY_CLASSES]);
    $accounted = array_merge($included, $excluded);
    sort($accounted);

    expect($included)->not->toBeEmpty('DECLARATION_KEYWORDS was found and read')
        ->and($excluded)->not->toBeEmpty('NON_DECLARATION_KEYWORDS was found and read')
        ->and(array_values(array_intersect($included, $excluded)))
        ->toBe([], 'no token is both reported and deliberately unreported')
        ->and($accounted)->toBe($family, 'every accepted declaration token is accounted for');
});

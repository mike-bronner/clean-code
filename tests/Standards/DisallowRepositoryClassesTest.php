<?php

/**
 * Tests the custom CleanCode.Pattern.DisallowRepositoryClasses sniff (Pattern:
 * Repository, #6, partial enforcement per #126). Fixtures live in
 * tests/fixtures/DisallowRepositoryClassesSniff/: every consumption site and
 * near-miss name in passing.php, every reported shape in failing.php, the
 * one-namespace-per-file spelling in unbraced.php, the global namespace in
 * no-namespace.php, and the mid-edit declaration in
 * unterminated-declaration.php. The rule is detection-only, so there is no
 * autofixed fixture.
 *
 * passing.php and failing.php use braced namespace blocks. Half of this sniff's
 * subject is the namespace a declaration sits in, and one file can only carry
 * more than one of those in the braced spelling; unbraced.php carries the
 * everyday spelling on its own.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Exceptions\RuntimeException;

const DISALLOW_REPOSITORY_CLASSES = 'CleanCode.Pattern.DisallowRepositoryClasses';

const DISALLOW_REPOSITORY_CLASSES_WARNING = DISALLOW_REPOSITORY_CLASSES . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_REPOSITORY_CLASSES);
});

/**
 * Every consumption site and near-miss name stays silent. Each group pins one
 * of the sniff's exits, and a false positive on any of them makes the rule
 * unusable in a real application:
 *
 * - lines 6 and 7, `use App\Contracts\UserRepositoryInterface;` and
 *   `use App\Support\Repositories\CachesQueries;` — *imports* of a repository
 *   name and of a `Repositories` namespace. Importing is not declaring, and the
 *   sniff registers on declaration keywords alone.
 * - line 9, `class User extends EloquentRepository implements
 *   UserRepositoryInterface` — the third-party base class the acceptance
 *   criteria single out. Both the parent and the interface carry a repository
 *   name; the declared name is `User`, and only the declared name is read.
 * - line 11, `use CachesQueries;` — a trait mixed into a class body, the other
 *   consumption site the criteria name.
 * - lines 13 and 15, `public function repository()` and `new UserRepository()` —
 *   a method named for the pattern and an instantiation of a repository type.
 *   Neither is an OO declaration, and T_FUNCTION is deliberately outside the
 *   registered family.
 * - line 19, `class UserRepositoryFactory` — a name that *contains* the suffix
 *   without ending in it. Degrading the suffix test into a substring match
 *   flags it.
 * - line 23, `class UserRepositories` — the plural. It ends in neither
 *   configured suffix, and a factory of repositories is not itself one.
 * - line 29, `namespace App\Repository` — the singular namespace segment. The
 *   standard names `Repositories\` as the directory the pattern is filed under;
 *   a namespace named for one repository the project consumes is not it.
 * - line 35, `namespace App\RepositoriesLegacy` — a segment that *contains* the
 *   one looked for. The comparison is whole-segment, and a substring match
 *   flags this.
 * - line 45, `namespace\Repositories\present()` — the relative-name operator,
 *   which tokenises as T_NAMESPACE too. The `Repositories` qualifier is what
 *   makes the line discriminating: read as a namespace declaration, it puts
 *   `class Formatter` on line 41 inside a repository namespace and the file
 *   reports.
 * - line 51, `new class` inside `namespace App\Repositories` — an anonymous
 *   class. It is a `new` expression rather than a dedicated type, and
 *   PHP_CodeSniffer gives it its own T_ANON_CLASS token, which is not
 *   registered.
 * - line 60, `class Ledger` in the global `namespace { }` block — the braced
 *   global namespace, whose name Slevomat's helper reads as `{`. Nothing there
 *   is a `Repositories` segment, so the block is left alone.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every dedicated repository type is flagged at its own declaration.
 *
 * - lines 6, 10, 14 and 18 — the four registered keywords, `class`,
 *   `interface`, `trait` and `enum`, each named for the pattern. Dropping any
 *   one of them from the registered family leaves that spelling of the
 *   violation unreported.
 * - line 10 also pins the second suffix on its own: `OrderRepositoryInterface`
 *   ends in neither `Repository` nor any suffix of it, so `repository` alone
 *   never matches it.
 * - line 22, `class Repository` — the suffix as the whole name.
 * - line 26, `class userrepository` — the suffix comparison is
 *   case-insensitive, which is what PHP's own case-insensitive type names make
 *   necessary.
 * - line 32, `class UserFinder` in `App\Repositories` — the namespace half,
 *   with a name that matches nothing.
 * - line 38, `class LedgerFinder` in `App\Domain\Repositories\Eloquent` — the
 *   segment is neither the first nor the last. A check anchored to either
 *   position misses it.
 * - line 44, `class Registrar` in `App\repositories` — the segment comparison
 *   is case-insensitive too.
 * - line 50, `class OrderRepository` in `App\Repositories\Support` — both
 *   halves hold at once, and the declaration is reported exactly once.
 * - line 56, `class GlobalRepository` in the global `namespace { }` block — the
 *   name half still applies where there is no namespace to read.
 */
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

/**
 * The report severity is the standard's own judgement, not an incidental
 * detail: the heuristic reads names rather than behaviour, so a hit is a hint
 * for the reviewer and must not fail a consumer's build on a type it has merely
 * misread. Asserted as a property of the sniff's whole output — every message
 * it raised on the failing fixture arrived through addWarning(), none through
 * addError(), and none is offered to the fixer — so a single detection switched
 * to error severity, or handed to the fixer, fails here.
 */
it('reports at warning severity, never error', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(11)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * Each warning is reported at the declaration keyword, so an editor's inline
 * marker sits on the declaration that has to go rather than on its body or its
 * name. Column 5 is the indentation inside a braced namespace block, which is
 * where every declaration in this fixture sits.
 */
it('reports at the declaration keyword', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 6, 'column' => 5, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING])
        ->toContain(['line' => 32, 'column' => 5, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING]);
});

/**
 * The message names the declaration, says which half of the convention it
 * matched, and points at both standards the reader has to act on. The
 * `userrepository` and `App\repositories` messages are asserted because both
 * comparisons are case-insensitive while the message is not: the source
 * spelling has to survive into the output rather than the lowercased copy the
 * check works from.
 *
 * Line 50 is the declaration where both halves hold. It carries the *name*
 * message and not the namespace one, which is what pins the choice between them
 * rather than leaving it to whichever branch happened to run first.
 */
it('names the declaration and the matched half in the message', function (): void {
    $warnings = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'failing.php')->getWarnings();

    expect($warnings[6][5][0]['message'])
        ->toContain('Class UserRepository names itself a repository')
        ->toContain('move the persistence behaviour onto the model')
        ->toContain('docs/standards/pattern-repository.md')
        ->toContain('docs/standards/models-persistence-methods-repository-pattern.md')
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

/**
 * The everyday spelling: one semicolon-terminated declaration per file, which
 * is what an application actually writes and what the two braced fixtures give
 * up to carry more than one namespace at a time.
 *
 * `class Ledger` on line 21 is the discriminating half. It sits in `App\Support`
 * — no repository segment — but *after* a `namespace\Repositories\present()`
 * call in a method body on line 13. A namespace resolution that took the
 * nearest preceding `namespace` token rather than the enclosing declaration
 * reads that operator as the current namespace and reports line 21. Line 17
 * proves the file is not simply being skipped.
 */
it('reads the enclosing namespace, not the nearest namespace token', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'unbraced.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 17, 'column' => 1, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING],
        ])
        ->and($file->getWarnings()[17][1][0]['message'])
        ->toContain('Class LedgerRepository names itself a repository');
});

/**
 * A file with no namespace declaration at all, where the helper hands back null
 * rather than a name. The name half still applies (line 9) and the namespace
 * half has nothing to read (line 5), so a null the sniff failed to handle would
 * either report `class Ledger` or crash the run.
 */
it('flags by name in the global namespace', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'no-namespace.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 9, 'column' => 1, 'source' => DISALLOW_REPOSITORY_CLASSES_WARNING],
        ]);
});

/**
 * A file whose last token is the `class` keyword itself — a truncated or
 * mid-edit file, which PHPCS still tokenises and hands to every sniff. There is
 * no name to read, and the file's namespace is `App\Repositories`, so the
 * namespace half would report a declaration the developer is still typing and
 * have nothing to name in the message. The name check has to run first and end
 * the process there.
 */
it('stays silent on a declaration with no name after it', function (): void {
    $file = analyzeFixture(DISALLOW_REPOSITORY_CLASSES, 'unterminated-declaration.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The registered family is *every token type File::getDeclarationName()
 * accepts*, and the sniff's DECLARATION_KEYWORDS map has to account for all of
 * it — included, or excluded with a reason — per CONTRIBUTING.md's "give every
 * token-kind classification array a named family" step.
 *
 * Neither side is restated here. The family is read out of PHP_CodeSniffer at
 * run time, from the rejection message getDeclarationName() itself raises, so a
 * PHPCS release that starts accepting a fifth declaration keyword reddens this
 * test instead of slipping past it. The accounting is parsed out of the
 * constant's own docblock, so a keyword added to the map without a reason fails
 * here too.
 */
it('accounts for every declaration name PHPCS defines', function (): void {
    $source = (string) file_get_contents(
        cleanCodeRoot() . '/CleanCode/Sniffs/Pattern/DisallowRepositoryClassesSniff.php'
    );
    $declaration = strpos($source, 'private const DECLARATION_KEYWORDS');

    expect($declaration)->not->toBeFalse('the constant is still declared under that name');

    $commentEnd = (int) strrpos(substr($source, 0, (int) $declaration), '*/');
    $commentStart = (int) strrpos(substr($source, 0, $commentEnd), '/**');
    $docblock = substr($source, $commentStart, $commentEnd - $commentStart);

    preg_match_all(
        '/^\s*\*\s+- `(T_[A-Z_0-9]+)` — (included|excluded): (\S[^\r\n]*)$/m',
        $docblock,
        $entries,
        PREG_SET_ORDER
    );

    $accounted = [];
    $shortReasons = [];

    foreach ($entries as [, $name, $disposition, $reason]) {
        $accounted[$name] = $disposition;

        if (strlen(trim($reason)) < 10) {
            $shortReasons[] = $name;
        }
    }

    expect($accounted)->toHaveCount(count($entries), 'no token is accounted for twice')
        ->and($shortReasons)->toBe([], 'every member carries a reason, not a placeholder');

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

        // The rejected token names itself in the message before the accepted
        // list; it is the probe, not a member of the family.
        $family = array_values(array_diff($names[0], ['T_ECHO']));
    }

    expect($family)->not->toBeEmpty('getDeclarationName() still names its accepted token types');

    $documented = array_keys($accounted);
    sort($documented);
    sort($family);

    $included = array_keys(array_filter(
        $accounted,
        static fn (string $disposition): bool => $disposition === 'included'
    ));
    sort($included);

    $registered = array_map(
        static fn (int $code): string => (string) token_name($code),
        array_keys((new ReflectionClassConstant(
            MikeBronner\CleanCode\Sniffs\Pattern\DisallowRepositoryClassesSniff::class,
            'DECLARATION_KEYWORDS'
        ))->getValue())
    );
    sort($registered);

    expect($documented)->toBe($family, 'every accepted declaration token is accounted for')
        ->and($registered)->toBe($included, 'the map holds exactly the tokens documented as included');
});

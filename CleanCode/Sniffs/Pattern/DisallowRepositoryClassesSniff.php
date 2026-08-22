<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

/**
 * Warns on a class, interface, trait, or enum declared as a dedicated
 * repository type.
 *
 * Partial enforcement of the "Pattern: Repository" standard
 * (docs/standards/pattern-repository.md, #6). The standard's core rule — that
 * persistence behaviour lives on the model and in its attribute/query traits,
 * shaped by "Models: Persistence Methods (Repository Pattern)"
 * (docs/standards/models-persistence-methods-repository-pattern.md, #37) — is
 * Tier 3: repository *behaviour* is spread across methods and traits
 * throughout a codebase, and one file's token stream cannot decide whether it
 * ended up where the standard requires. That half stays with code review.
 *
 * One slice announces itself in the tokens, and it is the only thing this
 * sniff reads: a dedicated repository type *names* itself for the pattern.
 * A declaration is reported when either half of that naming convention holds:
 *
 * - its own name ends in `Repository` or `RepositoryInterface`
 *   (`UserRepository`, `OrderRepositoryInterface`), or
 * - its declared namespace carries a `Repositories` segment
 *   (`App\Repositories\…`, including any namespace below it).
 *
 * Both halves are compared case-insensitively. PHP resolves type and namespace
 * names case-insensitively, so `Userrepository` and `App\repositories` declare
 * exactly the shape the standard rules out and a case-sensitive check would be
 * evaded by a spelling that changes nothing about the code. The sibling
 * CleanCode.ClearCode.ActionSingleEntryPoint sniff matches its class-name half
 * case-*sensitively* for the opposite reason, and it is worth naming here so
 * the two do not read as an inconsistency: `Action` is a suffix of ordinary
 * English words a project really declares (`Transaction`, `Reaction`,
 * `Interaction`), while no English word ends in `repository`, so there is no
 * collision to protect against on this side.
 *
 * Only the *declaration* is read, never a reference to one. `extends`,
 * `implements`, a trait `use`, an import, and `new` are all consumption sites,
 * and a project that has to extend a third-party `*Repository` base class has
 * not itself declared a repository type. PHP_CodeSniffer gives each of those
 * its own token type, so registering the four declaration keywords excludes
 * them without an exclusion list to keep current.
 *
 * Boundaries — accepted, by design:
 *
 * - The heuristic reads names, not behaviour, so it is evadable in both
 *   directions, exactly as the standard's own doc says: a dedicated
 *   persistence class called `UserStore` outside a `Repositories` namespace
 *   does what the standard forbids and is never reported, while a type that
 *   merely matches the convention is reported whether or not it drives model
 *   persistence. A hit is a hint for the reviewer, not a verdict — which is
 *   why this is a **warning** and never an error.
 * - `Repository` in the singular is not a namespace segment this sniff knows.
 *   The standard names `Repositories\` as the directory the pattern is filed
 *   under, and widening the segment to the singular would flag every
 *   declaration under a namespace named for one repository the project
 *   legitimately consumes.
 * - An anonymous class is never reported. `new class implements
 *   UserRepositoryInterface {}` is a `new` expression — a consumption site,
 *   and not a *dedicated* type at all: it cannot be autoloaded, type-hinted,
 *   or bound by name, so it is not the declaration the standard rules out.
 *   PHP_CodeSniffer tokenises it as T_ANON_CLASS, which is not registered.
 * - One report per declaration, even when both halves hold. `UserRepository`
 *   inside `App\Repositories` is one dedicated repository type with one fix —
 *   move the behaviour onto the model — so it is reported once, by the name
 *   half, which is the more specific of the two.
 * - Detection only. Dissolving a repository type means moving its methods onto
 *   a model and rewriting every call site that reaches it, which no
 *   single-file, token-based fixer can do — so nothing is offered to the fixer
 *   and there is no autofixed.php fixture.
 */
class DisallowRepositoryClassesSniff implements Sniff
{
    /**
     * The OO declaration keywords this sniff reads, each mapped to the word its
     * message calls it by.
     *
     * The family is *every token type `File::getDeclarationName()` accepts* —
     * the canonical list PHP_CodeSniffer states in that method's own rejection
     * message ("is not T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT or T_ENUM"),
     * which is the closed set of declarations whose name is readable at all.
     * Every member is accounted for:
     *
     * - `T_CLASS` — included: the ordinary dedicated repository class.
     * - `T_INTERFACE` — included: `OrderRepositoryInterface` is the shape the
     *   standard names alongside the class.
     * - `T_TRAIT` — included: a trait is how repository behaviour is most
     *   often smuggled into several classes at once.
     * - `T_ENUM` — included: an enum declared as a repository type is the same
     *   declaration under a different keyword, and excluding it would leave one
     *   spelling of the violation unreported.
     * - `T_FUNCTION` — excluded: a function is not an OO declaration, and the
     *   standard is about the types a project declares, not what it calls its
     *   functions. It is the one member of the family this sniff drops.
     *
     * `T_ANON_CLASS` is deliberately absent rather than excluded: it is not a
     * member of this family at all, because `getDeclarationName()` rejects it —
     * see the class docblock for why an anonymous class is not reported.
     *
     * @var array<int, string>
     */
    private const DECLARATION_KEYWORDS = [
        T_CLASS => 'Class',
        T_ENUM => 'Enum',
        T_INTERFACE => 'Interface',
        T_TRAIT => 'Trait',
    ];

    /**
     * The declared-name suffixes that put a declaration in scope, lowercased
     * for a case-insensitive comparison.
     *
     * `repositoryinterface` is not redundant beside `repository`:
     * `OrderRepositoryInterface` ends in neither `Repository` nor any suffix of
     * it, so the shorter entry alone leaves the interface shape unreported.
     *
     * @var array<int, string>
     */
    private const NAME_SUFFIXES = [
        'repository',
        'repositoryinterface',
    ];

    /**
     * The namespace segment that puts a declaration in scope, lowercased for a
     * case-insensitive comparison against each segment of the declared
     * namespace.
     */
    private const NAMESPACE_SEGMENT = 'repositories';

    /**
     * The one message every report carries, differing only in the clause that
     * names which half of the convention matched. Held as a constant so the two
     * reports cannot drift apart in what they ask the developer to do.
     */
    private const REMEDY = 'the model is the repository, so move the persistence behaviour onto the'
        . ' model instead of declaring a dedicated repository type (see'
        . ' docs/standards/pattern-repository.md and'
        . ' docs/standards/models-persistence-methods-repository-pattern.md)';

    /**
     * Registration is derived from DECLARATION_KEYWORDS rather than restated,
     * so a keyword added to the map cannot be left unregistered, and a
     * registered keyword cannot arrive at process() with no word to call it by.
     *
     * @return array<int|string>
     */
    public function register(): array
    {
        return array_keys(self::DECLARATION_KEYWORDS);
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A keyword with no name after it is what PHP_CodeSniffer hands a sniff
        // for a file caught mid-edit. Neither half of the convention can be
        // read from it — the name is missing, and reporting the namespace half
        // would leave the message with nothing to point at — so it passes over
        // rather than flagging a declaration the developer is still typing.
        if ($name === null) {
            return;
        }

        $keyword = self::DECLARATION_KEYWORDS[$phpcsFile->getTokens()[$stackPtr]['code']];

        if ($this->hasRepositoryName($name) === true) {
            $phpcsFile->addWarning(
                '%s %s names itself a repository: ' . self::REMEDY,
                $stackPtr,
                'Found',
                [$keyword, $name]
            );

            return;
        }

        $namespace = $this->repositoryNamespace($phpcsFile, $stackPtr);

        if ($namespace === null) {
            return;
        }

        $phpcsFile->addWarning(
            '%s %s is declared in the %s namespace: ' . self::REMEDY,
            $stackPtr,
            'Found',
            [$keyword, $name, $namespace]
        );
    }

    /**
     * Whether $name ends in one of the repository suffixes.
     *
     * Lowercased once, on the whole name, rather than per suffix: PHP type
     * names are case-insensitive, so `Userrepository` and `UserREPOSITORY`
     * declare the same type as `UserRepository` and are the same violation.
     */
    private function hasRepositoryName(string $name): bool
    {
        $lowercased = strtolower($name);

        foreach (self::NAME_SUFFIXES as $suffix) {
            if (str_ends_with($lowercased, $suffix) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The namespace enclosing the declaration at $stackPtr when it carries a
     * `Repositories` segment, or null when it does not — including the global
     * namespace, where there is no segment to read.
     *
     * Slevomat's NamespaceHelper resolves the enclosing *declaration* rather
     * than the nearest `namespace` token, which is what keeps the two spellings
     * and one look-alike apart: the one-per-file form, braced blocks, and the
     * `namespace\thing()` relative-name operator, which is the same T_NAMESPACE
     * token and can sit in a method body above a later declaration.
     * slevomat/coding-standard is a hard `require` of this package, not a
     * dev-only tool, so the helper ships wherever this sniff does, and the
     * fixtures pin the behaviour relied on here so that a vendor upgrade
     * changing it fails the suite rather than silently narrowing the rule. The
     * sibling CleanCode.ClearCode.ActionSingleEntryPoint sniff reads its own
     * namespace half through the same helper.
     *
     * `namespace { … }` opens the global namespace and names no segments; the
     * helper reads its `{` as the name, which is not a segment this sniff looks
     * for, so the block is left alone.
     */
    private function repositoryNamespace(File $phpcsFile, int $stackPtr): ?string
    {
        $namespace = NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);

        if ($namespace === null) {
            return null;
        }

        $segments = array_map('strtolower', explode('\\', $namespace));

        return in_array(self::NAMESPACE_SEGMENT, $segments, true) === true ? $namespace : null;
    }
}

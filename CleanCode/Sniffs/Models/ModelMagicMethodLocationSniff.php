<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a Laravel attribute or query-scope method declared directly in a class
 * body instead of in the model's Attributes or Queries trait.
 *
 * Partial enforcement of the "Models: Structure (Attributes/Queries traits)"
 * standard (docs/standards/models-structure-attributes-queries-traits.md). The
 * standard itself is Tier 3: whether a model's logic lives in properly paired
 * traits, whether shared concerns sit on a BaseModel, and whether the model is
 * lean are cross-file judgements a token-based sniff cannot make. One slice is
 * token-visible, and it is the standard's anti-pattern exactly — Laravel's
 * attribute and scope methods follow strict naming and typing conventions, so
 * a declaration matching one of them, written in a class body rather than a
 * trait, is the extraction candidate the standard is about.
 *
 * The four shapes read here:
 *
 * - Legacy accessors and mutators — a `get<Name>Attribute` / `set<Name>Attribute`
 *   method name.
 * - Modern attributes — a return type resolving to
 *   Illuminate\Database\Eloquent\Casts\Attribute through the file's imports.
 * - Local query scopes — a `scope<Name>` method name.
 * - Laravel >= 12 query scopes — a method carrying the `#[Scope]` attribute,
 *   whatever it is named.
 *
 * Scope decisions:
 *
 * - The sniff registers on T_CLASS, so the enclosing-scope rule is structural
 *   rather than a test to remember. PHP_CodeSniffer gives every other
 *   class-like construct its own token code, so none of them is ever visited:
 *   T_TRAIT (the compliant destination — a trait holding these methods is what
 *   the standard asks for), T_INTERFACE (a method with no body has nothing to
 *   extract), T_ENUM (an enum is not an Eloquent model), and T_ANON_CLASS (an
 *   anonymous class cannot be named by a paired `App\Concerns\Attributes\<Model>`
 *   trait, so the advice has no target). tests/fixtures/
 *   ModelMagicMethodLocationSniff/passing.php carries one of each, so any
 *   later widening of register() reddens it rather than passing silently.
 * - Only methods the class itself declares are read. A method's innermost
 *   'conditions' entry has to be this class, which drops a named function or a
 *   nested anonymous class written inside a method body. Closures and arrow
 *   functions never arrive at all: they are T_CLOSURE and T_FN, and only
 *   T_FUNCTION is walked.
 * - Names are matched in their conventional Studly-cased spelling —
 *   `get`/`set` or `scope` followed by an upper-case letter. Laravel builds
 *   these names with Str::studly()/ucfirst() before probing for them, so that
 *   is the shape convention-following code has. PHP method names are
 *   case-insensitive, so an off-convention spelling (`getfooattribute`) does
 *   resolve at runtime and is a disclosed false negative: matching it would
 *   also flag ordinary methods like `scopes()` or `getAttributes()`, which is
 *   the worse trade for a heuristic that cannot confirm the class is a model.
 *   The upper-case requirement is also what keeps Eloquent's own `getAttribute()`
 *   and `setAttribute()` overrides out, neither of which is an accessor.
 * - A return type counts only when the file's own imports resolve it to
 *   Illuminate\Database\Eloquent\Casts\Attribute, or it is written fully
 *   qualified. A bare `Attribute` with no matching import is PHP's own
 *   #[Attribute] class or a same-namespace class of that name, neither of
 *   which is an Eloquent cast. Union, intersection, nullable and DNF types are
 *   split apart, because any member being the cast makes the method an
 *   attribute method.
 * - The `#[Scope]` attribute is matched on its short name. Unlike the return
 *   type, the attribute name carries no other plausible meaning on a model
 *   method, and Laravel's own docs write it bare, so requiring the import to
 *   resolve would trade a real detection for no gain in precision.
 * - One warning per method. The four shapes describe two destinations, and a
 *   method matching more than one of them still moves to a single trait, so
 *   the first destination found is the one reported.
 * - Warning severity, not error. A single file's tokens cannot confirm the
 *   class is an Eloquent model, so a non-model class using one of these naming
 *   shapes is reported too. The sniff points at extraction candidates; code
 *   review settles them.
 * - Detection only. Moving a method to another file is not a mechanical
 *   rewrite PHP_CodeSniffer's fixer can make, so there is no autofixed
 *   fixture.
 */
class ModelMagicMethodLocationSniff implements Sniff
{
    /**
     * The fully-qualified name of Laravel's modern attribute cast, lower-cased
     * for comparison because PHP class names are case-insensitive.
     */
    private const ATTRIBUTE_CAST = 'illuminate\database\eloquent\casts\attribute';

    /**
     * The short name of Laravel's query-scope attribute
     * (Illuminate\Database\Eloquent\Attributes\Scope), lower-cased for the same
     * reason.
     */
    private const SCOPE_ATTRIBUTE = 'scope';

    /**
     * The tokens a qualified name is built from. PHP 8 emits one token for a
     * whole qualified name, but PHP_CodeSniffer still tokenises files written
     * for earlier versions into this pair, so a name is read as a run of them.
     *
     * @var array<int|string, true>
     */
    private const NAME_TOKENS = [
        T_STRING => true,
        T_NS_SEPARATOR => true,
    ];

    /**
     * The file the import map below was built from — its name, its token count
     * and the fixer loop that produced it. A fixer loop belonging to another
     * sniff rewrites the token stream underneath this one, so the loop counter
     * is part of the identity rather than an optimisation.
     */
    private ?string $importsKey = null;

    /**
     * Every class import in the current file, as alias => fully-qualified name.
     * Rebuilt only when the key above changes, because resolving a return type
     * would otherwise rescan the whole file once per method — quadratic on a
     * large class, which is exactly the shape a model with many accessors has.
     *
     * @var array<string, string>
     */
    private array $imports = [];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // A class the tokenizer never closed has no body to walk.
        // PHP_CodeSniffer records the pair together or not at all, so either
        // key answers the question; both are named because both are read
        // below, and the walk would otherwise run to the end of the file on a
        // null bound.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $end = $tokens[$stackPtr]['scope_closer'];
        $ptr = $tokens[$stackPtr]['scope_opener'];

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $end)) !== false) {
            // PHP_CodeSniffer records 'conditions' on every token, so the
            // innermost enclosing scope is always readable. Anything but this
            // class means a function or an anonymous class declared inside a
            // method body, which is not a member of this class.
            if (array_key_last($tokens[$ptr]['conditions']) !== $stackPtr) {
                continue;
            }

            // A `function` keyword with no parameter list is unfinished
            // source. The guard is not merely defensive: getDeclarationName()
            // bounds its search at the parenthesis opener, so without one it
            // searches to the end of the file and returns the *next*
            // declaration's name, reporting that name against this line.
            if (isset($tokens[$ptr]['parenthesis_opener']) === false) {
                continue;
            }

            $name = $phpcsFile->getDeclarationName($ptr);

            // Defensive only, and unreachable as the code stands: with a
            // parameter list to stop at, getDeclarationName() always finds the
            // name token in front of it. A reserved word used as a method name
            // is no exception — PHP_CodeSniffer relabels `function list()`'s
            // `list` T_STRING, which is what that search reads. Kept because
            // the helper is declared ?string and preg_match() takes a string
            // under strict_types, so a future widening of either would
            // otherwise become a TypeError rather than a skipped declaration.
            if ($name === null) {
                continue;
            }

            $destination = $this->destinationTrait($phpcsFile, $ptr, $name);

            if ($destination === null) {
                continue;
            }

            $this->report($phpcsFile, $ptr, $name, $destination);
        }
    }

    /**
     * The trait the method belongs in — 'Attributes', 'Queries', or null when
     * the method is none of the four shapes.
     *
     * Attribute shapes are tested before scope shapes so that a method
     * matching both resolves to one destination deterministically; no real
     * declaration is both an accessor and a scope.
     */
    private function destinationTrait(File $phpcsFile, int $functionPtr, string $name): ?string
    {
        if (preg_match('/^(?:get|set)[A-Z].*Attribute$/', $name) === 1) {
            return 'Attributes';
        }

        if ($this->returnsAttributeCast($phpcsFile, $functionPtr) === true) {
            return 'Attributes';
        }

        if (preg_match('/^scope[A-Z]/', $name) === 1) {
            return 'Queries';
        }

        return $this->hasScopeAttribute($phpcsFile, $functionPtr) === true ? 'Queries' : null;
    }

    /**
     * Reports the method, naming the trait it belongs in and an example of the
     * namespace convention the standard uses.
     */
    private function report(File $phpcsFile, int $functionPtr, string $name, string $destination): void
    {
        $phpcsFile->addWarning(
            'Model method %s() is declared in the class body; extract it to the model\'s '
                . '%s trait (e.g. App\Concerns\%s\Book) so the model stays lean '
                . '(see docs/standards/models-structure-attributes-queries-traits.md)',
            $functionPtr,
            $destination === 'Attributes' ? 'AttributeMethod' : 'ScopeMethod',
            [$name, $destination, $destination]
        );
    }

    /**
     * Whether any member of the method's return type resolves to Laravel's
     * attribute cast.
     *
     * getMethodProperties() hands the type back as written, so a nullable,
     * union, intersection or DNF type arrives as one string — `?Attribute`,
     * `Attribute|null`, `(A&B)|Attribute`. Splitting on both operators and
     * stripping the grouping and nullable punctuation leaves the individual
     * names, and any one of them being the cast makes this an attribute
     * method. A method with no return type yields an empty string, which
     * splits to nothing matchable.
     *
     * Calling getMethodProperties() is safe here because it throws only on a
     * token that is not a function-like declaration, and the caller walks
     * T_FUNCTION exclusively.
     */
    private function returnsAttributeCast(File $phpcsFile, int $functionPtr): bool
    {
        $returnType = $phpcsFile->getMethodProperties($functionPtr)['return_type'];

        foreach (explode('|', str_replace('&', '|', $returnType)) as $member) {
            $name = trim($member, "? \t\n\r\0\x0B()");

            if ($name !== '' && $this->resolve($phpcsFile, $name) === self::ATTRIBUTE_CAST) {
                return true;
            }
        }

        return false;
    }

    /**
     * A type name resolved to its lower-cased fully-qualified form, or the
     * lower-cased name itself when nothing in the file resolves it.
     *
     * Three spellings reach the cast: fully qualified (a leading separator,
     * already absolute), imported under its own name (`use …\Attribute;`), and
     * imported under an alias (`use …\Attribute as CastAttribute;`). Only the
     * first segment of a qualified name is an alias — `Casts\Attribute` binds
     * `Casts` — so the lookup uses that segment and keeps the remainder.
     *
     * An unresolved name is returned as written rather than qualified against
     * the file's namespace. A bare `Attribute` in a namespaced file means that
     * namespace's own class, and in a file with no namespace it is PHP's own
     * #[Attribute]; neither is Laravel's cast, and neither should match.
     */
    private function resolve(File $phpcsFile, string $name): string
    {
        if (str_starts_with($name, '\\') === true) {
            return strtolower(ltrim($name, '\\'));
        }

        $segments = explode('\\', $name);
        $alias = strtolower(array_shift($segments));
        $imports = $this->imports($phpcsFile);

        if (isset($imports[$alias]) === false) {
            return strtolower($name);
        }

        return strtolower(implode('\\', array_merge([$imports[$alias]], $segments)));
    }

    /**
     * Whether the method carries a `#[Scope]` attribute.
     *
     * Attribute groups sit immediately before the declaration, ahead of any
     * visibility, static, abstract or final keyword, and several groups may be
     * stacked, so the walk steps back over each group it finds and looks for
     * another behind it.
     */
    private function hasScopeAttribute(File $phpcsFile, int $functionPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $skippable = array_merge(Tokens::$emptyTokens, Tokens::$methodPrefixes);
        $ptr = $functionPtr;

        while (true) {
            $previous = $phpcsFile->findPrevious($skippable, ($ptr - 1), null, true);

            if ($previous === false || $tokens[$previous]['code'] !== T_ATTRIBUTE_END) {
                return false;
            }

            // An attribute group the tokenizer never opened is unfinished
            // source; there is nothing behind it to keep walking towards.
            if (isset($tokens[$previous]['attribute_opener']) === false) {
                return false;
            }

            $opener = $tokens[$previous]['attribute_opener'];

            if (in_array(self::SCOPE_ATTRIBUTE, $this->attributeNames($phpcsFile, $opener, $previous), true) === true) {
                return true;
            }

            $ptr = $opener;
        }
    }

    /**
     * The short names of every attribute in one `#[…]` group, lower-cased.
     *
     * A group may hold several attributes (`#[Foo, Scope]`) and each may carry
     * arguments (`#[Foo(Scope::class)]`). Only names at the group's own
     * parenthesis depth are attribute names; anything deeper is an argument,
     * which is what keeps a class-constant reference to some other `Scope`
     * from reading as the attribute itself.
     *
     * @return array<string>
     */
    private function attributeNames(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $depth = count($tokens[$opener]['nested_parenthesis'] ?? []);
        $names = [];
        $expectName = true;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $closer, true)) !== false) {
            if (count($tokens[$ptr]['nested_parenthesis'] ?? []) !== $depth) {
                continue;
            }

            if ($tokens[$ptr]['code'] === T_COMMA) {
                $expectName = true;

                continue;
            }

            if ($expectName === false || isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === false) {
                continue;
            }

            [$name, $ptr] = $this->readName($phpcsFile, $ptr);
            $segments = explode('\\', $name);
            $names[] = strtolower((string) end($segments));
            $expectName = false;
        }

        return $names;
    }

    /**
     * Every class import in the file, as lower-cased alias => fully-qualified
     * name, memoised for the current token stream.
     *
     * @return array<string, string>
     */
    private function imports(File $phpcsFile): array
    {
        $key = $phpcsFile->getFilename()
            . '|' . count($phpcsFile->getTokens())
            . '|' . ($phpcsFile->fixer->loops ?? 0);

        if ($this->importsKey === $key) {
            return $this->imports;
        }

        $this->importsKey = $key;
        $this->imports = $this->readImports($phpcsFile);

        return $this->imports;
    }

    /**
     * Reads the file's import statements.
     *
     * Only a `use` at file level imports a class: one in a class body pulls in
     * a trait, and one after a closure's parameter list captures variables.
     * `use function` and `use const` import no class, so both are skipped.
     *
     * Of the three tests, the 'conditions' one is defensive: dropping it
     * leaves every fixture unchanged, because a closure capture puts a `(`
     * where a name would be and falls out at the next test, while a trait
     * `use` only ever adds the trait's own short name to the map — a name a
     * return type would have to be spelled with before it could matter, and
     * one that still does not resolve to the cast. It is kept because it says
     * what an import is instead of relying on the two later tests to reject
     * everything that is not one, as the sibling DisallowAlwaysOnEagerLoading
     * sniff keeps its own mutation-green guards.
     *
     * @return array<string, string>
     */
    private function readImports(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];
        $ptr = -1;

        while (($ptr = $phpcsFile->findNext(T_USE, ($ptr + 1))) !== false) {
            if ($tokens[$ptr]['conditions'] !== []) {
                continue;
            }

            $first = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            if ($first === false || isset(self::NAME_TOKENS[$tokens[$first]['code']]) === false) {
                continue;
            }

            if (in_array(strtolower($tokens[$first]['content']), ['function', 'const'], true) === true) {
                continue;
            }

            $imports += $this->readImportStatement($phpcsFile, $first);
        }

        return $imports;
    }

    /**
     * Reads one `use` statement from its first name token. Covers the plain
     * form, the aliased form, the comma-separated form `use A\B, C\D;`, and
     * the group form `use A\{B, C as D};`.
     *
     * @return array<string, string>
     */
    private function readImportStatement(File $phpcsFile, int $ptr): array
    {
        $tokens = $phpcsFile->getTokens();
        [$name, $end] = $this->readName($phpcsFile, $ptr);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

        if ($next !== false && $tokens[$next]['code'] === T_OPEN_USE_GROUP) {
            return $this->readImportGroup($phpcsFile, $next, $name);
        }

        $imports = [];

        // `use A\B, C\D;` — a comma continues the same statement with another
        // name, each carrying its own optional alias. The alias reader hands
        // back the last pointer its import occupies, which is what the comma
        // lookup has to start from: an `as` clause sits between the name and
        // the comma, and searching from the name would stop on it instead.
        while (true) {
            [$one, $end] = $this->readImportAlias($phpcsFile, $end, $name, '');
            $imports += $one;
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if ($next === false || $tokens[$next]['code'] !== T_COMMA) {
                return $imports;
            }

            $start = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

            if ($start === false || isset(self::NAME_TOKENS[$tokens[$start]['code']]) === false) {
                return $imports;
            }

            [$name, $end] = $this->readName($phpcsFile, $start);
        }
    }

    /**
     * Reads the members of a group import, each against the group's prefix.
     *
     * @return array<string, string>
     */
    private function readImportGroup(File $phpcsFile, int $opener, string $prefix): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true)) !== false) {
            if ($tokens[$ptr]['code'] === T_CLOSE_USE_GROUP || $tokens[$ptr]['code'] === T_SEMICOLON) {
                return $imports;
            }

            if (isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === false) {
                continue;
            }

            [$name, $end] = $this->readName($phpcsFile, $ptr);
            [$one, $ptr] = $this->readImportAlias($phpcsFile, $end, $name, $prefix);
            $imports += $one;
        }

        return $imports;
    }

    /**
     * One import — the name just read, plus the `as` alias behind it when
     * there is one — and the last pointer the import occupies. Without an
     * alias PHP binds the name's last segment.
     *
     * @return array{0: array<string, string>, 1: int}
     */
    private function readImportAlias(File $phpcsFile, int $ptr, string $name, string $prefix): array
    {
        $tokens = $phpcsFile->getTokens();
        $fullyQualified = ltrim($prefix . $name, '\\');
        $segments = explode('\\', $fullyQualified);
        $alias = strtolower((string) end($segments));
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

        if ($next !== false && $tokens[$next]['code'] === T_AS) {
            $aliasPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

            if ($aliasPtr !== false && $tokens[$aliasPtr]['code'] === T_STRING) {
                return [[strtolower($tokens[$aliasPtr]['content']) => $fullyQualified], $aliasPtr];
            }
        }

        return [($alias === '' ? [] : [$alias => $fullyQualified]), $ptr];
    }

    /**
     * The whole qualified name starting at $ptr, and the last pointer it
     * occupies. PHP 8 forbids whitespace inside a qualified name, but
     * PHP_CodeSniffer still tokenises files written for older versions, so the
     * run is walked across empty tokens rather than by raw adjacency.
     *
     * @return array{0: string, 1: int}
     */
    private function readName(File $phpcsFile, int $ptr): array
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';
        $end = $ptr;

        while ($ptr !== false && isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === true) {
            $name .= $tokens[$ptr]['content'];
            $end = $ptr;
            $ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);
        }

        return [$name, $end];
    }
}

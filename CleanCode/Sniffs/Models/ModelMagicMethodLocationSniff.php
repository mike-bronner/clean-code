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
 * - The sniff registers on T_CLASS, so a trait — the compliant destination —
 *   is never the subject of a report. Every other class-like construct has its
 *   own token code and so is never registered either: T_TRAIT, T_INTERFACE (a
 *   method with no body has nothing to extract), T_ENUM (an enum is not an
 *   Eloquent model), and T_ANON_CLASS (an anonymous class cannot be named by a
 *   paired `App\Concerns\Attributes\<Model>` trait, so the advice has no
 *   target). tests/fixtures/ModelMagicMethodLocationSniff/passing.php carries
 *   one of each, so any later widening of register() reddens it rather than
 *   passing silently.
 * - Only methods the class itself declares are read. isOwnMethod() requires the
 *   nearest enclosing class to be this one and no closer scope of any kind in
 *   between, which drops a named function or a nested anonymous class written
 *   inside a method body. Closures and arrow functions never arrive at all:
 *   they are T_CLOSURE and T_FN, and only T_FUNCTION is walked.
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
 *
 * The import map is rebuilt for each class the file declares rather than
 * memoised on the sniff. A sniff object outlives the file it is given, so a
 * cached map has to be invalidated against the fixer's rewrite loop as well as
 * against the file, and getting that wrong caches a stale answer silently. One
 * scan per class costs a single pass over a file that is almost always one
 * class long, which is not worth that risk.
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
     * The destination traits, spelled as the standard spells them.
     */
    private const ATTRIBUTES = 'Attributes';

    private const QUERIES = 'Queries';

    /**
     * The name pattern of a legacy accessor or mutator, and of a local query
     * scope. Both require the upper-case letter the Studly-cased convention
     * puts there; see the class docblock for why.
     */
    private const ACCESSOR_PATTERN = '/^(?:get|set)[A-Z].*Attribute$/';

    private const SCOPE_PATTERN = '/^scope[A-Z]/';

    /**
     * The keywords that turn a `use` into a function or constant import, which
     * imports no class. Lower-cased because PHP keywords are case-insensitive.
     *
     * @var array<int, string>
     */
    private const IMPORT_KEYWORDS = ['function', 'const'];

    /**
     * The tokens a qualified name is built from. PHP 8 emits one token for a
     * whole qualified name, but PHP_CodeSniffer still tokenises files written
     * for earlier versions into this pair, so a name is read as a run of them.
     *
     * @var array<int, int|string>
     */
    private const NAME_TOKENS = [T_STRING, T_NS_SEPARATOR];

    /**
     * The tokens an attribute name may follow inside a `#[…]` group: the
     * group's own opener for the first attribute, a comma for each one after
     * it. Any other predecessor — an opening parenthesis, a namespace
     * separator — means the name is part of an argument or of a longer
     * qualified name rather than the start of an attribute.
     *
     * @var array<int, int|string>
     */
    private const NAME_STARTERS = [T_ATTRIBUTE, T_COMMA];

    /**
     * The tokens that end one member of a `use` statement. A member holds no
     * comma of its own, so the first of these after a member's first token
     * closes it: a comma hands over to another member, the group brace and the
     * semicolon end the statement.
     *
     * @var array<int, int|string>
     */
    private const MEMBER_ENDS = [T_COMMA, T_CLOSE_USE_GROUP, T_SEMICOLON];

    /**
     * The scopes that can sit between a class and a function declared inside
     * it. Any of them nearer than the class means the function is not a method
     * of that class: an anonymous class or a nested named function declares its
     * own members, and a closure or arrow function is not a declaration the
     * standard speaks about. Every other scope a class body can hold — an `if`,
     * a `foreach` — is reachable only through one of these, so the four cover
     * the question between them.
     *
     * @var array<int, int|string>
     */
    private const NESTED_SCOPES = [T_ANON_CLASS, T_CLOSURE, T_FN, T_FUNCTION];

    /**
     * Stands in for "no pointer" while folding pointers with max(), which needs
     * a number rather than null. Below every real pointer, so it loses every
     * comparison.
     */
    private const NO_POINTER = -1;

    /**
     * The report format string. Written as a NOWDOC because
     * CleanCode.Strings.MultilineStrings rejects a string concatenated across
     * lines, and folded back to one line by message() before it is reported —
     * every other sniff here emits a single-line message, and the CSV and
     * checkstyle reports put one violation on one line.
     */
    private const MESSAGE = <<<'MESSAGE'
        Model method %s() is declared in the class body; extract it to the model's %s trait
        (e.g. App\Concerns\%s\Book) so the model stays lean
        (see docs/standards/models-structure-attributes-queries-traits.md)
        MESSAGE;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * The native int hint SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * asks for on $stackPtr cannot be written: PHP_CodeSniffer's Sniff
     * interface declares the parameter untyped, and narrowing an inherited
     * untyped parameter is a fatal error, so the hint would stop the sniff
     * loading at all. The return hint has no such constraint and is written.
     *
     * @param int $stackPtr
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- see above
    public function process(File $phpcsFile, $stackPtr): void
    {
        $imports = $this->readImports($phpcsFile);

        // Walked to the end of the file rather than to the class's closing
        // brace: PHP_CodeSniffer publishes a scope's bounds on the token array
        // only, and isOwnMethod() already answers the question the bound would
        // — a declaration belonging to anything other than this class is
        // rejected whether it sits inside the body or after it.
        $bodyPtr = ($stackPtr + 1);

        foreach ($this->pointersOfType($phpcsFile, T_FUNCTION, $bodyPtr, null) as $functionPtr) {
            $this->inspect($phpcsFile, $functionPtr, $stackPtr, $imports);
        }
    }

    /**
     * Reports one declaration when it is a method of this class and matches one
     * of the four shapes.
     *
     * @param array<string, string> $imports
     */
    private function inspect(File $phpcsFile, int $functionPtr, int $classPtr, array $imports): void
    {
        $name = $this->declarationName($phpcsFile, $functionPtr);
        $destination = $this->destinationTrait($phpcsFile, $functionPtr, $name, $imports);

        // One code per destination trait, so a ruleset can silence either half
        // on its own.
        $code = match ($destination) {
            self::ATTRIBUTES => 'AttributeMethod',
            default => 'ScopeMethod',
        };

        match (true) {
            $this->isOwnMethod($phpcsFile, $functionPtr, $classPtr) === false => null,
            $destination === null => null,
            default => $phpcsFile->addWarning(
                $this->message(),
                $functionPtr,
                $code,
                [$name, $destination, $destination]
            ),
        };
    }

    /**
     * Whether the declaration is a method the class itself declares.
     *
     * Conditions nest, so the ancestor that opens last is the innermost one.
     * The class has to be the nearest enclosing class *and* nearer than every
     * scope that could hold a declaration of its own, which is what separates a
     * method from a function or an anonymous class written inside a method
     * body.
     */
    private function isOwnMethod(File $phpcsFile, int $functionPtr, int $classPtr): bool
    {
        $ownerPtr = $this->conditionPointer($phpcsFile, $functionPtr, T_CLASS);
        $nestedPtr = $this->innermostConditionPointer(
            $phpcsFile,
            $functionPtr,
            self::NESTED_SCOPES
        );

        return match ($ownerPtr) {
            $classPtr => $classPtr > ($nestedPtr ?? self::NO_POINTER),
            default => false,
        };
    }

    /**
     * The trait the method belongs in — 'Attributes', 'Queries', or null when
     * the method is none of the four shapes.
     *
     * Attribute shapes are tested before scope shapes so that a method
     * matching both resolves to one destination deterministically; no real
     * declaration is both an accessor and a scope.
     *
     * @param array<string, string> $imports
     */
    private function destinationTrait(
        File $phpcsFile,
        int $functionPtr,
        ?string $name,
        array $imports
    ): ?string {
        return match (true) {
            $name === null => null,
            preg_match(self::ACCESSOR_PATTERN, $name) === 1 => self::ATTRIBUTES,
            $this->hasAttributeCastReturn($phpcsFile, $functionPtr, $imports) === true
                => self::ATTRIBUTES,
            preg_match(self::SCOPE_PATTERN, $name) === 1 => self::QUERIES,
            $this->hasScopeAttribute($phpcsFile, $functionPtr) === true => self::QUERIES,
            default => null,
        };
    }

    /**
     * The declared method name, null when the tokens do not spell a complete
     * declaration.
     *
     * Read from the tokens rather than through getDeclarationName(), because
     * that helper bounds its search at the parameter list's opening
     * parenthesis and, on an unfinished declaration that has none, searches on
     * and returns the *next* declaration's name. Requiring the parenthesis here
     * is what makes a truncated declaration a skip rather than a report against
     * a name from somewhere else. A reserved word used as a method name is no
     * exception: PHP_CodeSniffer relabels `function list()`'s `list` T_STRING,
     * which is the token this reads.
     */
    private function declarationName(File $phpcsFile, int $functionPtr): ?string
    {
        $afterPtr = $this->nextSignificant($phpcsFile, $functionPtr);

        // Steps the `&` of a by-reference declaration, which sits between the
        // `function` keyword and the name.
        $namePtr = match ($this->isToken($phpcsFile, $afterPtr, T_BITWISE_AND)) {
            true => $this->nextSignificant($phpcsFile, $afterPtr),
            default => $afterPtr,
        };
        $openPtr = $this->nextSignificant($phpcsFile, $namePtr);

        return match (true) {
            $this->isToken($phpcsFile, $namePtr, T_STRING) === false => null,
            $this->isToken($phpcsFile, $openPtr, T_OPEN_PARENTHESIS) === false => null,
            default => $this->contentOf($phpcsFile, (int) $namePtr),
        };
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
     *
     * @param array<string, string> $imports
     */
    private function hasAttributeCastReturn(File $phpcsFile, int $functionPtr, array $imports): bool
    {
        $returnType = $phpcsFile->getMethodProperties($functionPtr)['return_type'];
        $found = false;

        foreach (explode('|', str_replace('&', '|', $returnType)) as $member) {
            $name = trim($member, "? \t\n\r\0\x0B()");
            $found = match (true) {
                $found === true => true,
                $name === '' => false,
                default => $this->resolve($name, $imports) === self::ATTRIBUTE_CAST,
            };
        }

        return $found;
    }

    /**
     * A type name resolved to its lower-cased fully-qualified form, or the
     * lower-cased name itself when nothing in the file resolves it.
     *
     * Three spellings reach the cast: fully qualified (a leading separator,
     * already absolute), imported under its own name (`use …\Attribute;`), and
     * imported under an alias (`use …\Attribute as CastAttribute;`).
     *
     * Only the first segment of a qualified name is an alias —
     * `Casts\Attribute` binds `Casts` — so the lookup matches that segment and
     * keeps the remainder. An unresolved name is returned as written rather
     * than qualified against the file's namespace: a bare `Attribute` in a
     * namespaced file means that namespace's own class, and in a file with no
     * namespace it is PHP's own #[Attribute]; neither is Laravel's cast, and
     * neither should match.
     *
     * @param array<string, string> $imports
     */
    private function resolve(string $name, array $imports): string
    {
        $segments = explode('\\', $name);
        $head = strtolower((string) array_shift($segments));
        $resolved = strtolower($name);

        // No import binds the empty name readNamedImport() refuses to record,
        // so a fully-qualified spelling — whose first segment is empty — passes
        // through this fold untouched and is answered by the return below.
        foreach ($imports as $alias => $fullyQualified) {
            $resolved = match ($alias) {
                $head => strtolower(implode('\\', array_merge([$fullyQualified], $segments))),
                default => $resolved,
            };
        }

        return match (str_starts_with($name, '\\')) {
            true => strtolower(ltrim($name, '\\')),
            default => $resolved,
        };
    }

    /**
     * Whether the method carries a `#[Scope]` attribute.
     *
     * Attribute groups sit immediately before the declaration, ahead of any
     * visibility, static, abstract or final keyword, and several groups may be
     * stacked, so the walk steps back over each group it finds and looks for
     * another behind it. A group the tokenizer never opened ends the walk:
     * there is nothing behind it to keep walking towards.
     */
    private function hasScopeAttribute(File $phpcsFile, int $functionPtr): bool
    {
        $closerPtr = $this->attributeCloserBefore($phpcsFile, $functionPtr);
        $found = false;

        while ($closerPtr !== null) {
            $openerPtr = $this->orNull($phpcsFile->findPrevious(T_ATTRIBUTE, ($closerPtr - 1)));
            $found = match (true) {
                $found === true => true,
                $openerPtr === null => false,
                default => in_array(
                    self::SCOPE_ATTRIBUTE,
                    $this->attributeNames($phpcsFile, $openerPtr, $closerPtr),
                    true
                ),
            };
            $closerPtr = $this->attributeCloserBefore($phpcsFile, $openerPtr);
        }

        return $found;
    }

    /**
     * The `]` of the attribute group immediately before the pointer, null when
     * what sits there is not one.
     */
    private function attributeCloserBefore(File $phpcsFile, ?int $stackPtr): ?int
    {
        // The previous token that is neither empty nor one of the keywords a
        // declaration may carry, which is where an attribute group's `]` sits.
        $skippable = array_merge(Tokens::$emptyTokens, Tokens::$methodPrefixes);
        $previousPtr = match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findPrevious($skippable, ($stackPtr - 1), null, true)
            ),
        };

        return match ($this->isToken($phpcsFile, $previousPtr, T_ATTRIBUTE_END)) {
            false => null,
            default => $previousPtr,
        };
    }

    /**
     * The short names of every attribute in one `#[…]` group, lower-cased.
     *
     * A group may hold several attributes (`#[Foo, Scope]`) and each may carry
     * arguments (`#[Foo(Scope::class)]`). An attribute name is a name run that
     * follows the group's opener or a comma and sits outside every argument
     * list, which is what keeps a class-constant reference to some other
     * `Scope` — whether it follows a parenthesis or a comma inside one — from
     * reading as the attribute itself.
     *
     * @return array<int, string>
     */
    private function attributeNames(File $phpcsFile, int $openerPtr, int $closerPtr): array
    {
        $names = [];
        $first = ($openerPtr + 1);

        foreach ($this->pointersOfType($phpcsFile, self::NAME_TOKENS, $first, $closerPtr) as $ptr) {
            $previousPtr = $this->orNull(
                $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true)
            );
            [$name] = $this->readName($phpcsFile, $ptr);

            // Depth is counted rather than read from the token array's
            // nested_parenthesis map, which File does not publish. An attribute
            // group holds no construct that can put an unbalanced parenthesis
            // in front of a name, so every argument list opened before the
            // pointer and not yet closed leaves the two counts apart.
            $opened = $this->pointersOfType($phpcsFile, T_OPEN_PARENTHESIS, $openerPtr, $ptr);
            $closed = $this->pointersOfType($phpcsFile, T_CLOSE_PARENTHESIS, $openerPtr, $ptr);

            $names = array_merge($names, match (true) {
                $this->isToken($phpcsFile, $previousPtr, self::NAME_STARTERS) === false => [],
                count($opened) !== count($closed) => [],
                default => [$this->shortName($name)],
            });
        }

        return $names;
    }

    /**
     * A qualified name's last segment, lower-cased.
     */
    private function shortName(string $name): string
    {
        $segments = explode('\\', $name);

        return strtolower((string) end($segments));
    }

    /**
     * Every class import in the file, as lower-cased alias => fully-qualified
     * name.
     *
     * @return array<string, string>
     */
    private function readImports(File $phpcsFile): array
    {
        $imports = [];

        foreach ($this->pointersOfType($phpcsFile, T_USE, 0, null) as $usePtr) {
            $imports += $this->readFileLevelImport($phpcsFile, $usePtr);
        }

        return $imports;
    }

    /**
     * One `use` statement's imports, empty when the statement imports no class.
     *
     * Only a `use` at file level imports a class: one inside any scope at all
     * pulls in a trait, and one after a closure's parameter list captures
     * variables. `use function` and `use const` import no class either, so the
     * whole statement is skipped — including its group form, `use function
     * A\{b, c};`, whose keyword sits in the same place.
     *
     * @return array<string, string>
     */
    private function readFileLevelImport(File $phpcsFile, int $usePtr): array
    {
        $firstPtr = $this->nextSignificant($phpcsFile, $usePtr);

        return match (true) {
            $phpcsFile->hasCondition($usePtr, Tokens::$scopeOpeners) === true => [],
            $this->isToken($phpcsFile, $firstPtr, self::NAME_TOKENS) === false => [],
            $this->isImportKeyword($phpcsFile, (int) $firstPtr) === true => [],
            default => $this->readImportBody($phpcsFile, (int) $firstPtr),
        };
    }

    /**
     * The imports of a `use` statement whose first name has already been found
     * to be a class name — either the group form `use A\{B, C as D};` or the
     * comma-separated form `use A\B, C\D;`.
     *
     * @return array<string, string>
     */
    private function readImportBody(File $phpcsFile, int $firstPtr): array
    {
        [$prefix, $endPtr] = $this->readName($phpcsFile, $firstPtr);
        $groupPtr = $this->nextSignificant($phpcsFile, $endPtr);
        $memberPtr = $this->nextSignificant($phpcsFile, $groupPtr);

        return match ($this->isToken($phpcsFile, $groupPtr, T_OPEN_USE_GROUP)) {
            true => $this->readMembers($phpcsFile, $memberPtr, $prefix),
            default => $this->readMembers($phpcsFile, $firstPtr, ''),
        };
    }

    /**
     * Reads every member of a `use` statement, each against the group's prefix
     * — empty for the comma-separated form, which has no prefix to apply.
     *
     * @return array<string, string>
     */
    private function readMembers(File $phpcsFile, ?int $startPtr, string $prefix): array
    {
        $imports = [];
        $ptr = $startPtr;

        while ($ptr !== null) {
            $imports += $this->readMember($phpcsFile, $ptr, $prefix);
            $ptr = $this->nextMemberPointer($phpcsFile, $ptr);
        }

        return $imports;
    }

    /**
     * One member's import, empty when the member imports a function or a
     * constant rather than a class.
     *
     * PHP lets a group mix the three kinds — `use A\{function b, const C, D};`
     * is valid — and the keyword binds to its own member only, so the test
     * belongs here rather than at the statement. Skipping the whole member is
     * what the keyword means: a function import binds a function name, which
     * no return type can be spelled with. Reading it as a class instead lets it
     * claim an alias a real class import behind it in the same group then loses
     * to, because the members are merged first-one-wins.
     *
     * In the comma-separated form the keyword cannot appear on a member at all
     * — PHP only accepts it directly after `use`, where readFileLevelImport()
     * has already rejected the whole statement — so this test is inert on that
     * path and live on the group path.
     *
     * @return array<string, string>
     */
    private function readMember(File $phpcsFile, int $startPtr, string $prefix): array
    {
        // Read before the keyword test rather than after it, so the whole
        // member is one expression. readName() walks a `function b` member's
        // two name tokens into one glued name, which is exactly why that
        // member must not be recorded; the keyword arm below discards it.
        [$name, $endPtr] = $this->readName($phpcsFile, $startPtr);
        $fullyQualified = ltrim($prefix . $name, '\\');
        $alias = $this->aliasOf($phpcsFile, $endPtr, $fullyQualified);

        return match (true) {
            $this->isImportKeyword($phpcsFile, $startPtr) === true => [],
            $alias === '' => [],
            default => [$alias => $fullyQualified],
        };
    }

    /**
     * The lower-cased name a member binds.
     */
    private function aliasOf(File $phpcsFile, int $endPtr, string $fullyQualified): string
    {
        $asPtr = $this->nextSignificant($phpcsFile, $endPtr);
        $aliasPtr = $this->nextSignificant($phpcsFile, $asPtr);
        $bound = $this->shortName($fullyQualified);

        return match (true) {
            $this->isToken($phpcsFile, $asPtr, T_AS) === false => $bound,
            $this->isToken($phpcsFile, $aliasPtr, T_STRING) === false => $bound,
            default => strtolower($this->contentOf($phpcsFile, (int) $aliasPtr)),
        };
    }

    /**
     * The first token of the member after the one starting at the pointer, null
     * when the statement ends instead.
     */
    private function nextMemberPointer(File $phpcsFile, int $startPtr): ?int
    {
        $endPtr = $this->orNull($phpcsFile->findNext(self::MEMBER_ENDS, ($startPtr + 1)));

        return match ($this->isToken($phpcsFile, $endPtr, T_COMMA)) {
            false => null,
            default => $this->nextSignificant($phpcsFile, $endPtr),
        };
    }

    private function isImportKeyword(File $phpcsFile, int $stackPtr): bool
    {
        return in_array(
            strtolower($this->contentOf($phpcsFile, $stackPtr)),
            self::IMPORT_KEYWORDS,
            true
        );
    }

    /**
     * The whole qualified name starting at the pointer, and the last pointer it
     * occupies. PHP 8 forbids whitespace inside a qualified name, but
     * PHP_CodeSniffer still tokenises files written for older versions, so the
     * run is walked across empty tokens rather than by raw adjacency.
     *
     * @return array{0: string, 1: int}
     */
    private function readName(File $phpcsFile, int $startPtr): array
    {
        $name = '';
        $endPtr = $startPtr;
        $ptr = $startPtr;

        while ($this->isToken($phpcsFile, $ptr, self::NAME_TOKENS) === true) {
            $name .= $this->contentOf($phpcsFile, (int) $ptr);
            $endPtr = (int) $ptr;
            $ptr = $this->nextSignificant($phpcsFile, $ptr);
        }

        return [$name, $endPtr];
    }

    /**
     * Every pointer of the given types in the range, in source order.
     *
     * @param array<int, int|string>|int|string $types
     *
     * @return array<int, int>
     */
    private function pointersOfType(
        File $phpcsFile,
        array|int|string $types,
        int $startPtr,
        ?int $endPtr
    ): array {
        $pointers = [];
        $ptr = $this->orNull($phpcsFile->findNext($types, $startPtr, $endPtr));

        while ($ptr !== null) {
            $pointers[] = $ptr;
            $ptr = $this->orNull($phpcsFile->findNext($types, ($ptr + 1), $endPtr));
        }

        return $pointers;
    }

    /**
     * Pointer to the nearest enclosing scope of any of the given types, null
     * when the token is inside none of them.
     *
     * Folded with max() rather than mapped and filtered, because
     * CleanCode.Arrays.ConvertToCollection rejects array_map() and
     * array_filter() in favour of collect(), a Laravel helper this package does
     * not ship.
     *
     * @param array<int, int|string> $types
     */
    private function innermostConditionPointer(File $phpcsFile, int $stackPtr, array $types): ?int
    {
        $innermost = self::NO_POINTER;

        foreach ($types as $type) {
            $innermost = max(
                $innermost,
                $this->conditionPointer($phpcsFile, $stackPtr, $type) ?? self::NO_POINTER
            );
        }

        return match ($innermost) {
            self::NO_POINTER => null,
            default => $innermost,
        };
    }

    private function conditionPointer(File $phpcsFile, int $stackPtr, int|string $type): ?int
    {
        // The assignment is hoisted out of the match subject rather than
        // written inline: rules.xml reports an assignment in a condition (#79),
        // and a match subject is one of the conditions it reads.
        $pointer = $phpcsFile->getCondition($stackPtr, $type, false);

        return $this->orNull($pointer);
    }

    /**
     * The next non-empty token after the pointer, null at end of file or when
     * the pointer it is asked to advance from is itself absent.
     */
    private function nextSignificant(File $phpcsFile, ?int $stackPtr): ?int
    {
        return match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true)
            ),
        };
    }

    /**
     * Whether the token at the pointer is of one of the given types. Reading it
     * through a one-token findNext() window keeps the type test off the token
     * array.
     *
     * @param array<int, int|string>|int|string $types
     */
    private function isToken(File $phpcsFile, ?int $stackPtr, array|int|string $types): bool
    {
        return match ($stackPtr) {
            null => false,
            default => $phpcsFile->findNext($types, $stackPtr, ($stackPtr + 1)) !== false,
        };
    }

    private function contentOf(File $phpcsFile, int $stackPtr): string
    {
        return $phpcsFile->getTokensAsString($stackPtr, 1);
    }

    private function message(): string
    {
        return str_replace("\n", ' ', self::MESSAGE);
    }

    private function orNull(int|false $pointer): ?int
    {
        return match ($pointer) {
            false => null,
            default => $pointer,
        };
    }
}

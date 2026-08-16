<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the member ordering the "Models: Organization" standard prescribes
 * (docs/standards/models-organization.md).
 *
 * The standard's five rules, and what this sniff does with each:
 *
 * 1. Traits alphabetical, one per line — TraitOrder, MultipleTraitsPerLine.
 * 2. Properties grouped public → protected → private, alphabetical inside each
 *    group — PropertyGroupOrder, PropertyOrder.
 * 3. Relationship methods alphabetical — RelationshipMethodOrder.
 * 4. Getters and setters alphabetical — AccessorMethodOrder.
 * 5. All other methods alphabetical — MethodOrder.
 *
 * What is deliberately NOT enforced, and why:
 *
 * - The *sequence between* the member kinds — traits before properties before
 *   methods, and relationship methods before accessors before the rest. The
 *   standard numbers its rules but states an ordering requirement only
 *   *within* each kind ("list X in alphabetical order"); the one place it
 *   spells out a cross-group sequence is rule 2's public → protected → private,
 *   which is enforced. Reading the numbering itself as a sixth, unwritten rule
 *   would flag code the standard never speaks about, so the sequence between
 *   kinds stays with code review. A consumer that wants it can add
 *   SlevomatCodingStandard.Classes.ClassStructure, which does exactly that and
 *   nothing this sniff does.
 * - An auto-fixer. See the class-level note below.
 *
 * Why a custom sniff — a standard is only written as one once no shipped sniff
 * already enforces it. That evaluation, settled against the pinned Slevomat
 * version and pinned by tests/Standards/MemberOrderingTest.php:
 *
 * - SlevomatCodingStandard.Classes.ClassStructure orders *groups* of members
 *   (uses, then constants, then properties by visibility, then methods) and has
 *   no notion of alphabetical order at all, inside a group or anywhere else. It
 *   covers the grouping half of rule 2 and no part of rules 1, 3, 4, or 5.
 * - SlevomatCodingStandard.Classes.TraitUseDeclaration reports only
 *   MultipleTraitsPerDeclaration — the one-per-line half of rule 1, never the
 *   alphabetical half — and it is unscoped, so wiring it in would impose the
 *   rule on every class in a consuming codebase, not the models this standard
 *   addresses.
 * - SlevomatCodingStandard.Classes.PropertyDeclaration polices modifier order
 *   and whitespace within a single declaration. It says nothing about the order
 *   of one member relative to another.
 *
 * No combination of the three satisfies the acceptance criteria, and none was
 * bent to fit: the alphabetical requirement, which is four of the five rules,
 * exists in none of them.
 *
 * Scope decisions:
 *
 * - Restricted to classes whose extends clause names a model-shaped parent,
 *   the same gate the sibling DisallowAlwaysOnEagerLoading sniff applies and
 *   for the same reason: this is a *models* standard, and an ordering rule
 *   imposed on every class in a consuming codebase would be a different, much
 *   larger rule than the one documented. The gate is re-implemented here rather
 *   than shared, because PHPCS loads every class under a standard's Sniffs/
 *   directory as a sniff — a shared parent or trait cannot live there.
 * - Errors, not warnings. Both sibling Models sniffs warn because their
 *   standards say "avoid" and admit a legitimate exception; this standard is a
 *   flat imperative ("list … in alphabetical order") with no exception, and
 *   whether one name sorts before another is decidable rather than a judgement
 *   call.
 * - Detection only, no fixer. Reordering members means moving whole
 *   declarations along with everything bound to them — doc block, attributes,
 *   preceding comments, surrounding blank lines — and the binding is a
 *   convention about adjacency, not something the token stream marks. Trait
 *   uses are worse: a `use A, B { A::x insteadof B; }` conflict block makes the
 *   declarations order-dependent in a way a mechanical sort would silently
 *   break. The risk of a fixer that quietly moves a comment onto the wrong
 *   member, or breaks working code, outweighs the convenience of not
 *   reordering by hand, so the sniff reports and leaves the edit to the author.
 * - An anonymous class extending a model-shaped parent is checked, against
 *   itself. See register() for why registering T_CLASS alone was not enough and
 *   why the enumeration stops at the two class-like tokens it holds.
 * - Promoted constructor properties are not checked. They are declared in the
 *   constructor's parameter list, so their order is the constructor's
 *   signature — a different thing from the class body's member list that rule 2
 *   orders, and one a caller using named arguments can depend on.
 * - The body of a PHP 8.4 property hook is not checked. The hooked property is
 *   ordered like any other; the `$this`, parameters, and locals inside its
 *   `get` or `set` body are not properties. See checkProperties() for why the
 *   tokenizer makes that a test the sniff has to make rather than a given.
 * - Magic methods are not checked. Their names are PHP's, their placement is
 *   conventional (a constructor leads a class; it does not sort under "c"), and
 *   rule 5 addresses the methods an author names.
 * - Every name comparison is case-insensitive, so a class mixing `$Total` and
 *   `$amount` sorts the way a reader reads it rather than the way ASCII orders
 *   the alphabet twice over.
 */
class MemberOrderingSniff implements Sniff
{
    /**
     * Short names of the parent classes that mark a class as a model. Matches
     * the sibling DisallowAlwaysOnEagerLoading sniff's list and is configurable
     * the same way, from a ruleset via <property name="modelParentClasses"
     * type="array" .../>, for projects whose base model is named something
     * else. A parent whose short name ends in "Model" matches regardless of
     * this list.
     *
     * @var array<string>
     */
    public array $modelParentClasses = [
        'Authenticatable',
        'Model',
        'Pivot',
    ];

    /**
     * Short names of the return types that mark a public method as a
     * relationship method, so that rule 3 orders it rather than rule 5.
     * Configurable from a ruleset via <property name="relationReturnTypes"
     * type="array" .../> for a codebase with its own relation classes.
     *
     * These are Eloquent's concrete relation classes plus the two abstract
     * bases a project's own relation can extend, all matched on the short name
     * only — the FQCN is not resolvable at lint time, and both
     * `Relations\HasMany` and `HasMany` put `HasMany` in this file's tokens.
     *
     * @var array<string>
     */
    public array $relationReturnTypes = [
        'BelongsTo',
        'BelongsToMany',
        'HasMany',
        'HasManyThrough',
        'HasOne',
        'HasOneOrMany',
        'HasOneOrManyThrough',
        'HasOneThrough',
        'MorphMany',
        'MorphOne',
        'MorphOneOrMany',
        'MorphTo',
        'MorphToMany',
        'MorphedByMany',
        'Relation',
    ];

    /**
     * Visibility keywords in the order rule 2 requires them, as
     * keyword => rank. A property whose rank is lower than the one before it
     * sits in the wrong group.
     *
     * @var array<string, int>
     */
    private const VISIBILITY_RANKS = [
        'public' => 0,
        'protected' => 1,
        'private' => 2,
    ];

    /**
     * The non-visibility keywords a property declaration can start with. A
     * declaration leads with one of these or with a visibility modifier, and
     * `var $legacy;` still parses, so T_VAR belongs here too.
     *
     * Matches the list the sibling LongVariableSniff keeps for the same test.
     *
     * @var array<int|string>
     */
    private const PROPERTY_MODIFIERS = [
        T_FINAL,
        T_READONLY,
        T_STATIC,
        T_VAR,
    ];

    /**
     * Both of the class-like tokens that can name a model-shaped parent.
     *
     * PHPCS retokenizes `new class … {` to T_ANON_CLASS rather than T_CLASS, so
     * a standard registering T_CLASS alone never sees an anonymous class at all
     * — `new class extends Model { … }` went entirely unchecked. The rest of
     * this class needs nothing else for it: process() and every walk below key
     * off $stackPtr and its scope bounds generically, and the conditions test
     * each walk applies already scopes a member to the class that declares it,
     * which is what keeps a nested anonymous class's members off the enclosing
     * class's list and, now, on their own.
     *
     * The enumeration is closed rather than short by one. The class-like tokens
     * PHPCS produces are T_CLASS, T_ANON_CLASS, T_INTERFACE, T_TRAIT and
     * T_ENUM; of those only a class can extend a model, since PHP lets an
     * interface extend only interfaces and gives a trait and an enum no extends
     * clause at all. An interface reaches findExtendedClassName() but declares
     * no traits and no properties and is not a model, so it stays out
     * deliberately.
     *
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_ANON_CLASS, T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->hasModelShapedParent($phpcsFile, $stackPtr) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        // findExtendedClassName() returns false for a class the tokenizer never
        // opened, so the gate above has already excluded that case and both
        // bounds are set. They are read defensively all the same, because a
        // null $end would make every walk below run to the end of the file and
        // report a later class's members against this one.
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return;
        }

        $this->checkTraits($phpcsFile, $stackPtr, $opener, $closer);
        $this->checkProperties($phpcsFile, $stackPtr, $opener, $closer);
        $this->checkMethods($phpcsFile, $stackPtr, $opener, $closer);
    }

    /**
     * Rule 1 — traits alphabetical, one per line.
     *
     * Every `use` in a class body is a trait use: a closure's `use` clause and
     * an import `use` both live somewhere else, and the conditions check below
     * keeps a nested anonymous class's traits with that class.
     */
    private function checkTraits(File $phpcsFile, int $classPtr, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousName = null;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_USE, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            $names = $this->traitNames($phpcsFile, $ptr, $closer);

            if ($names === []) {
                continue;
            }

            if (count($names) > 1) {
                $phpcsFile->addError(
                    'Each trait needs its own use statement; this one declares %d '
                        . '(see docs/standards/models-organization.md)',
                    $ptr,
                    'MultipleTraitsPerLine',
                    [count($names)]
                );
            }

            // A multi-trait declaration is reported above and then ordered on
            // its first name, so the alphabetical check still sees one entry
            // per statement and never double-reports the same line.
            [$namePtr, $name] = $names[0];

            if ($previousName !== null && strcasecmp($name, $previousName) < 0) {
                $phpcsFile->addError(
                    'Trait %s is out of alphabetical order; it belongs before %s '
                        . '(see docs/standards/models-organization.md)',
                    $namePtr,
                    'TraitOrder',
                    [$name, $previousName]
                );
            }

            $previousName = $name;
        }
    }

    /**
     * The names a single trait-use statement declares, each with the pointer to
     * its first name token.
     *
     * The scan stops at the statement's `;` or at the `{` opening a conflict
     * resolution block, whichever comes first — the names inside that block are
     * references to already-declared traits, not further declarations.
     *
     * A name is accumulated across T_STRING and T_NS_SEPARATOR so a qualified
     * `use Vendor\Package\Concern;` compares as it is written. Comparing the
     * whole written name is what keeps the check honest: two traits imported
     * under the same short name cannot both be used, so the short name is never
     * ambiguous, but a codebase that qualifies inline sorts on what a reader
     * actually sees on the line.
     *
     * @return array<int, array{int, string}>
     */
    private function traitNames(File $phpcsFile, int $usePtr, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], ($usePtr + 1), $closer);

        if ($end === false) {
            return [];
        }

        $names = [];
        $current = '';
        $currentPtr = null;

        for ($ptr = ($usePtr + 1); $ptr < $end; $ptr++) {
            if ($tokens[$ptr]['code'] === T_COMMA) {
                $this->appendName($names, $current, $currentPtr);

                continue;
            }

            if (in_array($tokens[$ptr]['code'], [T_STRING, T_NS_SEPARATOR], true) === false) {
                continue;
            }

            $currentPtr ??= $ptr;
            $current .= $tokens[$ptr]['content'];
        }

        $this->appendName($names, $current, $currentPtr);

        return $names;
    }

    /**
     * Moves one accumulated name into the list and resets the accumulator.
     *
     * A leading namespace separator is stripped so `use \Concern;` and
     * `use Concern;` compare alike. An empty accumulator is dropped rather than
     * recorded: it means a trailing comma or a truncated statement, neither of
     * which names a trait.
     *
     * @param array<int, array{int, string}> $names
     */
    private function appendName(array &$names, string &$current, ?int &$currentPtr): void
    {
        $name = ltrim($current, '\\');

        if ($name !== '' && $currentPtr !== null) {
            $names[] = [$currentPtr, $name];
        }

        $current = '';
        $currentPtr = null;
    }

    /**
     * Rule 2 — properties grouped public → protected → private, alphabetical
     * inside each group.
     *
     * Three tests decide what is a member property, and each excludes a
     * different thing. The conditions test excludes a method body's local
     * variables and a nested anonymous class's own properties — that class is
     * registered in its own right and orders its own members. The parenthesis
     * test excludes a method's parameters and a promoted constructor property.
     * The declaration test excludes the inside of a PHP 8.4 property hook.
     *
     * The third is not redundant. PHP_CodeSniffer opens no scope for a hook, so
     * every `$this`, hook parameter, and hook local written inside one arrives
     * with the class as its innermost condition and, for the locals, no
     * parentheses either — indistinguishable from a member property by the
     * first two tests alone. Left unfiltered they were reported as properties
     * in their own right (`Property $this is out of alphabetical order`) and,
     * worse, took the baseline the real properties around them are compared
     * against, so a genuinely misordered property after a hooked one went
     * unreported. The sibling TooManyFieldsSniff and LongVariableSniff carry
     * the same test against the same tokenizer behaviour.
     *
     * Together the three are what make the getMemberProperties() call below
     * provably safe — it answers by throwing on anything that is not a member
     * var. A property default cannot contain a variable, so nothing else in a
     * class body can reach here.
     *
     * `public $first, $second;` declares two properties from one statement, and
     * both arrive here with the same visibility — the standard orders
     * properties, not statements, so both are checked.
     */
    private function checkProperties(File $phpcsFile, int $classPtr, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousName = null;
        $previousRank = null;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_VARIABLE, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            if (empty($tokens[$ptr]['nested_parenthesis']) === false) {
                continue;
            }

            if ($this->isPropertyDeclaration($phpcsFile, $ptr) === false) {
                continue;
            }

            $scope = $phpcsFile->getMemberProperties($ptr)['scope'];
            $rank = self::VISIBILITY_RANKS[$scope];
            $name = ltrim($tokens[$ptr]['content'], '$');

            if ($previousRank !== null && $rank < $previousRank) {
                $phpcsFile->addError(
                    'Property $%s is %s and follows a %s property; list properties '
                        . 'public, then protected, then private '
                        . '(see docs/standards/models-organization.md)',
                    $ptr,
                    'PropertyGroupOrder',
                    [$name, $scope, array_search($previousRank, self::VISIBILITY_RANKS, true)]
                );
            } elseif (
                $previousName !== null
                && $rank === $previousRank
                && strcasecmp($name, $previousName) < 0
            ) {
                $phpcsFile->addError(
                    'Property $%s is out of alphabetical order; it belongs before $%s '
                        . '(see docs/standards/models-organization.md)',
                    $ptr,
                    'PropertyOrder',
                    [$name, $previousName]
                );
            }

            // The displaced member becomes the new baseline whether or not it
            // was reported, so one member in the wrong place earns one error
            // rather than one for every member that follows it.
            $previousName = $name;
            $previousRank = $rank;
        }
    }

    /**
     * Whether the variable at $variablePtr opens a property declaration — that
     * is, whether the statement holding it starts with a visibility or property
     * modifier.
     *
     * The statement starts after the nearest preceding `;`, `{`, or `}`, and
     * any attributes between there and the variable are stepped over — as many
     * as are written, since `#[Encrypted] #[Cast(…)] public string $alpha` is
     * one declaration with two of them. A comma is deliberately not a boundary:
     * `public $delta, $bravo;` declares two properties from one statement, and
     * the second has to find the same `public` the first does.
     *
     * What this rejects is every statement inside a property hook's body, which
     * starts with the hook's own name, an expression, or a keyword — never a
     * modifier — and a hook parameter, whose statement starts at the hook name.
     *
     * Matches the sibling LongVariableSniff's test of the same name.
     */
    private function isPropertyDeclaration(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundary = $phpcsFile->findPrevious(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET],
            ($variablePtr - 1)
        );

        // Not reachable from checkProperties(), which only calls this for a
        // variable inside a class body — and a class body opens with the `{`
        // this search cannot miss. It is here because the alternative is worse
        // than dead: `false + 1` is 1 in PHP, so without the guard a boundary
        // that was never found would silently start the scan at the file's
        // second token and answer from whatever is there.
        if ($boundary === false) {
            return false;
        }

        $start = ($boundary + 1);

        while (
            ($start = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $variablePtr, true)) !== false
            && $tokens[$start]['code'] === T_ATTRIBUTE
        ) {
            $start = ($tokens[$start]['attribute_closer'] + 1);
        }

        // Reached whenever the variable is itself the first thing in its
        // statement — `$local = $value;` in a hook body, which is exactly what
        // this method exists to reject. Answering false here is the same answer
        // the comparison below would reach, and it reaches it without indexing
        // $tokens with a bool.
        if ($start === false) {
            return false;
        }

        return in_array($tokens[$start]['code'], Tokens::$scopeModifiers, true)
            || in_array($tokens[$start]['code'], self::PROPERTY_MODIFIERS, true);
    }

    /**
     * Rules 3, 4 and 5 — relationship methods, accessors, and everything else,
     * each alphabetical within itself.
     *
     * The three categories are ordered independently, so interleaving them
     * reports nothing: which category leads is the cross-group sequence the
     * class docblock explains this sniff does not enforce.
     *
     * Classification is relationship first, then accessor, then other. A public
     * `getPosts(): HasMany` is a relationship — it returns one — and reading it
     * as a getter instead would order it against the accessors it does not
     * belong to.
     */
    private function checkMethods(File $phpcsFile, int $classPtr, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousNames = [];
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            $name = $phpcsFile->getDeclarationName($ptr);
            $namePtr = $phpcsFile->findNext(T_STRING, ($ptr + 1), $closer);

            // A method with no name is an abstract-looking fragment the
            // tokenizer could not finish reading. Nothing about it says where
            // it belongs, so it is passed over rather than guessed at.
            if ($name === null || $namePtr === false || str_starts_with($name, '__') === true) {
                continue;
            }

            $category = $this->methodCategory($phpcsFile, $ptr, $name);
            $previousName = $previousNames[$category] ?? null;

            if ($previousName !== null && strcasecmp($name, $previousName) < 0) {
                $phpcsFile->addError(
                    '%s %s() is out of alphabetical order; it belongs before %s() '
                        . '(see docs/standards/models-organization.md)',
                    $namePtr,
                    $category,
                    [$this->categoryLabel($category), $name, $previousName]
                );
            }

            $previousNames[$category] = $name;
        }
    }

    /**
     * The error code naming the ordering rule a method answers to.
     */
    private function methodCategory(File $phpcsFile, int $methodPtr, string $name): string
    {
        $properties = $phpcsFile->getMethodProperties($methodPtr);

        if ($properties['scope'] === 'public' && $this->isRelationReturnType($properties['return_type']) === true) {
            return 'RelationshipMethodOrder';
        }

        if (preg_match('/^(get|set)[A-Z]/', $name) === 1) {
            return 'AccessorMethodOrder';
        }

        return 'MethodOrder';
    }

    /**
     * The human-readable name of a category, for the error message.
     */
    private function categoryLabel(string $category): string
    {
        $labels = [
            'RelationshipMethodOrder' => 'Relationship method',
            'AccessorMethodOrder' => 'Accessor',
        ];

        return $labels[$category] ?? 'Method';
    }

    /**
     * Whether a declared return type names an Eloquent relation.
     *
     * getMethodProperties() hands the type over exactly as written — `?` and
     * all, despite also reporting nullability separately — so it may be
     * nullable, namespace-qualified, or a union or intersection of several
     * types, including PHP 8.2's DNF spelling, which parenthesises each
     * intersection arm: `(HasMany&Countable)|null`. Each part is stripped to
     * its short name and any one part matching is enough.
     *
     * The separators stripped here are the whole set that can reach this
     * method, not a sample. getMethodProperties() concatenates only the tokens
     * in its own `$valid` list, which holds no whitespace token, so the type
     * arrives with none: the only characters around a name are the `?` it
     * prepends for a nullable, the `|` and `&` split on above, the `\` the
     * explode below handles, and the DNF parentheses. Splitting without
     * stripping the parentheses left `(HasMany` as a short name, which matches
     * no relation — a DNF relationship method was filed under rule 5 and, worse,
     * dragged an unrelated method into a spurious rule-5 violation with it.
     *
     * A method with no declared return type arrives as the empty string, which
     * reduces to an empty short name and matches nothing, so it is not a
     * relationship. That is the intended answer rather than an oversight: the
     * relation is invisible to a single-file token scan without the hint, and
     * rules.xml already requires the hint through
     * SlevomatCodingStandard.TypeHints.ReturnTypeHint.
     */
    private function isRelationReturnType(string $returnType): bool
    {
        $accepted = array_map('strtolower', $this->relationReturnTypes);
        // The pattern is a literal character class, so preg_split() cannot
        // fail; the ?: states that outright rather than leaning on it.
        $parts = preg_split('/[|&]/', $returnType) ?: [];

        foreach ($parts as $part) {
            $qualifiers = explode('\\', trim($part, '?()'));

            if (in_array(strtolower(end($qualifiers)), $accepted, true) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the class extends a model-shaped parent.
     *
     * findExtendedClassName() returns the parent exactly as written — with any
     * namespace qualification and leading separator — and false both when there
     * is no extends clause and when the class has no scope_opener, so a class
     * the tokenizer never opened needs no separate guard here.
     *
     * Only the parent's short name is compared: the FQCN is not resolvable at
     * lint time, but `extends \Illuminate\Database\Eloquent\Model` and
     * `extends Model` both put `Model` in this file's tokens. The comparison is
     * case-insensitive because PHP class names are.
     */
    private function hasModelShapedParent(File $phpcsFile, int $classPtr): bool
    {
        $parent = $phpcsFile->findExtendedClassName($classPtr);

        if ($parent === false) {
            return false;
        }

        // explode() always yields at least one element, so end() is a string.
        $qualifiers = explode('\\', $parent);
        $shortName = strtolower(end($qualifiers));

        if (in_array($shortName, array_map('strtolower', $this->modelParentClasses), true) === true) {
            return true;
        }

        return str_ends_with($shortName, 'model');
    }
}

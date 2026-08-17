<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use MikeBronner\CleanCode\Support\ParameterDeclaration;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a model that eager loads relationships on every query through a
 * populated $with property.
 *
 * Partial enforcement of the "Models: Eager Loading" standard
 * (docs/standards/models-eager-loading.md). The standard's first rule
 * prohibits exactly this construct — always-on eager loading leads to data
 * bloat, because every query pays for the relationship whether it is used or
 * not — and the construct is plain single-file token content: a class property
 * named $with whose default is a non-empty array literal. The standard's
 * second rule (prefer a query-site with() over a later load()) is not
 * token-visible and stays code review, as does dynamic assignment
 * ($this->with = … in a constructor, setEagerLoads()), which no
 * property-default check can see.
 *
 * The warning is reported on the $with property itself, which is where the
 * fix belongs.
 *
 * Scope decisions:
 *
 * - Restricted to classes whose extends clause names a model-shaped parent.
 *   $with is an ordinary property name that any class may use for anything, so
 *   the parent gate is what keeps the sniff off unrelated code. Only the
 *   parent's short name is compared: the FQCN is not resolvable at lint time,
 *   but `extends \Illuminate\Database\Eloquent\Model` and `extends Model` both
 *   put `Model` in this file's tokens.
 * - The property name is matched case-sensitively. PHP property names are
 *   case-sensitive and Eloquent reads $with, so $With is a different property
 *   with no eager-loading meaning. Parent class names are matched
 *   case-insensitively, because PHP class names are case-insensitive.
 * - Only properties of the matched class count, whether written in its body or
 *   promoted in its constructor — promotion declares the property and its
 *   default, so it eager loads the same way. A local $with inside a method, an
 *   ordinary $with parameter of a method, and a property of a nested anonymous
 *   class are all a different variable.
 * - Warning severity, not error. The standard says *avoid*, and a rare
 *   legitimate use exists — a tiny lookup relation genuinely needed on every
 *   load — so the sniff surfaces the smell without hard-blocking.
 * - Undeterminable source is not reported. A half-written property (no
 *   default, an unclosed array literal) says nothing about eager loading, so
 *   the sniff passes over it rather than guessing, matching how the sibling
 *   RequireLazyLoadingPrevention sniff treats a truncated call.
 */
class DisallowAlwaysOnEagerLoadingSniff implements Sniff
{
    /**
     * Short names of the parent classes that mark a class as a model.
     * Configurable from a ruleset via <property name="modelParentClasses"
     * type="array" .../> for projects whose base model is named something
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
        if ($this->hasModelShapedParent($phpcsFile, $stackPtr) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        // $end bounds the walk; it is not what scopes it. The conditions check
        // below is the scoping rule, and it independently rejects everything a
        // file-wide walk would additionally reach — a later class's or trait's
        // own property answers to that class or trait, and PHP has no nested
        // classes. Setting $end to null therefore changes no result, only the
        // work done, which is why no fixture pins it. This differs from the
        // sibling RequireLazyLoadingPrevention sniff, whose equivalent bound is
        // its only scoping and is pinned by a fixture.
        $end = $tokens[$stackPtr]['scope_closer'] ?? null;
        $ptr = $stackPtr;

        while (($ptr = $phpcsFile->findNext(T_VARIABLE, ($ptr + 1), $end)) !== false) {
            if ($tokens[$ptr]['content'] !== '$with') {
                continue;
            }

            // PHPCS records 'conditions' on every token, so the innermost
            // enclosing scope is always readable. Anything but this class means
            // a local variable in a method or a nested class's own property.
            if (array_key_last($tokens[$ptr]['conditions']) !== $stackPtr) {
                continue;
            }

            // Promotion is the exception a bare conditions check cannot see:
            // `__construct(public array $with = ['author'])` declares the
            // property and its default, so it eager loads exactly like the long
            // form, while an ordinary `$with` parameter declares nothing. See
            // ParameterDeclaration for why the two are indistinguishable by
            // position.
            if (ParameterDeclaration::isPlainParameter($phpcsFile, $ptr) === true) {
                continue;
            }

            if ($this->hasNonEmptyArrayDefault($phpcsFile, $ptr, $end) === false) {
                continue;
            }

            $phpcsFile->addWarning(
                'Model property $with eager loads relationships on every query, which '
                    . 'bloats the result set; load them explicitly at the query site with '
                    . 'with() instead (see docs/standards/models-eager-loading.md)',
                $ptr,
                'Found'
            );
        }
    }

    /**
     * Whether the class extends a model-shaped parent.
     *
     * findExtendedClassName() returns the parent exactly as written — with any
     * namespace qualification and leading separator — and false both when
     * there is no extends clause and when the class has no scope_opener, so a
     * class the tokenizer never opened needs no separate guard here.
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

    /**
     * Whether the property is declared with an array literal holding at least
     * one element.
     *
     * Both hops step over whitespace and comments, so a declaration written
     * across them still reads as one. An empty array, a non-array default, and
     * a property with no default at all all return false: none of them eager
     * loads anything.
     *
     * The two false-returning lookaheads read alike but are not alike:
     *
     * - The $equalPtr one is defensive only, and removing it changes no result:
     *   PHP resolves $tokens[false] to $tokens[0], the open tag, which fails the
     *   T_EQUAL comparison anyway. Confirmed by mutation — dropping the
     *   === false half leaves the whole suite green. It is kept for saying so
     *   outright instead of leaning on that coercion, as the sibling
     *   RequireLazyLoadingPrevention sniff keeps its equivalent guard.
     * - The $defaultPtr one is load-bearing: arrayBounds() takes an int under
     *   strict_types, so passing false through raises a TypeError. Pinned by
     *   no-value.php.
     */
    private function hasNonEmptyArrayDefault(File $phpcsFile, int $propertyPtr, ?int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $equalPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($propertyPtr + 1), $end, true);

        if ($equalPtr === false || $tokens[$equalPtr]['code'] !== T_EQUAL) {
            return false;
        }

        $defaultPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($equalPtr + 1), $end, true);

        if ($defaultPtr === false) {
            return false;
        }

        $bounds = $this->arrayBounds($tokens, $defaultPtr);

        if ($bounds === null) {
            return false;
        }

        [$opener, $closer] = $bounds;

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($opener + 1), $closer, true) !== false;
    }

    /**
     * The opening and closing bracket of an array literal, or null when the
     * token opens no array literal the tokenizer managed to close.
     *
     * The two array syntaxes need different treatment on unclosed input:
     *
     * - A short array needs no closer guard. PHPCS only relabels an opening
     *   square bracket T_OPEN_SHORT_ARRAY once it has matched the pair — the
     *   relabelling reads bracket_closer unconditionally (Tokenizers/PHP.php)
     *   — so an unclosed `[` stays T_OPEN_SQUARE_BRACKET and leaves by the
     *   fall-through below. unclosed-short-array.php is not a guard's coverage
     *   but the regression guard for that tokenizer behaviour: adding a
     *   fallback to the read changes no result today, and this fixture is what
     *   would fail if a future PHPCS relabelled an unmatched bracket.
     * - array() does reach here unclosed: an unterminated `array(` keeps its
     *   T_ARRAY label with no parenthesis pair recorded, so this guard is
     *   load-bearing — dropping the isset() reddens unclosed-long-array.php,
     *   and nothing else.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array{int, int}|null
     */
    private function arrayBounds(array $tokens, int $ptr): ?array
    {
        if ($tokens[$ptr]['code'] === T_OPEN_SHORT_ARRAY) {
            return [$ptr, $tokens[$ptr]['bracket_closer']];
        }

        if (
            $tokens[$ptr]['code'] === T_ARRAY
            && isset($tokens[$ptr]['parenthesis_opener'], $tokens[$ptr]['parenthesis_closer']) === true
        ) {
            return [$tokens[$ptr]['parenthesis_opener'], $tokens[$ptr]['parenthesis_closer']];
        }

        return null;
    }
}

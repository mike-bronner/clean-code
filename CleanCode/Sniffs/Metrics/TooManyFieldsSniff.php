<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's CodeSize/TooManyFields rule (issue #98).
 *
 * A class carrying too many fields is usually holding several ideas at once;
 * the fix is to group related fields into their own object (the city/state/zip
 * trio becoming an Address). The sniff counts the fields a class declares
 * itself and reports the class declaration when that count goes *above*
 * $maxFields — PHPMD's `maxfields` property, same default of 15, so a class
 * sitting exactly on the threshold is silent.
 *
 * Counted, matching PHPMD:
 *
 * - every declared property, whatever its visibility, and whether or not it is
 *   static or readonly;
 * - each variable of a multi-property declaration (`private $a, $b;` is two);
 * - nothing else: class constants, enum cases, and interface constants are not
 *   fields, inherited properties belong to the parent, and properties reached
 *   through a `use` of a trait are declared by the trait.
 *
 * Three shapes diverge from PHPMD 2.15.0, all deliberately, all measured
 * against a live install, and each pinned by a fixture. Every one of them is a
 * field PHPMD misses, so this sniff is stricter here and never looser:
 *
 * - **Promoted constructor properties are counted** (`divergences.php`).
 *   PDepend, which supplies PHPMD's field metric, does not model them, so PHPMD
 *   reads a fully promoted class as having zero fields. This ruleset *requires*
 *   promotion (SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion),
 *   so copying that blind spot would leave the rule unable to see the fields of
 *   the very classes it is meant to police.
 * - **PHP 8.4's `final` properties and asymmetric visibility are counted**
 *   (`php84-modifiers.php`). PDepend parses neither, and abandons the whole
 *   file rather than the one declaration.
 * - **An anonymous class is counted as its own class** (`divergences.php`).
 *   PHPMD charges the fields of a nested anonymous class to the enclosing class
 *   — reporting a class that declares no fields of its own — and misses a
 *   top-level anonymous class altogether.
 *
 * A property hook's body is not counted either (`property-hooks.php`). It is
 * not a divergence so much as a gap: PHPMD cannot read a file containing one.
 *
 * Only classes are examined, as in PHPMD, whose rule is `ClassAware`. An
 * interface or enum cannot declare a property at all, and a trait's fields are
 * counted where they land, in the class that uses it.
 *
 * Detection only: the fix is to extract a new object and re-point every use of
 * the moved fields, which is a design decision, not a mechanical rewrite.
 */
class TooManyFieldsSniff implements Sniff
{
    /**
     * The keywords a property declaration opens with. Every property carries at
     * least one of them: since PHP 8.0 a bare `$field;` in a class body is a
     * parse error, and `var` is the pre-5.0 spelling that still parses.
     */
    private const PROPERTY_MODIFIERS = [
        T_VAR,
        T_STATIC,
        T_READONLY,
        T_FINAL,
    ];

    /**
     * A class declaring more fields than this is reported. PHPMD spells the
     * same property `maxfields`; the default matches PHPMD's.
     *
     * @var integer
     */
    public $maxFields = 15;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS, T_ANON_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // An unterminated class body is live coding, not a design smell: its
        // remaining fields have not been typed yet, so any count taken here
        // would be of a half-written class.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $threshold = (int) $this->maxFields;
        $fields = $this->countFields($phpcsFile, $stackPtr);

        if ($fields <= $threshold) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        $phpcsFile->addError(
            'The class %s has %s fields; consider redesigning it to keep the number of fields under %s',
            $stackPtr,
            'MaxExceeded',
            [($name ?? '{anonymous}'), $fields, $threshold]
        );
    }

    /**
     * Counts the fields the class at $classPtr declares itself.
     */
    private function countFields(File $phpcsFile, int $classPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$classPtr]['scope_closer'];
        $fields = 0;
        $ptr = $tokens[$classPtr]['scope_opener'];

        while (($ptr = $phpcsFile->findNext(T_VARIABLE, ($ptr + 1), $closer)) !== false) {
            if ($this->isFieldOf($phpcsFile, $ptr, $classPtr) === true) {
                $fields++;
            }
        }

        return $fields;
    }

    /**
     * Whether the variable at $variablePtr is a field the class at $classPtr
     * declares.
     *
     * The innermost scope the variable sits in has to be the class itself,
     * which is what rules out everything written inside a method body, a
     * closure, or a nested anonymous class — each of those opens a scope of its
     * own, and the anonymous class is processed separately as its own class.
     *
     * That leaves two ways to reach the class scope: from a parameter list,
     * where only a promoted parameter declares a field, and from the class body
     * itself, where the statement has to actually be a property declaration.
     * The second check is what keeps the bodies of property hooks out of the
     * count — PHP_CodeSniffer opens no scope for a hook, so every `$this` and
     * every local inside one arrives here looking class-scoped.
     */
    private function isFieldOf(File $phpcsFile, int $variablePtr, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (array_key_last($tokens[$variablePtr]['conditions']) !== $classPtr) {
            return false;
        }

        if (empty($tokens[$variablePtr]['nested_parenthesis']) === false) {
            return $this->isPromotedParameter($phpcsFile, $variablePtr);
        }

        return $this->isPropertyDeclaration($phpcsFile, $variablePtr);
    }

    /**
     * Whether the parameter at $variablePtr carries a visibility modifier, and
     * so declares a field rather than taking an argument.
     *
     * The parameter starts after the nearest preceding comma, or at the opening
     * parenthesis for the first one, and a modifier can only appear between
     * that point and the variable. Bounding the search at the comma is what
     * stops a plain parameter inheriting the modifier of a promoted one
     * declared before it.
     */
    private function isPromotedParameter(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $opener = array_key_first($tokens[$variablePtr]['nested_parenthesis']);
        $comma = $phpcsFile->findPrevious(T_COMMA, ($variablePtr - 1), $opener);
        $start = ($comma === false ? $opener : $comma);

        return $phpcsFile->findPrevious(
            Tokens::$scopeModifiers,
            ($variablePtr - 1),
            $start
        ) !== false;
    }

    /**
     * Whether the class-scoped variable at $variablePtr opens or continues a
     * property declaration.
     *
     * The declaration starts at the previous statement boundary, so the first
     * meaningful token after that boundary — past any attribute — has to be one
     * of the keywords a property declaration opens with. Reading the modifier
     * from the start of the statement rather than from the variable is what
     * makes the second half of `private $first, $second;` count as a field too.
     */
    private function isPropertyDeclaration(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundary = $phpcsFile->findPrevious(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET],
            ($variablePtr - 1)
        );
        $start = ($boundary + 1);

        while (
            ($start = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $variablePtr, true)) !== false
            && $tokens[$start]['code'] === T_ATTRIBUTE
        ) {
            $start = ($tokens[$start]['attribute_closer'] + 1);
        }

        if ($start === false) {
            return false;
        }

        return in_array($tokens[$start]['code'], Tokens::$scopeModifiers, true) === true
            || in_array($tokens[$start]['code'], self::PROPERTY_MODIFIERS, true) === true;
    }
}

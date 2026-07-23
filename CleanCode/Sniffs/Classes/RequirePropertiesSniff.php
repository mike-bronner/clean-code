<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the "Properties: Are Required" standard.
 *
 * A class represents a concept, and every concept has attributes. A class that
 * declares no properties encapsulates no state and has no identity — it is
 * procedural, non-object-oriented code wearing a class keyword. This sniff
 * flags every `class` declaration that declares zero instance or static
 * properties, reporting at the class declaration keyword.
 *
 * What counts as a property declaration:
 *
 * - a conventional member variable in the class body (`private int $total;`),
 *   instance or `static`;
 * - a constructor-promoted parameter (`__construct(private int $total)`) — the
 *   visibility modifier turns the parameter into a real instance property, so a
 *   class whose only state is promoted is compliant. This also keeps the sniff
 *   consistent with the enforced "Constructors: Property Promotion" standard.
 *
 * What does NOT count, so a class with only these is still flagged:
 *
 * - plain (non-promoted) constructor or method parameters — they are call
 *   arguments, not stored state;
 * - class constants — constants are not the target of this standard;
 * - properties belonging to a nested class or anonymous class inside a method.
 *
 * Only classes are checked. Interfaces and traits cannot declare instance
 * state the way a class does, and enums carry identity through their cases;
 * registering solely on `T_CLASS` excludes all three (and anonymous classes,
 * tokenized as `T_ANON_CLASS`).
 *
 * Detection only: a class with no state cannot be given meaningful state
 * mechanically — the missing property is a design decision — so no auto-fix is
 * offered.
 */
class RequirePropertiesSniff implements Sniff
{
    /**
     * Modifiers that, on a constructor parameter, promote it to a property.
     * A visibility keyword is what makes a parameter a promoted property;
     * `readonly` only ever accompanies one.
     */
    private const PROMOTION_MODIFIERS = [
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_READONLY,
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
        $tokens = $phpcsFile->getTokens();

        // A class with no body scope (parse error / incomplete source) has
        // nothing to inspect.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        if ($this->hasProperty($phpcsFile, $stackPtr)) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr) ?? 'class';

        $phpcsFile->addError(
            'Class %s declares no properties; a class must encapsulate state (add at least one property)',
            $stackPtr,
            'MissingProperty',
            [$name]
        );
    }

    /**
     * Whether the class at $classPtr declares at least one property — a
     * conventional member variable or a constructor-promoted parameter that
     * belongs directly to this class.
     */
    private function hasProperty(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$classPtr]['scope_opener'];
        $closer = $tokens[$classPtr]['scope_closer'];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] !== T_VARIABLE) {
                continue;
            }

            // Skip variables owned by a nested scope (a method body, a nested
            // or anonymous class) — only members of this class count.
            if ($this->belongsToClass($tokens[$i]['conditions'], $classPtr) === false) {
                continue;
            }

            // A member variable sitting directly in the class body (inside no
            // parentheses) is a conventional property declaration.
            if (empty($tokens[$i]['nested_parenthesis'])) {
                return true;
            }

            // Otherwise it is a parameter; it only counts when promoted.
            if ($this->isPromotedParameter($phpcsFile, $i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $conditions' innermost enclosing scope is exactly the class at
     * $classPtr. Constructor parameters sit at class-body level (outside the
     * method's own brace scope), so a promoted property resolves here too.
     *
     * @param array<int, int|string> $conditions
     */
    private function belongsToClass(array $conditions, int $classPtr): bool
    {
        return $conditions !== [] && array_key_last($conditions) === $classPtr;
    }

    /**
     * Whether the parameter variable at $variablePtr is a promoted property —
     * a visibility (or readonly) modifier appears between the parameter's start
     * (the previous comma or the opening parenthesis) and the variable itself.
     * Those keywords are only legal on a promoted constructor parameter.
     */
    private function isPromotedParameter(File $phpcsFile, int $variablePtr): bool
    {
        $boundary = $phpcsFile->findPrevious([T_COMMA, T_OPEN_PARENTHESIS], ($variablePtr - 1));

        if ($boundary === false) {
            return false;
        }

        return $phpcsFile->findNext(self::PROMOTION_MODIFIERS, ($boundary + 1), $variablePtr) !== false;
    }
}

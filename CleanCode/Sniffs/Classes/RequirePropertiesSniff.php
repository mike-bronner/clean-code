<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the "Properties: Are Required" standard.
 *
 * A class represents a concept, and every concept has attributes. A class that
 * encapsulates no state has no identity — it is procedural, non-object-oriented
 * code wearing a class keyword. This sniff flags every `class` declaration that
 * has no state at all, reporting at the class declaration keyword.
 *
 * What counts as state the class holds:
 *
 * - a conventional member variable in the class body (`private int $total;`),
 *   instance or `static`;
 * - a constructor-promoted parameter (`__construct(private int $total)`) — the
 *   visibility modifier turns the parameter into a real instance property, so a
 *   class whose only state is promoted is compliant. This also keeps the sniff
 *   consistent with the enforced "Constructors: Property Promotion" standard;
 * - an `extends` clause — the parent's properties are this class's state;
 * - a `use` of a trait in the class body — the trait's properties become this
 *   class's own.
 *
 * The last two make this a deliberate *heuristic*. A single-file sniff cannot
 * confirm the parent or the trait really declares anything, so a class using a
 * genuinely stateless trait slips through. The residual hole is small: a
 * stateless parent declares no properties of its own and is itself flagged, so
 * at least one class in every inheritance chain has to own state. The
 * alternative — reading `extends` and `use` as irrelevant — routinely flags
 * idiomatic code that plainly *has* state (`class NotFoundException extends
 * HttpException {}`, an entity composing `HasTimestamps`), which contradicts
 * the standard this sniff exists to enforce.
 *
 * What does NOT count, so a class with only these is still flagged:
 *
 * - plain (non-promoted) constructor or method parameters — they are call
 *   arguments, not stored state;
 * - class constants — constants are not the target of this standard;
 * - an `implements` clause — an interface declares no instance state to inherit;
 * - properties, `extends` clauses, and trait uses belonging to a nested class
 *   or an anonymous class inside a method.
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

        if ($this->hasState($phpcsFile, $stackPtr)) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr) ?? 'class';

        $phpcsFile->addError(
            'Class %s encapsulates no state; it declares no property, extends no class, and uses no trait'
                . ' (add at least one property)',
            $stackPtr,
            'MissingProperty',
            [$name]
        );
    }

    /**
     * Whether the class at $classPtr holds state: a property of its own, a
     * parent to inherit properties from, or a trait to compose them in.
     */
    private function hasState(File $phpcsFile, int $classPtr): bool
    {
        return $phpcsFile->findExtendedClassName($classPtr) !== false
            || $this->hasProperty($phpcsFile, $classPtr)
            || $this->usesTrait($phpcsFile, $classPtr);
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
     * Whether the class at $classPtr composes a trait. Only a `use` sitting
     * directly in this class's body is a trait import: a file-level import is
     * outside the class scope entirely, and a closure's `use (...)` binding —
     * or a trait imported by a nested/anonymous class — resolves to an inner
     * scope, so neither reaches belongsToClass().
     */
    private function usesTrait(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$classPtr]['scope_closer'];
        $usePtr = $tokens[$classPtr]['scope_opener'];

        while (($usePtr = $phpcsFile->findNext(T_USE, ($usePtr + 1), $closer)) !== false) {
            if ($this->belongsToClass($tokens[$usePtr]['conditions'], $classPtr)) {
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

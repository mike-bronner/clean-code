<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Classes: No Statics" standard.
 *
 * Classes are meant to be instantiated and identifiable; static methods and
 * static properties give a class no identity and are little more than modern
 * `GOTO`s. This sniff flags every static method and static property
 * declaration in any object-oriented container — class, abstract class,
 * interface, trait, and enum — reporting at the offending `static` keyword.
 *
 * Only member *declarations* are flagged. The `static` keyword also appears in
 * constructs that are not static members and are left untouched:
 *
 * - `static` return types (`function make(): static`) and late static binding
 *   (`new static`, `static::foo()`) — these reference the runtime class, they
 *   do not declare a static member.
 * - static closures and arrow functions (`static fn () => ...`) — anonymous
 *   functions that merely drop the `$this` binding.
 * - function-local `static` variables (`static $count = 0;`) — a statement
 *   inside a method body, not a class member.
 * - class constants — constants are not the target of this standard.
 *
 * Detection only: converting a static member to an instance member requires
 * rewriting every call site (`Class::member()` becomes an instance access), so
 * a token-based auto-fix cannot be applied safely.
 */
class DisallowStaticMembersSniff implements Sniff
{
    /**
     * Member-declaration modifier keywords that may sit between the `static`
     * keyword and the method/property it modifies.
     */
    private const MEMBER_MODIFIERS = [
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_FINAL,
        T_ABSTRACT,
        T_READONLY,
        T_VAR,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_STATIC];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // A member modifier lives directly in a class-like body; anything whose
        // innermost scope is a function (local `static`, `new static`,
        // `static::`, static closures) is not a member declaration.
        $conditions = $tokens[$stackPtr]['conditions'];

        if ($conditions === []) {
            return;
        }

        if (in_array(end($conditions), Tokens::$ooScopeTokens, true) === false) {
            return;
        }

        // Walk past whitespace, comments, and sibling modifiers to the token
        // the `static` actually modifies.
        $skip = array_merge(array_values(Tokens::$emptyTokens), self::MEMBER_MODIFIERS);
        $declaratorPtr = $phpcsFile->findNext($skip, ($stackPtr + 1), null, true);

        if ($declaratorPtr === false) {
            return;
        }

        if ($tokens[$declaratorPtr]['code'] === T_FUNCTION) {
            $this->reportStaticMethod($phpcsFile, $stackPtr, $declaratorPtr);

            return;
        }

        $this->reportStaticProperty($phpcsFile, $stackPtr, $declaratorPtr);
    }

    /**
     * Reports the static method modified at $staticPtr, naming it from the
     * method identifier that follows the `function` keyword.
     */
    private function reportStaticMethod(File $phpcsFile, int $staticPtr, int $functionPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $openParen = $phpcsFile->findNext([T_OPEN_PARENTHESIS], ($functionPtr + 1), null);
        $namePtr = $phpcsFile->findNext(
            [T_STRING],
            ($functionPtr + 1),
            ($openParen === false ? null : $openParen)
        );
        $name = ($namePtr === false ? 'method' : $tokens[$namePtr]['content']) . '()';

        $phpcsFile->addError(
            'Static method %s is not allowed; a class should be instantiated, not called statically',
            $staticPtr,
            'StaticMethod',
            [$name]
        );
    }

    /**
     * Reports a static property when the `static` keyword introduces one — i.e.
     * a property variable appears before the declaration's structural boundary.
     * A `static` used as a return type (`function make(): static`) reaches the
     * method body's `{` with no variable in between and is left untouched.
     */
    private function reportStaticProperty(File $phpcsFile, int $staticPtr, int $declaratorPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $boundary = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $declaratorPtr, null);
        $propertyPtr = $phpcsFile->findNext(
            [T_VARIABLE],
            $declaratorPtr,
            ($boundary === false ? null : $boundary)
        );

        if ($propertyPtr === false) {
            return;
        }

        $phpcsFile->addError(
            'Static property %s is not allowed; a class should be instantiated, not accessed statically',
            $staticPtr,
            'StaticProperty',
            [$tokens[$propertyPtr]['content']]
        );
    }
}

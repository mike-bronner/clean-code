<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a method whose entire body is a single `throw`, declared in a type
 * that `extends` a parent or `implements` an interface.
 *
 * Partial enforcement of the "Pattern: SOLID" standard
 * (docs/standards/pattern-solid.md, #5), spun out as #131 — the Liskov
 * Substitution slice. Behavioural substitutability cannot be read off one
 * file's tokens, but *refused bequest* can: a subtype that stubs an inherited
 * method out with `throw new BadMethodCallException('not supported')` rejects
 * behaviour its supertype promises, so a caller substituting the subtype
 * breaks. That is exactly what LSP forbids, and it is visible in the
 * declaration alone.
 *
 * It sits in `Pattern/` beside `AvoidDuplicateCodeBlocks`, which is the same
 * shape: the token-visible slice of a `Pattern:` standard. #131 suggests a
 * `SOLID/` category by example; the existing category is what this package
 * actually files pattern sniffs under, and CONTRIBUTING.md is explicit that
 * the repo's convention wins over an issue's illustration.
 *
 * Reports a **warning**, never an error. The remedy is a design change — split
 * the hierarchy, or segregate the interface so the method is never promised —
 * and which of those applies is a judgement the sniff cannot make. It points
 * at candidates rather than failing a consumer's build.
 *
 * Detection only. There is no mechanical rewrite: deleting the method changes
 * the type's surface, and moving it changes the hierarchy.
 *
 * What has to hold, all three read from this one file:
 *
 * - The method is declared **directly** inside a type that `extends` and/or
 *   `implements`, resolved through `File::findExtendedClassName()` and
 *   `File::findImplementedInterfaceNames()` over the innermost enclosing
 *   scope. The parent's own source is never consulted: the signal is the stub,
 *   not what it overrides (#131's stated boundary).
 * - The method **has a body**. An abstract declaration and an interface
 *   signature carry no `scope_opener`, so neither is reported.
 * - The body's first non-whitespace, non-comment token is `T_THROW`, and the
 *   statement that token opens ends at the last thing in the body.
 *   `File::findEndOfStatement()` supplies the statement's end, so a `throw`
 *   whose arguments contain a closure — semicolons and all — is still one
 *   statement, while `throw ...; $more = 1;` is two and is not reported.
 *
 * Which enclosing scopes can satisfy the first condition is decided by PHPCS
 * rather than by a list here: both finders return `false` for any token that
 * is not the construct they read, so a `trait` (which declares no hierarchy of
 * its own), a nested function, and a function at file scope all fall out
 * without a special case. A `class`, an anonymous class, and a backed or pure
 * `enum` are what remain in practice — an enum cannot extend, but it can
 * implement, and an enum case is as substitutable for the interface as any
 * object. An `interface` extending another does satisfy `findExtendedClassName()`,
 * but every method it declares is a signature with no body; the bodiless guard
 * runs first in `process()`, so no interface method ever reaches the check.
 *
 * Reported on the `function` keyword under the single code `RefusedBequest`.
 * The defect is what the whole body *is*, so the declaration is the only
 * location that names the method rather than one line of it.
 *
 * Deliberately **not** narrowed, per #131's "start strict":
 *
 * - A `private` or `final` method, which no supertype can be promising.
 * - An `abstract` class's intentionally-guarded template method.
 * - A method the supertype never declares at all.
 *
 * Each is a shape the heuristic over-reports, and each is a warning a reviewer
 * dismisses in a second. Narrowing any of them needs real-world noise to
 * justify it, which this package does not have yet.
 */
class ThrowOnlyMethodOverrideSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $method = $phpcsFile->getDeclarationName($stackPtr);

        // Defensive only, and no fixture can pin it: PHPCS gives a closure its
        // own T_CLOSURE token, which this sniff never registers for, so the
        // one nameless T_FUNCTION is a truncated declaration — and a
        // truncation deep enough to strip the name also strips the body the
        // checks below need.
        if ($method === null) {
            return;
        }

        // An abstract declaration or an interface signature has no body.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        if ($this->declaresHierarchy($phpcsFile, $tokens[$stackPtr]['conditions']) === false) {
            return;
        }

        if ($this->bodyIsOneThrow($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addWarning(
            '%s() stubs out an inherited method with a single throw; a subtype that refuses'
                . ' behaviour its supertype promises breaks Liskov Substitution. Split the'
                . ' hierarchy, or segregate the interface so this method is never promised,'
                . ' instead of throwing (see docs/standards/pattern-solid.md)',
            $stackPtr,
            'RefusedBequest',
            [$method]
        );
    }

    /**
     * Whether the innermost scope holding this function declares a supertype.
     *
     * Only the innermost one is asked. A function nested in another function,
     * or declared at file scope, overrides nothing whatever its outer scopes
     * declare — and a method of an anonymous class declared inside a method
     * answers to that anonymous class's own hierarchy, not the outer type's.
     *
     * The two finders are also what decides which scopes qualify: each returns
     * `false` for any token other than the construct it reads, so a `trait`
     * and an enclosing function need no case of their own here.
     *
     * @param array<int, int|string> $conditions
     */
    private function declaresHierarchy(File $phpcsFile, array $conditions): bool
    {
        if ($conditions === []) {
            return false;
        }

        $ownerPtr = array_key_last($conditions);

        return $phpcsFile->findExtendedClassName($ownerPtr) !== false
            || $phpcsFile->findImplementedInterfaceNames($ownerPtr) !== false;
    }

    /**
     * Whether the method body holds exactly one statement, and that statement
     * is a `throw`.
     *
     * Two reads, in the order that makes each cheap. The body has to *open* on
     * `throw` — `return throw new X();` opens on `T_RETURN` and returns a
     * value the caller can use, so it refuses nothing — and the statement that
     * `throw` opens has to *close* the body, which is what separates a stub
     * from a guard clause followed by real work.
     */
    private function bodyIsOneThrow(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closerPtr = $tokens[$stackPtr]['scope_closer'];
        $firstPtr = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            ($tokens[$stackPtr]['scope_opener'] + 1),
            $closerPtr,
            true
        );

        if ($firstPtr === false || $tokens[$firstPtr]['code'] !== T_THROW) {
            return false;
        }

        $endPtr = $phpcsFile->findEndOfStatement($firstPtr);

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($endPtr + 1), null, true) === $closerPtr;
    }
}

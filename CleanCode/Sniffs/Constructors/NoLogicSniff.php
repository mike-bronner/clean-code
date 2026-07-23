<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Constructors;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Constructors: No Logic in Constructors" standard.
 *
 * A constructor should merely assign values to object properties. Any other
 * work — branching, looping, throwing, calling methods or functions,
 * intermediate computation — is logic that runs on every instantiation and
 * signals that the information passed in should have been another object.
 *
 * The sniff walks the top-level statements of every `__construct()` body and
 * flags each statement that is not one of the two allowed forms:
 *
 * - a **property assignment** — a statement that begins with `$this->…` and has
 *   a plain `=` assignment operator at its top level (`$this->foo = …;`). The
 *   right-hand side is not inspected, so defaulting with `??` or a ternary
 *   (`$this->foo = $foo ?? 0;`) stays compliant.
 * - a **`parent::__construct(...)` call** — delegating to the parent
 *   constructor is assignment, not logic.
 *
 * Everything else is flagged at the statement's first token:
 * control structures (`if`/`for`/`foreach`/`while`/`do`/`switch`/`try`),
 * `throw`, method and function calls, increments, and assignments whose target
 * is not a property (a local variable is intermediate computation, not object
 * state). Statements nested inside a flagged control structure are not examined
 * separately — the enclosing statement is reported once.
 *
 * Detection only: moving logic out of a constructor is a refactor — the code
 * has to land somewhere deliberate (a named constructor, a factory, or a
 * collaborator object). A token-based fixer cannot make that decision, so no
 * auto-fix is offered.
 */
class NoLogicSniff implements Sniff
{
    /**
     * Keywords that continue a control structure findEndOfStatement() treats as
     * a fresh statement (`if … elseif … else`, `try … catch … finally`). They
     * are consumed into the statement they continue so the construct is
     * reported once, at its opening keyword.
     */
    private const CONTINUATION_KEYWORDS = [
        T_ELSEIF,
        T_ELSE,
        T_CATCH,
        T_FINALLY,
    ];

    /**
     * Bracket tokens that open a nested context; a top-level assignment
     * operator must sit outside all of them.
     */
    private const BRACKET_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_CURLY_BRACKET,
    ];

    /**
     * Bracket tokens that close a nested context, paired with BRACKET_OPENERS.
     */
    private const BRACKET_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

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

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || strtolower($name) !== '__construct') {
            return;
        }

        // Abstract and interface constructors declare no body.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $opener = $tokens[$stackPtr]['scope_opener'];
        $closer = $tokens[$stackPtr]['scope_closer'];

        $statementStart = $phpcsFile->findNext(Tokens::$emptyTokens, ($opener + 1), $closer, true);

        while ($statementStart !== false && $statementStart < $closer) {
            $statementEnd = $this->endOfStatement($phpcsFile, $statementStart, $closer);

            if (
                $this->isPropertyAssignment($phpcsFile, $statementStart, $statementEnd) === false
                && $this->isParentConstructorCall($phpcsFile, $statementStart) === false
            ) {
                $phpcsFile->addError(
                    'Constructors must contain no logic, only property assignments; move this statement '
                        . 'into a named constructor, factory, or collaborator',
                    $statementStart,
                    'LogicFound'
                );
            }

            $statementStart = $phpcsFile->findNext(Tokens::$emptyTokens, ($statementEnd + 1), $closer, true);
        }
    }

    /**
     * Returns the position of the last token of the full statement starting at
     * $start, extended across continuation clauses (`elseif`/`else`/`catch`/
     * `finally`, and the trailing `while (...);` of a `do … while`) that PHPCS
     * would otherwise report as separate statements.
     */
    private function endOfStatement(File $phpcsFile, int $start, int $limit): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $phpcsFile->findEndOfStatement($start);

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), $limit, true);

            if ($next === false) {
                break;
            }

            $code = $tokens[$next]['code'];

            if (in_array($code, self::CONTINUATION_KEYWORDS, true)) {
                $end = $phpcsFile->findEndOfStatement($next);

                continue;
            }

            // The `while (...);` tail of a `do … while` carries the loop
            // condition only (no brace scope of its own).
            if (
                $code === T_WHILE
                && $tokens[$start]['code'] === T_DO
                && isset($tokens[$next]['scope_opener']) === false
            ) {
                $end = $phpcsFile->findEndOfStatement($next);

                continue;
            }

            break;
        }

        return $end;
    }

    /**
     * A statement is a property assignment when it begins with `$this`, accesses
     * a property via `->`, and carries a plain `=` operator at bracket depth 0.
     * The right-hand side is intentionally not inspected.
     */
    private function isPropertyAssignment(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] !== T_VARIABLE || $tokens[$start]['content'] !== '$this') {
            return false;
        }

        $access = $phpcsFile->findNext(Tokens::$emptyTokens, ($start + 1), ($end + 1), true);

        if ($access === false || $tokens[$access]['code'] !== T_OBJECT_OPERATOR) {
            return false;
        }

        return $this->hasTopLevelAssignment($phpcsFile, $start, $end);
    }

    /**
     * Whether a plain `=` assignment operator sits at bracket depth 0 between
     * $start and $end — i.e. it is the statement's own assignment, not one
     * buried inside a call argument, array, or nested expression.
     */
    private function hasTopLevelAssignment(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $depth = 0;

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if (in_array($code, self::BRACKET_OPENERS, true)) {
                $depth++;
            } elseif (in_array($code, self::BRACKET_CLOSERS, true)) {
                $depth--;
            } elseif ($code === T_EQUAL && $depth === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the statement at $start is a `parent::__construct(...)` call.
     */
    private function isParentConstructorCall(File $phpcsFile, int $start): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] !== T_PARENT) {
            return false;
        }

        $colon = $phpcsFile->findNext(Tokens::$emptyTokens, ($start + 1), null, true);

        if ($colon === false || $tokens[$colon]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        $method = $phpcsFile->findNext(Tokens::$emptyTokens, ($colon + 1), null, true);

        return $method !== false
            && $tokens[$method]['code'] === T_STRING
            && strtolower($tokens[$method]['content']) === '__construct';
    }
}

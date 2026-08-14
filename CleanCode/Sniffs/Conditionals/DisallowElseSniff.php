<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids every `else` and every `elseif` (including the two-word `else if`).
 * Both are always avoidable with an early exit or a guard clause, which is the
 * clean-code standard this sniff enforces
 * ([#14](https://github.com/mike-bronner/phpcs-rules/issues/14) —
 * docs/standards/conditionals-no-else-or-elseif.md).
 *
 * The sniff started as a replica of PHPMD's CleanCode/ElseExpression (#77),
 * which reports the `else` scope only and leaves `elseif` alone. #14 is
 * stricter on purpose, so `elseif` is now reported too and the PHPMD mapping
 * is documented as *stricter than* parity rather than equal to it —
 * docs/phpmd/cleancode-elseexpression.md carries the difference.
 *
 * Two message codes, because a consumer excluding one shape should not lose
 * the other:
 *
 *  - `Found` — an `else`. The code predates #14 and is kept verbatim, so a
 *    consumer's existing `<exclude name="…DisallowElse.Found"/>` keeps
 *    meaning what it meant.
 *  - `ElseIfFound` — an `elseif` or an `else if`.
 *
 * PHP allows the reserved word `else` as a member name — a method or class
 * constant since 7.0, an enum case since 8.1 — and `token_get_all()` still
 * returns T_ELSE for the declaration and for a `Foo::else` reference. No guard
 * is needed here: PHPCS's own tokenizer rewrites every one of those to
 * T_STRING before a sniff sees it, so this sniff never registers on one.
 * tests/fixtures/DisallowElseSniff/passing.php carries each shape, which keeps
 * that dependency honest if the tokenizer ever changes.
 *
 * Auto-fix is offered only where the rewrite provably preserves behavior and
 * source content; see isFixableElse()/isFixableElseIf(). Everything else is
 * reported and left for a manual refactor.
 */
class DisallowElseSniff implements Sniff
{
    /**
     * Statements that unconditionally leave the branch they end. When every
     * branch before an `else`/`elseif` ends in one of these, the branch can
     * never fall through to the code after the construct, so unwrapping the
     * `else` (or splitting the `elseif` into its own `if`) cannot change
     * which statements run.
     */
    private const TERMINATING_STATEMENTS = [
        T_RETURN,
        T_THROW,
        T_CONTINUE,
        T_BREAK,
        T_EXIT,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_ELSE, T_ELSEIF];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        // The `!== false` honours findNext()'s int|false contract. It is not a
        // behavioural branch and carries no fixture: reaching it would need a
        // file whose very last token is `else`, which is not parseable PHP.
        $isElseIf = $tokens[$stackPtr]['code'] === T_ELSEIF
            || ($next !== false && $tokens[$next]['code'] === T_IF);

        if ($isElseIf === true) {
            $this->processElseIf($phpcsFile, $stackPtr);

            return;
        }

        $this->processElse($phpcsFile, $stackPtr);
    }

    private function processElseIf(File $phpcsFile, int $stackPtr): void
    {
        $message = 'Elseif clauses are unnecessary; use a separate if with an early exit instead';

        if ($this->isFixableElseIf($phpcsFile, $stackPtr) === false) {
            $phpcsFile->addError($message, $stackPtr, 'ElseIfFound');

            return;
        }

        if ($phpcsFile->addFixableError($message, $stackPtr, 'ElseIfFound') === true) {
            $this->fixElseIf($phpcsFile, $stackPtr);
        }
    }

    private function processElse(File $phpcsFile, int $stackPtr): void
    {
        $message = 'Else clauses are unnecessary; use an early exit or a guard clause instead';

        if ($this->isFixableElse($phpcsFile, $stackPtr) === false) {
            $phpcsFile->addError($message, $stackPtr, 'Found');

            return;
        }

        if ($phpcsFile->addFixableError($message, $stackPtr, 'Found') === true) {
            $this->fixElse($phpcsFile, $stackPtr);
        }
    }

    /**
     * An `elseif` (or `else if`) becomes a standalone `if` only when the
     * construct is braced (alternative syntax would need its own `endif`), the
     * layout is canonical, nothing but whitespace sits in the ranges the fixer
     * deletes — between the previous closing brace and the keyword, and, for
     * the two-word form, between `else` and `if` — and every branch before it
     * terminates.
     */
    private function isFixableElseIf(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_ELSEIF && $this->hasCurlyScope($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($this->hasCanonicalLayout($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($tokens[$stackPtr]['code'] === T_ELSE) {
            $if = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

            if ($if === false || $this->containsOnlyWhitespace($phpcsFile, ($stackPtr + 1), $if) === false) {
                return false;
            }
        }

        return $this->precedingBranchesTerminate($phpcsFile, $stackPtr);
    }

    /**
     * A plain `else` wrapper is removed only when it has a curly-brace body,
     * the layout is canonical, nothing but whitespace sits between the `else`
     * and its opening brace (a comment there would be deleted with the
     * wrapper), its closing brace sits alone on its own line (removing the
     * brace of an inline body would splice the following line onto the
     * statement; a comment trailing the brace would be detached from the
     * construct it annotates), and every branch before it terminates.
     */
    private function isFixableElse(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($this->hasCurlyScope($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($this->containsOnlyWhitespace($phpcsFile, ($stackPtr + 1), $tokens[$stackPtr]['scope_opener']) === false) {
            return false;
        }

        if ($this->hasCanonicalLayout($phpcsFile, $stackPtr) === false) {
            return false;
        }

        if ($this->closesOnOwnLine($phpcsFile, $stackPtr) === false) {
            return false;
        }

        return $this->precedingBranchesTerminate($phpcsFile, $stackPtr);
    }

    /**
     * The fixers assume the canonical `} else {` / `} elseif (…) {` layout:
     * the previous branch's closing brace is the first non-whitespace token
     * on its line, and the keyword follows it on the same line with nothing
     * but whitespace in between. Anything else — a comment before the
     * keyword (which the rewrite would silently delete), a compact
     * single-line construct (which it would splice or mis-indent), an
     * `else` on its own line — is flagged but not auto-fixed.
     */
    private function hasCanonicalLayout(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previousCloser === false || $tokens[$previousCloser]['line'] !== $tokens[$stackPtr]['line']) {
            return false;
        }

        if ($this->containsOnlyWhitespace($phpcsFile, ($previousCloser + 1), $stackPtr) === false) {
            return false;
        }

        return $phpcsFile->findFirstOnLine(T_WHITESPACE, $previousCloser, true) === $previousCloser;
    }

    /**
     * Whether the scope closer of the construct at $stackPtr sits alone on
     * its own line: it is the first non-whitespace token on the line and
     * nothing but whitespace follows it. False for an inline `{ … }` body
     * and for a trailing comment (`} // note`), which the fixer would
     * de-indent and detach from the construct it annotates.
     */
    private function closesOnOwnLine(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $scopeCloser = $tokens[$stackPtr]['scope_closer'];

        if ($phpcsFile->findFirstOnLine(T_WHITESPACE, $scopeCloser, true) !== $scopeCloser) {
            return false;
        }

        $nextAfterCloser = $phpcsFile->findNext(T_WHITESPACE, ($scopeCloser + 1), null, true);

        return $nextAfterCloser === false
            || $tokens[$nextAfterCloser]['line'] !== $tokens[$scopeCloser]['line'];
    }

    private function containsOnlyWhitespace(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = $start; $ptr < $end; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_WHITESPACE) {
                return false;
            }
        }

        return true;
    }

    private function hasCurlyScope(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return false;
        }

        return $tokens[$tokens[$stackPtr]['scope_opener']]['code'] === T_OPEN_CURLY_BRACKET;
    }

    /**
     * Whether *every* branch before the `else`/`elseif` at $stackPtr ends in a
     * statement that unconditionally leaves it.
     *
     * Checking only the immediately preceding branch is not enough. In
     * `if ($a) { $r = 1; } elseif ($b) { return 2; } else { $r = 3; }` the
     * branch before the `else` does terminate, but unwrapping the `else`
     * would let the `$a` branch fall through into `$r = 3`. So the walk runs
     * the whole chain back to its head `if`, and any branch that is braceless,
     * empty, or ends in something other than a terminating statement stops it.
     */
    private function precedingBranchesTerminate(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $stackPtr;

        while (true) {
            $closer = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if ($closer === false || $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET) {
                return false;
            }

            if ($this->branchTerminates($phpcsFile, $closer) === false) {
                return false;
            }

            if (isset($tokens[$closer]['scope_condition']) === false) {
                return false;
            }

            $condition = $tokens[$closer]['scope_condition'];

            if ($tokens[$condition]['code'] === T_ELSEIF) {
                $ptr = $condition;

                continue;
            }

            if ($tokens[$condition]['code'] !== T_IF) {
                return false;
            }

            // A chain head, unless this `if` is the second word of an
            // `else if` — in which case the walk carries on from the `else`.
            $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($condition - 1), null, true);

            if ($before === false || $tokens[$before]['code'] !== T_ELSE) {
                return true;
            }

            $ptr = $before;
        }
    }

    /**
     * Whether the braced branch closed by $closer ends in a terminating
     * statement. Anything unclear — an empty body, a nested construct as the
     * last statement — returns false, and the construct is reported without
     * a fixer rather than rewritten on a guess.
     */
    private function branchTerminates(File $phpcsFile, int $closer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $lastSemicolon = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($closer - 1), null, true);

        if ($lastSemicolon === false || $tokens[$lastSemicolon]['code'] !== T_SEMICOLON) {
            return false;
        }

        $lastExpressionToken = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($lastSemicolon - 1), null, true);

        if ($lastExpressionToken === false) {
            return false;
        }

        $statementStart = $phpcsFile->findStartOfStatement($lastExpressionToken);

        return in_array($tokens[$statementStart]['code'], self::TERMINATING_STATEMENTS, true);
    }

    /**
     * Rewrites `} elseif (…) {` as `}\n<indent>if (…) {` (and `} else if (…)`
     * as `}\n<indent>if (…)`), aligned with the closing brace of the previous
     * branch. The body is not touched: it keeps its own indentation and its
     * own braces, so nothing has to move.
     */
    private function fixElseIf(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $indent = str_repeat(' ', ($tokens[$previousCloser]['column'] - 1));

        $phpcsFile->fixer->beginChangeset();

        for ($ptr = ($previousCloser + 1); $ptr < $stackPtr; $ptr++) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }

        $phpcsFile->fixer->addContent($previousCloser, "\n" . $indent);

        if ($tokens[$stackPtr]['code'] === T_ELSEIF) {
            $phpcsFile->fixer->replaceToken($stackPtr, 'if');
        }

        if ($tokens[$stackPtr]['code'] === T_ELSE) {
            $if = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

            for ($ptr = $stackPtr; $ptr < $if; $ptr++) {
                $phpcsFile->fixer->replaceToken($ptr, '');
            }
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Removes the `else` wrapper entirely: ` else {` and the closing `}` are
     * deleted and every body line is dedented one level (four spaces).
     * PHPCS splits whitespace tokens at newlines, so a line's indent is a
     * standalone column-1 T_WHITESPACE token. That is what keeps multi-line
     * string content out of the dedent, and it is load-bearing rather than
     * incidental: a heredoc's body lines and its closing marker each carry
     * their own leading spaces *inside* a T_HEREDOC/T_END_HEREDOC token, and a
     * multi-line double-quoted string's continuation lines are string tokens
     * too, so none of them is whitespace at column 1 and none is rewritten.
     * Both would change the string's value if they were.
     * tests/fixtures/DisallowElseSniff/failing.php pins this with a heredoc
     * and a multi-line string inside fixable else bodies.
     */
    private function fixElse(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousCloser = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $scopeOpener = $tokens[$stackPtr]['scope_opener'];
        $scopeCloser = $tokens[$stackPtr]['scope_closer'];

        $phpcsFile->fixer->beginChangeset();

        for ($ptr = ($previousCloser + 1); $ptr <= $scopeOpener; $ptr++) {
            $phpcsFile->fixer->replaceToken($ptr, '');
        }

        for ($ptr = ($scopeOpener + 1); $ptr < $scopeCloser; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_WHITESPACE || $tokens[$ptr]['column'] !== 1) {
                continue;
            }

            if ($tokens[$ptr]['line'] === $tokens[$scopeCloser]['line']) {
                $phpcsFile->fixer->replaceToken($ptr, '');

                continue;
            }

            $phpcsFile->fixer->replaceToken(
                $ptr,
                (string) preg_replace('/^    /', '', $tokens[$ptr]['content'])
            );
        }

        $phpcsFile->fixer->replaceToken($scopeCloser, '');

        $afterCloser = ($scopeCloser + 1);

        if (
            isset($tokens[$afterCloser]) === true
            && $tokens[$afterCloser]['code'] === T_WHITESPACE
            && $tokens[$afterCloser]['line'] === $tokens[$scopeCloser]['line']
        ) {
            $phpcsFile->fixer->replaceToken($afterCloser, '');
        }

        $phpcsFile->fixer->endChangeset();
    }
}

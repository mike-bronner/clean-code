<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NPathComplexitySniff implements Sniff
{
    public $minimum = 200;

    private const BOOLEAN_TOKENS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
    ];

    private const EXPRESSION_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
        T_COMMA,
        T_DOUBLE_ARROW,
        T_INLINE_THEN,
        T_INLINE_ELSE,
        T_RETURN,
        T_ECHO,
        T_PRINT,
        T_CASE,
        T_OPEN_TAG,
    ];

    private const BRANCH_TERMINATORS = [
        T_SEMICOLON,
        T_COMMA,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    private const CEILING = PHP_INT_MAX;

    private array $branchEnds = [];

    private array $scanCounts = [
        'expressionEnd.scans' => 0,
        'expressionEnd.hits' => 0,
    ];

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function scanCounts(): array
    {
        return $this->scanCounts;
    }

    private function add(int $left, int $right): int
    {
        if ($left > (self::CEILING - $right)) {
            return self::CEILING;
        }

        return ($left + $right);
    }

    private function multiply(int $left, int $right): int
    {
        if (
            $left === 0
            || $right === 0
        ) {
            return 0;
        }

        if ($left > intdiv(self::CEILING, $right)) {
            return self::CEILING;
        }

        return ($left * $right);
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $minimum = (int) $this->minimum;
        $npath = $this->callableComplexity($phpcsFile, $stackPtr);

        if (
            $npath === null
            || $npath < $minimum
        ) {
            return;
        }

        // A measurement that hit the ceiling is a lower bound, not the exact
        // count, and says so rather than reporting the ceiling as if it were
        // the real value.
        $measured = $npath === self::CEILING ? ('at least ' . self::CEILING) : (string) $npath;

        $phpcsFile->addError(
            'The %s %s() has an NPath complexity of %s, at or above the '
                . 'configured minimum of %s; break it into smaller pieces (see '
                . 'docs/phpmd/codesize-npathcomplexity.md)',
            $stackPtr,
            'MinimumExceeded',
            [
                $this->callableKind($phpcsFile, $stackPtr),
                (string) $phpcsFile->getDeclarationName($stackPtr),
                $measured,
                $minimum,
            ]
        );
    }

    private function callableKind(File $phpcsFile, int $functionPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $code) {
            if (in_array($code, [T_CLASS, T_ANON_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) === true) {
                return 'method';
            }
        }

        return 'function';
    }

    private function callableComplexity(File $phpcsFile, int $functionPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'] ?? [];

        if (in_array(T_ANON_CLASS, $conditions, true) === true) {
            return null;
        }

        if (in_array(T_INTERFACE, $conditions, true) === true) {
            return null;
        }

        $opener = $tokens[$functionPtr]['scope_opener'] ?? null;
        $closer = $tokens[$functionPtr]['scope_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return 1;
        }

        // The same PHPCS 3.13.6 defect switchBody() covers also truncates the
        // scope of the callable *around* an alternative-syntax `switch` whose
        // subject holds a `match`: `scope_closer` lands on the `endswitch`
        // rather than on the body's own brace, hiding every statement after it.
        // The brace carries the true end in `bracket_closer` whether or not a
        // scope was attached, so it is preferred where it is available.
        $closer = $tokens[$opener]['bracket_closer'] ?? $closer;

        // Token offsets are per file, so the memo from the previous callable
        // must not be read against this one.
        $this->branchEnds = [];
        $ptr = ($opener + 1);

        return $this->blockComplexity($phpcsFile, $tokens, $ptr, $closer);
    }

    private function blockComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $npath = 1;

        while ($ptr < $end) {
            $npath = $this->multiply(
                $npath,
                $this->statementComplexity($phpcsFile, $tokens, $ptr, $end)
            );
        }

        return $npath;
    }

    private function statementComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $code = $tokens[$ptr]['code'];

        // A named function declared inside another callable is its own PHPMD
        // artifact, reported separately by this sniff's own registration on it.
        // An anonymous class body belongs to the anonymous class. A closure or
        // arrow function is neither: it is walked as part of this callable.
        if (
            $code === T_FUNCTION
            || $this->opensAnonymousClassBody($tokens, $ptr) === true
        ) {
            $ptr = (($tokens[$ptr]['scope_closer'] ?? $ptr) + 1);

            return 1;
        }

        if ($code === T_IF) {
            return $this->ifComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_DO) {
            return $this->doWhileComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if (
            $code === T_WHILE
            || $code === T_FOR
            || $code === T_FOREACH
        ) {
            return $this->loopComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_SWITCH) {
            return $this->switchComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_TRY) {
            return $this->tryComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_RETURN) {
            return $this->returnComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        if ($code === T_INLINE_THEN) {
            return $this->ternaryComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        $ptr++;

        return 1;
    }

    private function opensAnonymousClassBody(array $tokens, int $ptr): bool
    {
        if ($tokens[$ptr]['code'] !== T_OPEN_CURLY_BRACKET) {
            return false;
        }

        $owner = $tokens[$ptr]['scope_condition'] ?? null;

        return $owner !== null && $tokens[$owner]['code'] === T_ANON_CLASS;
    }

    private function ifComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $npath = $this->conditionComplexity($phpcsFile, $tokens, $ptr);
        $npath = $this->add($npath, $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end));

        $chain = $this->nextChainLink($phpcsFile, $tokens, $ptr, $end);

        if ($chain === null) {
            return $this->add($npath, 1);
        }

        $ptr = $chain;

        if ($tokens[$chain]['code'] === T_ELSEIF) {
            return $this->add($npath, $this->ifComplexity($phpcsFile, $tokens, $ptr, $end));
        }

        // `else if` written with a space is one construct to PDepend, scored
        // exactly as `elseif`. PHPCS builds no scope for the `else` in that
        // shape, so measuring it as an ordinary `else` body would leave the
        // `if` to be counted again as a statement of its own and multiply the
        // two instead of adding them (ElseIfWithSpace in failing.php).
        $inner = $phpcsFile->findNext(Tokens::$emptyTokens, ($chain + 1), $end, true);

        if (
            $inner !== false
            && $tokens[$inner]['code'] === T_IF
        ) {
            $ptr = $inner;

            return $this->add($npath, $this->ifComplexity($phpcsFile, $tokens, $ptr, $end));
        }

        return $this->add($npath, $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end));
    }

    private function nextChainLink(File $phpcsFile, array $tokens, int $ptr, int $end): ?int
    {
        $code = $tokens[$ptr]['code'];

        if (
            $code === T_ELSEIF
            || $code === T_ELSE
        ) {
            return $ptr;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $end, true);

        if ($next === false) {
            return null;
        }

        if (
            $tokens[$next]['code'] === T_ELSEIF
            || $tokens[$next]['code'] === T_ELSE
        ) {
            return $next;
        }

        return null;
    }

    private function loopComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $condition = $tokens[$ptr]['code'] === T_FOR
            ? $this->forConditionComplexity($phpcsFile, $tokens, $ptr)
            : $this->conditionComplexity($phpcsFile, $tokens, $ptr);
        $body = $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);

        return $this->add($this->add($condition, $body), 1);
    }

    private function forConditionComplexity(File $phpcsFile, array $tokens, int $ptr): int
    {
        $opener = $tokens[$ptr]['parenthesis_opener'] ?? null;
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return 0;
        }

        $semicolons = [];
        $cursor = $opener;

        while (++$cursor < $closer) {
            $skip = $this->closerFor($tokens, $cursor);

            if ($skip !== null) {
                $cursor = $skip;

                continue;
            }

            if ($tokens[$cursor]['code'] === T_SEMICOLON) {
                $semicolons[] = $cursor;
            }
        }

        if (count($semicolons) < 2) {
            return 0;
        }

        $conditionPtr = ($semicolons[0] + 1);

        return $this->expressionComplexity($phpcsFile, $tokens, $conditionPtr, $semicolons[1]);
    }

    private function doWhileComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $body = $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);
        $while = $phpcsFile->findNext(T_WHILE, $ptr, $end);

        if ($while === false) {
            return $this->add($body, 1);
        }

        $condition = $this->conditionComplexity($phpcsFile, $tokens, $while);
        $closer = $tokens[$while]['parenthesis_closer'] ?? $while;
        $ptr = ($closer + 1);

        return $this->add($this->add($condition, $body), 1);
    }

    private function switchComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $switchPtr = $ptr;
        $npath = $this->conditionComplexity($phpcsFile, $tokens, $switchPtr);
        $body = $this->switchBody($phpcsFile, $tokens, $switchPtr, $end);

        if ($body === null) {
            $ptr++;

            return $npath;
        }

        [$opener, $closer] = $body;
        $labels = $this->switchLabels($phpcsFile, $tokens, $opener, $closer);

        foreach ($labels as $index => $label) {
            $bodyEnd = $labels[($index + 1)] ?? $closer;
            $bodyPtr = ($this->labelBodyStart($phpcsFile, $tokens, $label, $bodyEnd) + 1);
            $npath = $this->add(
                $npath,
                $this->blockComplexity($phpcsFile, $tokens, $bodyPtr, $bodyEnd)
            );
        }

        $ptr = ($closer + 1);

        return $npath;
    }

    private function switchBody(File $phpcsFile, array $tokens, int $switchPtr, int $end): ?array
    {
        $opener = $tokens[$switchPtr]['scope_opener'] ?? null;
        $closer = $tokens[$switchPtr]['scope_closer'] ?? null;

        if (
            $opener !== null
            && $closer !== null
        ) {
            return [$opener, $closer];
        }

        $subjectEnd = $tokens[$switchPtr]['parenthesis_closer'] ?? null;

        if ($subjectEnd === null) {
            return null;
        }

        $opener = $phpcsFile->findNext(Tokens::$emptyTokens, ($subjectEnd + 1), $end, true);

        if ($opener === false) {
            return null;
        }

        $closer = $this->switchCloser($phpcsFile, $tokens, $opener, $end);

        return $closer === null ? null : [$opener, $closer];
    }

    // A switch body closes with `}` in brace form and with `endswitch` in the
    // alternative form. Anything else is neither, and answers null.
    private function switchCloser(File $phpcsFile, array $tokens, int $opener, int $end): ?int
    {
        $code = $tokens[$opener]['code'];

        if ($code === T_OPEN_CURLY_BRACKET) {
            return $tokens[$opener]['bracket_closer'] ?? null;
        }

        if ($code === T_COLON) {
            return $this->nextAtLevel($phpcsFile, $tokens, [T_ENDSWITCH], ($opener + 1), $end);
        }

        return null;
    }

    private function switchLabels(File $phpcsFile, array $tokens, int $opener, int $closer): array
    {
        $labels = [];
        $ptr = ($opener + 1);

        while (($ptr = $this->nextAtLevel($phpcsFile, $tokens, [T_CASE, T_DEFAULT], $ptr, $closer)) !== null) {
            $labels[] = $ptr;
            $ptr++;
        }

        return $labels;
    }

    private function nextAtLevel(File $phpcsFile, array $tokens, array $codes, int $ptr, int $end): ?int
    {
        while ($ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if (in_array($code, $codes, true) === true) {
                return $ptr;
            }

            // Two independent jumps, never both: $code is read once above, so a
            // token is either the brace or the switch, never the other's case.
            if (
                $code === T_OPEN_CURLY_BRACKET
                && isset($tokens[$ptr]['bracket_closer']) === true
            ) {
                $ptr = $tokens[$ptr]['bracket_closer'];
            }

            if ($code === T_SWITCH) {
                $body = $this->switchBody($phpcsFile, $tokens, $ptr, $end);
                $ptr = ($body === null ? $ptr : $body[1]);
            }

            $ptr++;
        }

        return null;
    }

    private function labelBodyStart(File $phpcsFile, array $tokens, int $label, int $end): int
    {
        $colon = $phpcsFile->findNext([T_COLON, T_SEMICOLON], $label, $end);

        return $colon === false ? $label : $colon;
    }

    private function tryComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $npath = $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end);

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $end, true);

            if ($next === false) {
                return $npath;
            }

            $code = $tokens[$next]['code'];

            if (
                $code !== T_CATCH
                && $code !== T_FINALLY
            ) {
                return $npath;
            }

            $ptr = $next;
            $npath = $this->add($npath, $this->scopeComplexity($phpcsFile, $tokens, $ptr, $end));
        }
    }

    private function returnComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $stop = $this->statementEnd($tokens, ($ptr + 1), $end);
        $exprPtr = ($ptr + 1);
        $complexity = $this->expressionComplexity($phpcsFile, $tokens, $exprPtr, $stop);
        $ptr = ($stop + 1);

        return $complexity === 0 ? 1 : $complexity;
    }

    private function statementEnd(array $tokens, int $from, int $end): int
    {
        $ptr = ($from - 1);

        while (++$ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_SEMICOLON) {
                return $ptr;
            }

            $skip = $this->closerFor($tokens, $ptr);

            if ($skip !== null) {
                $ptr = $skip;
            }
        }

        return $end;
    }

    private function closerFor(array $tokens, int $ptr): ?int
    {
        $code = $tokens[$ptr]['code'];

        if ($code === T_OPEN_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_closer'] ?? null;
        }

        if (
            $code === T_OPEN_SHORT_ARRAY
            || $code === T_OPEN_SQUARE_BRACKET
        ) {
            return $tokens[$ptr]['bracket_closer'] ?? null;
        }

        if ($code === T_OPEN_CURLY_BRACKET) {
            return $tokens[$ptr]['scope_closer'] ?? ($tokens[$ptr]['bracket_closer'] ?? null);
        }

        return null;
    }

    private function ternaryComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $thenPtr = $ptr;
        $start = $this->expressionStart($tokens, $thenPtr);
        $condPtr = $start;
        $condition = $this->expressionComplexity(
            $phpcsFile,
            $tokens,
            $condPtr,
            $this->conditionNodeEnd($tokens, $start, $thenPtr)
        );

        $elsePtr = $this->ternaryElse($phpcsFile, $tokens, $thenPtr, $end);

        if ($elsePtr === null) {
            $ptr = ($thenPtr + 1);

            return $this->add($condition, 2);
        }

        $branchPtr = ($thenPtr + 1);
        $then = $this->expressionComplexity($phpcsFile, $tokens, $branchPtr, $elsePtr);
        $isShort = $phpcsFile->findNext(Tokens::$emptyTokens, ($thenPtr + 1), $end, true) === $elsePtr;

        $stop = $this->expressionEnd($phpcsFile, $tokens, $elsePtr, $end);
        $otherwisePtr = ($elsePtr + 1);
        $otherwise = $this->expressionComplexity($phpcsFile, $tokens, $otherwisePtr, $stop);
        $ptr = $stop;

        if ($isShort === true) {
            return $this->add($this->add($this->multiply($condition, 2), $otherwise), 2);
        }

        return $this->add($this->add($this->add($condition, $then), $otherwise), 2);
    }

    private function conditionNodeEnd(array $tokens, int $start, int $thenPtr): int
    {
        $ptr = ($start - 1);

        while (++$ptr < $thenPtr) {
            $skip = $this->closerFor($tokens, $ptr);

            if ($skip !== null) {
                $ptr = $skip;

                continue;
            }

            if (
                $ptr > $start
                && $this->separatesNodes($tokens[$ptr]['code']) === true
            ) {
                return $ptr;
            }
        }

        return $thenPtr;
    }

    private function separatesNodes(int|string $code): bool
    {
        return isset(Tokens::$operators[$code]) === true
            || isset(Tokens::$comparisonTokens[$code]) === true
            || isset(Tokens::$booleanOperators[$code]) === true
            || $code === T_STRING_CONCAT
            || $code === T_INSTANCEOF;
    }

    private function ternaryElse(File $phpcsFile, array $tokens, int $thenPtr, int $end): ?int
    {
        $depth = 0;
        $ptr = $thenPtr;

        while (++$ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if (
                isset($tokens[$ptr]['parenthesis_closer']) === true
                && $code === T_OPEN_PARENTHESIS
            ) {
                $ptr = $tokens[$ptr]['parenthesis_closer'];

                continue;
            }

            if (isset($tokens[$ptr]['bracket_closer']) === true) {
                $ptr = $tokens[$ptr]['bracket_closer'];

                continue;
            }

            if (
                isset($tokens[$ptr]['scope_closer']) === true
                && $code === T_MATCH
            ) {
                $ptr = $tokens[$ptr]['scope_closer'];

                continue;
            }

            if ($code === T_INLINE_THEN) {
                $depth++;

                continue;
            }

            if ($code === T_INLINE_ELSE) {
                if ($depth === 0) {
                    return $ptr;
                }

                $depth--;

                continue;
            }

            if (
                $code === T_SEMICOLON
                || $code === T_OPEN_CURLY_BRACKET
            ) {
                return null;
            }
        }

        return null;
    }

    private function expressionStart(array $tokens, int $thenPtr): int
    {
        $ptr = $thenPtr;

        while (--$ptr > 0) {
            $code = $tokens[$ptr]['code'];

            if (
                isset($tokens[$ptr]['parenthesis_opener']) === true
                && $code === T_CLOSE_PARENTHESIS
            ) {
                $ptr = $tokens[$ptr]['parenthesis_opener'];

                continue;
            }

            if (isset($tokens[$ptr]['bracket_opener']) === true) {
                $ptr = $tokens[$ptr]['bracket_opener'];

                continue;
            }

            if (in_array($code, self::EXPRESSION_BOUNDARIES, true) === true) {
                return ($ptr + 1);
            }

            if (isset(Tokens::$assignmentTokens[$code]) === true) {
                return ($ptr + 1);
            }
        }

        return ($thenPtr - 1);
    }

    private function expressionEnd(File $phpcsFile, array $tokens, int $elsePtr, int $end): int
    {
        if (isset($this->branchEnds[$elsePtr]) === true) {
            $this->scanCounts['expressionEnd.hits']++;

            // A terminator at or past the caller's limit is out of its reach,
            // and the scan below would have run out at $end instead.
            return min($this->branchEnds[$elsePtr], $end);
        }

        $this->scanCounts['expressionEnd.scans']++;
        $ptr = $elsePtr;
        $stepped = [];

        while (++$ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if (
                isset($tokens[$ptr]['parenthesis_closer']) === true
                && $code === T_OPEN_PARENTHESIS
            ) {
                $ptr = $tokens[$ptr]['parenthesis_closer'];

                continue;
            }

            if (isset($tokens[$ptr]['bracket_closer']) === true) {
                $ptr = $tokens[$ptr]['bracket_closer'];

                continue;
            }

            if (in_array($code, self::BRANCH_TERMINATORS, true) === true) {
                foreach ($stepped as $position) {
                    $this->branchEnds[$position] = $ptr;
                }

                $this->branchEnds[$elsePtr] = $ptr;

                return $ptr;
            }

            $stepped[] = $ptr;
        }

        // Nothing is recorded when the scan runs out at $end: that answer is the
        // caller's limit rather than a terminator, and says nothing about where
        // a scan with a later limit would stop.
        return $end;
    }

    private function conditionComplexity(File $phpcsFile, array $tokens, int $ptr): int
    {
        $opener = $tokens[$ptr]['parenthesis_opener'] ?? null;
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return 0;
        }

        $cursor = ($opener + 1);

        return $this->expressionComplexity($phpcsFile, $tokens, $cursor, $closer);
    }

    private function expressionComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $sum = 0;

        while ($ptr < $end) {
            $code = $tokens[$ptr]['code'];

            if (in_array($code, self::BOOLEAN_TOKENS, true) === true) {
                $sum = $this->add($sum, 1);
                $ptr++;

                continue;
            }

            if ($code === T_INLINE_THEN) {
                $sum = $this->add(
                    $sum,
                    $this->ternaryComplexity($phpcsFile, $tokens, $ptr, $end)
                );

                continue;
            }

            $ptr++;
        }

        return $sum;
    }

    private function scopeComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $opener = $tokens[$ptr]['scope_opener'] ?? null;
        $closer = $tokens[$ptr]['scope_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return $this->bracelessBodyComplexity($phpcsFile, $tokens, $ptr, $end);
        }

        $bodyPtr = ($opener + 1);
        $bodyEnd = min($closer, $end);
        $npath = $this->blockComplexity($phpcsFile, $tokens, $bodyPtr, $bodyEnd);
        $ptr = $closer;

        return $npath;
    }

    private function bracelessBodyComplexity(File $phpcsFile, array $tokens, int &$ptr, int $end): int
    {
        $closer = $tokens[$ptr]['parenthesis_closer'] ?? $ptr;
        $body = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), $end, true);

        if ($body === false) {
            $ptr++;

            return 1;
        }

        $bodyPtr = $body;
        $npath = $this->statementComplexity($phpcsFile, $tokens, $bodyPtr, $end);

        if ($bodyPtr > ($body + 1)) {
            $ptr = $bodyPtr;

            return $npath;
        }

        $stop = $this->statementEnd($tokens, $body, $end);
        $npath = $this->multiply(
            $npath,
            $this->blockComplexity($phpcsFile, $tokens, $bodyPtr, $stop)
        );
        $ptr = $stop;

        return $npath;
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces one level of indentation for lines that continue a multi-line
 * statement.
 *
 * If a single statement extends over multiple lines, every line after the
 * first must be indented exactly one level relative to the line that opened
 * its innermost enclosing construct:
 *
 * - a continuation line inside parentheses or brackets sits one level in
 *   from the line containing the opener;
 * - a continuation line that starts with an operator (`->`, `.`, `?`, `&&`,
 *   …) sits one level in from the line where its expression started;
 * - a closing bracket on its own line matches the indent of the line that
 *   opened the bracket.
 *
 * Bodies of closures, anonymous classes, and match expressions are scope
 * blocks governed by scope-indent rules, so their inner lines are skipped.
 */
class MultiLineStatementIndentSniff implements Sniff
{
    /**
     * Bracket openers that group continuation lines inside a statement.
     */
    private const BRACKET_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
    ];

    /**
     * Curly-brace scopes that appear inside expressions; their bodies are
     * governed by scope-indent rules, not by this sniff.
     */
    private const EXPRESSION_SCOPES = [
        T_CLOSURE,
        T_ANON_CLASS,
        T_MATCH,
    ];

    /**
     * Tokens whose lines carry raw content (heredoc/nowdoc bodies, inline
     * HTML) where indentation is data, not code style.
     */
    private const RAW_CONTENT = [
        T_HEREDOC,
        T_NOWDOC,
        T_END_HEREDOC,
        T_END_NOWDOC,
        T_ENCAPSED_AND_WHITESPACE,
        T_INLINE_HTML,
    ];

    /**
     * Number of spaces per indentation level.
     */
    public int $indent = 4;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    /**
     * @param int $stackPtr
     *
     * @return int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $i = $stackPtr;

        while ($i < $phpcsFile->numTokens) {
            $start = $this->findStatementStart($phpcsFile, $i);

            if ($start === null) {
                break;
            }

            $end = $this->findStatementEnd($phpcsFile, $start);

            if ($tokens[$end]['line'] > $tokens[$start]['line']) {
                $this->checkStatement($phpcsFile, $start, $end);
            }

            $i = $end + 1;
        }

        return $phpcsFile->numTokens;
    }

    /**
     * Finds the first token at or after $ptr that can start a statement.
     */
    private function findStatementStart(File $phpcsFile, int $ptr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $skip = Tokens::$emptyTokens + [
            T_OPEN_TAG => T_OPEN_TAG,
            T_CLOSE_TAG => T_CLOSE_TAG,
            T_INLINE_HTML => T_INLINE_HTML,
            T_SEMICOLON => T_SEMICOLON,
            T_OPEN_CURLY_BRACKET => T_OPEN_CURLY_BRACKET,
            T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET,
        ];

        for ($i = $ptr; $i < $phpcsFile->numTokens; $i++) {
            if (isset($skip[$tokens[$i]['code']]) === false) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Finds the token that ends the statement starting at $start: the
     * closing semicolon, or the scope opener of a control structure or
     * declaration header. Bracket pairs and expression scopes are jumped
     * wholesale so their contents never terminate the statement early.
     */
    private function findStatementEnd(File $phpcsFile, int $start): int
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $start; $i < $phpcsFile->numTokens; $i++) {
            $token = $tokens[$i];
            $code = $token['code'];

            if ($code === T_SEMICOLON || $code === T_CLOSE_TAG) {
                return $i;
            }

            if ($code === T_OPEN_PARENTHESIS && isset($token['parenthesis_closer']) === true) {
                $i = $token['parenthesis_closer'];
                continue;
            }

            $isBracket = $code === T_OPEN_SQUARE_BRACKET || $code === T_OPEN_SHORT_ARRAY;

            if ($isBracket === true && isset($token['bracket_closer']) === true) {
                $i = $token['bracket_closer'];
                continue;
            }

            if (in_array($code, self::EXPRESSION_SCOPES, true) === true && isset($token['scope_closer']) === true) {
                $i = $token['scope_closer'];
                continue;
            }

            if (isset($token['scope_opener']) === true && $code !== T_FN) {
                return $token['scope_opener'];
            }
        }

        return $phpcsFile->numTokens - 1;
    }

    /**
     * Walks a multi-line statement and checks the indent of every line that
     * continues it.
     */
    private function checkStatement(File $phpcsFile, int $start, int $end): void
    {
        $tokens = $phpcsFile->getTokens();
        $baseIndent = $this->lineIndent($phpcsFile, $start);
        $continuation = $this->continuationTokens();
        $exprStart = $start;
        $stack = [];
        $line = $tokens[$start]['line'];

        for ($i = $start; $i <= $end; $i++) {
            $token = $tokens[$i];
            $code = $token['code'];

            if ($code === T_WHITESPACE) {
                continue;
            }

            $isLineFirst = $token['line'] > $line;
            $line = $token['line'] + substr_count($token['content'], "\n");

            if (isset(Tokens::$emptyTokens[$code]) === true || in_array($code, self::RAW_CONTENT, true) === true) {
                continue;
            }

            $isStatementScopeOpener = $i === $end && ($code === T_OPEN_CURLY_BRACKET || $code === T_COLON);

            if ($isLineFirst === true && $isStatementScopeOpener === false) {
                $this->checkLine($phpcsFile, $i, $stack, $exprStart, $baseIndent, $continuation);
            }

            // Any code token can begin the expression current in its scope.
            if ($stack !== []) {
                if ($stack[count($stack) - 1]['exprStart'] === null) {
                    $stack[count($stack) - 1]['exprStart'] = $i;
                }
            } elseif ($exprStart === null) {
                $exprStart = $i;
            }

            if (in_array($code, self::BRACKET_OPENERS, true) === true) {
                $stack[] = ['opener' => $i, 'exprStart' => null];
                continue;
            }

            $opener = $token['parenthesis_opener'] ?? $token['bracket_opener'] ?? null;

            if ($opener !== null && $stack !== [] && $stack[count($stack) - 1]['opener'] === $opener) {
                array_pop($stack);
                continue;
            }

            if ($code === T_COMMA) {
                if ($stack !== []) {
                    $stack[count($stack) - 1]['exprStart'] = null;
                } else {
                    $exprStart = null;
                }

                continue;
            }

            if (in_array($code, self::EXPRESSION_SCOPES, true) === true && isset($token['scope_closer']) === true) {
                $i = $token['scope_closer'] - 1;
            }
        }
    }

    /**
     * Checks (and fixes) the indent of the line whose first token is $ptr.
     *
     * @param array<int, array{opener: int, exprStart: int|null}> $stack
     * @param array<int|string, int|string> $continuation
     */
    private function checkLine(
        File $phpcsFile,
        int $ptr,
        array $stack,
        ?int $exprStart,
        int $baseIndent,
        array $continuation
    ): void {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$ptr];
        $actual = $token['column'] - 1;
        $closedOpener = $this->closedOpener($token);

        if ($closedOpener !== null) {
            $expected = $this->lineIndent($phpcsFile, $closedOpener);
            $errorCode = 'CloseBracketIndent';
            $error = 'Closing bracket of a multi-line statement not indented correctly;'
                . ' expected %s spaces but found %s';
        } else {
            if (isset($continuation[$token['code']]) === true) {
                $anchor = $stack === [] ? $exprStart : $stack[count($stack) - 1]['exprStart'];
                $anchor ??= $stack === [] ? null : $stack[count($stack) - 1]['opener'];
            } else {
                $anchor = $stack === [] ? null : $stack[count($stack) - 1]['opener'];
            }

            $anchorIndent = $anchor === null ? $baseIndent : $this->lineIndent($phpcsFile, $anchor);
            $expected = $anchorIndent + $this->indent;
            $errorCode = 'IncorrectIndent';
            $error = 'Line in multi-line statement not indented correctly;'
                . ' expected %s spaces but found %s';
        }

        if ($actual === $expected) {
            return;
        }

        $fix = $phpcsFile->addFixableError($error, $ptr, $errorCode, [$expected, $actual]);

        if ($fix === false) {
            return;
        }

        $padding = str_repeat(' ', $expected);

        if ($token['column'] === 1) {
            $phpcsFile->fixer->addContentBefore($ptr, $padding);
        } else {
            $phpcsFile->fixer->replaceToken($ptr - 1, $padding);
        }
    }

    /**
     * The opener whose bracket/scope the token closes, or null when the
     * token is not a closer.
     *
     * @param array<string, mixed> $token
     */
    private function closedOpener(array $token): ?int
    {
        if ($token['code'] === T_CLOSE_PARENTHESIS) {
            return $token['parenthesis_opener'] ?? null;
        }

        if ($token['code'] === T_CLOSE_SQUARE_BRACKET || $token['code'] === T_CLOSE_SHORT_ARRAY) {
            return $token['bracket_opener'] ?? null;
        }

        if ($token['code'] === T_CLOSE_CURLY_BRACKET) {
            return $token['scope_opener'] ?? null;
        }

        return null;
    }

    /**
     * The indent (in spaces) of the line the token at $ptr sits on.
     */
    private function lineIndent(File $phpcsFile, int $ptr): int
    {
        $tokens = $phpcsFile->getTokens();
        $first = $ptr;

        while ($first > 0 && $tokens[$first - 1]['line'] === $tokens[$ptr]['line']) {
            $first--;
        }

        if ($tokens[$first]['code'] === T_WHITESPACE) {
            $first++;
        }

        return $tokens[$first]['column'] - 1;
    }

    /**
     * Operator tokens that mark a line as continuing the expression begun
     * on an earlier line.
     *
     * @return array<int|string, int|string>
     */
    private function continuationTokens(): array
    {
        return Tokens::$operators
            + Tokens::$comparisonTokens
            + Tokens::$booleanOperators
            + Tokens::$assignmentTokens
            + [
                T_STRING_CONCAT => T_STRING_CONCAT,
                T_OBJECT_OPERATOR => T_OBJECT_OPERATOR,
                T_NULLSAFE_OBJECT_OPERATOR => T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON => T_DOUBLE_COLON,
                T_INLINE_THEN => T_INLINE_THEN,
                T_INLINE_ELSE => T_INLINE_ELSE,
                T_COALESCE => T_COALESCE,
                T_INSTANCEOF => T_INSTANCEOF,
            ];
    }
}

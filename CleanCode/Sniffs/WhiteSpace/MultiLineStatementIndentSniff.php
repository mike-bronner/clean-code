<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class MultiLineStatementIndentSniff implements Sniff
{
    private const BRACKET_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_ATTRIBUTE,
        T_OPEN_USE_GROUP,
    ];

    private const UNLINKED_PAIRS = [
        T_CLOSE_USE_GROUP => T_OPEN_USE_GROUP,
    ];

    private const CHAIN_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    private const SIBLING_OPERATORS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
    ];

    public const EXPRESSION_SCOPES = [
        T_CLOSURE,
        T_ANON_CLASS,
        T_MATCH,
    ];

    // Every scope opener PHPCS defines that is deliberately not an expression
    // scope. Together with EXPRESSION_SCOPES this must cover the whole set, so
    // a scope opener a later PHPCS adds fails the suite instead of being
    // silently ignored. T_OBJECT and T_PROPERTY are emitted only by the
    // JavaScript tokenizer, which this standard never runs.
    public const NON_EXPRESSION_SCOPES = [
        T_FN,
        T_FUNCTION,
        T_CLASS,
        T_TRAIT,
        T_INTERFACE,
        T_ENUM,
        T_NAMESPACE,
        T_USE,
        T_IF,
        T_ELSEIF,
        T_ELSE,
        T_DO,
        T_WHILE,
        T_FOR,
        T_FOREACH,
        T_SWITCH,
        T_TRY,
        T_CATCH,
        T_FINALLY,
        T_DECLARE,
        T_CASE,
        T_DEFAULT,
        T_OBJECT,
        T_PROPERTY,
    ];

    private const RAW_CONTENT = [
        T_HEREDOC,
        T_NOWDOC,
        T_END_HEREDOC,
        T_END_NOWDOC,
        T_ENCAPSED_AND_WHITESPACE,
        T_INLINE_HTML,
    ];

    private const STRING_LITERALS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    private const TRAILING_OPERATORS = [
        T_DOUBLE_ARROW,
        T_FN_ARROW,
    ];

    public int $indent = 4;

    private array $commentOpeners = [];

    private array $lineStarts = [];

    private array $scanCounts = [
        'commentStaysOpen.evaluations' => 0,
        'lineFirstToken.readings' => 0,
        'lineFirstToken.commentHops' => 0,
        'lineStart.steps' => 0,
    ];

    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    public function scanCounts(): array
    {
        return $this->scanCounts;
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $this->mapLines($phpcsFile);
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

    private function findStatementEnd(File $phpcsFile, int $start): int
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $start; $i < $phpcsFile->numTokens; $i++) {
            $token = $tokens[$i];
            $code = $token['code'];

            if (
                $code === T_SEMICOLON
                || $code === T_CLOSE_TAG
            ) {
                return $i;
            }

            if (
                $code === T_OPEN_PARENTHESIS
                && isset($token['parenthesis_closer']) === true
            ) {
                $i = $token['parenthesis_closer'];
                continue;
            }

            $isBracket = $code === T_OPEN_SQUARE_BRACKET || $code === T_OPEN_SHORT_ARRAY;

            if (
                $isBracket === true
                && isset($token['bracket_closer']) === true
            ) {
                $i = $token['bracket_closer'];
                continue;
            }

            if (
                in_array($code, self::EXPRESSION_SCOPES, true) === true
                && isset($token['scope_closer']) === true
            ) {
                $i = $token['scope_closer'];
                continue;
            }

            $isRawString = $code === T_START_HEREDOC || $code === T_START_NOWDOC;

            if (
                $isRawString === true
                && isset($token['scope_closer']) === true
            ) {
                $i = $token['scope_closer'];
                continue;
            }

            if (
                $code === T_ATTRIBUTE
                && isset($token['attribute_closer']) === true
            ) {
                return $token['attribute_closer'];
            }

            if (
                isset($token['scope_opener']) === true
                && $token['scope_opener'] > $i
                && $code !== T_FN
            ) {
                return $token['scope_opener'];
            }
        }

        return $phpcsFile->numTokens - 1;
    }

    private function checkStatement(File $phpcsFile, int $start, int $end): void
    {
        $tokens = $phpcsFile->getTokens();
        $baseIndent = $this->lineIndent($phpcsFile, $start);
        $continuation = $this->continuationTokens();
        $exprStart = $start;
        $stack = [];
        $line = $tokens[$start]['line'];
        $commentOpen = false;

        for ($i = $start; $i <= $end; $i++) {
            $token = $tokens[$i];
            $code = $token['code'];

            if ($code === T_WHITESPACE) {
                continue;
            }

            $isRawContent = in_array($code, self::RAW_CONTENT, true) === true
                || $this->isStringTail($tokens, $i) === true;
            $isComment = isset(Tokens::$emptyTokens[$code]) === true && $isRawContent === false;
            $isLineFirst = $token['line'] > $line;
            $lastLine = $token['line'] + substr_count(rtrim($token['content'], "\n"), "\n");

            // A comment is not the statement's own content, so it must not
            // take the first-token slot from code that shares its line — that
            // code's indent would then never be checked. Its last line stays
            // open unless the comment ran onto that line from above, where
            // what precedes the code is the comment's own body. Whether it did
            // is what the open state, read before this token updates it, says.
            $holdsLastLine = $isComment === false || $commentOpen === true;
            $commentOpen = $this->commentStaysOpen($commentOpen, $code, $token['content']);
            $line = max($line, $holdsLastLine === true ? $lastLine : $lastLine - 1);

            if (
                $isComment === true
                || $isRawContent === true
            ) {
                continue;
            }

            $isStatementScopeOpener = $i === $end && ($code === T_OPEN_CURLY_BRACKET || $code === T_COLON);

            if (
                $isLineFirst === true
                && $isStatementScopeOpener === false
            ) {
                $this->checkLine($phpcsFile, $i, $stack, $exprStart, $baseIndent, $continuation);
            }

            // Any code token can begin the expression current in its scope.
            $this->beginExpression($stack, $exprStart, $i);

            if (in_array($code, self::BRACKET_OPENERS, true) === true) {
                $stack[] = ['opener' => $i, 'exprStart' => null];
                continue;
            }

            $opener = $this->closedOpener($tokens, $i, $stack);

            if (
                $opener !== null
                && $stack !== []
                && $stack[count($stack) - 1]['opener'] === $opener
            ) {
                array_pop($stack);
                continue;
            }

            if ($code === T_COMMA) {
                $this->endExpression($stack, $exprStart);

                continue;
            }

            $isExpressionScopeOpener = $code === T_OPEN_CURLY_BRACKET
                && isset($token['scope_condition'], $token['scope_closer']) === true
                && in_array($tokens[$token['scope_condition']]['code'], self::EXPRESSION_SCOPES, true) === true;

            if ($isExpressionScopeOpener === true) {
                $i = $token['scope_closer'] - 1;
            }
        }
    }

    private function checkLine(
        File $phpcsFile,
        int $ptr,
        array $stack,
        ?int $exprStart,
        int $baseIndent,
        array $continuation
    ): void {
        $tokens = $phpcsFile->getTokens();
        $lineStart = $this->lineFirstToken($phpcsFile, $ptr);
        $actual = $tokens[$lineStart]['column'] - 1;
        $closedOpener = $this->closedOpener($tokens, $ptr, $stack);

        if ($closedOpener !== null) {
            $this->reportIndent(
                $phpcsFile,
                $lineStart,
                $this->lineIndent($phpcsFile, $closedOpener),
                $actual,
                'CloseBracketIndent',
                'Closing bracket of a multi-line statement not indented correctly;'
                    . ' expected %s spaces but found %s'
            );

            return;
        }

        $anchor = $this->continuationAnchor($phpcsFile, $ptr, $stack, $exprStart, $continuation);
        $anchorIndent = $anchor === null ? $baseIndent : $this->lineIndent($phpcsFile, $anchor);

        $this->reportIndent(
            $phpcsFile,
            $lineStart,
            $anchorIndent + $this->indent,
            $actual,
            'IncorrectIndent',
            'Line in multi-line statement not indented correctly;'
                . ' expected %s spaces but found %s'
        );
    }

    // The line this one hangs off. A chain, concatenation, or other
    // binary/ternary operator continues the expression above it, so it hangs one
    // level below the line where that expression started. Every other line — an
    // operand, an argument, or a boolean-operator-led sibling condition — sits
    // one level in from the line its enclosing construct opens on.
    private function continuationAnchor(
        File $phpcsFile,
        int $ptr,
        array $stack,
        ?int $exprStart,
        array $continuation
    ): ?int {
        $code = $phpcsFile->getTokens()[$ptr]['code'];
        $opener = $stack === [] ? null : $stack[count($stack) - 1]['opener'];
        $continues = in_array($code, self::CHAIN_OPERATORS, true) === true
            || $this->isContinuationOperator($code, $continuation) === true
            || $this->followsTrailingOperator($phpcsFile, $ptr) === true;

        if ($continues === false) {
            return $opener;
        }

        $anchor = $stack === [] ? $exprStart : $stack[count($stack) - 1]['exprStart'];

        return $anchor ?? $opener;
    }

    private function reportIndent(
        File $phpcsFile,
        int $lineStart,
        int $expected,
        int $actual,
        string $errorCode,
        string $error
    ): void {
        if ($actual === $expected) {
            return;
        }

        $fix = $phpcsFile->addFixableError($error, $lineStart, $errorCode, [$expected, $actual]);

        if ($fix === false) {
            return;
        }

        $padding = str_repeat(' ', $expected);

        if ($phpcsFile->getTokens()[$lineStart]['column'] === 1) {
            $phpcsFile->fixer
                ->addContentBefore($lineStart, $padding);

            return;
        }

        $phpcsFile->fixer
            ->replaceToken($lineStart - 1, $padding);
    }

    // The expression start lives on the innermost bracket frame when there is
    // one, and in the statement-level variable when there is not.
    private function beginExpression(array &$stack, ?int &$exprStart, int $pointer): void
    {
        if ($stack === []) {
            $exprStart ??= $pointer;

            return;
        }

        $stack[count($stack) - 1]['exprStart'] ??= $pointer;
    }

    private function endExpression(array &$stack, ?int &$exprStart): void
    {
        if ($stack === []) {
            $exprStart = null;

            return;
        }

        $stack[count($stack) - 1]['exprStart'] = null;
    }

    private function isContinuationOperator(int|string $code, array $continuation): bool
    {
        if (in_array($code, self::SIBLING_OPERATORS, true) === true) {
            return false;
        }

        return isset($continuation[$code]);
    }

    private function followsTrailingOperator(File $phpcsFile, int $ptr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $ptr - 1, null, true);

        if ($previous === false) {
            return false;
        }

        return in_array($phpcsFile->getTokens()[$previous]['code'], self::TRAILING_OPERATORS, true);
    }

    private function isStringTail(array $tokens, int $ptr): bool
    {
        if (in_array($tokens[$ptr]['code'], self::STRING_LITERALS, true) === false) {
            return false;
        }

        return isset($tokens[$ptr - 1]) === true
            && in_array($tokens[$ptr - 1]['code'], self::STRING_LITERALS, true) === true;
    }

    private function commentStaysOpen(bool $open, int|string $code, string $content): bool
    {
        $this->scanCounts['commentStaysOpen.evaluations']++;

        if (isset(Tokens::$commentTokens[$code]) === false) {
            return false;
        }

        if ($code === T_DOC_COMMENT_OPEN_TAG) {
            return true;
        }

        if ($code === T_DOC_COMMENT_CLOSE_TAG) {
            return false;
        }

        if ($code !== T_COMMENT) {
            return $open;
        }

        $content = trim($content);

        if (
            $open === false
            && str_starts_with($content, '/*') === true
        ) {
            // A fragment that opens and closes on one line needs four
            // characters to do it; `/*/` only looks like both ends at once.
            return strlen($content) < 4 || str_ends_with($content, '*/') === false;
        }

        return $open === true && str_ends_with($content, '*/') === false;
    }

    private function closedOpener(array $tokens, int $ptr, array $stack): ?int
    {
        $code = $tokens[$ptr]['code'];

        if ($code === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? null;
        }

        if (
            $code === T_CLOSE_SQUARE_BRACKET
            || $code === T_CLOSE_SHORT_ARRAY
        ) {
            return $tokens[$ptr]['bracket_opener'] ?? null;
        }

        if ($code === T_CLOSE_CURLY_BRACKET) {
            return $tokens[$ptr]['scope_opener'] ?? null;
        }

        if ($code === T_ATTRIBUTE_END) {
            return $tokens[$ptr]['attribute_opener'] ?? null;
        }

        $unlinked = self::UNLINKED_PAIRS[$code] ?? null;

        if (
            $unlinked === null
            || $stack === []
        ) {
            return null;
        }

        $opener = $stack[count($stack) - 1]['opener'];

        return $tokens[$opener]['code'] === $unlinked ? $opener : null;
    }

    private function lineFirstToken(File $phpcsFile, int $ptr): int
    {
        $this->scanCounts['lineFirstToken.readings']++;
        $first = $this->lineStart($phpcsFile, $ptr);

        while (isset($this->commentOpeners[$first]) === true) {
            $this->scanCounts['lineFirstToken.commentHops']++;
            $first = $this->lineStart($phpcsFile, $this->commentOpeners[$first]);
        }

        return $first;
    }

    private function lineStart(File $phpcsFile, int $ptr): int
    {
        // Two tokens examined, both through step(): the one asked about, to get
        // its line, and the line's recorded first, to see whether it is indent.
        // Neither is searched for, and this method binds no token array of its
        // own to search one in.
        $first = $this->lineStarts[$this->step($phpcsFile, $ptr)['line']];

        if ($this->step($phpcsFile, $first)['code'] === T_WHITESPACE) {
            $first++;
        }

        return $first;
    }

    private function step(File $phpcsFile, int $ptr): array
    {
        $this->scanCounts['lineStart.steps']++;

        return $phpcsFile->getTokens()[$ptr];
    }

    private function mapLines(File $phpcsFile): void
    {
        $this->commentOpeners = [];
        $this->lineStarts = [];
        $opener = null;

        foreach ($phpcsFile->getTokens() as $i => $token) {
            $code = $token['code'];
            $line = $token['line'];

            if (isset($this->lineStarts[$line]) === false) {
                $this->lineStarts[$line] = $i;
            }

            if (isset(Tokens::$commentTokens[$code]) === false) {
                $opener = null;

                continue;
            }

            if ($opener !== null) {
                $this->commentOpeners[$i] = $opener;
            }

            $stillOpen = $this->commentStaysOpen($opener !== null, $code, $token['content']);

            if ($stillOpen === false) {
                $opener = null;

                continue;
            }

            $opener ??= $i;
        }
    }

    private function lineIndent(File $phpcsFile, int $ptr): int
    {
        return $phpcsFile->getTokens()[$this->lineFirstToken($phpcsFile, $ptr)]['column'] - 1;
    }

    private function continuationTokens(): array
    {
        return Tokens::$operators
            + Tokens::$comparisonTokens
            + Tokens::$booleanOperators
            + Tokens::$assignmentTokens
            + [
                T_STRING_CONCAT => T_STRING_CONCAT,
                T_INLINE_THEN => T_INLINE_THEN,
                T_INLINE_ELSE => T_INLINE_ELSE,
                T_INSTANCEOF => T_INSTANCEOF,
                T_FN_ARROW => T_FN_ARROW,
            ];
    }
}

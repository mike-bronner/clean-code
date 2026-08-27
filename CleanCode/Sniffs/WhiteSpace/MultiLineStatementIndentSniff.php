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
 * first must be indented exactly one level. Which line it is a level in from
 * depends on what the line is:
 *
 * - a *sibling* line — an argument, an array item, or a condition led by a
 *   boolean operator (`&&`, `||`, `and`, `or`, `xor`) — sits one level in
 *   from the line its enclosing construct opens on. Boolean operators are
 *   siblings because `CleanCode.Conditionals.OneConditionPerLine` puts each
 *   top-level condition on its own line, making them peers of the first
 *   condition rather than a continuation of it;
 * - a *continuation* line — one led by a chain operator (`->`, `?->`, `::`)
 *   or by any other binary or ternary operator (`.`, `+`, `?`, `:`, `??`,
 *   …), or one sitting below a trailing `=>` — sits one level in from the
 *   line where the expression it continues started. Inside a bracket that is
 *   the element's own line, not the opener's, so a wrapped argument's
 *   continuation hangs below the argument;
 * - a closing bracket on its own line matches the indent of the line that
 *   opened the bracket. A grouped `use`'s braces are a bracket pair like any
 *   other here, though PHPCS links neither half to the other — see
 *   UNLINKED_PAIRS.
 *
 * `=>` is the only operator whose *trailing* position is read here — in both
 * of the tokens PHPCS emits for it, `T_DOUBLE_ARROW` (array key, named
 * argument) and `T_FN_ARROW` (arrow function). Every other dangling operator
 * is `CleanCode.Operators.OperatorLineBreak`'s to report, and re-anchoring
 * around one would put a second violation on a line that already has its own.
 * The third arrow token, `T_MATCH_ARROW`, is unreachable here: a match body is
 * a scope block, skipped whole.
 *
 * Bodies of closures, anonymous classes, and match expressions are scope
 * blocks governed by scope-indent rules, so their inner lines are skipped;
 * their headers (parameter lists, match subjects) are still checked. An arrow
 * function is the exception: its body is an expression, not a scope block, so
 * it stays inside the statement and is checked as a continuation of the line
 * its `fn` sits on.
 *
 * A PHP attribute (`#[…]`) is a construct of its own: the declaration it
 * decorates starts a fresh statement. Heredoc and nowdoc bodies are raw
 * content and are never checked, and neither are the tail lines of a quoted
 * string that spans lines — PHPCS splits such a string into one token per
 * physical line, and those lines are the string's own value, not code.
 *
 * A comment is never measured, but it never exempts a line either: a line
 * that opens with a comment and then carries code is checked on the code, at
 * the indent the comment sits at. A comment that *runs onto* a line from above
 * is the exception — the code after it shares the line with the comment's own
 * body rather than with the line's indent, so that line is the comment's and
 * is left alone, exactly as a multi-line string's tail lines are.
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
        T_ATTRIBUTE,
        T_OPEN_USE_GROUP,
    ];

    /**
     * Closers PHPCS records no opener for, and the opener each one closes.
     *
     * Every other bracket kind carries its partner's index on the token itself,
     * put there by the tokenizer. A grouped `use`'s braces carry nothing — no
     * `bracket_opener`, no `scope_opener` — so the only record of where the pair
     * began is the stack this sniff pushes as it walks, and closedOpener() reads
     * the opener from there for these tokens instead.
     */
    private const UNLINKED_PAIRS = [
        T_CLOSE_USE_GROUP => T_OPEN_USE_GROUP,
    ];

    /**
     * Dereference operators that begin a chained continuation line; a chain
     * hangs one level below the line where its expression started.
     */
    private const CHAIN_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    /**
     * Boolean operators join *sibling* expressions rather than continue one.
     *
     * `CleanCode.Conditionals.OneConditionPerLine` puts every top-level
     * condition on its own line with the operator leading, so a
     * boolean-operator line is a peer of the first condition — one level in
     * from the line its construct opens on, not one level in from the
     * condition above it.
     */
    private const SIBLING_OPERATORS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
    ];

    /**
     * Curly-brace scopes that appear inside expressions; their bodies are
     * governed by scope-indent rules, not by this sniff.
     *
     * Family: every member of `PHP_CodeSniffer\Util\Tokens::$scopeOpeners` —
     * PHPCS's own register of the tokens a `scope_opener`/`scope_closer` pair
     * hangs off — plus `T_FN`, which the tokenizer gives that same pair in
     * `PHP::processAdditional()` while leaving it out of that array. That
     * union is the whole set of tokens findStatementEnd() could be asked to
     * skip, so it is the set this constant answers to, and every member of it
     * is listed below. A member excluded here is not thereby unhandled: the
     * last branch of findStatementEnd() ends the statement at its scope
     * opener, which is the right answer for a construct that starts a
     * statement of its own.
     *
     * - `T_CLOSURE` — included: a `function () {…}` written as an operand sits
     *   mid-statement, and its body is a scope block.
     * - `T_ANON_CLASS` — included: `new class {…}` is an operand whose body is
     *   a scope block.
     * - `T_MATCH` — included: a `match` is an operand; its arms are a scope
     *   block, while its subject stays on the statement and is checked.
     * - `T_FN` — excluded: an arrow function's body is one expression that
     *   stays in the statement, so it is walked into and checked as a
     *   continuation rather than skipped — findStatementEnd() names it.
     * - `T_FUNCTION` — excluded: a named function or method declaration, never
     *   an operand; `T_CLOSURE` is the expression form.
     * - `T_CLASS` — excluded: a class declaration, never an operand;
     *   `T_ANON_CLASS` is the expression form, and `Foo::class` is a
     *   `T_STRING`.
     * - `T_TRAIT` — excluded: a trait declaration, never an operand.
     * - `T_INTERFACE` — excluded: an interface declaration, never an operand.
     * - `T_ENUM` — excluded: an enum declaration, never an operand.
     * - `T_NAMESPACE` — excluded: a braced namespace declaration, always at
     *   file level; the relative-name form `namespace\Foo` opens no scope.
     * - `T_USE` — excluded: it opens a scope only as a trait-adaptation block
     *   in a class body, a statement of its own. A closure's `use (…)` capture
     *   is parentheses, and a grouped import's braces are BRACKET_OPENERS and
     *   UNLINKED_PAIRS.
     * - `T_IF` — excluded: a control-flow keyword; it opens a statement rather
     *   than continuing one.
     * - `T_ELSEIF` — excluded: a control-flow keyword, as `T_IF` is.
     * - `T_ELSE` — excluded: a control-flow keyword, as `T_IF` is.
     * - `T_DO` — excluded: a loop keyword; it opens a statement.
     * - `T_WHILE` — excluded: a loop keyword; it opens a statement.
     * - `T_FOR` — excluded: a loop keyword; it opens a statement.
     * - `T_FOREACH` — excluded: a loop keyword; it opens a statement.
     * - `T_SWITCH` — excluded: a control-flow keyword; it opens a statement.
     * - `T_TRY` — excluded: a control-flow keyword; it opens a statement.
     * - `T_CATCH` — excluded: a control-flow keyword; it opens a statement.
     * - `T_FINALLY` — excluded: a control-flow keyword; it opens a statement.
     * - `T_DECLARE` — excluded: a directive, at file or block level, never an
     *   operand.
     * - `T_CASE` — excluded: a `switch` body label; an enum's `case` is
     *   `T_ENUM_CASE` and opens no scope.
     * - `T_DEFAULT` — excluded: a `switch` body label; a match arm's `default`
     *   is `T_MATCH_DEFAULT` and opens no scope.
     * - `T_OBJECT` — excluded: only the JavaScript tokenizer emits it, so no
     *   PHP source reaches it.
     * - `T_PROPERTY` — excluded: only the JavaScript tokenizer emits it, so no
     *   PHP source reaches it.
     */
    private const EXPRESSION_SCOPES = [
        T_CLOSURE,
        T_ANON_CLASS,
        T_MATCH,
    ];

    /**
     * Tokens whose lines carry raw content (heredoc/nowdoc bodies, backtick
     * strings, inline HTML) where indentation is data, not code style.
     *
     * `T_ENCAPSED_AND_WHITESPACE` survives as its own token only for a
     * backtick string: PHPCS folds the double-quoted case into
     * `T_DOUBLE_QUOTED_STRING` (see STRING_LITERALS) but leaves the backtick
     * delimiter alone. `T_INLINE_HTML` is unreachable in the same way
     * `T_MATCH_ARROW` is — findStatementEnd() stops at `T_CLOSE_TAG` before
     * any inline HTML begins, and findStatementStart() skips it outright — and
     * is kept only so the list reads as the complete set of raw-content
     * tokens.
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
     * Token types PHPCS emits for a quoted string literal.
     *
     * A quoted string whose source spans lines is split into one token per
     * physical line, all of these types. Every fragment after the first is the
     * string's own value rather than a line of code, so it carries raw content
     * the same way a heredoc body does — see isStringTail(). The opening
     * fragment stays code: it is the argument or operand the line begins with.
     */
    private const STRING_LITERALS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    /**
     * The `=>` tokens whose *trailing* position leaves an element open, so the
     * line below continues it.
     *
     * PHPCS emits a distinct token per context: `T_DOUBLE_ARROW` for an array
     * key or named argument, `T_FN_ARROW` for an arrow function. Both read the
     * same way here. `T_MATCH_ARROW` is deliberately absent — a match body is
     * an expression scope this sniff skips whole, so no match arm is ever
     * reached.
     */
    private const TRAILING_OPERATORS = [
        T_DOUBLE_ARROW,
        T_FN_ARROW,
    ];

    /**
     * Number of spaces per indentation level.
     */
    public int $indent = 4;

    /**
     * For every token that sits inside a comment which opened before it, the
     * token that opened that comment. Rebuilt per file by mapLines().
     *
     * @var array<int, int>
     */
    private array $commentOpeners = [];

    /**
     * The lowest-numbered token on each line, keyed by line number. Rebuilt per
     * file by mapLines().
     *
     * @var array<int, int>
     */
    private array $lineStarts = [];

    /**
     * The work each of this sniff's three per-line readings costs, cumulative
     * for the life of this instance.
     *
     * Every one of them used to be a scan whose length was the thing being
     * read — the comment replayed from its start, the line walked back token by
     * token — and each was therefore quadratic over a file that grows the
     * comment or the line. mapLines() answers all three from one pass, and the
     * only observable of that is these counts or the elapsed time they replace,
     * which a shared CI runner's jitter can carry across any fixed budget with
     * no code change (#321, #354).
     *
     * Each counter names the unit of work its reading is charged in, so a
     * reading that went back to scanning raises it without the counter moving:
     *
     * - `commentStaysOpen.evaluations` — one per fragment the open/closed
     *   question is asked of. Carrying the answer forward asks it once per
     *   fragment; recovering it by replaying the comment asks it once per
     *   fragment *per line read*.
     * - `lineFirstToken.readings` — one per line whose first token is asked
     *   for, which is the number of readings the cost is multiplied by.
     * - `lineStart.steps` — tokens examined to answer one of those readings.
     *   The map answers in one; walking back examines one per token already on
     *   the line.
     * - `lineFirstToken.commentHops` — steps taken from a line that opens
     *   inside a comment to the line that comment opened on. The map makes that
     *   one step whatever the comment's length.
     *
     * @var array<string, int>
     */
    private array $scanCounts = [
        'commentStaysOpen.evaluations' => 0,
        'lineFirstToken.readings' => 0,
        'lineFirstToken.commentHops' => 0,
        'lineStart.steps' => 0,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    /**
     * What each per-line reading has cost, cumulative for the life of this
     * instance. See $scanCounts.
     *
     * @return array<string, int>
     */
    public function scanCounts(): array
    {
        return $this->scanCounts;
    }

    /**
     * @param int $stackPtr
     *
     * @return int
     */
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

            $isRawString = $code === T_START_HEREDOC || $code === T_START_NOWDOC;

            if ($isRawString === true && isset($token['scope_closer']) === true) {
                $i = $token['scope_closer'];
                continue;
            }

            if ($code === T_ATTRIBUTE && isset($token['attribute_closer']) === true) {
                return $token['attribute_closer'];
            }

            if (isset($token['scope_opener']) === true && $token['scope_opener'] > $i && $code !== T_FN) {
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

            if ($isComment === true || $isRawContent === true) {
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

            $opener = $this->closedOpener($tokens, $i, $stack);

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

            $isExpressionScopeOpener = $code === T_OPEN_CURLY_BRACKET
                && isset($token['scope_condition'], $token['scope_closer']) === true
                && in_array($tokens[$token['scope_condition']]['code'], self::EXPRESSION_SCOPES, true) === true;

            if ($isExpressionScopeOpener === true) {
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
        $lineStart = $this->lineFirstToken($phpcsFile, $ptr);
        $actual = $tokens[$lineStart]['column'] - 1;
        $closedOpener = $this->closedOpener($tokens, $ptr, $stack);

        if ($closedOpener !== null) {
            $expected = $this->lineIndent($phpcsFile, $closedOpener);
            $errorCode = 'CloseBracketIndent';
            $error = 'Closing bracket of a multi-line statement not indented correctly;'
                . ' expected %s spaces but found %s';
        } else {
            $continues = in_array($token['code'], self::CHAIN_OPERATORS, true) === true
                || $this->isContinuationOperator($token['code'], $continuation) === true
                || $this->followsTrailingOperator($phpcsFile, $ptr) === true;

            if ($continues === true) {
                // A chain, concatenation, or other binary/ternary operator
                // continues the expression above it, so it hangs one level
                // below the line where that expression started.
                $anchor = $stack === [] ? $exprStart : $stack[count($stack) - 1]['exprStart'];
                $anchor ??= $stack === [] ? null : $stack[count($stack) - 1]['opener'];
            } else {
                // Every other line — an operand, an argument, or a
                // boolean-operator-led sibling condition — sits one level in
                // from the line its enclosing construct opens on.
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

        $fix = $phpcsFile->addFixableError($error, $lineStart, $errorCode, [$expected, $actual]);

        if ($fix === false) {
            return;
        }

        $padding = str_repeat(' ', $expected);

        if ($tokens[$lineStart]['column'] === 1) {
            $phpcsFile->fixer->addContentBefore($lineStart, $padding);
        } else {
            $phpcsFile->fixer->replaceToken($lineStart - 1, $padding);
        }
    }

    /**
     * Whether a token continues the expression above it: a binary or ternary
     * operator, but not one of the boolean operators that join siblings.
     *
     * @param array<int|string, int|string> $continuation
     */
    private function isContinuationOperator(int|string $code, array $continuation): bool
    {
        if (in_array($code, self::SIBLING_OPERATORS, true) === true) {
            return false;
        }

        return isset($continuation[$code]);
    }

    /**
     * Whether the line beginning at $ptr continues an element the line above
     * left open by ending on `=>`.
     *
     * `=>` is the one operator this package lets trail: every other one is
     * `CleanCode.Operators.OperatorLineBreak`'s to report at the line it
     * dangles on, and re-anchoring around it here would put a second
     * violation on a line that already has its own.
     */
    private function followsTrailingOperator(File $phpcsFile, int $ptr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $ptr - 1, null, true);

        if ($previous === false) {
            return false;
        }

        return in_array($phpcsFile->getTokens()[$previous]['code'], self::TRAILING_OPERATORS, true);
    }

    /**
     * Whether the token at $ptr is a tail fragment of a quoted string that
     * spans lines — a string token whose immediate predecessor is also one.
     *
     * PHPCS gives a multi-line quoted string one token per physical line, so
     * without this the string's second and later lines read as code lines
     * needing statement indent. Reporting them is not merely a false positive:
     * `phpcbf` then injects the padding *into the string's value*, which
     * changes nothing the sniff measures, so it re-reports on the next pass and
     * the fixer never converges — the whole file comes back `FAILED TO FIX`,
     * including violations from every other sniff.
     *
     * `CleanCode.Strings.MultilineStrings` is what actually forbids this shape
     * (rewriting it to a heredoc/nowdoc), so silence here is the correct
     * division of labour rather than a gap.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isStringTail(array $tokens, int $ptr): bool
    {
        if (in_array($tokens[$ptr]['code'], self::STRING_LITERALS, true) === false) {
            return false;
        }

        return isset($tokens[$ptr - 1]) === true
            && in_array($tokens[$ptr - 1]['code'], self::STRING_LITERALS, true) === true;
    }

    /**
     * Whether a comment is still open once the token carrying $content has
     * been read, given that it was $open before.
     *
     * PHPCS never gives a comment one token spanning lines: a block comment is
     * one `T_COMMENT` per physical line, and a doc comment is a run of
     * `T_DOC_COMMENT_*` tokens broken at every newline — the same
     * per-physical-line split this file already handles for heredocs and
     * strings. So a fragment says nothing on its own about whether it is the
     * comment's first line, and the answer has to be carried forward from the
     * fragment before it. Both callers carry it rather than recompute it, for
     * the same reason: scanning back to the start of the comment for each of its
     * lines is quadratic, and a long comment is enough to stall the run.
     * checkStatement() carries it along the token walk it already makes;
     * mapLines() carries it along one pass over the file, for lineFirstToken(),
     * which reads lines in no order it could carry anything along.
     *
     * Carrying the state is also what tells apart two shapes that look
     * identical at the fragment: a body line whose text begins with a slash
     * pair opens nothing, because the comment above it is still open, while a
     * whole one-line block comment sitting directly below a slash-pair comment
     * opens and closes its own and leaves the rest of its line to the code that
     * follows.
     */
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

        if ($open === false && str_starts_with($content, '/*') === true) {
            // A fragment that opens and closes on one line needs four
            // characters to do it; `/*/` only looks like both ends at once.
            return strlen($content) < 4 || str_ends_with($content, '*/') === false;
        }

        return $open === true && str_ends_with($content, '*/') === false;
    }

    /**
     * The opener whose bracket/scope the token at $ptr closes, or null when the
     * token is not a closer, or is one whose opener cannot be established.
     *
     * A closer PHPCS links reads its partner off the token. One it does not
     * (UNLINKED_PAIRS says which, and why) is answered from the open bracket
     * this sniff is currently inside, which is that partner as long as it is an
     * opener of the matching kind. Anything else — an unbalanced closer, or a
     * stack whose top belongs to some other bracket — leaves the pair
     * unestablished, and the closer is then measured as an ordinary line rather
     * than anchored on a guess.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param array<int, array{opener: int, exprStart: int|null}> $stack
     */
    private function closedOpener(array $tokens, int $ptr, array $stack): ?int
    {
        $code = $tokens[$ptr]['code'];

        if ($code === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? null;
        }

        if ($code === T_CLOSE_SQUARE_BRACKET || $code === T_CLOSE_SHORT_ARRAY) {
            return $tokens[$ptr]['bracket_opener'] ?? null;
        }

        if ($code === T_CLOSE_CURLY_BRACKET) {
            return $tokens[$ptr]['scope_opener'] ?? null;
        }

        if ($code === T_ATTRIBUTE_END) {
            return $tokens[$ptr]['attribute_opener'] ?? null;
        }

        $unlinked = self::UNLINKED_PAIRS[$code] ?? null;

        if ($unlinked === null || $stack === []) {
            return null;
        }

        $opener = $stack[count($stack) - 1]['opener'];

        return $tokens[$opener]['code'] === $unlinked ? $opener : null;
    }

    /**
     * The first token that is not the indent on the line the token at $ptr
     * sits on.
     *
     * What indents a line is whatever opens it, which is not always the code
     * the line is checked for: a comment may sit in front of that code on the
     * same line. Measuring and padding here rather than at the code token
     * keeps both halves reading the one thing the line actually has.
     *
     * A line that opens *inside* a comment has no indent of its own — what
     * stands in front of its code is the comment's body, whose alignment is the
     * comment's business. The indent governing it is the one on the line that
     * comment opened, so the search moves there in one step, whatever the
     * comment's length. Every measurement this sniff makes runs through here,
     * which is why the rule lives here rather than at each caller: the same line
     * reads the same way whether it is being checked, used as an anchor, or read
     * as the statement's own base indent. That also makes this the one place a
     * per-line reading can be made cheap for all of them — mapLines() says how.
     */
    private function lineFirstToken(File $phpcsFile, int $ptr): int
    {
        $tokens = $phpcsFile->getTokens();
        $this->scanCounts['lineFirstToken.readings']++;
        $first = $this->lineStart($tokens, $ptr);

        while (isset($this->commentOpeners[$first]) === true) {
            $this->scanCounts['lineFirstToken.commentHops']++;
            $first = $this->lineStart($tokens, $this->commentOpeners[$first]);
        }

        return $first;
    }

    /**
     * The first token past the indent on the line the token at $ptr sits on.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function lineStart(array $tokens, int $ptr): int
    {
        // One token examined: the line's first is recorded, not searched for.
        $this->scanCounts['lineStart.steps']++;
        $first = $this->lineStarts[$tokens[$ptr]['line']];

        if ($tokens[$first]['code'] === T_WHITESPACE) {
            $first++;
        }

        return $first;
    }

    /**
     * Answers both of this sniff's per-line questions in a single pass over the
     * file: where each line begins, and which comment — if any — each token sits
     * inside.
     *
     * Every reading here used to be a scan backwards from the token being asked
     * about, and each one was quadratic for the same reason. A line's first
     * token was found by walking back token by token until the line changed, and
     * lineIndent() asks that of the *same* bracket opener once per line the
     * bracket wraps — so an opener late on a long line, with many lines under
     * it, re-walked that line every time. Whether a comment fragment continues
     * the one above it is a question about the fragment before it
     * (commentStaysOpen() says why), and replaying the comment from its start to
     * recover the answer cost one pass per line of it. Neither reader can carry
     * state along: lineFirstToken() reads lines in whatever order the anchors
     * fall in.
     *
     * So the state is carried here instead, once, and every later reading is a
     * lookup — the treatment checkStatement() already gives its own walk. Both
     * maps are rebuilt per file, which includes once per `phpcbf` pass, so
     * neither outlives the tokens it was built from.
     */
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
            } elseif ($opener === null) {
                $opener = $i;
            }
        }
    }

    /**
     * The indent (in spaces) of the line the token at $ptr sits on.
     */
    private function lineIndent(File $phpcsFile, int $ptr): int
    {
        return $phpcsFile->getTokens()[$this->lineFirstToken($phpcsFile, $ptr)]['column'] - 1;
    }

    /**
     * Operator tokens that mark a line as continuing the expression begun
     * on an earlier line.
     *
     * Only tokens no PHPCS union already carries are listed, and only ones
     * checkLine() can still reach. A member that duplicates either source is
     * unreachable *and* untestable — the surviving copy answers first, so no
     * fixture can ever tell whether this one is load-bearing. `T_COALESCE`
     * (carried by `Tokens::$operators`) and `T_DOUBLE_ARROW` (carried by
     * `Tokens::$assignmentTokens`) are therefore absent, as are the three
     * CHAIN_OPERATORS members (checkLine() tests that constant first). Both
     * delegated tokens are fixtured anyway, so the behaviour is pinned wherever
     * it comes from: dropping `T_DOUBLE_ARROW` from its one union reddens the
     * suite, and `T_COALESCE` reddens it once every union carrying it does
     * (`Tokens::$comparisonTokens` carries that one too). `T_FN_ARROW` stays:
     * no union carries it, and
     * TRAILING_OPERATORS only reads it in the *trailing* position, so a
     * leading one reaches this list alone. `T_MATCH_ARROW` is the one arrow
     * missing for neither reason — no union carries it either, but a match
     * body is a scope block this sniff skips whole, so nothing reaches it.
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
                T_INLINE_THEN => T_INLINE_THEN,
                T_INLINE_ELSE => T_INLINE_ELSE,
                T_INSTANCEOF => T_INSTANCEOF,
                T_FN_ARROW => T_FN_ARROW,
            ];
    }
}

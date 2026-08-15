<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use MikeBronner\CleanCode\Support\ConditionOperatorOwnership;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Operators: Manipulative" standard.
 *
 * A manipulation operator that joins two operands across a line break must
 * *start* the continuation line, never trail the end of the previous one. So
 *
 *     $result = 4
 *         + 4;
 *
 * is compliant, while
 *
 *     $result = 4 +
 *         4;
 *
 * is not. Operators used inline — both operands on the same line, e.g.
 * `floor(4 + 4.1)` — are always fine; the rule only governs how a
 * genuinely wrapped expression breaks.
 *
 * Manipulation operators covered here: the math operators `+ - * / % **` and
 * the bitwise operators `& | ^ << >>` — the slice of the standard's list that
 * no other rule in the master ruleset already polices. The remaining
 * manipulation operators are deliberately left out, so that a wrapped
 * expression is reported exactly once:
 *
 * - string concatenation (`.`) and the logical connectives (`&& ||`) are
 *   already enforced by {@see OperatorLineBreakSniff} (#35), which reports the
 *   same "operator must not trail the line" rule on them; and
 * - inside an `if`/`elseif`/`while`/`for` condition that sniff in turn defers
 *   to `CleanCode.Conditionals.OneConditionPerLine`, so this one defers there
 *   on the same terms — see {@see ConditionOperatorOwnership}, which holds the
 *   single copy of that decision for both sniffs.
 *
 * Unary bitwise NOT (`~`) has no binary/continuation
 * form and is not subject to the rule. Some tokens double as non-binary forms
 * that are never manipulation operators and so are exempt regardless of layout:
 * a unary sign (`-5`, `+5`), recognised because no real left-hand operand ends
 * the previous line — including the sign that opens a discarded statement right
 * after a control structure's closing brace, which is told from a `match`,
 * closure, or anonymous-class brace by the scope the brace closes; a reference
 * `&` (`&$ref`, a by-reference parameter,
 * return, assignment, `foreach`, or array element), recognised by PHP_CodeSniffer's
 * own reference detection rather than by what token happens to precede it; and a
 * `|`/`&` separating exception types in a `catch (TypeA | TypeB $e)` clause,
 * recognised by its enclosing `catch` parenthesis (PHP_CodeSniffer leaves that
 * separator as `T_BITWISE_OR`/`T_BITWISE_AND` rather than retokenising it as a
 * type union the way it does for parameter, return, and property types).
 *
 * The auto-fixer moves the trailing operator down to lead the continuation
 * line, indented one level past the statement's first line, with a single
 * space before its right-hand operand. A comment sitting between the two
 * operands makes the move unsafe (it would be reordered), so those violations
 * are reported but left for the developer.
 */
class ManipulationOperatorPlacementSniff implements Sniff
{
    /**
     * The manipulation operators this sniff governs — the math and bitwise
     * groups. Two members of the standard's wider list are intentionally
     * absent:
     *
     * - `.` and `&& ||`, because {@see OperatorLineBreakSniff} already reports
     *   the same rule on them (#35). Registering them here as well would report
     *   every wrapped concatenation and boolean twice, which is what
     *   tests/Integration/OperatorRulesIntegrationTest.php exists to prevent.
     * - `~` (unary bitwise NOT), because it has no binary form, so "start the
     *   new line" is meaningless for it.
     *
     * @var array<int|string>
     */
    private const MANIPULATION_OPERATORS = [
        T_PLUS,          // +
        T_MINUS,         // -
        T_MULTIPLY,      // *
        T_DIVIDE,        // /
        T_MODULUS,       // %
        T_POW,           // **
        T_BITWISE_AND,   // &
        T_BITWISE_OR,    // |
        T_BITWISE_XOR,   // ^
        T_SL,            // <<
        T_SR,            // >>
    ];

    /**
     * Operators whose token can also be a unary sign (`+5`, `-5`), so they are
     * only treated as manipulation operators when a real operand ends the
     * previous line. `&` is *not* here: its reference form is told apart by
     * {@see File::isReference()}, not by the preceding token.
     *
     * @var array<int|string>
     */
    private const UNARY_CAPABLE = [
        T_PLUS,
        T_MINUS,
    ];

    /**
     * Tokens that terminate a left-hand operand: literals, identifiers that
     * resolve to a value, string terminators, postfix `++`/`--`, and closing
     * brackets. Used to tell a binary `+`/`-` (preceded by a value) from its
     * unary sign form (preceded by punctuation, a keyword, or another
     * operator). The magic constants (`__LINE__`, `__FILE__`, …) are covered
     * separately via {@see Tokens::$magicConstants} so the set stays complete
     * as PHP adds more.
     *
     * The set is *admission*, not exclusion, on purpose. Both forms are
     * hand-maintained lists that a new PHP construct can outdate, so the choice
     * is between their failure modes: a member missing from this set costs a
     * false negative — one real violation goes unreported — while a member
     * missing from the inverted "these tokens mean a unary sign follows" set
     * costs a false positive, and the fixer then rewrites correct code.
     *
     * Every entry was derived by tokenising the construct rather than by
     * reasoning about it, and the families are closed:
     *
     * - **Values** — `T_VARIABLE`, the two numeric literals, `T_TRUE`,
     *   `T_FALSE`, `T_NULL`, and `T_STRING` (which is what a constant, a class
     *   constant, and a property fetch all end on).
     * - **String terminators** — `Tokens::$stringTokens` (a single-quoted and
     *   an interpolated literal each end on their own token) plus the two
     *   heredoc/nowdoc closers from `Tokens::$heredocTokens`, and `T_BACKTICK`
     *   for shell execution. A backtick opener can never be mistaken for the
     *   closer here: the delimited body tokenises as encapsed content, so a
     *   leading `-` inside it (`` `ls -la` ``) is never a `T_MINUS`.
     * - **Closing brackets** — `Tokens::$bracketTokens`' three closers plus
     *   `T_CLOSE_SHORT_ARRAY`, the separate token PHP_CodeSniffer gives a short
     *   array's `]`. `T_CLOSE_CURLY_BRACKET` is admitted here but qualified in
     *   {@see self::endsLeftOperand()}, since only some `}` close a value.
     * - **Postfix operators** — `T_INC`/`T_DEC`. A prefix `++`/`--` cannot
     *   precede a `+`/`-` (its own operand does), so no qualification is needed.
     *
     * tests/Standards/ManipulationOperatorPlacementTest.php pins the string and
     * bracket families against those PHP_CodeSniffer enumerations directly, so
     * a token added to either upstream fails the suite instead of silently
     * widening the gap.
     *
     * @var array<int|string>
     */
    private const OPERAND_END_TOKENS = [
        T_VARIABLE,
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
        T_END_HEREDOC,
        T_END_NOWDOC,
        T_BACKTICK,
        T_STRING,
        T_TRUE,
        T_FALSE,
        T_NULL,
        T_INC,
        T_DEC,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * The constructs whose closing `}` yields a value, so that a `+`/`-`
     * directly after it is binary. Everything else PHP_CodeSniffer can hang a
     * scope off — `if`, `while`, `for`, `foreach`, `switch`, `try`, `function`,
     * `class`, … — closes a *statement*, and a sign following that brace opens
     * a new expression instead of continuing the old one.
     *
     * A `}` with no scope at all (`${$name}`, `$object->{$name}`) is a value
     * too, and is admitted in {@see self::endsLeftOperand()} rather than here,
     * having no owner to name.
     *
     * @var array<int|string>
     */
    private const VALUE_PRODUCING_SCOPE_OWNERS = [
        T_ANON_CLASS,
        T_CLOSURE,
        T_MATCH,
    ];

    /**
     * Tokens the continuation-indent anchor escapes past to reach the
     * statement's true root line: expression-grouping openers (`(`, `[`, short
     * array `[`) and the argument/element separator `,`. A wrapped operator
     * inside any of these lives on a line already indented one or more levels
     * below its statement's first line, so {@see \PHP_CodeSniffer\Files\File::findStartOfStatement()}
     * — which halts at the nearest of them — must be escaped outward. Curly
     * braces are intentionally absent: a `{` is a real scope boundary, and a
     * statement inside a block should indent relative to that block.
     *
     * @var array<int|string>
     */
    private const STATEMENT_ANCHOR_ESCAPE_TOKENS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_COMMA,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return self::MANIPULATION_OPERATORS;
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        // Inside an if/elseif/while/for condition, OneConditionPerLine reports
        // (and fixes) the same wrap wholesale, so stand down there exactly
        // where CleanCode.Operators.OperatorLineBreak does.
        if (ConditionOperatorOwnership::isDeferredToOneConditionPerLine($phpcsFile, $stackPtr) === true) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($previous === false || $next === false) {
            return;
        }

        // The operator only "trails" when its left-hand operand ends on the
        // operator's own line and the right-hand operand lives on a later one.
        // A left operand on an earlier line means the operator already leads —
        // including when it sits alone on its own line — which is compliant.
        if ($tokens[$previous]['line'] !== $tokens[$stackPtr]['line']) {
            return;
        }

        // Both operands on the operator's line: inline usage, compliant.
        if ($tokens[$next]['line'] === $tokens[$stackPtr]['line']) {
            return;
        }

        // A reference `&` (by-ref parameter, return, assignment, foreach, array
        // element, or by-ref call argument) is not a bitwise-AND manipulation
        // operator, whatever token precedes it. PHP_CodeSniffer resolves the
        // reference-vs-bitwise question directly, so defer to it.
        if ($tokens[$stackPtr]['code'] === T_BITWISE_AND && $phpcsFile->isReference($stackPtr) === true) {
            return;
        }

        // A `|`/`&` separating types in a `catch (TypeA | TypeB $e)` clause is a
        // type-union/intersection separator, not a bitwise manipulation operator.
        // PHP_CodeSniffer retokenises union/intersection types to T_TYPE_UNION /
        // T_TYPE_INTERSECTION in parameter, return, and property positions (which
        // are never registered here), but leaves the catch-clause separator as
        // T_BITWISE_OR/T_BITWISE_AND, so exempt it by its enclosing context.
        if (
            in_array($tokens[$stackPtr]['code'], [T_BITWISE_OR, T_BITWISE_AND], true) === true
            && $this->isCatchTypeSeparator($phpcsFile, $stackPtr) === true
        ) {
            return;
        }

        // A unary sign (`-5`, `+5`) carries no left-hand operand, so `+`/`-` are
        // manipulation operators only when a real operand ends the previous line.
        if (
            in_array($tokens[$stackPtr]['code'], self::UNARY_CAPABLE, true) === true
            && $this->endsLeftOperand($phpcsFile, $previous) === false
        ) {
            return;
        }

        $error = 'Manipulation operator "%s" must start the continuation line, not trail the previous one';
        $code = 'OperatorNotLeading';
        $data = [$tokens[$stackPtr]['content']];

        // Moving the operator across a comment that sits between the two
        // operands would reorder the comment, so leave that case unfixed.
        $hasComment = $phpcsFile->findNext(Tokens::$commentTokens, ($previous + 1), $next) !== false;

        if ($hasComment === true) {
            $phpcsFile->addError($error, $stackPtr, $code, $data);

            return;
        }

        $fix = $phpcsFile->addFixableError($error, $stackPtr, $code, $data);

        if ($fix === false) {
            return;
        }

        $this->moveOperatorToNextLine($phpcsFile, $stackPtr);
    }

    /**
     * Whether the token at $previous terminates a left-hand operand — true for
     * value literals, postfix `++`/`--`, closing brackets, and the magic
     * constants, which makes a following `+`/`-` a binary manipulation operator
     * rather than a unary sign.
     *
     * A closing `}` is the one entry that cannot be decided by its token alone:
     * the same `T_CLOSE_CURLY_BRACKET` ends a `match` expression, an anonymous
     * class, and a closure — all values — as ends an `if`/`while`/`foreach`
     * body, which is not one. Reading the brace's scope owner is what tells
     * `match (…) { … } - 5` (a subtraction) from the discarded `-5;` statement
     * that follows `if (…) { … }`.
     */
    private function endsLeftOperand(File $phpcsFile, int $previous): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$previous]['code'];

        if ($code === T_CLOSE_CURLY_BRACKET) {
            return $this->closesValue($phpcsFile, $previous);
        }

        return in_array($code, self::OPERAND_END_TOKENS, true) === true
            || isset(Tokens::$magicConstants[$code]) === true;
    }

    /**
     * Whether a closing `}` ends an expression that yields a value. A brace
     * carrying no `scope_condition` closes an interpolation-style construct
     * (`${$name}`, `$object->{$name}`), which is always a value; a brace that
     * does carry one is a value only for the constructs named in
     * {@see self::VALUE_PRODUCING_SCOPE_OWNERS}.
     */
    private function closesValue(File $phpcsFile, int $closer): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$closer]['scope_condition']) === false) {
            return true;
        }

        $owner = $tokens[$tokens[$closer]['scope_condition']]['code'];

        return in_array($owner, self::VALUE_PRODUCING_SCOPE_OWNERS, true);
    }

    /**
     * Whether the operator sits directly inside a `catch (...)` clause, where a
     * `|`/`&` separates caught exception types rather than performing a bitwise
     * operation. The innermost enclosing parenthesis owned by a `T_CATCH` token
     * is a catch type list.
     */
    private function isCatchTypeSeparator(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $opener = array_key_last($tokens[$stackPtr]['nested_parenthesis']);

        return isset($tokens[$opener]['parenthesis_owner']) === true
            && $tokens[$tokens[$opener]['parenthesis_owner']]['code'] === T_CATCH;
    }

    /**
     * Rewrites the whitespace around a trailing operator so it leads the
     * continuation line: the space before it becomes a newline plus the
     * continuation indent, and the newline after it collapses to a single
     * space before the right-hand operand.
     */
    private function moveOperatorToNextLine(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $indent = $this->continuationIndent($phpcsFile, $stackPtr);

        $phpcsFile->fixer->beginChangeset();

        for ($i = ($stackPtr - 1); $tokens[$i]['code'] === T_WHITESPACE; $i--) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        for ($i = ($stackPtr + 1); $tokens[$i]['code'] === T_WHITESPACE; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->addContentBefore($stackPtr, $phpcsFile->eolChar . $indent);
        $phpcsFile->fixer->addContent($stackPtr, ' ');

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * The start of the whole statement the operator belongs to, escaping any
     * enclosing parentheses, brackets, or argument separators.
     * {@see \PHP_CodeSniffer\Files\File::findStartOfStatement()} halts at the
     * nearest enclosing `(`/`[`/`{` (all block openers) and returns the first
     * token *inside* it, so for an operator wrapped in a call, `if (...)`
     * condition, or array literal it yields a line already indented one or more
     * levels deep. Escaping outward past the grouping tokens reaches the true
     * root line, so the continuation indent lands one level past the statement —
     * not past the enclosing bracket.
     */
    private function outermostStatementStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $phpcsFile->findStartOfStatement($stackPtr);

        while ($start > 0) {
            $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

            if (
                $before === false
                || in_array($tokens[$before]['code'], self::STATEMENT_ANCHOR_ESCAPE_TOKENS, true) === false
            ) {
                break;
            }

            $start = $phpcsFile->findStartOfStatement($before);
        }

        return $start;
    }

    /**
     * Continuation indent for a wrapped operator: the leading whitespace of the
     * line the statement starts on, plus one four-space level. Basing it on the
     * statement's root line (not the operator's line) keeps every continuation
     * operator of a multi-line statement level, rather than stair-stepping.
     */
    private function continuationIndent(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $start = $this->outermostStatementStart($phpcsFile, $stackPtr);
        $line = $tokens[$start]['line'];
        $firstOnLine = $start;

        while ($firstOnLine > 0 && $tokens[$firstOnLine - 1]['line'] === $line) {
            $firstOnLine--;
        }

        $indent = '';

        if ($tokens[$firstOnLine]['code'] === T_WHITESPACE) {
            $indent = str_replace(["\r", "\n"], '', $tokens[$firstOnLine]['content']);
        }

        return $indent . '    ';
    }
}

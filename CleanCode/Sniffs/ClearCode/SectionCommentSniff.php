<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags section-labelling comments inside a function or method body.
 *
 * Clear Code: Encapsulate Each Concept in a Method (#13) —
 * docs/standards/clear-code-encapsulate-each-concept-in-a-method.md — is a
 * Tier 3 standard: where one concept ends and the next begins is a semantic
 * judgement. One slice of it is token-visible though, and it is the most
 * common textual footprint of an unextracted concept (#159): a standalone
 * comment on its own line that labels the block of statements after it.
 * `// validate the payload` followed by four statements is a method named
 * `validatePayload()` waiting to be extracted, after which the comment is
 * redundant.
 *
 * The shape reported is narrow, and every part of it is load-bearing:
 *
 * - A **self-contained single-line** comment. `//`, `#` and a one-line
 *   `/* … *\/` all qualify. A `/* … *\/` spanning several lines does not,
 *   because PHP_CodeSniffer splits it into one T_COMMENT per physical line —
 *   the opener carries no terminator and every continuation line arrives as a
 *   comment token that looks, on its own, exactly like a section label.
 * - **On its own line**, with no code before or after it there. A comment
 *   trailing a statement annotates that statement; it labels nothing.
 * - **The head of its run.** Two adjacent comment lines are one label for one
 *   block, so only the first reports. Reporting each line would multiply one
 *   extraction candidate into several.
 * - **At a statement boundary** — the token before it is `;`, `{` or `}`. This
 *   is what separates a label from a comment inside an array literal or an
 *   argument list, where the following "statement" is an element rather than a
 *   statement in the enclosing scope.
 * - **Inside a function body**, decided from the innermost enclosing scope
 *   rather than from whether a function appears anywhere in the chain: a
 *   comment inside an anonymous class or a `match` arm-list nested in a method
 *   still has T_FUNCTION in its conditions, and neither labels a block of
 *   statements.
 * - **Followed by a further statement in the same scope**, blank lines and
 *   further comments ignored. A comment with nothing but the closing brace
 *   after it introduces no block, so there is nothing to extract.
 *
 * Two comment shapes are excluded because a sibling standard owns them, and
 * reporting them here would double up:
 *
 * - Debt markers (`TODO`, `FIXME`, `HACK`, `XXX`) — Debt: Technical Debt
 *   (#138). Matched on word boundaries, so `// TODO: extract this` is excluded
 *   while a comment merely containing the letters is not.
 * - Auto-formatter directives (`@formatter:off`, `@formatter:on`,
 *   `prettier-ignore`) — Code Style: Linters & Config (#143). Matched as
 *   case-insensitive substrings, because these are literal spellings and the
 *   leading `@` has no word boundary before it.
 *
 * Both lists are public properties, so a consuming ruleset can extend either
 * without touching the other. PHP_CodeSniffer's own `phpcs:` annotations never
 * reach the sniff at all: the tokenizer gives them their own token types
 * (T_PHPCS_IGNORE and friends), not T_COMMENT. Docblocks are excluded the same
 * way — they tokenize as T_DOC_COMMENT_*, and this sniff registers T_COMMENT
 * only.
 *
 * Warnings, not errors, and detection-only. A comment can explain *why*
 * instead of labelling *what*, and a token stream cannot tell the two apart;
 * the rule points at extraction candidates rather than mandating a fix, and
 * extracting a block into a well-named method is a redesign with no mechanical
 * rewrite.
 *
 * Known limits, all of them deliberate silence rather than a guess:
 *
 * - A comment inside a PHP 8.4 property hook is not reported. The tokenizer
 *   gives a hook no scope of its own, so its body's comments carry the class
 *   as their innermost scope and read as class-level.
 * - A comment introducing a `case` body or an alternative-syntax block is not
 *   reported, because the token before it is `:` — which also ends a ternary
 *   arm and a return type, so admitting it would trade a rare miss for a
 *   plausible false positive on a warning-level advisory rule.
 * - An unrecognized enclosing scope resolves to "not a function body", so a
 *   construct this sniff has never seen stays silent rather than reporting on
 *   a guess.
 *
 * Fixtured in tests/fixtures/SectionCommentSniff/ and covered by
 * tests/Standards/SectionCommentTest.php.
 */
class SectionCommentSniff implements Sniff
{
    /**
     * Scopes that hold statements and therefore do not themselves decide
     * whether the comment sits in a function body — the search for the owning
     * scope walks straight through them. Everything else terminates the walk,
     * so a class, an interface, a trait, an enum, an anonymous class and a
     * `match` all resolve to "not a function body" without being enumerated.
     */
    private const TRANSPARENT_SCOPES = [
        T_IF,
        T_ELSE,
        T_ELSEIF,
        T_FOR,
        T_FOREACH,
        T_WHILE,
        T_DO,
        T_SWITCH,
        T_CASE,
        T_DEFAULT,
        T_TRY,
        T_CATCH,
        T_FINALLY,
    ];

    /**
     * The scopes that are a function body: a named function or method, a
     * closure, and an arrow function. T_FN is listed for completeness — an
     * arrow function's body is a single expression and holds no comment on its
     * own line — so that a future tokenizer change cannot silently drop it.
     */
    private const FUNCTION_LIKE = [
        T_FUNCTION,
        T_CLOSURE,
        T_FN,
    ];

    /**
     * The tokens a comment may follow and still be labelling the block after
     * it: the end of the previous statement, the start of the block, and the
     * end of a nested block.
     */
    private const STATEMENT_BOUNDARY = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * Debt markers that hand the comment to Debt: Technical Debt (#138)
     * instead. Matched case-insensitively on word boundaries. Configurable via
     * <property name="debtMarkers" type="array" .../>.
     *
     * @var array<string>
     */
    public array $debtMarkers = [
        'TODO',
        'FIXME',
        'HACK',
        'XXX',
    ];

    /**
     * Auto-formatter directives that hand the comment to Code Style: Linters &
     * Config (#143) instead. Matched as case-insensitive substrings.
     * Configurable via <property name="formatterDirectives" type="array" .../>.
     *
     * @var array<string>
     */
    public array $formatterDirectives = [
        '@formatter:off',
        '@formatter:on',
        'prettier-ignore',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_COMMENT];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $comment = $tokens[$stackPtr];

        if ($this->isSelfContainedSingleLine($comment['content']) === false) {
            return;
        }

        if ($this->isExcludedByASiblingStandard($comment['content']) === true) {
            return;
        }

        if ($this->ownsAFunctionBody($comment['conditions']) === false) {
            return;
        }

        if ($this->standsAloneOnItsLine($phpcsFile, $stackPtr, $comment['line']) === false) {
            return;
        }

        if ($this->headsItsRun($phpcsFile, $stackPtr, $comment['line']) === false) {
            return;
        }

        if ($this->followsAStatementBoundary($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->introducesABlock($phpcsFile, $stackPtr, $comment['conditions']) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'Section-labelling comment (%s): extract the block it introduces into a method named after it'
                . ' (see docs/standards/clear-code-encapsulate-each-concept-in-a-method.md)',
            $stackPtr,
            'Found',
            [trim($comment['content'])]
        );
    }

    /**
     * Whether the comment token is a whole comment on one line.
     *
     * `//` and `#` comments always are. A `/* … *\/` comment is only when the
     * same token carries its terminator: PHP_CodeSniffer splits a multi-line
     * one into a token per physical line, so the opener arrives without `*\/`
     * and each continuation line arrives without an opener at all. Both are
     * rejected — the first because the label would be reported against a
     * fragment, the second because a continuation line is indistinguishable
     * from a section label once it is read on its own.
     */
    private function isSelfContainedSingleLine(string $content): bool
    {
        $written = trim($content);

        if (str_starts_with($written, '//') === true || str_starts_with($written, '#') === true) {
            return true;
        }

        return str_starts_with($written, '/*') === true && str_ends_with($written, '*/') === true;
    }

    /**
     * Whether a sibling standard owns this comment.
     *
     * The two lists are matched differently on purpose. A debt marker is a
     * word, so `\b` keeps `// TODO: extract` out while leaving a comment that
     * merely contains those letters in scope. A formatter directive is a
     * literal spelling whose leading `@` has no word boundary before it, so it
     * is matched as a substring.
     */
    private function isExcludedByASiblingStandard(string $content): bool
    {
        foreach ($this->formatterDirectives as $directive) {
            if ($directive !== '' && stripos($content, $directive) !== false) {
                return true;
            }
        }

        $markers = array_filter($this->debtMarkers, static fn (string $marker): bool => $marker !== '');

        if ($markers === []) {
            return false;
        }

        $quoted = array_map(static fn (string $marker): string => preg_quote($marker, '/'), $markers);
        $pattern = '/\b(?:' . implode('|', $quoted) . ')\b/i';

        return preg_match($pattern, $content) === 1;
    }

    /**
     * Whether the innermost scope that decides where the comment sits is a
     * function body.
     *
     * The chain is walked from the inside out, past the control structures
     * that merely hold statements, and the first scope that is not one of
     * those is the answer. Reading "is T_FUNCTION anywhere in the chain?"
     * instead would report a comment labelling a member of an anonymous class
     * declared inside a method, and a comment above a `match` arm — neither of
     * which introduces a block of statements. A comment outside any function
     * (file level, or between class members) has no such scope and falls out
     * here.
     *
     * @param array<int, int|string> $conditions
     */
    private function ownsAFunctionBody(array $conditions): bool
    {
        foreach (array_reverse($conditions) as $scope) {
            if (in_array($scope, self::TRANSPARENT_SCOPES, true) === true) {
                continue;
            }

            return in_array($scope, self::FUNCTION_LIKE, true);
        }

        return false;
    }

    /**
     * Whether the comment has its line to itself — nothing but whitespace
     * before it there, and nothing at all after it.
     *
     * Only a one-line `/* … *\/` can have anything on either side: a `//` or
     * `#` comment runs to the end of its line by definition. The token before
     * is compared by its *end* line rather than its start line, so a token
     * spanning several lines cannot be mistaken for one that ends above the
     * comment.
     */
    private function standsAloneOnItsLine(File $phpcsFile, int $stackPtr, int $commentLine): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);

        if ($before === false || $this->endLine($tokens[$before]) === $commentLine) {
            return false;
        }

        $after = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        return $after === false || $tokens[$after]['line'] !== $commentLine;
    }

    /**
     * Whether the comment is the first line of its own comment run.
     *
     * Adjacent comment lines are one label for one block, so only the first
     * reports; a blank line ends the run, because a comment set apart from the
     * one above it labels its own block. Adjacency is measured against the
     * preceding comment's end line, so a multi-line block comment above still
     * counts as directly above.
     */
    private function headsItsRun(File $phpcsFile, int $stackPtr, int $commentLine): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);

        if ($before === false || isset(Tokens::$commentTokens[$tokens[$before]['code']]) === false) {
            return true;
        }

        return $this->endLine($tokens[$before]) !== ($commentLine - 1);
    }

    /**
     * Whether the comment follows the end of a statement, the start of a block
     * or the end of a nested block — the three places a label can stand.
     *
     * Any comments above are looked past, so a label separated from the code
     * by a blank line and a second comment is still judged on the code. A
     * comment preceded by anything else sits inside a statement — an array
     * element, an argument, a multi-line expression — where what follows is
     * not a statement in this scope.
     */
    private function followsAStatementBoundary(File $phpcsFile, int $stackPtr): bool
    {
        $boundary = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($boundary === false) {
            return false;
        }

        return in_array($phpcsFile->getTokens()[$boundary]['code'], self::STATEMENT_BOUNDARY, true);
    }

    /**
     * The last line a token puts anything on. PHP_CodeSniffer stores only the
     * line a token starts on, and several token types carry embedded newlines.
     *
     * A trailing newline is stripped first, because it belongs to the end of
     * the token's last line rather than to a further one — a `//` comment's
     * content always carries one, and counting it would place every such
     * comment a line below where it is written.
     *
     * @param array<string, mixed> $token
     */
    private function endLine(array $token): int
    {
        return ((int) $token['line'] + substr_count(rtrim((string) $token['content'], "\r\n"), "\n"));
    }

    /**
     * Whether at least one further statement follows in the comment's own
     * scope.
     *
     * Blank lines and any further comments are skipped — Tokens::$emptyTokens
     * covers both. The scope is compared by the whole conditions chain rather
     * than by testing for a closing brace: a scope's closer carries a
     * different chain from its body, so a comment with nothing but the closer
     * after it falls out here whatever shape that closer takes.
     *
     * @param array<int, int|string> $conditions
     */
    private function introducesABlock(File $phpcsFile, int $stackPtr, array $conditions): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false) {
            return false;
        }

        return $phpcsFile->getTokens()[$next]['conditions'] === $conditions;
    }
}

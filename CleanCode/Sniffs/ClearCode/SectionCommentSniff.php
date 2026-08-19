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
 * - **The head of its run.** Every *label* line with nothing but whitespace
 *   between it and the one above is part of the same run — a blank line
 *   between two label lines does not start a second label — and only the head
 *   reports. Reporting each line would multiply one extraction candidate into
 *   several. A comment the rule would not report in its own right is not a
 *   label line and does not absorb the one below it, so a label written under
 *   a debt marker, a formatter directive, a docblock or a comment trailing a
 *   statement is still the head of its own run.
 * - **At a statement boundary** — the token before it is `;`, `}`, or the
 *   opener of the block the comment stands in (`{`, or the `:` of a
 *   `case`/`default` arm or an alternative-syntax block). This is what
 *   separates a label from a comment inside an array literal or an argument
 *   list, where the following "statement" is an element rather than a
 *   statement in the enclosing scope.
 * - **Between two statements rather than inside one.** A `}` closes a
 *   statement only when the construct it belongs to is over there: a closure,
 *   an anonymous class and a `match` are expressions whose brace is followed by
 *   the rest of the expression, and a `do` block is followed by its `while`.
 *   The same holds read forwards — an `else`, `elseif`, `catch` or `finally`
 *   after the comment continues the construct above it rather than starting a
 *   statement. A comment in either position labels nothing.
 * - **Inside a function body**, decided from the innermost enclosing scope
 *   rather than from whether a function appears anywhere in the chain: a
 *   comment inside an anonymous class or a `match` arm-list nested in a method
 *   still has T_FUNCTION in its conditions, and neither labels a block of
 *   statements. Control structures — `if`, loops, `switch` arms, `try` — hold
 *   statements rather than deciding the question, so the walk passes through
 *   them to the function that owns them.
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
 * - Auto-formatter directives — Code Style: Linters & Config (#143). The
 *   spellings are the $formatterDirectives defaults below, deliberately not
 *   repeated here: written out in a comment they would trip
 *   CleanCode.CodeStyle.NoFormatterDirectives against this very file. Matched
 *   as case-insensitive substrings, because they are literal spellings whose
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
 * - A comment inside an arrow function carries the *enclosing* function as its
 *   innermost scope, because PHP_CodeSniffer leaves `T_FN` out of a token's
 *   `conditions` altogether. It is judged as a label of that enclosing body,
 *   which the rules above already decide correctly — an arrow function is an
 *   expression, so a comment inside one never stands between two statements.
 * - A comment above a `match` arm is not reported. An arm list is a
 *   comma-separated expression list, like an array literal, so the "block" a
 *   label there introduces is not a run of statements that can move into a
 *   method of its own. A `switch` arm, whose body *is* a run of statements,
 *   is reported.
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
     * scope walks straight through them, on to the scope outside.
     *
     * Family: every member of `PHP_CodeSniffer\Util\Tokens::$scopeOpeners`,
     * PHPCS's own register of the tokens a `scope_opener`/`scope_closer` pair
     * hangs off, plus `T_FN`, which the tokenizer gives that same pair in
     * `PHP::processAdditional()` while leaving it out of that array. That union
     * is every scope this walk can meet in a `conditions` chain, so it is the
     * family both this constant and FUNCTION_LIKE answer to, and it is
     * accounted for once, here. A member excluded from both is not thereby
     * unhandled: it ends the walk at "not a function body", which is the right
     * answer for a scope whose contents are not statements.
     *
     * - `T_IF` — included: its body is a run of statements inside the function
     *   that holds it.
     * - `T_ELSEIF` — included: a branch body, as `T_IF` is.
     * - `T_ELSE` — included: a branch body, as `T_IF` is.
     * - `T_FOR` — included: a loop body is a run of statements.
     * - `T_FOREACH` — included: a loop body, as `T_FOR` is.
     * - `T_WHILE` — included: a loop body, as `T_FOR` is.
     * - `T_DO` — included: a loop body, as `T_FOR` is.
     * - `T_SWITCH` — included: its body holds the arms, and a comment above an
     *   arm labels the arms that follow.
     * - `T_CASE` — included: an arm body is a run of statements. An enum's
     *   `case` is `T_ENUM_CASE` and opens no scope, so it cannot arrive here.
     * - `T_DEFAULT` — included: an arm body, as `T_CASE` is. A match arm's
     *   `default` is `T_MATCH_DEFAULT` and opens no scope.
     * - `T_TRY` — included: its body is a run of statements.
     * - `T_CATCH` — included: a handler body, as `T_TRY` is.
     * - `T_FINALLY` — included: a handler body, as `T_TRY` is.
     * - `T_FUNCTION`, `T_CLOSURE`, `T_FN` — excluded from this list because
     *   they end the walk with the answer "yes": FUNCTION_LIKE holds them.
     * - `T_CLASS` — excluded: a class body holds members, not statements, so a
     *   comment there labels no block to extract.
     * - `T_ANON_CLASS` — excluded: a class body, as `T_CLASS` is. Reaching the
     *   method around it instead is exactly the false positive the innermost
     *   scope prevents.
     * - `T_INTERFACE` — excluded: a body of bodyless signatures.
     * - `T_TRAIT` — excluded: a class body, as `T_CLASS` is.
     * - `T_ENUM` — excluded: a class body, as `T_CLASS` is.
     * - `T_MATCH` — excluded: an arm list is a comma-separated expression
     *   list, like an array literal, so what a label there introduces is not a
     *   run of statements.
     * - `T_NAMESPACE` — excluded: a braced namespace holds declarations, and a
     *   comment at that level labels no block.
     * - `T_DECLARE` — excluded: a directive block, at file level.
     * - `T_USE` — excluded: it opens a scope only as a trait-adaptation block,
     *   which holds adaptations rather than statements.
     * - `T_PROPERTY` — excluded: only the JavaScript tokenizer emits it, so no
     *   PHP source reaches it.
     * - `T_OBJECT` — excluded: only the JavaScript tokenizer emits it, so no
     *   PHP source reaches it.
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
     * The scopes that end the walk with "yes, a function body". The family is
     * TRANSPARENT_SCOPES's, accounted for in full there.
     *
     * `T_FN` is included but never consulted, for a reason that is a tokenizer
     * detail rather than a property of arrow functions: PHP_CodeSniffer does
     * not put `T_FN` in a token's `conditions` array at all, though it does
     * give the token a `scope_opener`/`scope_closer` pair. A comment written
     * inside an arrow function therefore arrives here carrying the *enclosing*
     * function as its innermost scope, and this entry decides nothing.
     *
     * That is not the same as "a comment inside an arrow function is never
     * reported". It is reported when it reads as a label of the enclosing
     * function's body — which is why an arrow function holding a nested scope
     * has a fixture of its own in passing.php, pinned by the same construct-
     * continuation rule that covers the shape without the arrow function. The
     * entry stays listed because the alternative is silence built on that
     * tokenizer detail: a PHPCS release that started recording `T_FN` in
     * `conditions` would otherwise resolve an arrow function to "not a function
     * body" without anything saying so.
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
     *
     * Family: the punctuation tokens PHPCS emits for the five characters that
     * can stand between one PHP construct and the next — `;`, `{`, `}`, `:`
     * and `,`. Every one is accounted for:
     *
     * - `T_SEMICOLON` — included: it ends the statement above the comment.
     * - `T_OPEN_CURLY_BRACKET` — included: the comment heads the block that
     *   brace opens. Listed by type as well as resolved through
     *   opensTheEnclosingScope(), because a bare `{ … }` block is a scope PHPCS
     *   records no owner for, so no `conditions` entry points at it.
     * - `T_CLOSE_CURLY_BRACKET` — included: a nested block ended above the
     *   comment, and the next statement belongs to this scope.
     * - `T_COLON` — excluded here, admitted by opensTheEnclosingScope()
     *   instead: it opens a `case`/`default` arm and an alternative-syntax
     *   block, but the same token also ends a ternary arm, a return type and a
     *   named argument, so only the colon that opens the comment's own scope
     *   can be admitted.
     * - `T_COMMA` — excluded: it separates the elements of an array literal,
     *   an argument list or a `match` arm list. What follows one is an element,
     *   not a statement, which is the false positive this rule is narrowed to
     *   avoid.
     */
    private const STATEMENT_BOUNDARY = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * The scopes whose closing brace leaves the statement around it unfinished,
     * so a comment after that brace stands inside one construct rather than
     * between two.
     *
     * Family: TRANSPARENT_SCOPES's, accounted for in full there, read for a
     * different question — not "does this scope hold statements?" but "is the
     * statement over once this scope closes?". Every member whose closer is a
     * brace answers yes except these four, and the two answers are independent:
     * `T_DO` is transparent *and* unfinished at its brace.
     *
     * - `T_CLOSURE` — excluded: a closure is an expression, so its brace is
     *   followed by the rest of the expression that holds it.
     * - `T_ANON_CLASS` — excluded: an anonymous class is an expression, as a
     *   closure is.
     * - `T_MATCH` — excluded: a `match` is an expression, as a closure is.
     * - `T_DO` — excluded: the `while` that ends the loop follows the brace.
     * - `T_IF`, `T_ELSEIF`, `T_TRY`, `T_CATCH` — included, though each *may*
     *   be continued by an `else`, `elseif`, `catch` or `finally`. Whether it
     *   is cannot be read off the brace, so it is read off what follows the
     *   comment instead, through CONTINUATION_KEYWORDS.
     * - `T_FN` — omitted: an arrow function's scope closes on the token that
     *   ends its expression — `;`, `,`, `)` — never on a brace, so it can never
     *   own the token this list is matched against.
     * - `T_OBJECT` and `T_PROPERTY` — omitted: PHP_CodeSniffer emits both from
     *   its JavaScript tokenizer only, so neither reaches a PHP file.
     * - Every other member — included: a declaration or a statement block is
     *   over at its closing brace.
     */
    private const CONTINUING_SCOPES = [
        T_CLOSURE,
        T_ANON_CLASS,
        T_MATCH,
        T_DO,
    ];

    /**
     * The keywords that continue the construct above them instead of starting a
     * statement, so a comment before one introduces nothing.
     *
     * Family: the four keywords PHP allows after a closed brace to extend the
     * construct that brace belongs to. `while` — the one other such keyword,
     * ending a `do` — is not here because it also opens a loop of its own; the
     * `do` it ends is read off the brace instead, through CONTINUING_SCOPES.
     * The alternative-syntax terminators (`endif`, `endforeach` and their
     * siblings) are not here either: each closes the scope the comment sits in,
     * so the comment is already last in its scope and reports nothing.
     */
    private const CONTINUATION_KEYWORDS = [
        T_ELSE,
        T_ELSEIF,
        T_CATCH,
        T_FINALLY,
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

        if ($this->headsItsRun($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->followsAStatementBoundary($phpcsFile, $stackPtr, $comment['conditions']) === false) {
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

        $markers = [];

        foreach ($this->debtMarkers as $marker) {
            if ($marker !== '') {
                $markers[] = preg_quote($marker, '/');
            }
        }

        if ($markers === []) {
            return false;
        }

        return preg_match('/\b(?:' . implode('|', $markers) . ')\b/i', $content) === 1;
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
     * A run is every label line with nothing but whitespace between it and the
     * one above — blank lines included, because a label written as a paragraph
     * and a label written as one block are the same label for the same block.
     * It reports once, at its first line: reporting each line would multiply
     * one extraction candidate into several.
     */
    private function headsItsRun(File $phpcsFile, int $stackPtr): bool
    {
        $before = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);

        return $before === false || $this->isALabelLine($phpcsFile, $before) === false;
    }

    /**
     * Whether the token is a line of the same label as the comment below it.
     *
     * Run membership is the sniff's own candidacy test, applied to the line
     * above: a comment only continues into the comment below it when this rule
     * could have reported it in its own right. Asking "is it comment-shaped?"
     * instead — the whole of Tokens::$commentTokens, which spans every docblock
     * fragment and every PHPCS annotation — silently drops a genuine label
     * written directly under a `// TODO`, under a formatter directive, under a
     * docblock or under a comment trailing a statement — the spellings of the
     * directives are the $formatterDirectives defaults, deliberately not
     * repeated here for the reason given on the class above. None of those four
     * is a label line, so none
     * of them takes the label below it into its run: a debt marker and a
     * formatter directive belong to the sibling standards that own them, a
     * docblock documents the thing after it, and a comment sharing a line with
     * code annotates that code.
     */
    private function isALabelLine(File $phpcsFile, int $stackPtr): bool
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        if ($token['code'] !== T_COMMENT) {
            return false;
        }

        if ($this->isSelfContainedSingleLine($token['content']) === false) {
            return false;
        }

        if ($this->isExcludedByASiblingStandard($token['content']) === true) {
            return false;
        }

        return $this->standsAloneOnItsLine($phpcsFile, $stackPtr, $token['line']);
    }

    /**
     * Whether the comment follows the end of a statement, the end of a nested
     * block, or the opening of the block it stands in — the places a label can
     * stand.
     *
     * Any comments above are looked past, so a label separated from the code
     * by a blank line and a second comment is still judged on the code. A
     * comment preceded by anything else sits inside a statement — an array
     * element, an argument, a multi-line expression — where what follows is
     * not a statement in this scope.
     *
     * @param array<int, int|string> $conditions
     */
    private function followsAStatementBoundary(File $phpcsFile, int $stackPtr, array $conditions): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundary = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($boundary === false) {
            return false;
        }

        if (in_array($tokens[$boundary]['code'], self::STATEMENT_BOUNDARY, true) === true) {
            return $this->finishesWhatItCloses($tokens, $boundary);
        }

        return $this->opensTheEnclosingScope($tokens, $boundary, $conditions);
    }

    /**
     * Whether the statement the boundary token belongs to is over at it.
     *
     * `;` and `{` always are: one ends a statement, the other opens the block
     * the comment heads. A `}` only is when the scope it closes is one the
     * statement does not outlive — see CONTINUING_SCOPES. A brace PHP_CodeSniffer
     * records no owner for is a bare `{ … }` block, which is over at its closer
     * like any other statement block.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function finishesWhatItCloses(array $tokens, int $boundary): bool
    {
        if ($tokens[$boundary]['code'] !== T_CLOSE_CURLY_BRACKET) {
            return true;
        }

        $owner = ($tokens[$boundary]['scope_condition'] ?? null);

        if ($owner === null) {
            return true;
        }

        return in_array($tokens[$owner]['code'], self::CONTINUING_SCOPES, true) === false;
    }

    /**
     * Whether the token is the opener of the scope the comment sits directly
     * inside.
     *
     * `{` covers most blocks by itself, but a `case`/`default` arm and an
     * alternative-syntax block open on `:` — a token that also ends a ternary
     * arm and a return type, and so cannot be admitted by its type alone.
     * Reading the opener off the comment's own innermost scope admits exactly
     * the colons that open the block the comment stands in, and no other. A
     * bare `{ … }` block, which PHP_CodeSniffer records as no scope at all, is
     * why `{` stays in STATEMENT_BOUNDARY rather than relying on this.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param array<int, int|string> $conditions
     */
    private function opensTheEnclosingScope(array $tokens, int $boundary, array $conditions): bool
    {
        if ($conditions === []) {
            return false;
        }

        $owner = array_key_last($conditions);

        return ($tokens[$owner]['scope_opener'] ?? null) === $boundary;
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
     * A keyword that continues the construct above the comment carries that
     * same chain — an `else` sits in the scope its `if` sits in — so it is
     * excluded by name first. Between an `if` block and its `else` the comment
     * stands inside one statement, not before a new one.
     *
     * @param array<int, int|string> $conditions
     */
    private function introducesABlock(File $phpcsFile, int $stackPtr, array $conditions): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        if (in_array($tokens[$next]['code'], self::CONTINUATION_KEYWORDS, true) === true) {
            return false;
        }

        return $tokens[$next]['conditions'] === $conditions;
    }
}

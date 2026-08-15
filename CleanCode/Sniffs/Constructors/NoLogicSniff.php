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
 *   a plain `=` assignment operator at its top level whose target is a direct
 *   property chain on `$this` (`$this->foo = …;`, `$this->arr[] = …;`,
 *   `$this->cfg['k'] = …;`). The right-hand side is not inspected, so defaulting
 *   with `??` or a ternary (`$this->foo = $foo ?? 0;`) stays compliant.
 * - a **`parent::__construct(…)` call** — delegating to the parent
 *   constructor is assignment, not logic. The statement has to be *exactly*
 *   that call: a real argument list whose closing parenthesis is the last thing
 *   before the semicolon, so trailing logic (`parent::__construct($a) or
 *   $this->boot();`, `parent::__construct($a)->extra();`) is still flagged, and
 *   an argument list that actually invokes — the first-class callable
 *   `parent::__construct(...)` only builds a Closure, never runs the parent
 *   constructor, and is flagged too.
 *
 * Everything else is flagged at the statement's first token:
 * control structures (`if`/`for`/`foreach`/`while`/`do`/`switch`/`try`, in both
 * the brace and the `:`/`endif;` alternative syntax), `match`, `throw`, method
 * and function calls, increments, compound assignments (`+=`, `.=`, `??=` read
 * the property before writing it, so they are computation rather than plain
 * assignment), assignments whose target is not a property (a local variable is
 * intermediate computation, not object state), and assignments whose target
 * invokes something, because it runs on every instantiation — a call
 * parenthesis (`$this->make()->x = …`, `$this->items[$this->key()] = …`), an
 * invoking keyword that needs no parenthesis (a backtick shell execution, a
 * `new` or `clone`), or a complex interpolation, which can hide a call inside a
 * string PHPCS keeps opaque (`$this->items["{$this->key()}"] = …`). A
 * parenthesis that merely *groups* invokes nothing and stays compliant
 * (`$this->items[($this->a + $this->b)] = …`).
 *
 * A target that *writes* runs on every instantiation for the same reason, so an
 * assignment operator or an increment inside one is flagged wherever it hides
 * (`$this->items[$this->total += 1] = …`, `$this->items[$this->total++] = …`,
 * `$this->items[$this->a = $this->b] = …`). Reading those same properties to
 * compute a key writes nothing and stays compliant.
 *
 * Statements nested inside a flagged control structure are not examined
 * separately, and a chained construct (`if … elseif … else`,
 * `try … catch … finally`, `do … while`) is reported once at its opening
 * keyword.
 *
 * Detection only: moving logic out of a constructor is a refactor — the code
 * has to land somewhere deliberate (a named constructor, a factory, or a
 * collaborator object). A token-based fixer cannot make that decision, so no
 * auto-fix is offered.
 */
class NoLogicSniff implements Sniff
{
    /**
     * Statement-opening tokens whose construct is delimited by its own scope
     * rather than by a semicolon. A statement that starts with one of these
     * ends at the scope PHPCS recorded for it, not at the next `;` — scanning
     * for a semicolon instead would stop inside the construct's body.
     */
    private const BLOCK_STATEMENT_TOKENS = [
        T_IF,
        T_ELSEIF,
        T_ELSE,
        T_FOR,
        T_FOREACH,
        T_WHILE,
        T_DO,
        T_SWITCH,
        T_TRY,
        T_CATCH,
        T_FINALLY,
        T_DECLARE,
        T_FUNCTION,
        T_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_ENUM,
    ];

    /**
     * Keywords that continue a construct PHPCS scopes as a separate clause
     * (`if … elseif … else`, `try … catch … finally`). They are consumed into
     * the statement they continue so the construct is reported once, at its
     * opening keyword.
     */
    private const CONTINUATION_KEYWORDS = [
        T_ELSEIF,
        T_ELSE,
        T_CATCH,
        T_FINALLY,
    ];

    /**
     * The closing keywords of the alternative control-structure syntax. PHPCS
     * records one of these as a clause's `scope_closer`, and PHP requires a
     * `;` after it, which belongs to the same statement.
     */
    private const ALTERNATIVE_SYNTAX_CLOSERS = [
        T_ENDIF,
        T_ENDFOR,
        T_ENDFOREACH,
        T_ENDWHILE,
        T_ENDSWITCH,
        T_ENDDECLARE,
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
     * The string tokens PHPCS hands over as one opaque token — one per physical
     * line for a multi-line literal — without tokenising what is interpolated
     * inside them. A call spelled into one of these is invisible to a token
     * scan, so its text has to be read instead. Mirrors the STRING_TOKENS
     * convention in the sibling UnusedPrivateElementsSniff, minus the nowdoc and
     * single-quoted forms, neither of which interpolates.
     */
    private const INTERPOLATABLE_STRING_TOKENS = [
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    /**
     * Tokens that run code on their own, with no parenthesis for the call scan
     * below to classify. A backtick executes a shell command; `new` and `clone`
     * run a constructor or `__clone()` and both have parenthesis-free spellings
     * (`new Foo;`, `new class {…}`, `clone $obj`); the remaining keywords are
     * PHP's expression-level constructs that evaluate or emit something, each of
     * which also has a form without parentheses (`include 'x.php'`, `print $x`,
     * `throw $e`, `yield $v`).
     */
    private const INVOKING_TOKENS = [
        T_BACKTICK,
        T_NEW,
        T_CLONE,
        T_EVAL,
        T_EXIT,
        T_PRINT,
        T_THROW,
        T_YIELD,
        T_YIELD_FROM,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,
    ];

    /**
     * The tokens that write, on top of PHPCS's own assignment family. A prefix
     * or postfix `++`/`--` is one token either way, so the pair covers both
     * spellings.
     *
     * The family itself is read from `Tokens::$assignmentTokens` rather than
     * respelled here, so an operator PHP adds later is rejected the day PHPCS
     * tokenises it, instead of waiting for this list to be noticed.
     */
    private const WRITING_TOKENS = [
        T_INC,
        T_DEC,
    ];

    /**
     * The one member of PHPCS's assignment family that writes nothing. `=>`
     * binds a key to a value inside an array literal — `$this->items[[1 =>
     * $this->a][1]]` reads that literal and discards it — so it is an operand
     * separator here, not a write, and is exempted from the rejection above.
     */
    private const NON_WRITING_ASSIGNMENT_TOKENS = [
        T_DOUBLE_ARROW,
    ];

    /**
     * The tokens after which an open parenthesis can only be grouping, never a
     * call: operators, brackets, and separators, none of which can end an
     * operand. Everything else — a name, a variable, a closing bracket, a
     * construct keyword — either invokes or is the head of something that does.
     *
     * Enumerated as the grouping side on purpose. A missing entry makes a
     * grouping parenthesis read as a call and over-reports, which is the safe
     * direction; enumerating the *call* side instead would let an unlisted
     * spelling invoke unseen. The PHPCS token groups are not reused because
     * their membership is narrower than their names suggest — `Tokens::$operators`
     * carries no `T_STRING_CONCAT`, `T_BOOLEAN_NOT`, `T_BITWISE_NOT` or ternary
     * token — so the list is spelled out and pinned by fixtures.
     *
     * An assignment operator is not among them, and cannot be: the scan ends at
     * the statement's own `=` and rejects a nested one before either reaches a
     * parenthesis, so no parenthesis this check ever sees has one in front of
     * it. Listing one would be an entry no fixture could reach.
     */
    private const GROUPING_PARENTHESIS_PRECEDERS = [
        // Arithmetic and bitwise.
        T_PLUS,
        T_MINUS,
        T_MULTIPLY,
        T_DIVIDE,
        T_MODULUS,
        T_POW,
        T_BITWISE_AND,
        T_BITWISE_OR,
        T_BITWISE_XOR,
        T_BITWISE_NOT,
        T_SL,
        T_SR,
        T_STRING_CONCAT,
        // Comparison.
        T_IS_EQUAL,
        T_IS_NOT_EQUAL,
        T_IS_IDENTICAL,
        T_IS_NOT_IDENTICAL,
        T_IS_GREATER_OR_EQUAL,
        T_IS_SMALLER_OR_EQUAL,
        T_GREATER_THAN,
        T_LESS_THAN,
        T_SPACESHIP,
        // Logical.
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_BOOLEAN_NOT,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
        // Conditional.
        T_INLINE_THEN,
        T_INLINE_ELSE,
        T_COALESCE,
        // Type checks and error control.
        T_INSTANCEOF,
        T_ASPERAND,
        // Casts, each a single token, so the parenthesis after one is grouping.
        T_INT_CAST,
        T_DOUBLE_CAST,
        T_STRING_CAST,
        T_ARRAY_CAST,
        T_OBJECT_CAST,
        T_BOOL_CAST,
        T_UNSET_CAST,
        T_BINARY_CAST,
        // Openers and separators.
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_CURLY_BRACKET,
        T_COMMA,
        T_DOUBLE_ARROW,
    ];

    /**
     * The token attributes that record where a group opened at a token ends,
     * in the order they are consulted.
     */
    private const GROUP_CLOSER_KEYS = [
        'parenthesis_closer',
        'bracket_closer',
        'scope_closer',
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

        // A constructor is a method of an object-oriented container. A free
        // function named __construct is legal PHP but not a constructor, so its
        // innermost enclosing scope must be a class/trait/enum/interface —
        // mirrors the guard convention in the sibling DisallowStaticMembersSniff.
        $conditions = $tokens[$stackPtr]['conditions'];

        if ($conditions === [] || in_array(end($conditions), Tokens::$ooScopeTokens, true) === false) {
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
                && $this->isParentConstructorCall($phpcsFile, $statementStart, $statementEnd) === false
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
     * $start, extended across the continuation clauses (`elseif`/`else`/
     * `catch`/`finally`, and the trailing `while (...);` of a `do … while`)
     * that PHPCS scopes separately, so a chained construct is reported once.
     */
    private function endOfStatement(File $phpcsFile, int $start, int $limit): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $this->endOfClause($phpcsFile, $start, $limit);

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), $limit, true);

            if ($next === false) {
                break;
            }

            $code = $tokens[$next]['code'];

            if (in_array($code, self::CONTINUATION_KEYWORDS, true)) {
                $end = $this->endOfClause($phpcsFile, $next, $limit);

                continue;
            }

            // The `while (...);` tail of a `do … while` carries the loop
            // condition only (no scope of its own).
            if (
                $code === T_WHILE
                && $tokens[$start]['code'] === T_DO
                && isset($tokens[$next]['scope_opener']) === false
            ) {
                $end = $this->endOfSimpleStatement($phpcsFile, $next, $limit);

                continue;
            }

            break;
        }

        return $end;
    }

    /**
     * The last token of one clause of a statement — the whole statement when it
     * has no continuation.
     *
     * A block statement ends at the scope PHPCS recorded for it. Three shapes
     * need more than that closer on its own:
     *
     * - the alternative syntax closes on `endif`/`endforeach`/… , and the `;`
     *   PHP requires after that keyword belongs to the same statement;
     * - under the alternative syntax a clause's `scope_closer` is the *next*
     *   clause's keyword, so the clause ends one token earlier and the
     *   continuation walk picks that keyword up;
     * - the `else` of a two-word `else if` carries no scope of its own — the
     *   `if` after it does.
     *
     * Everything else is a semicolon-terminated statement.
     */
    private function endOfClause(File $phpcsFile, int $start, int $limit): int
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$start]['code'];

        // A free `{ … }` block: PHPCS gives it a bracket pair, not a scope.
        if ($code === T_OPEN_CURLY_BRACKET) {
            return $this->groupCloser($tokens, $start, $limit);
        }

        if (in_array($code, self::BLOCK_STATEMENT_TOKENS, true) === false) {
            return $this->endOfSimpleStatement($phpcsFile, $start, $limit);
        }

        if (isset($tokens[$start]['scope_closer']) === false) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($start + 1), $limit, true);

            if ($next !== false && in_array($tokens[$next]['code'], self::BLOCK_STATEMENT_TOKENS, true)) {
                return $this->endOfClause($phpcsFile, $next, $limit);
            }

            return $this->endOfSimpleStatement($phpcsFile, $start, $limit);
        }

        $closer = $tokens[$start]['scope_closer'];
        $closerCode = $tokens[$closer]['code'];

        if (in_array($closerCode, self::ALTERNATIVE_SYNTAX_CLOSERS, true)) {
            $semicolon = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), $limit, true);

            if ($semicolon !== false && $tokens[$semicolon]['code'] === T_SEMICOLON) {
                return $semicolon;
            }

            return $closer;
        }

        if (in_array($closerCode, self::CONTINUATION_KEYWORDS, true)) {
            return ($closer - 1);
        }

        return $closer;
    }

    /**
     * The position of the semicolon that terminates the statement at $start,
     * or $limit when the statement is not terminated inside the body.
     *
     * Every group opened along the way — an argument list, an array literal, a
     * closure, an arrow function, a `match` block, an anonymous class — is
     * jumped over, so only a semicolon at the statement's own level ends it.
     */
    private function endOfSimpleStatement(File $phpcsFile, int $start, int $limit): int
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = $start; $ptr <= $limit; $ptr++) {
            $ptr = $this->groupCloser($tokens, $ptr, $limit);

            if ($tokens[$ptr]['code'] === T_SEMICOLON) {
                return $ptr;
            }
        }

        return $limit;
    }

    /**
     * Where the group opened at $ptr closes, or $ptr itself when the token
     * opens nothing. A closing token carries the same attribute pointing at
     * itself, which the forward comparison rejects.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function groupCloser(array $tokens, int $ptr, int $limit): int
    {
        foreach (self::GROUP_CLOSER_KEYS as $key) {
            if (isset($tokens[$ptr][$key]) && $tokens[$ptr][$key] > $ptr) {
                return min($tokens[$ptr][$key], $limit);
            }
        }

        return $ptr;
    }

    /**
     * A statement is a property assignment when it begins with `$this`, accesses
     * a property via `->`, and carries a plain `=` operator at bracket depth 0
     * whose target is a direct property chain on `$this`.
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

        return $this->hasPlainAssignmentTarget($phpcsFile, $start, $end);
    }

    /**
     * Whether the statement carries a plain `=` operator at bracket depth 0
     * whose target — everything to its left — is a direct property chain on
     * `$this` with no call.
     *
     * The scan runs left-to-right and stops at that first depth-0 `=`, so the
     * right-hand side is never inspected (a call or `??`/ternary default there
     * stays compliant). Anything in the target that *writes*, however, runs on
     * every instantiation and is rejected: an assignment operator other than the
     * statement's own, or an increment, in any position the target admits —
     * `$this->items[$this->total += 1] = …`, `$this->items[$this->total++] = …`,
     * `$this->items[$this->a = $this->b] = …`. See writes() for the set.
     *
     * Anything that *invokes* is rejected for the same reason, in each of the
     * three spellings that reach here:
     *
     * - a **call parenthesis** (`$this->make()->x = …`,
     *   `$this->items[$this->key()] = …`). A parenthesis that only *groups*
     *   (`$this->items[($this->a + $this->b)] = …`) invokes nothing and stays
     *   compliant, so each one is classified by what precedes it;
     * - an **invoking token** carrying no parenthesis of its own — a backtick
     *   shell execution, a `new`/`clone`, or one of PHP's expression keywords;
     * - a **complex interpolation**, `{$…}` or `${…}`, inside a double-quoted
     *   string or a heredoc (`$this->items["{$this->key()}"] = …`). PHPCS
     *   collapses an interpolated string into one opaque token, so a call
     *   spelled inside it surfaces no parenthesis at all.
     *
     * Array-subscript writes to this object's own properties (`$this->arr[] =
     * …`, `$this->cfg['k'] = …`) invoke nothing and stay compliant.
     */
    private function hasPlainAssignmentTarget(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $depth = 0;

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            $code = $tokens[$ptr]['code'];

            // The statement's own assignment operator: the target ends here, and
            // it is plain. Tested before the rejection below, which every other
            // assignment operator — and this one nested inside a bracket — falls
            // into.
            if ($code === T_EQUAL && $depth === 0) {
                return true;
            }

            if ($this->writes($code) === true) {
                return false;
            }

            if (in_array($code, self::INVOKING_TOKENS, true)) {
                return false;
            }

            if ($code === T_OPEN_PARENTHESIS && $this->isGroupingParenthesis($phpcsFile, $ptr, $start) === false) {
                return false;
            }

            if (
                in_array($code, self::INTERPOLATABLE_STRING_TOKENS, true)
                && $this->hasComplexInterpolation($tokens[$ptr]['content']) === true
            ) {
                return false;
            }

            if (in_array($code, self::BRACKET_OPENERS, true)) {
                $depth++;
            } elseif (in_array($code, self::BRACKET_CLOSERS, true)) {
                $depth--;
            }
        }

        return false;
    }

    /**
     * Whether the token writes something.
     *
     * A target may compute — `$this->items[$this->a + $this->b] = …` reads two
     * properties and throws the sum away — but it may not *write*, because that
     * write runs on every instantiation, which is the whole of what this sniff
     * exists to catch. Every assignment operator writes (`$this->items[$this->a
     * = $this->b] = …`, `[$this->total += 1]`), and so does an increment or a
     * decrement in either position (`[$this->total++]`, `[--$this->total]`).
     *
     * PHPCS's assignment family is consulted directly instead of being
     * respelled, so the set stays complete as PHP grows; only `=>`, which
     * separates operands rather than writing, is taken back out of it. The one
     * member PHP itself cannot spell, `T_ZSR_EQUAL` (`>>>=`, from PHPCS's
     * JavaScript tokeniser), stays in harmlessly — no PHP source produces it.
     *
     * @param int|string $code
     */
    private function writes($code): bool
    {
        if (in_array($code, self::WRITING_TOKENS, true)) {
            return true;
        }

        return isset(Tokens::$assignmentTokens[$code])
            && in_array($code, self::NON_WRITING_ASSIGNMENT_TOKENS, true) === false;
    }

    /**
     * Whether the open parenthesis at $ptr only groups a sub-expression, rather
     * than opening a call's argument list.
     *
     * A parenthesis calls whatever precedes it, so the token before it decides:
     * a name, a variable, or a closing bracket ends an operand and makes the
     * parenthesis an invocation (`key(…)`, `$fn(…)`, `(fn() => …)()`,
     * `$fns['k'](…)`, `$this->{$m}(…)`), and a construct keyword heads one
     * (`match(…)`, `fn(…)`, `eval(…)`). Only after an operator, an opening
     * bracket, or a separator can nothing be called, which is the list this
     * checks — see GROUPING_PARENTHESIS_PRECEDERS for why it is the grouping
     * side that is enumerated.
     */
    private function isGroupingParenthesis(File $phpcsFile, int $ptr, int $start): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), $start, true);

        // $start is the statement's own `$this`, so a parenthesis in the target
        // always has a token before it. Read an unexpected miss as a call.
        if ($previous === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        return in_array($tokens[$previous]['code'], self::GROUPING_PARENTHESIS_PRECEDERS, true);
    }

    /**
     * Whether one interpolatable string token's raw text carries a *complex*
     * interpolation — `{$…}` or `${…}`.
     *
     * Complex is the whole family that can hold a call: `"{$this->key()}"`,
     * `"${$this->key()}"`. Simple interpolation cannot — `"$key"` and
     * `"$this->prefix"` admit no parentheses — so it stays compliant, spelling
     * the same read as the bare `$this->arr[$this->prefix]` that already is.
     *
     * The test is presence, not a call found inside: PHPCS hands the string over
     * as text rather than tokens, and splits a multi-line one at every physical
     * line, so matching a call in it would mean re-lexing PHP across token
     * boundaries. Rejecting the syntax that can carry a call is the conservative
     * side of that trade, and the compliant spelling of a genuinely
     * call-free key is the direct one the sniff already accepts.
     *
     * Only `\\` and `\$` change whether what follows opens an interpolation, so
     * dropping those two pairs left to right leaves the text PHP really
     * interpolates.
     *
     * On that text, a backslash still sitting before `{$` suppresses the
     * complex opener: PHP reads `"\{$this->key()}"` as a literal `\{`, the
     * *simple* interpolation `$this->key`, and a literal `()`, so the call never
     * runs. Parity is what decides, and the pair-strip already normalises it —
     * an odd number of backslashes leaves one behind and suppresses, an even
     * number leaves none and interpolates for real. Both directions were read
     * off the PHP runtime, at one through four backslashes.
     *
     * `${` needs no such check: a backslash immediately before it *is* the `\$`
     * escape the strip already removed, which is why `"\${key()}"` is literal
     * text while `"\\${key()}"` interpolates.
     *
     * Falling back to the raw text keeps a failed strip on the conservative
     * side: escapes left in place can only make this read *more* of the string
     * as interpolation, never less, so nothing escapes detection by it. A failed
     * match is read the same way, as an interpolation present.
     */
    private function hasComplexInterpolation(string $content): bool
    {
        $unescaped = preg_replace('/\\\\[\\\\$]/', '', $content) ?? $content;

        if (preg_match('/(?<!\\\\)\{\$/', $unescaped) !== 0) {
            return true;
        }

        return str_contains($unescaped, '${');
    }

    /**
     * Whether the statement running from $start to $end is exactly a
     * `parent::__construct(…)` call.
     *
     * The argument list has to be real — `parent::__construct;` is a bare
     * reference and `parent::__construct(...)` a first-class callable, neither
     * of which delegates — and its closing parenthesis has to be the last thing
     * in the statement, so anything trailing the call (`… or $this->boot();`,
     * `…->initializeExtra();`) leaves this false and the statement is reported.
     */
    private function isParentConstructorCall(File $phpcsFile, int $start, int $end): bool
    {
        $open = $this->parentConstructorParenthesis($phpcsFile, $start, $end);

        if ($open === null || $this->isFirstClassCallable($phpcsFile, $open) === true) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        $after = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            ($tokens[$open]['parenthesis_closer'] + 1),
            ($end + 1),
            true
        );

        return $after === false || $tokens[$after]['code'] === T_SEMICOLON;
    }

    /**
     * Whether the argument list opened at $openerPtr is PHP 8.1's first-class
     * callable syntax — a list that is exactly one ellipsis.
     *
     * `parent::__construct(...)` builds a Closure over the parent constructor
     * and discards it; the parent constructor never runs, so the statement
     * delegates nothing and is a call-shaped lookalike like the rest of this
     * family. It is the only spelling of an argument list that does not invoke:
     * empty (`()`), positional, named (`(a: 1)`) and spread (`(...$args)`)
     * lists all call the parent for real, which is why the token *after* the
     * ellipsis is checked rather than the ellipsis being taken alone — a spread
     * carries its argument there, a first-class callable carries nothing.
     *
     * The sibling DisallowCountInLoopExpressionSniff::isFirstClassCallable()
     * makes the same distinction for the same reason.
     *
     * $openerPtr always carries a `parenthesis_closer`: it comes from
     * parentConstructorParenthesis(), which returns null without one.
     */
    private function isFirstClassCallable(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$openerPtr]['parenthesis_closer'];

        $ellipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), $closer, true);

        if ($ellipsis === false || $tokens[$ellipsis]['code'] !== T_ELLIPSIS) {
            return false;
        }

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsis + 1), $closer, true) === false;
    }

    /**
     * The position of the `(` that opens `parent::__construct(…)`'s argument
     * list, or null when the statement does not open with that call.
     */
    private function parentConstructorParenthesis(File $phpcsFile, int $start, int $end): ?int
    {
        $method = $this->parentConstructorName($phpcsFile, $start, $end);

        if ($method === null) {
            return null;
        }

        $tokens = $phpcsFile->getTokens();
        $open = $phpcsFile->findNext(Tokens::$emptyTokens, ($method + 1), ($end + 1), true);

        if ($open === false || $tokens[$open]['code'] !== T_OPEN_PARENTHESIS) {
            return null;
        }

        return isset($tokens[$open]['parenthesis_closer']) === true ? $open : null;
    }

    /**
     * The position of the `__construct` name in a `parent::__construct` prefix,
     * or null when the statement does not open with one. PHP method names are
     * case-insensitive, so the name is compared case-insensitively too.
     */
    private function parentConstructorName(File $phpcsFile, int $start, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] !== T_PARENT) {
            return null;
        }

        $colon = $phpcsFile->findNext(Tokens::$emptyTokens, ($start + 1), ($end + 1), true);

        if ($colon === false || $tokens[$colon]['code'] !== T_DOUBLE_COLON) {
            return null;
        }

        $method = $phpcsFile->findNext(Tokens::$emptyTokens, ($colon + 1), ($end + 1), true);

        if ($method === false || $tokens[$method]['code'] !== T_STRING) {
            return null;
        }

        return strtolower($tokens[$method]['content']) === '__construct' ? $method : null;
    }
}

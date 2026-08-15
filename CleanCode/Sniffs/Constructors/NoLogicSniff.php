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
 * contains a call (`$this->make()->x = …`, `$this->items[$this->key()] = …`) —
 * the call runs on every instantiation. Statements nested inside a flagged
 * control structure are not examined separately, and a chained construct
 * (`if … elseif … else`, `try … catch … finally`, `do … while`) is reported
 * once at its opening keyword.
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
     * stays compliant). A call parenthesis in the target, however, executes
     * logic on every instantiation (`$this->make()->x = …`,
     * `$this->items[$this->key()] = …`) and is rejected; array-subscript writes
     * to this object's own properties (`$this->arr[] = …`, `$this->cfg['k'] = …`)
     * carry no parenthesis and stay compliant.
     */
    private function hasPlainAssignmentTarget(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $depth = 0;

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_OPEN_PARENTHESIS) {
                return false;
            }

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

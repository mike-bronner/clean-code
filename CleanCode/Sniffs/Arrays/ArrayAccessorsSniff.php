<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Arrays: Array Accessors" standard: values are read with
 * `data_get()` rather than by direct element (`$array['key']`) or property
 * (`$object->property`) access, so a missing element falls back instead of
 * erroring, any shape (array, collection, object, model) resolves without type
 * checks, and nested paths read as one expression.
 *
 * A violation is reported once per accessor chain, at the variable the chain is
 * rooted in: `$payload['address']['city']` and `$order->customer->name` are one
 * diagnostic each, not one per link, because a single `data_get()` call
 * replaces the whole chain.
 *
 * Only *read* access is flagged. These constructs are deliberately left alone:
 *
 * - **Write-side access** (`$array['key'] = $value`, `$array['key'] .= $more`,
 *   `$array[] = $value`, `$object->property = $value`, `++$array['key']`) —
 *   `data_get()` reads; it cannot stand in for an assignment target. This
 *   covers every shape the target can take: a destructuring pattern
 *   (`[$array['a'], $array['b']] = $source`, `list($object->property) =
 *   $source`), a `foreach` value, key, or pattern target (`foreach ($rows as
 *   $out['key'] => $value)`), and a reference bind (`$ref = &$array['key']`,
 *   which `data_get()`'s by-value return could not preserve).
 * - **Existence checks** (`isset()`, `empty()`, `unset()`,
 *   `array_key_exists()`) — these already handle the missing-element case that
 *   `data_get()`'s fallback exists to solve.
 * - **Array literals** (`['key' => $value]`) — a declaration, not a read. Only
 *   the literal's own syntax is exempt: an accessor used as a literal's key or
 *   value (`[$row['id'] => $row['name']]`) is read to build it, so both sides
 *   are reported.
 * - **`$this`-rooted access** (`$this->property`, `$this->config['key']`) — an
 *   object's own state is known to exist; no fallback or type check applies.
 * - **Method calls** (`$object->method()`, `$object?->method()`, and the
 *   dynamic `$object->{$name}()`) — an invocation of the object's own API,
 *   which `data_get()` does not resolve. A dynamic *property*
 *   (`$object->{$name}`) is a read and is flagged.
 *
 * Three blind spots follow from the token stream and are accepted: accessors
 * embedded in interpolated strings ("{$array['key']}") are a single string
 * token to PHP_CodeSniffer and cannot be inspected, a chain rooted in
 * anything other than a variable (`foo()['key']`, `self::CONSTANTS['key']`) has
 * no variable to report against, and an argument bound to a by-reference
 * parameter (`bump($array['key'])` where `function bump(&$value)`) is a write
 * that only the callee's signature reveals — a sniff sees one file, so the call
 * site is indistinguishable from a by-value read.
 *
 * Detection only. A fixer would have to rewrite `$array['key']['nested']` to
 * `data_get($array, 'key.nested')`, which is unsafe on two counts: the dotted
 * path silently changes meaning when a key itself contains a dot, and
 * `data_get()` is a Laravel helper, so emitting it into a file outside a
 * Laravel application produces code that does not run. Both make the rewrite a
 * judgement call rather than a mechanical one, so the replacement is left to
 * the developer.
 */
class ArrayAccessorsSniff implements Sniff
{
    /**
     * Error code => message, keyed by the code `readAccessCode()` resolves.
     */
    private const MESSAGES = [
        'DirectArrayAccess' => 'Direct array element access on %s is not allowed;'
            . ' use data_get(%s, ...) so a missing element falls back instead of erroring',
        'DirectPropertyAccess' => 'Direct property access on %s is not allowed;'
            . ' use data_get(%s, ...) so any object shape resolves without type checks',
    ];

    /**
     * The property-access operators. `?->` is included: it guards against a
     * null *object*, not against a missing property, so the standard's fallback
     * argument applies to it just as it does to `->`.
     */
    private const OBJECT_OPERATORS = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];

    /**
     * The closers of the two constructs a destructuring pattern is written
     * with: a short array (`[$a, $b] = $source`) and `list($a, $b) = $source`.
     */
    private const PATTERN_CLOSERS = [T_CLOSE_SHORT_ARRAY, T_CLOSE_PARENTHESIS];

    /**
     * Constructs whose parentheses answer "does this exist?" — the question
     * `data_get()`'s fallback exists to make unnecessary. Matched by name so
     * the language constructs and the function are handled the same way.
     *
     * @var array<string, true>
     */
    private const EXISTENCE_CHECKS = [
        'isset' => true,
        'empty' => true,
        'unset' => true,
        'array_key_exists' => true,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_VARIABLE];
    }

    /**
     * @param int $stackPtr
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // PHP variable names are case-sensitive, so only the exact `$this`
        // names the current instance.
        if ($tokens[$stackPtr]['content'] === '$this') {
            return;
        }

        if ($this->isChainMember($phpcsFile, $stackPtr) === true) {
            return;
        }

        $accessorPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($accessorPtr === false) {
            return;
        }

        $errorCode = $this->readAccessCode($phpcsFile, $accessorPtr);

        if ($errorCode === null) {
            return;
        }

        if ($this->isWriteTarget($phpcsFile, $stackPtr) === true) {
            return;
        }

        if ($this->isInsideExistenceCheck($phpcsFile, $stackPtr) === true) {
            return;
        }

        $variable = $tokens[$stackPtr]['content'];

        $phpcsFile->addError(
            self::MESSAGES[$errorCode],
            $stackPtr,
            $errorCode,
            [$variable, $variable]
        );
    }

    /**
     * Whether the variable names a property inside an accessor chain
     * (`$order->$field`) rather than rooting one; the root carries the
     * diagnostic for the whole chain, so the link is skipped to avoid a second
     * one. A static property (`self::$registry`) is *not* a link — nothing
     * precedes it that could be reported instead — so it roots its own chain.
     */
    private function isChainMember(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previousPtr === false) {
            return false;
        }

        return in_array($tokens[$previousPtr]['code'], self::OBJECT_OPERATORS, true);
    }

    /**
     * Resolves the error code for the accessor that opens the chain, or null
     * when it is not a read of an element or property.
     */
    private function readAccessCode(File $phpcsFile, int $accessorPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$accessorPtr]['code'];

        // `$array[] = $value` needs no index case of its own: PHP only permits
        // the empty index as an assignment target, which isWriteTarget() skips.
        if ($code === T_OPEN_SQUARE_BRACKET) {
            return 'DirectArrayAccess';
        }

        if (in_array($code, self::OBJECT_OPERATORS, true) === false) {
            return null;
        }

        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($accessorPtr + 1), null, true);

        if ($memberPtr === false) {
            return null;
        }

        $memberEndPtr = $memberPtr;

        // A dynamic member name spans a brace pair (`$object->{$name}`), and
        // whether it is a property or a method call is only visible after the
        // closing brace. An unresolvable pair in a file being edited is
        // reported rather than assumed harmless.
        if ($tokens[$memberPtr]['code'] === T_OPEN_CURLY_BRACKET) {
            if (isset($tokens[$memberPtr]['bracket_closer']) === false) {
                return 'DirectPropertyAccess';
            }

            $memberEndPtr = $tokens[$memberPtr]['bracket_closer'];
        }

        $afterMemberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberEndPtr + 1), null, true);

        // `$object->method()` invokes the object's own API; data_get() reads
        // properties, so it is not a substitute.
        if ($afterMemberPtr !== false && $tokens[$afterMemberPtr]['code'] === T_OPEN_PARENTHESIS) {
            return null;
        }

        return 'DirectPropertyAccess';
    }

    /**
     * Whether the chain is written to rather than read from — the assignment,
     * compound-assignment, increment/decrement, reference-bind, destructuring,
     * and `foreach` targets `data_get()` cannot replace.
     *
     * The decision needs a wider window than the one token following the
     * chain: a destructuring pattern puts its `=` beyond the pattern's own
     * closer, a `foreach` target has no assignment operator at all, and a
     * reference bind is marked by an `&` that precedes the chain.
     */
    private function isWriteTarget(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previousPtr !== false) {
            // Pre-increment/decrement: `++$array['key']`.
            if (in_array($tokens[$previousPtr]['code'], [T_INC, T_DEC], true) === true) {
                return true;
            }

            // A reference bind (`$ref = &$array['key']`) writes through the
            // chain, and `data_get()` returns a value — rewriting it would
            // silently drop the reference, leaving no compliant form of the
            // statement. isReference() tells the bind apart from a bitwise
            // and (`$mask & $array['flag']`), which is an ordinary read.
            if (
                $tokens[$previousPtr]['code'] === T_BITWISE_AND
                && $phpcsFile->isReference($previousPtr) === true
            ) {
                return true;
            }
        }

        if ($this->isForeachTarget($phpcsFile, $stackPtr) === true) {
            return true;
        }

        $endPtr = $this->findChainEnd($phpcsFile, $stackPtr);
        $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($endPtr + 1), null, true);

        if ($nextPtr === false) {
            return false;
        }

        $code = $tokens[$nextPtr]['code'];

        if (in_array($code, [T_INC, T_DEC], true) === true) {
            return true;
        }

        // T_DOUBLE_ARROW is a member of Tokens::$assignmentTokens, but a chain
        // *followed* by `=>` is a read: the key of an array literal
        // (`[$row['id'] => $row['name']]`) or a match arm's condition. The one
        // construct where `=>` does mark a write — a `foreach` key target
        // (`foreach ($rows as $out['key'] => $value)`) — is settled by
        // isForeachTarget() above, so it is excluded here.
        if ($code !== T_DOUBLE_ARROW && isset(Tokens::$assignmentTokens[$code]) === true) {
            return true;
        }

        return $this->isDestructuringTarget($phpcsFile, $stackPtr, $endPtr);
    }

    /**
     * Whether the chain sits in a `foreach`'s `as` clause, which assigns into
     * every accessor it names: the value target (`foreach ($rows as
     * $out['value'])`), the key target (`foreach ($rows as $out['key'] =>
     * $value)`), and any destructuring pattern (`foreach ($rows as
     * [$out['a'], $out['b']])`). None of them carries an assignment operator
     * the trailing-token check could see.
     *
     * Only the clause after `as` is a target; the subject before it
     * (`foreach ($payload['rows'] as $row)`) is a read and stays reportable.
     */
    private function isForeachTarget(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        // A token's nested_parenthesis only records matched pairs, so the
        // opener's closer is always resolvable here.
        foreach ($tokens[$stackPtr]['nested_parenthesis'] ?? [] as $openerPtr => $closerPtr) {
            $ownerPtr = $tokens[$openerPtr]['parenthesis_owner'] ?? null;

            if ($ownerPtr === null || $tokens[$ownerPtr]['code'] !== T_FOREACH) {
                continue;
            }

            $asPtr = $phpcsFile->findNext(T_AS, ($openerPtr + 1), $closerPtr);

            if ($asPtr !== false && $asPtr < $stackPtr) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the chain is an element of a destructuring pattern that is
     * assigned to — `[$array['a'], $array['b']] = $source`,
     * `list($object->property) = $source`, `['x' => $array['a']] = $source`.
     * Destructuring is write-side access, and `data_get()` cannot stand in for
     * it any more than it can for a plain assignment target.
     *
     * findChainEnd() stops at the chain's own closer, where the next token is
     * the pattern's `,` or `]`, so the `=` governing the whole pattern is only
     * reachable by walking out of each enclosing pattern in turn.
     */
    private function isDestructuringTarget(File $phpcsFile, int $stackPtr, int $chainEndPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $searchPtr = $chainEndPtr;

        while (true) {
            $closerPtr = $this->findEnclosingPatternCloser($phpcsFile, $stackPtr, $searchPtr);

            if ($closerPtr === false) {
                return false;
            }

            $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($closerPtr + 1), null, true);

            // Destructuring assigns with `=`; PHP has no compound form of it.
            // A pattern closed at the very end of a file being edited has no
            // following token to test, so the walk continues outward, runs out
            // of enclosing patterns, and the read is reported — the safe
            // direction for a linter.
            if ($nextPtr !== false && $tokens[$nextPtr]['code'] === T_EQUAL) {
                return true;
            }

            // Not this pattern — try the one enclosing it (`[[$array['a']]]`).
            $searchPtr = ($closerPtr + 1);
        }
    }

    /**
     * Returns the closer of the nearest short-array or `list()` construct
     * enclosing the chain, searching forward from $searchPtr and stopping at
     * the end of the statement, or false when no such construct encloses it.
     * A closer whose opener precedes the chain's root is what makes the
     * construct an enclosing one rather than a sibling.
     *
     * Index brackets are deliberately not pattern closers: in
     * `$target[$array['key']] = $value` the enclosing `]` closes an index, and
     * counting it would read the trailing `=` as assigning to
     * `$array['key']`, which is a read. PHP_CodeSniffer tokenizes an index
     * closer as T_CLOSE_SQUARE_BRACKET and an array/pattern closer as
     * T_CLOSE_SHORT_ARRAY, so the two never blur.
     *
     * @return int|false
     */
    private function findEnclosingPatternCloser(File $phpcsFile, int $stackPtr, int $searchPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $searchPtr;

        while (($ptr = $phpcsFile->findNext(self::PATTERN_CLOSERS, $ptr, null, false, null, true)) !== false) {
            $openerPtr = $tokens[$ptr]['bracket_opener'] ?? $tokens[$ptr]['parenthesis_opener'] ?? null;

            if ($openerPtr !== null && $openerPtr < $stackPtr) {
                return $ptr;
            }

            ++$ptr;
        }

        return false;
    }

    /**
     * Returns the last token of the accessor chain rooted at $stackPtr, so the
     * token following it can be inspected for an assignment.
     *
     * An unresolvable chain (an unclosed bracket in a file being edited) ends
     * the walk at the offending bracket; the caller then sees a non-assignment
     * token and reports, which is the safe direction for a linter.
     */
    private function findChainEnd(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $endPtr = $stackPtr;

        while (true) {
            $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($endPtr + 1), null, true);

            if ($nextPtr === false) {
                return $endPtr;
            }

            $code = $tokens[$nextPtr]['code'];

            // An index (`['key']`) or a call's argument list (`(...)`) is
            // consumed whole; the chain may continue after the closer.
            if ($code === T_OPEN_SQUARE_BRACKET || $code === T_OPEN_PARENTHESIS) {
                $closer = $code === T_OPEN_SQUARE_BRACKET ? 'bracket_closer' : 'parenthesis_closer';

                if (isset($tokens[$nextPtr][$closer]) === false) {
                    return $nextPtr;
                }

                $endPtr = $tokens[$nextPtr][$closer];

                continue;
            }

            if (in_array($code, self::OBJECT_OPERATORS, true) === false) {
                return $endPtr;
            }

            $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($nextPtr + 1), null, true);

            if ($memberPtr === false) {
                return $endPtr;
            }

            // A variable property name (`$object->{$name}`) spans a brace pair.
            if ($tokens[$memberPtr]['code'] === T_OPEN_CURLY_BRACKET) {
                if (isset($tokens[$memberPtr]['bracket_closer']) === false) {
                    return $memberPtr;
                }

                $endPtr = $tokens[$memberPtr]['bracket_closer'];

                continue;
            }

            $endPtr = $memberPtr;
        }
    }

    /**
     * Whether the access sits inside an existence check, which already answers
     * the missing-element question `data_get()`'s fallback is for.
     */
    private function isInsideExistenceCheck(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis'] ?? []) as $openerPtr) {
            $ownerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

            if ($ownerPtr === false) {
                continue;
            }

            if (isset(self::EXISTENCE_CHECKS[strtolower($tokens[$ownerPtr]['content'])]) === true) {
                return true;
            }
        }

        return false;
    }
}

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
 * replaces the whole chain. Any `$` sigils in front of the variable are part of
 * that root — PHP 7's uniform variable syntax reads `$$name['key']` as
 * `($$name)['key']`, so the chain is rooted in the variable-variable `$$name`
 * and `data_get($$name, ...)` is what replaces it.
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
 * Four blind spots follow from the token stream and are accepted: accessors
 * embedded in interpolated strings ("{$array['key']}") are a single string
 * token to PHP_CodeSniffer and cannot be inspected, a chain rooted in
 * anything other than a variable (`foo()['key']`, `self::CONSTANTS['key']`) has
 * no variable to report against, and an argument bound to a by-reference
 * parameter (`bump($array['key'])` where `function bump(&$value)`) is a write
 * that only the callee's signature reveals — a sniff sees one file, so the call
 * site is indistinguishable from a by-value read.
 *
 * The fourth is upstream: a `foreach` whose target is a dynamic member holding
 * a brace-bearing expression (`$order->{match (true) { ... }}`) leaves
 * PHP_CodeSniffer unable to record the loop's scope, and the tokenizer then
 * labels the following statement's destructuring pattern T_OPEN_SQUARE_BRACKET
 * rather than T_OPEN_SHORT_ARRAY. That label is the only thing separating an
 * index (a read) from a pattern (a write), so the pattern's write target is
 * reported as though it were an offset read. It is recorded in
 * tokenizer-limits.php rather than worked around: the mislabelling happens
 * before any sniff runs, and reconstructing the distinction would mean
 * re-deriving it from token data already known to be wrong.
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
     * enclosureVerdict(): the enclosing construct assigns into the chain, so
     * the chain is a write target.
     */
    private const VERDICT_TARGET = 'target';

    /**
     * enclosureVerdict(): the enclosing construct reads the chain to locate
     * something else — an index or a dynamic member name — so the chain is a
     * read even when that construct is itself written to.
     */
    private const VERDICT_OFFSET = 'offset';

    /**
     * The property-access operators. `?->` is included: it guards against a
     * null *object*, not against a missing property, so the standard's fallback
     * argument applies to it just as it does to `->`.
     */
    private const OBJECT_OPERATORS = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];

    /**
     * The only tokens an accessor chain can be rooted in, and so the only ones
     * buildEnclosureMap() records an enclosure for: process() registers on
     * T_VARIABLE, and chainRoot() walks back from it over T_DOLLAR sigils
     * alone.
     *
     * @var array<int, int|string>
     */
    private const CHAIN_ROOT_TOKENS = [T_VARIABLE, T_DOLLAR];

    /**
     * Every construct that can enclose an accessor chain, named by its opener.
     * buildEnclosureMap() pairs each one with its closer in a single pass over
     * the file, so the outward walk reads its next enclosing construct in O(1)
     * rather than rescanning the token stream from the chain on every step.
     *
     * @var array<int, int|string>
     */
    private const ENCLOSING_OPENERS = [
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_PARENTHESIS,
        T_OPEN_CURLY_BRACKET,
    ];

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
     * The token stream the three maps below were built from, so a stream they
     * do not describe is never answered from. PHP_CodeSniffer re-tokenizes a
     * file on every `phpcbf` pass, and the maps hold pointers into one
     * particular stream: the fixer's loop counter is part of the key for that
     * reason, alongside the file and its token count.
     */
    private ?string $enclosureMapKey = null;

    /**
     * Chain-root token (a T_VARIABLE, or the T_DOLLAR sigil a
     * variable-variable roots at) => the closer of the construct immediately
     * enclosing it. Absent when nothing encloses the token, which is what the
     * outward walk reads as "nothing decides".
     *
     * Only the tokens a chain can be rooted in are recorded, rather than every
     * token in the file: process() registers on T_VARIABLE and chainRoot()
     * only ever walks back over T_DOLLAR, so no other token is ever asked.
     *
     * @var array<int, int>
     */
    private array $innermostCloser = [];

    /**
     * Construct closer => the closer of the construct enclosing *that*
     * construct, or null when it is outermost. This is the outward step: a
     * walk finished with one construct reads its parent here instead of
     * rescanning forward for it.
     *
     * @var array<int, int|null>
     */
    private array $parentCloser = [];

    /**
     * Construct closer => the position of the *last* opener directly inside it
     * that never closes. A file being edited can hold one, and the walk cannot
     * see past it: nothing beyond an unterminated construct can be proven to
     * enclose the chain rather than to sit inside that construct.
     *
     * The last one is what is recorded because the walk asks "is there one
     * ahead of me?" — if the last is behind the walk's position, so is every
     * other. Only openers *directly* inside the construct count: one nested in
     * a construct that does close is skipped whole, exactly as the walk used
     * to skip it token by token.
     *
     * @var array<int, int>
     */
    private array $lastUnterminatedOpener = [];

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

        $rootPtr = $this->chainRoot($phpcsFile, $stackPtr);

        if ($this->isChainMember($phpcsFile, $rootPtr) === true) {
            return;
        }

        // The accessor follows the variable, not the chain root: the `$` sigils
        // of a variable-variable precede the name they dereference.
        $accessorPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($accessorPtr === false) {
            return;
        }

        $errorCode = $this->readAccessCode($phpcsFile, $accessorPtr);

        if ($errorCode === null) {
            return;
        }

        if ($this->isWriteTarget($phpcsFile, $rootPtr, $stackPtr) === true) {
            return;
        }

        if ($this->isInsideExistenceCheck($phpcsFile, $rootPtr) === true) {
            return;
        }

        $variable = $this->chainRootName($phpcsFile, $rootPtr, $stackPtr);

        $phpcsFile->addError(
            self::MESSAGES[$errorCode],
            $rootPtr,
            $errorCode,
            [$variable, $variable]
        );
    }

    /**
     * Returns the token the accessor chain is rooted in: the variable itself,
     * unless `$` sigils precede it. PHP 7's uniform variable syntax reads
     * `$$name['key']` left to right as `($$name)['key']`, so the chain is rooted
     * in the variable-variable rather than in the name it dereferences.
     *
     * Stopping at the name instead would break the diagnostic in both
     * directions. It would name the wrong subject — `$name` holds the *name* of
     * the array, so `data_get($name, 'key')` searches a string and always
     * returns the fallback, advice the developer cannot apply. And it would hide
     * the tokens that precede the sigil from isWriteTarget(), reporting
     * `++$$name['key']` and `$ref = &$$name['key']` as reads when both are
     * writes.
     */
    private function chainRoot(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $rootPtr = $stackPtr;

        while (true) {
            $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($rootPtr - 1), null, true);

            if ($previousPtr === false || $tokens[$previousPtr]['code'] !== T_DOLLAR) {
                return $rootPtr;
            }

            $rootPtr = $previousPtr;
        }
    }

    /**
     * The chain root's source text, which is what the message tells the
     * developer to pass to `data_get()`: the variable, behind whatever `$`
     * sigils root it. The tokens are concatenated rather than the span measured,
     * because whitespace is legal between a sigil and what it dereferences
     * (`$ $name`).
     */
    private function chainRootName(File $phpcsFile, int $rootPtr, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($ptr = $rootPtr; $ptr <= $stackPtr; $ptr++) {
            if (isset(Tokens::$emptyTokens[$tokens[$ptr]['code']]) === false) {
                $name .= $tokens[$ptr]['content'];
            }
        }

        return $name;
    }

    /**
     * Whether the variable names a property inside an accessor chain
     * (`$order->$field`) rather than rooting one; the root carries the
     * diagnostic for the whole chain, so the link is skipped to avoid a second
     * one. A static property (`self::$registry`) is *not* a link — nothing
     * precedes it that could be reported instead — so it roots its own chain.
     *
     * Asked of the chain root rather than the variable, so the sigils of a
     * variable-variable do not stand between the two and hide the operator.
     */
    private function isChainMember(File $phpcsFile, int $rootPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($rootPtr - 1), null, true);

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

        // A file being edited can end on the operator itself, leaving no member
        // to tell a property from a method call. Nothing distinguishes the two,
        // so the access is reported on the same principle as the unresolvable
        // brace pair below: a linter reports on ambiguity rather than assuming
        // the ambiguous case harmless.
        if ($memberPtr === false) {
            return 'DirectPropertyAccess';
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
     *
     * A token adjacent to the chain settles the operator cases outright. The
     * rest — a `foreach` target, a destructuring target, and the offset read
     * that can sit inside either — are properties of a construct *enclosing*
     * the chain, and one enclosing construct covers the target and its offset
     * alike: `$target[$key['idx']]` names one write and one read. They are
     * therefore decided together by enclosureVerdict(), whose innermost
     * enclosing construct wins.
     *
     * The window opens at the chain root and the chain is walked from the
     * variable: `$` sigils sit between the two, so an operator before them is
     * only visible from the root, while the accessors that follow are only
     * reachable from the variable.
     */
    private function isWriteTarget(File $phpcsFile, int $rootPtr, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($rootPtr - 1), null, true);

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

        $endPtr = $this->findChainEnd($phpcsFile, $stackPtr);
        $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($endPtr + 1), null, true);

        if ($nextPtr !== false) {
            $code = $tokens[$nextPtr]['code'];

            if (in_array($code, [T_INC, T_DEC], true) === true) {
                return true;
            }

            // T_DOUBLE_ARROW is a member of Tokens::$assignmentTokens, but a
            // chain *followed* by `=>` is a read: the key of an array literal
            // (`[$row['id'] => $row['name']]`) or a match arm's condition. The
            // one construct where `=>` does mark a write — a `foreach` key
            // target (`foreach ($rows as $out['key'] => $value)`) — carries no
            // assignment operator of its own, so excluding `=>` here leaves it
            // to enclosureVerdict() rather than dropping it.
            if ($code !== T_DOUBLE_ARROW && isset(Tokens::$assignmentTokens[$code]) === true) {
                return true;
            }
        }

        return $this->enclosureVerdict($phpcsFile, $rootPtr) === self::VERDICT_TARGET;
    }

    /**
     * Classifies the chain by the nearest enclosing construct that settles what
     * it is: VERDICT_TARGET when the construct assigns into the chain,
     * VERDICT_OFFSET when the construct reads the chain to locate something
     * else, and null when nothing enclosing it decides either way.
     *
     * The walk goes outward one construct at a time and the *innermost*
     * decisive one wins, which is what keeps each verdict scoped to the
     * construct that earned it. Both directions of the pairing matter:
     *
     * - `foreach ($rows as $out[$key['idx']])` writes `$out` and reads `$key`.
     *   Walking out from `$key` meets the index `]` before the `foreach` `)`,
     *   so the read is an offset; from `$out` the `]` is not enclosing at all,
     *   so the `)` decides and it is a target.
     * - `$data[fn () => foreach-target-in-a-closure] = 'x'` inverts the nesting:
     *   walking out from the inner target meets the `foreach` `)` *before* the
     *   enclosing index `]`, so it stays a target. A walk that asked only
     *   "is an index bracket anywhere outside me?" would call it an offset and
     *   flag a write.
     *
     * Every construct that decides nothing is transparent and the walk steps
     * over it. That includes a scope brace: `match`, closures, arrow functions,
     * and anonymous classes are all expressions, so an offset can be computed
     * inside one and the brace ending it is not the end of the enclosing
     * expression.
     */
    private function enclosureVerdict(File $phpcsFile, int $rootPtr): ?string
    {
        $this->buildEnclosureMap($phpcsFile);

        $searchPtr = $rootPtr;
        $closerPtr = $this->innermostCloser[$rootPtr] ?? null;

        while ($closerPtr !== null) {
            // An unterminated construct standing between the walk and this
            // closer cannot be stepped over, and the walk cannot tell what
            // encloses the chain past it. It ends here and the read is
            // reported — the safe direction for a linter.
            if (($this->lastUnterminatedOpener[$closerPtr] ?? -1) > $searchPtr) {
                return null;
            }

            $verdict = $this->classifyEnclosure($phpcsFile, $rootPtr, $closerPtr);

            if ($verdict !== null) {
                return $verdict;
            }

            $searchPtr = $closerPtr;
            $closerPtr = $this->parentCloser[$closerPtr] ?? null;
        }

        return null;
    }

    /**
     * Classifies a single enclosing construct by its closer, or null when it
     * decides nothing and the walk should step over it.
     */
    private function classifyEnclosure(File $phpcsFile, int $rootPtr, int $closerPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$closerPtr]['code'];

        // An index closes with T_CLOSE_SQUARE_BRACKET; a short array or
        // destructuring pattern closes with T_CLOSE_SHORT_ARRAY, so the two
        // never blur. Being inside an index means being read to say *where*
        // the enclosing accessor points — a read even when that accessor is
        // itself written to.
        if ($code === T_CLOSE_SQUARE_BRACKET) {
            return self::VERDICT_OFFSET;
        }

        // A dynamic member name (`$order->{$key['name']}`) is the same offset
        // in a brace. Any other curly brace decides nothing.
        if ($code === T_CLOSE_CURLY_BRACKET) {
            return $this->isDynamicMemberBrace($phpcsFile, $tokens[$closerPtr]['bracket_opener']) === true
                ? self::VERDICT_OFFSET
                : null;
        }

        if ($this->isForeachTargetClause($phpcsFile, $rootPtr, $closerPtr) === true) {
            return self::VERDICT_TARGET;
        }

        if ($this->isAssignedPattern($phpcsFile, $closerPtr) === true) {
            return self::VERDICT_TARGET;
        }

        return null;
    }

    /**
     * Whether the closer ends a `foreach`'s parentheses with the chain in its
     * `as` clause, which assigns into the accessors it names: the value target
     * (`foreach ($rows as $out['value'])`), the key target (`foreach ($rows as
     * $out['key'] => $value)`), and any destructuring pattern (`foreach ($rows
     * as [$out['a'], $out['b']])`). None of them carries an assignment operator
     * an adjacent token could reveal.
     *
     * Only the clause after `as` is a target; the subject before it
     * (`foreach ($payload['rows'] as $row)`) is a read and stays reportable.
     */
    private function isForeachTargetClause(File $phpcsFile, int $rootPtr, int $closerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ownerPtr = $tokens[$closerPtr]['parenthesis_owner'] ?? null;

        if ($ownerPtr === null || $tokens[$ownerPtr]['code'] !== T_FOREACH) {
            return false;
        }

        $openerPtr = $tokens[$closerPtr]['parenthesis_opener'];
        $asPtr = $phpcsFile->findNext(T_AS, ($openerPtr + 1), $closerPtr);

        return $asPtr !== false && $asPtr < $rootPtr;
    }

    /**
     * Whether the closer ends a destructuring pattern that is assigned to —
     * `[$array['a'], $array['b']] = $source`, `list($object->property) =
     * $source`, `['x' => $array['a']] = $source`. Destructuring is write-side
     * access, and `data_get()` cannot stand in for it any more than it can for
     * a plain assignment target.
     *
     * A pattern *not* followed by `=` is an ordinary array literal, which
     * decides nothing: the caller steps over it and keeps walking, so a
     * pattern nested in another (`[[$array['a']]] = $source`) is still found.
     */
    private function isAssignedPattern(File $phpcsFile, int $closerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($this->isPatternCloser($phpcsFile, $closerPtr) === false) {
            return false;
        }

        $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($closerPtr + 1), null, true);

        // Destructuring assigns with `=`; PHP has no compound form of it. A
        // pattern closed at the very end of a file being edited has no
        // following token to test, so it decides nothing, the walk continues
        // outward, and the read is reported — the safe direction for a linter.
        return $nextPtr !== false && $tokens[$nextPtr]['code'] === T_EQUAL;
    }

    /**
     * Whether the brace at $openerPtr opens a dynamic member name
     * (`$object->{$name}`) rather than a scope. Only the object operator
     * before it tells them apart: PHP_CodeSniffer gives both a
     * T_OPEN_CURLY_BRACKET and a matching bracket_closer.
     */
    private function isDynamicMemberBrace(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

        if ($previousPtr === false) {
            return false;
        }

        return in_array($tokens[$previousPtr]['code'], self::OBJECT_OPERATORS, true);
    }

    /**
     * Whether the closer ends one of the two constructs a destructuring
     * pattern is written with: a short array (`[$a, $b] = $source`) or
     * `list($a, $b) = $source`.
     *
     * The `list()` check is on the parentheses' owning construct, not on the
     * parentheses alone. Any other call's `)` closes an expression, not a
     * pattern, and accepting it would let the parse error
     * `doSomething($array['key']) = $default` silently drop the read that its
     * well-formed counterpart reports — the unsafe direction for a linter,
     * which should report when a construct is ambiguous rather than stay
     * quiet.
     */
    private function isPatternCloser(File $phpcsFile, int $closerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$closerPtr]['code'];

        if ($code === T_CLOSE_SHORT_ARRAY) {
            return true;
        }

        if ($code !== T_CLOSE_PARENTHESIS) {
            return false;
        }

        $ownerPtr = $tokens[$closerPtr]['parenthesis_owner'] ?? null;

        return $ownerPtr !== null && $tokens[$ownerPtr]['code'] === T_LIST;
    }

    /**
     * Records every construct in the file and how the constructs nest, in one
     * pass, so enclosureVerdict() can answer "what encloses this token?" and
     * "what encloses *that*?" by lookup.
     *
     * The pass replaces a forward rescan the walk used to run from the chain
     * for each step outward. That rescan was linear in the distance to the
     * enclosing closer, which made a file of n accessor reads cost O(n²): each
     * read scanned over every construct that followed it. Measured on a file
     * of n reads, the rescan ran 0.64s at n=500 and 5.27s at n=2000 — ~3.4×
     * per doubling — where the map is flat.
     *
     * The maps stay valid only for the token stream they were built from, so
     * they are rebuilt whenever self::$enclosureMapKey no longer matches it.
     *
     * The pass is deliberately unbounded, for the same reason the rescan was.
     * PHP_CodeSniffer's `$local` flag stops at the first `;` in the token
     * stream regardless of nesting, which is not the end of the enclosing
     * expression: an offset can hold a closure or anonymous class whose body
     * has statements of its own (`$target[(function () { return $key['idx'];
     * })()]`), and bounding the search there would hide the construct the
     * chain is actually inside.
     */
    private function buildEnclosureMap(File $phpcsFile): void
    {
        $tokens = $phpcsFile->getTokens();
        $fixer = $phpcsFile->fixer;
        $key = $phpcsFile->getFilename()
            . '|' . count($tokens)
            . '|' . ($fixer->loops ?? 0);

        if ($this->enclosureMapKey === $key) {
            return;
        }

        $this->enclosureMapKey = $key;
        $this->innermostCloser = [];
        $this->parentCloser = [];
        $this->lastUnterminatedOpener = [];

        // The null standing at the bottom of the stack is "nothing encloses
        // this", so the innermost construct is always the last entry and the
        // stack never has to be tested for emptiness.
        $openCloserPtrs = [null];
        $innermostPtr = null;

        foreach ($tokens as $ptr => $token) {
            // A closer ends the construct it belongs to, so from the closer
            // onwards the enclosing one is whatever held that construct. A
            // stray closer never matches an opener the pass recorded, so it is
            // stepped over rather than taken for an enclosure.
            if ($innermostPtr === $ptr) {
                array_pop($openCloserPtrs);
                $innermostPtr = $openCloserPtrs[array_key_last($openCloserPtrs)];
            }

            $code = $token['code'];

            if (in_array($code, self::CHAIN_ROOT_TOKENS, true) === true) {
                if ($innermostPtr !== null) {
                    $this->innermostCloser[$ptr] = $innermostPtr;
                }

                continue;
            }

            if (in_array($code, self::ENCLOSING_OPENERS, true) === false) {
                continue;
            }

            $closerPtr = $token['bracket_closer'] ?? $token['parenthesis_closer'] ?? null;

            if ($closerPtr === null) {
                if ($innermostPtr !== null) {
                    $this->lastUnterminatedOpener[$innermostPtr] = $ptr;
                }

                continue;
            }

            $this->parentCloser[$closerPtr] = $innermostPtr;
            $openCloserPtrs[] = $closerPtr;
            $innermostPtr = $closerPtr;
        }
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
    private function isInsideExistenceCheck(File $phpcsFile, int $rootPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$rootPtr]['nested_parenthesis'] ?? []) as $openerPtr) {
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

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags chained property fetches rooted in a variable ($a->b->c).
 *
 * Enforces the "Models: Relationship Properties" standard
 * (docs/standards/models-relationship-properties.md): reading through a
 * relationship ($book->author->name) couples the caller to two models and
 * leaves every call site to guard against a missing relationship. The remedy
 * is an accessor attribute on the first model — getAuthorNameAttribute() —
 * so the caller writes $book->authorName instead.
 *
 * Two or more consecutive plain property-fetch hops are the token-visible
 * shape of that traversal. A method-call hop is a different access pattern,
 * so it ends the segment it belongs to; property fetches after it start a new
 * segment and are judged on their own ($a->b()->c->d flags c->d). A dynamic
 * member name ($a->{$b}, $a->$b) is unknowable at token level and ends its
 * segment the same way. Array access and static roots (Foo::bar()->baz->qux)
 * are out of scope. A grouping parenthesis around the root hides nothing —
 * ($book)->author->name reads the same relationship as $book->author->name,
 * so the root is looked for inside the group, as long as the whole group is
 * that one expression. A group holding several (a ternary's arms, a match's
 * arms) has no single root, so it is out of scope rather than judged on
 * whichever arm happens to be written last.
 *
 * What counts as a grouping parenthesis, and what counts as a receiver at all,
 * are both decided from closed admission sets rather than by excluding the
 * shapes that came to mind: a construct whose subject or body is written in
 * brackets — match ($book) {...}, eval($code), array($book), isset($book) — is
 * not a receiver this sniff models, and refusing everything unrecognised keeps
 * an unlisted one silent instead of reporting a chain against it.
 */
class DisallowChainedPropertyFetchSniff implements Sniff
{
    /**
     * The tokens a grouping parenthesis may follow, beyond the operator unions
     * PHP_CodeSniffer already publishes. See isGroupingParenthesis().
     *
     * Arrived at by sweeping PHP_CodeSniffer's whole token catalogue once —
     * every T_* constant it defines, not the members that came to mind — and
     * admitting each token a parenthesis can legally follow and still be
     * grouping one expression. Every admission below is a shape `php -l`
     * accepts and a fixture line reports; every token left out is refused on
     * purpose and recorded as such by the catalogue test, which fails on any
     * token this list and that one both leave unclassified. Adding the next
     * omission one at a time is what this is written to stop.
     *
     * No member is redundant with those unions, which is asserted rather than
     * assumed: `=>` was listed here until the sweep found Tokens::$assignmentTokens
     * already supplying it, and group-preceders.php pins the shape either way.
     *
     * @var array<int, int|string>
     */
    private const GROUP_PRECEDERS = [
        // Where an expression starts: the file's own opening tags, the end of
        // the statement before, and the brace opening a block or a braced
        // member name. The matching closers are absent — see the method.
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_GOTO_LABEL,

        // Openers and separators inside an expression: an argument list, a
        // subscript, an array literal, and the punctuation between their
        // elements or a ternary's arms.
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_COMMA,
        T_COLON,
        T_INLINE_THEN,
        T_INLINE_ELSE,
        T_FN_ARROW,
        T_MATCH_ARROW,

        // Keywords that take an expression without parenthesising it.
        T_RETURN,
        T_ECHO,
        T_PRINT,
        T_THROW,
        T_YIELD,
        T_YIELD_FROM,
        T_CASE,
        T_CLONE,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,

        // Keywords that take a bare statement, which an expression may be.
        T_ELSE,
        T_DO,

        // Prefix operators PHP_CodeSniffer's own unions leave out.
        T_BOOLEAN_NOT,
        T_BITWISE_NOT,
        T_ASPERAND,
        T_ELLIPSIS,

        // Concatenation, the one binary operator absent from those unions.
        T_STRING_CONCAT,

        // PHP 8.5's two new tokens. PHP_CodeSniffer 3.13.6 predates both, so
        // neither reaches the unions above however well it fits one of them —
        // Tokens::$castTokens has no T_VOID_CAST and Tokens::$operators no
        // T_PIPE. CleanCode/Support/BackportedTokens.php records how the pair
        // was measured; each is admitted on its own evidence:
        //
        // - `(void)` is a cast, and a cast takes an expression, so a
        //   parenthesis after one opens a group.
        //   `<?php (void) ($book)->author->name;` passes `php -l` on PHP 8.5,
        //   and PHP_CodeSniffer tokenises it T_VOID_CAST, T_OPEN_PARENTHESIS,
        //   T_VARIABLE — the grouped-root shape this sniff reports. Refusing it
        //   would lose that report on 8.5 and keep it on 8.4.
        // - `|>` is a binary operator whose right operand is an expression
        //   evaluating to a callable. `<?php $r = $y |> ($this->resolver)->handler;`
        //   passes `php -l` on PHP 8.5 and tokenises T_PIPE,
        //   T_OPEN_PARENTHESIS, T_VARIABLE, so the parenthesis groups exactly
        //   as it does after `.` above.
        T_VOID_CAST,
        T_PIPE,
    ];

    /**
     * The token stream self::$roots was built from, so a stream it does not
     * describe is never answered from. The record holds pointers into one
     * particular stream, and TokenStreams::key() — the one implementation the
     * four sniffs with a per-stream index in this package share — is what tells
     * that stream from every other, including the next `phpcbf` pass over the
     * same file.
     */
    private ?string $rootsKey = null;

    /**
     * Token the root walk stood on => where the walk from it ended, as
     * rootFrom() returns it: the pointer to the variable the expression is
     * rooted in, the opener of the group holding it, or false for anything
     * this sniff does not model.
     *
     * @var array<int, int|false>
     */
    private array $roots = [];

    /**
     * How many times the record was emptied for a new token stream, and how
     * many times the key guard left it standing for the stream it describes.
     *
     * The record exists to absorb many root walks per token stream, and nothing
     * a black-box test can observe tells "kept across the stream" from
     * "emptied on every read": both report the same violations. These two
     * counters are what tell them apart, and
     * tests/Standards/DisallowChainedPropertyFetchTest.php pins both numbers.
     *
     * Each increment sits inside the same branch as the guard it counts, so a
     * guard that stopped working cannot leave the counts intact. The totals are
     * cumulative for the life of the sniff instance — tests/Helpers.php's
     * buildRuleset() memoises the instance, so every test in one file shares
     * one — and are read as a delta around a single process() run.
     *
     * @var array<string, int>
     */
    private array $cacheCounts = [
        'roots.builds' => 0,
        'roots.hits' => 0,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    /**
     * How many times the walked-root record was emptied for a new stream and
     * how many times the key guard left it standing, cumulative for the life of
     * this instance.
     *
     * @return array<string, int>
     */
    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $memberPtr = $this->propertyNameAfter($phpcsFile, $stackPtr);

        if ($memberPtr === false) {
            return;
        }

        // The receiver of this hop has to be the member of a preceding hop for
        // the two to be consecutive. Anything else — a variable, a call's
        // closing parenthesis, an array subscript — starts a fresh segment.
        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($receiverPtr === false || $tokens[$receiverPtr]['code'] !== T_STRING) {
            return;
        }

        $previousOperatorPtr = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($receiverPtr - 1),
            null,
            true
        );

        if ($previousOperatorPtr === false || $this->isObjectOperator($tokens, $previousOperatorPtr) === false) {
            return;
        }

        // One diagnostic per chain: a third hop's receiver is preceded by an
        // object operator too, which means the pair before it was already
        // reported.
        //
        // Asked before the root walk, never after. This test reads a fixed two
        // tokens where the walk reads the whole receiver expression, so putting
        // it first keeps an already-reported hop from starting a walk at all.
        // What actually bounds the cost of the walk is rootFrom()'s record —
        // this ordering is a constant-factor gain on top of it, and the two are
        // measured apart in the sniff's linear-time test.
        if ($this->isPrecededByAnotherHop($phpcsFile, $previousOperatorPtr) === true) {
            return;
        }

        if ($this->isRootedInVariable($phpcsFile, $previousOperatorPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Chained property fetch %s; expose the value as an accessor attribute on the '
                . 'first model instead (e.g. getAuthorNameAttribute() so callers read '
                . '$book->authorName rather than $book->author->name) '
                . '(see docs/standards/models-relationship-properties.md)',
            $memberPtr,
            'Found',
            // The operator is quoted from the matched token rather than written
            // out, so a nullsafe hop reads back as the source wrote it
            // (author?->name) instead of being reported as author->name.
            [
                $tokens[$receiverPtr]['content']
                    . $tokens[$stackPtr]['content']
                    . $tokens[$memberPtr]['content'],
            ]
        );
    }

    /**
     * The pointer to this hop's member name when the hop is a plain property
     * fetch, false otherwise. A dynamic member name ($a->{$b}, $a->$b) is not
     * a T_STRING, and a name followed by an opening parenthesis is a method
     * call rather than a property read.
     *
     * @return int|false
     */
    private function propertyNameAfter(File $phpcsFile, int $operatorPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if ($memberPtr === false || $tokens[$memberPtr]['code'] !== T_STRING) {
            return false;
        }

        $afterMemberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if ($afterMemberPtr !== false && $tokens[$afterMemberPtr]['code'] === T_OPEN_PARENTHESIS) {
            return false;
        }

        return $memberPtr;
    }

    /**
     * Whether the expression the given hop hangs off ultimately starts at a
     * variable.
     */
    private function isRootedInVariable(File $phpcsFile, int $operatorPtr): bool
    {
        return $this->rootBefore($phpcsFile, $operatorPtr) !== false;
    }

    /**
     * The pointer to where the receiver expression ending just before the given
     * token starts, when it starts at a variable; false when it starts anywhere
     * else. Walks left over the whole expression, stepping over completed
     * calls, subscripts and braced member names, so that $a->b()->c->d is
     * recognised as variable-rooted while Foo::bar()->baz->qux is not. A
     * grouping parenthesis is walked into instead of over, since it is where
     * the root of ($a)->b->c actually sits, and the group's own opener is
     * returned as the start in that case.
     *
     * @return int|false
     */
    private function rootBefore(File $phpcsFile, int $beforePtr)
    {
        return $this->rootFrom(
            $phpcsFile,
            $phpcsFile->findPrevious(Tokens::$emptyTokens, ($beforePtr - 1), null, true)
        );
    }

    /**
     * rootBefore() from a token the walk is already standing on.
     *
     * The loop carries no state but $ptr, so where it ends up is a function of
     * where it starts and nothing else — which is what makes every token it
     * passes over answerable with the same result, and why they are all
     * recorded on the way out. Without that, a chain whose segments are broken
     * by method calls ($a->b()->c->d->e()->f->g...) walks the whole receiver
     * again for every segment, and a file of n hops costs O(n²): measured at
     * 4.2s for 4,000 hops, 16.3s for 8,000 and 62.1s for 16,000, against 0.3s
     * flat once the walk is answered from the record.
     *
     * @param int|false $ptr
     *
     * @return int|false
     */
    private function rootFrom(File $phpcsFile, $ptr)
    {
        $this->discardRootsOfOtherStreams($phpcsFile);

        $tokens = $phpcsFile->getTokens();
        $walked = [];

        while ($ptr !== false) {
            if (array_key_exists($ptr, $this->roots) === true) {
                return $this->recordRoots($walked, $this->roots[$ptr]);
            }

            $walked[] = $ptr;
            $code = $tokens[$ptr]['code'];

            if ($code === T_CLOSE_PARENTHESIS || $code === T_CLOSE_SQUARE_BRACKET || $code === T_CLOSE_CURLY_BRACKET) {
                $openerPtr = $this->openerOf($tokens, $ptr);

                if ($openerPtr === false) {
                    return $this->recordRoots($walked, false);
                }

                $beforeOpenerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

                // Braces reached from here are a braced member name and
                // nothing else — $a->{$b}->c, whose opener follows the hop's
                // own operator. Every other brace pair that can sit in front
                // of an operator closes a body, not a receiver: a match's arms
                // (match ($book) { ... }->author->name), a closure's, an
                // anonymous class's. None has a single root to walk to, so the
                // walk stops rather than reading one out of the subject in
                // front of the body.
                if ($code === T_CLOSE_CURLY_BRACKET) {
                    if ($beforeOpenerPtr === false || $this->isObjectOperator($tokens, $beforeOpenerPtr) === false) {
                        return $this->recordRoots($walked, false);
                    }

                    $ptr = $beforeOpenerPtr;

                    continue;
                }

                // A call's argument list and a subscript both belong to the
                // token in front of their opener, so the walk continues there.
                if ($code === T_CLOSE_SQUARE_BRACKET || $this->isInvokedOn($tokens, $beforeOpenerPtr) === true) {
                    $ptr = $beforeOpenerPtr;

                    continue;
                }

                // A grouping parenthesis belongs to nothing in front of it —
                // ($a)->b->c — and holds its own root, so the walk continues
                // inside the group. Anything else opening a parenthesis is a
                // construct this sniff does not model, and is refused.
                if ($this->isGroupingParenthesis($tokens, $beforeOpenerPtr) === false) {
                    return $this->recordRoots($walked, false);
                }

                return $this->recordRoots($walked, $this->rootInsideGroup($phpcsFile, $openerPtr, $ptr));
            }

            // Landing straight on an operator means the group just stepped
            // over was a braced member name ($a->{$b}); step over its hop too.
            if ($this->isObjectOperator($tokens, $ptr) === true) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

                continue;
            }

            if ($code !== T_STRING && $code !== T_VARIABLE) {
                return $this->recordRoots($walked, false);
            }

            $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if ($previousPtr !== false && $this->isObjectOperator($tokens, $previousPtr) === true) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previousPtr - 1), null, true);

                continue;
            }

            if ($previousPtr !== false && $tokens[$previousPtr]['code'] === T_DOUBLE_COLON) {
                return $this->recordRoots($walked, false);
            }

            return $this->recordRoots($walked, $code === T_VARIABLE ? $ptr : false);
        }

        return $this->recordRoots($walked, false);
    }

    /**
     * Empties the record of walked tokens when the token stream it describes is
     * no longer the one being processed.
     */
    private function discardRootsOfOtherStreams(File $phpcsFile): void
    {
        $key = TokenStreams::key($phpcsFile);

        if ($this->rootsKey === $key) {
            $this->cacheCounts['roots.hits']++;

            return;
        }

        $this->cacheCounts['roots.builds']++;
        $this->rootsKey = $key;
        $this->roots = [];
    }

    /**
     * Records $result against every token the walk passed over, and returns it
     * so a caller can `return $this->recordRoots(...)` in one step.
     *
     * @param array<int, int> $walked
     * @param int|false       $result
     *
     * @return int|false
     */
    private function recordRoots(array $walked, $result)
    {
        foreach ($walked as $ptr) {
            $this->roots[$ptr] = $result;
        }

        return $result;
    }

    /**
     * Where a grouping parenthesis's contents start, when the whole group is
     * the variable-rooted expression the walk found in it; false otherwise.
     *
     * The walk back from the closer consumes one expression, so the group is
     * only accepted when that expression reaches the group's own first token.
     * A group holding more than one — either arm of a ternary or a match, the
     * two sides of ?? and ?: — leaves tokens in front of it, and reading a root
     * out of the last arm alone would decide the verdict from the order the
     * arms are written in: ($cond ? Book::first() : $cached)->author->name and
     * ($cond ? $cached : Book::first())->author->name say the same thing, so
     * neither may be flagged while the other is silent. Such a group reports
     * nothing, the same as the static root inside it would on its own.
     *
     * The opener is returned rather than the root itself, so that a group
     * wrapped in another — (($book))->author->name — still reads to the outer
     * group as reaching its own first token.
     *
     * @return int|false
     */
    private function rootInsideGroup(File $phpcsFile, int $openerPtr, int $closerPtr)
    {
        $rootPtr = $this->rootBefore($phpcsFile, $closerPtr);

        if ($rootPtr === false) {
            return false;
        }

        $firstInGroupPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), $closerPtr, true);

        return $rootPtr === $firstInGroupPtr ? $openerPtr : false;
    }

    /**
     * Whether the hop before the given one is itself a property-fetch hop,
     * which means this chain already produced a diagnostic further left.
     */
    private function isPrecededByAnotherHop(File $phpcsFile, int $operatorPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($operatorPtr - 1), null, true);

        if ($receiverPtr === false || $tokens[$receiverPtr]['code'] !== T_STRING) {
            return false;
        }

        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($receiverPtr - 1), null, true);

        return $previousPtr !== false && $this->isObjectOperator($tokens, $previousPtr) === true;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isObjectOperator(array $tokens, int $ptr): bool
    {
        return $tokens[$ptr]['code'] === T_OBJECT_OPERATOR
            || $tokens[$ptr]['code'] === T_NULLSAFE_OBJECT_OPERATOR;
    }

    /**
     * Whether an opening parenthesis is an argument list belonging to what
     * precedes it, rather than a grouping parenthesis standing on its own.
     * Decided from the preceding token: only a token that ends an expression
     * can be called, and each listed here is one — a name (foo()), a variable
     * ($fn()), another call (foo()()), a subscript ($handlers['x']()) or a
     * braced member name ($book->{$method}()). Anything else in that position
     * (=, ?, :, return, another opener) cannot be, so the parenthesis groups.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param int|false                        $beforeOpenerPtr pointer to the
     *        token before the opener, false when the opener starts the file
     */
    private function isInvokedOn(array $tokens, $beforeOpenerPtr): bool
    {
        if ($beforeOpenerPtr === false) {
            return false;
        }

        return in_array(
            $tokens[$beforeOpenerPtr]['code'],
            [
                T_STRING,
                T_VARIABLE,
                T_CLOSE_PARENTHESIS,
                T_CLOSE_SQUARE_BRACKET,
                T_CLOSE_CURLY_BRACKET,
            ],
            true
        );
    }

    /**
     * Whether an opening parenthesis groups a subexpression, rather than
     * belonging to a language construct written the same way.
     *
     * Decided from the preceding token against a closed admission set, which is
     * the whole point of the method: isInvokedOn() above answers "is this a
     * call", and treating everything it rejects as a group made every
     * construct that writes its subject in parentheses — match ($book) {...},
     * eval($code), array($book), isset($book), list($book) — look like one, so
     * the walk read a root out of the subject and reported a chain the sniff
     * does not model. Naming what a group may follow instead of what it may
     * not means an omission here costs a missed diagnostic, never a false
     * positive on a build.
     *
     * Every member is a token that cannot start or continue an expression of
     * its own, so a parenthesis after it can only open one: the start of a
     * statement or of the file, an assignment, an operator of any kind, a
     * separator, or a keyword that takes an expression without parenthesising
     * it.
     *
     * Three groups of tokens are left out for reasons worth naming, because
     * each reads at a glance like it belongs:
     *
     * - The closers — `)`, `]`, `}` and a short array's — are absent
     *   deliberately: a parenthesis after one of them invokes what precedes it
     *   (`${'fn'}($book)` calls `fn` with `$book`), and isInvokedOn() has
     *   already claimed them. Admitting `}` alongside its opener for symmetry
     *   would read that argument list as a group and report a chain against
     *   the argument — the false positive this whole method exists to avoid.
     * - `new` and `instanceof` take a class, not an expression, so
     *   `new ($book)->author->name` and `$x instanceof ($book)->author->name`
     *   are both source PHP rejects outright.
     * - `break`, `continue`, `exit`, `static`, `namespace` and `goto` cannot be
     *   followed by a parenthesised expression at all; each was checked against
     *   `php -l` rather than assumed.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param int|false                        $beforeOpenerPtr pointer to the
     *        token before the opener, false when the opener starts the file
     */
    private function isGroupingParenthesis(array $tokens, $beforeOpenerPtr): bool
    {
        if ($beforeOpenerPtr === false) {
            return true;
        }

        $code = $tokens[$beforeOpenerPtr]['code'];

        return in_array($code, self::GROUP_PRECEDERS, true)
            || isset(Tokens::$assignmentTokens[$code]) === true
            || isset(Tokens::$operators[$code]) === true
            || isset(Tokens::$comparisonTokens[$code]) === true
            || isset(Tokens::$booleanOperators[$code]) === true
            || isset(Tokens::$castTokens[$code]) === true;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return int|false
     */
    private function openerOf(array $tokens, int $ptr)
    {
        if ($tokens[$ptr]['code'] === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? false;
        }

        return $tokens[$ptr]['bracket_opener'] ?? false;
    }
}

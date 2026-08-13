<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the token-visible slice of the "Conditionals: Combine Where
 * Possible" standard (#21), as scoped by #181.
 *
 * Reports one **warning** per participating branch, at its own `if`/`elseif`
 * keyword. "Same result" in full generality is semantic and no token walk can
 * see it; two shapes are mechanical, and those are what this sniff points at.
 * Whether the combined condition actually reads better stays a judgement for
 * code review, which is why this is a warning and not an error.
 *
 * Two patterns qualify, and only these two:
 *
 * 1. **Adjacent branches of one `if`/`elseif` chain** whose bodies are
 *    identical after normalization. Always combinable: only one branch of a
 *    chain ever runs, and short-circuit `||` preserves the order and the count
 *    of condition evaluations exactly, so `if ($a) { X } elseif ($b) { X }` is
 *    behavior-identical to `if ($a || $b) { X }` whatever X does. No exit
 *    requirement applies here.
 * 2. **Adjacent separate plain `if` statements** — no `elseif`/`else` on
 *    either, no statement between them — whose identical bodies
 *    **unconditionally exit** the enclosing scope, i.e. the body's last
 *    statement is `return`, `throw`, `continue`, `break`, or `exit`/`die`.
 *
 * The exit requirement in the second pattern is load-bearing, not decoration.
 * Without it, `if ($a) { X } if ($b) { X }` and `if ($a || $b) { X }` differ
 * whenever both conditions are truthy: the original runs X twice and evaluates
 * both conditions, the combined form runs X once and never evaluates the
 * second. An exiting body can never run twice, and the second condition is
 * skipped exactly when the original would have skipped it.
 *
 * Every branch of a group is reported, at its own keyword, naming the others —
 * the same choice `CleanCode.Pattern.AvoidDuplicateCodeBlocks` makes for the
 * DRY standard, and for the same reason: combinability is a property the
 * branches share, not something the later one did to the earlier. A reader with
 * the cursor on any one of them sees the whole group.
 *
 * Every continuation shape PHP offers is walked, because PHP_CodeSniffer
 * attaches scope to a different token in each (verified against the tokenizer,
 * not assumed):
 *
 * | Shape                        | Where the clause's scope lives                 |
 * |------------------------------|------------------------------------------------|
 * | `} elseif (…) {`             | `T_ELSEIF`, closer is the `}`                  |
 * | `} else if (…) {`            | the trailing `T_IF`; the `T_ELSE` has no scope |
 * | `if (…) return …;`           | no scope at all — body ends at the `;`         |
 * | `if (…): … elseif (…): …`    | opener is the `:`, closer is the *next clause* |
 * | `if (…): … endif;`           | opener is the `:`, closer is the `endif`       |
 *
 * Deliberately **not** flagged, and why:
 *
 * - Separate `if` statements whose identical bodies do not exit, and
 *   non-adjacent branches of a chain. Both rewrites change behaviour — see
 *   above for the first; merging non-adjacent branches reorders condition
 *   evaluation.
 * - Separate `if` statements with anything between them but comments and
 *   whitespace. An intervening statement can change what the second condition
 *   sees.
 * - An `else` branch. It carries no condition, so there is nothing to join
 *   with `||`; an `else` whose body repeats the `if`'s is a different finding
 *   about a redundant conditional altogether.
 * - An empty body. Two of them are trivially identical, and combining them
 *   removes nothing worth removing.
 * - A brace-less body that is itself a control structure (`if`, `while`,
 *   `for`, `foreach`, `switch`, `do`, `try`). Such a body is a nested
 *   conditional rather than a statement to compare, and measuring its extent
 *   costs a `findEndOfStatement()` walk of everything nested inside it — one
 *   per level, which is what turns a deeply nested brace-less file quadratic.
 *   Neither pattern can reach one: it is not an unconditional exit, and a
 *   brace-less nested `if` swallows any `else` that follows into itself.
 *
 *   Such a clause is skipped, never contagious. It ends the chain it sits in
 *   at the clause before it, and the branches already read stay comparable
 *   with each other — voiding the whole chain would lose a legitimate pair
 *   that happens to be followed by an unreadable sibling. What the truncated
 *   chain does lose is its claim to being a plain separate `if`: a chain that
 *   continues into a clause the sniff cannot read still continues, so its head
 *   never joins a run.
 *
 * - An `if` that is itself the brace-less body of an enclosing control
 *   structure, as a member of a run of separate `if` statements. Such an `if`
 *   is somebody's body rather than a statement standing beside its neighbours,
 *   so the statement that follows it belongs to the enclosing scope, not
 *   beside it — `if ($x) if ($a) return 1;` and a following `if ($b) return
 *   1;` are one scope apart, and `||` cannot join them. Its own chain is still
 *   read and reported: the branches of a nested chain are as combinable as any
 *   other.
 *
 * Detection only. Merging two conditions with `||` is a rewrite whose result
 * has to read better than the original to be worth making, and that is exactly
 * the judgement the standard leaves to a human, so there is nothing to
 * auto-fix. See docs/standards/conditionals-combine-where-possible.md.
 *
 * Cost. The whole file is walked once, from its first open tag. Each `if` has
 * its chain read at most twice — once as a chain head of its own, once as a
 * candidate continuation of the run before it — and a run's members are then
 * skipped by the outer walk, so no group is re-measured per member.
 */
class CombinableConditionsSniff implements Sniff
{
    /**
     * The tokens a file's PHP can open on, which is both what the sniff
     * registers for and what it looks back for to know it has already run.
     *
     * @var array<int, int|string>
     */
    private const OPEN_TAGS = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];

    /**
     * The statements that leave the enclosing scope unconditionally. `die` is
     * not listed because PHP tokenizes it as T_EXIT, exactly like `exit`.
     *
     * @var array<int, int|string>
     */
    private const EXIT_STATEMENTS = [T_RETURN, T_THROW, T_CONTINUE, T_BREAK, T_EXIT];

    /**
     * The control structures a brace-less body is never compared through — see
     * the class docblock for why the exclusion is both a correctness and a cost
     * decision.
     *
     * @var array<int, int|string>
     */
    private const NESTING_STATEMENTS = [T_IF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_DO, T_TRY];

    /**
     * The control structures whose brace-less body can be an `if` with another
     * statement behind it, named by the token owning the parentheses that body
     * follows. Every entry was confirmed against the tokenizer to produce the
     * pairing this list exists to refuse; nothing is listed on the strength of
     * looking like it belongs.
     *
     * `switch` and `try` are absent because PHP gives neither a brace-less
     * form. `do` is absent for a subtler reason: its body is followed by its
     * own `while`, never by the next statement, so a run can never reach past
     * it and there is nothing to refuse.
     *
     * @var array<int, int|string>
     */
    private const BRACELESS_BODY_OWNERS = [T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_DECLARE];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return self::OPEN_TAGS;
    }

    /**
     * Runs once per file, at its first PHP open tag, because combinability is a
     * relation between statements rather than a property of any one token.
     * Driving the whole scan from one dispatch is what lets a run of adjacent
     * `if`s be grouped and reported once, without carrying state between calls:
     * the set of already-grouped `if`s lives for the length of this method.
     *
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->findPrevious(self::OPEN_TAGS, ($stackPtr - 1)) !== false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $grouped = [];

        foreach ($tokens as $pointer => $token) {
            if ($token['code'] !== T_IF || isset($grouped[$pointer]) === true) {
                continue;
            }

            if ($this->isChainHead($phpcsFile, $pointer) === false) {
                continue;
            }

            $chain = $this->collectChain($phpcsFile, $pointer);

            if ($chain === null) {
                continue;
            }

            if (count($chain['clauses']) > 1) {
                $this->reportChain($phpcsFile, $chain['clauses']);

                continue;
            }

            $run = $this->collectRun($phpcsFile, $chain);

            foreach ($run as $member) {
                $grouped[$member['pointer']] = true;
            }

            $this->reportRun($phpcsFile, $run);
        }
    }

    /**
     * Whether this `if` opens a chain rather than continuing one.
     *
     * The `if` of a spaced `else if` is a full T_IF token with its own scope,
     * so it reaches the walk exactly like a leading one. Its chain is already
     * read from the real head, and treating it as a head of its own would both
     * report a group twice and let a chain's tail masquerade as a separate
     * statement adjacent to the next one.
     */
    private function isChainHead(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previous === false) {
            return true;
        }

        return $phpcsFile->getTokens()[$previous]['code'] !== T_ELSE;
    }

    /**
     * Whether this `if` is the brace-less body of an enclosing control
     * structure rather than a statement standing beside its neighbours.
     *
     * The distinction decides who the `if`'s neighbours *are*. A brace-less
     * body is the whole of its owner's body — PHP allows exactly one statement
     * there — so whatever follows it closes the owner and belongs to the scope
     * outside, one level up. Reading it as an adjacent statement pairs two
     * `if`s that no `||` can join, which is why a run never starts on one.
     *
     * Detection is by the token in front, because that is the only thing that
     * distinguishes the shape: PHP_CodeSniffer opens no scope for a brace-less
     * body, so the `if` carries nothing saying whose body it is. What such a
     * body follows is its owner's closing parenthesis. `else` needs no entry —
     * an `if` after one is the trailing half of a spaced `else if`, and
     * isChainHead() already refuses it a head's turn, so it never reaches a run
     * at all.
     */
    private function isBracelessBody(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previous === false) {
            return false;
        }

        $owner = $tokens[$previous]['parenthesis_owner'] ?? null;

        return $tokens[$previous]['code'] === T_CLOSE_PARENTHESIS
            && $owner !== null
            && in_array($tokens[$owner]['code'], self::BRACELESS_BODY_OWNERS, true) === true;
    }

    /**
     * The chain from its leading `if`: one entry per clause, the token the
     * chain ends on, and whether every clause of it could be read.
     *
     * A clause the sniff cannot read — a truncated file, or a brace-less body
     * it does not compare — stops the walk *at* that clause rather than
     * discarding the chain. The clauses already collected were each read in
     * full and remain fully comparable with each other, so an unreadable third
     * branch must not silence an identical first and second. Only a chain whose
     * *first* clause is unreadable yields null: there is nothing to compare.
     *
     * `complete` is what keeps that prefix honest. A chain stopped early still
     * continues in the source, so the `if` heading it is not a plain one and
     * the `end` reported is the prefix's rather than the whole statement's —
     * which is why a run refuses an incomplete chain as a member.
     *
     * A clause carries its own body signature, so the comparison never re-reads
     * the body: identical normalized bodies produce identical signatures, and
     * an `else` (or an uncomparable body) carries null, which no run can span.
     *
     * @return array{
     *     clauses: array<int, array{pointer: int, signature: string|null, exits: bool}>,
     *     end: int,
     *     complete: bool
     * }|null
     */
    private function collectChain(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $clauses = [];
        $complete = true;
        $pointer = $stackPtr;
        $end = $stackPtr;
        // What the current pointer is allowed to be. Only `elseif` and `else`
        // continue a chain: a bare `if` after a closing brace is the *next
        // statement*, and reading it as a fourth branch would both merge two
        // separate statements into one chain and report the same pair twice.
        // The one `if` that does continue a chain is the trailing half of a
        // spaced `else if`, and it is only ever reached through the hop below.
        $accepted = [T_IF];

        while (true) {
            $code = $tokens[$pointer]['code'];

            if (in_array($code, $accepted, true) === false) {
                break;
            }

            $extent = $this->clauseExtent($phpcsFile, $pointer);

            if ($extent === null) {
                $complete = false;

                break;
            }

            $signature = $code === T_ELSE
                ? null
                : $this->bodySignature($tokens, $extent['bodyStart'], $extent['bodyEnd']);

            $clauses[] = [
                'pointer' => $pointer,
                'signature' => $signature,
                'exits' => $signature === null
                    ? false
                    : $this->bodyExits($phpcsFile, $extent['bodyStart'], $extent['bodyEnd']),
            ];
            $end = $extent['end'];
            $next = $extent['next'];
            $accepted = [T_ELSEIF, T_ELSE];

            if ($code === T_ELSE || $next === null) {
                break;
            }

            // A spaced `else if`: the T_ELSE carries neither condition nor
            // scope, so the clause belongs to the trailing `if`.
            if ($tokens[$next]['code'] === T_ELSE) {
                $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

                if ($after !== false && $tokens[$after]['code'] === T_IF) {
                    $pointer = $after;
                    $accepted = [T_IF];

                    continue;
                }
            }

            $pointer = $next;
        }

        return $clauses === []
            ? null
            : ['clauses' => $clauses, 'end' => $end, 'complete' => $complete];
    }

    /**
     * Locates a clause's body, the token its statement ends on, and the token
     * where the next clause would begin.
     *
     * PHP_CodeSniffer models the body forms differently, so each is read on its
     * own terms rather than through one assumed scope shortcut:
     *
     * - braced — scope runs `{` to `}`, and the next clause follows the `}`;
     * - alternative syntax — scope runs `:` to the *next clause's own keyword*,
     *   which therefore doubles as the continuation pointer, or to `endif`,
     *   which ends the whole statement one `;` later;
     * - brace-less — no scope at all, so the body is the single statement after
     *   the condition, ending at its semicolon.
     *
     * The brace-less path refuses a body that opens a nested control structure
     * *before* it asks for the statement's end, because findEndOfStatement()
     * walks past every level nested inside it and a brace-less body is the one
     * shape PHP_CodeSniffer's 50-deep scope limit does not cap. It refuses a
     * body that does not end at a semicolon too: that is a truncated file,
     * where the tokens after the cut are not a statement to compare.
     *
     * @return array{bodyStart: int, bodyEnd: int, end: int, next: int|null}|null
     */
    private function clauseExtent(File $phpcsFile, int $clausePtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['scope_opener'], $tokens[$clausePtr]['scope_closer']) === true) {
            $opener = $tokens[$clausePtr]['scope_opener'];
            $closer = $tokens[$clausePtr]['scope_closer'];
            $body = ['bodyStart' => ($opener + 1), 'bodyEnd' => ($closer - 1)];

            if ($tokens[$closer]['code'] === T_CLOSE_CURLY_BRACKET) {
                $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

                return $body + ['end' => $closer, 'next' => $next === false ? null : $next];
            }

            if ($tokens[$closer]['code'] === T_ENDIF) {
                $semicolon = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);
                $ends = $semicolon !== false && $tokens[$semicolon]['code'] === T_SEMICOLON;

                return $body + ['end' => $ends === true ? $semicolon : $closer, 'next' => null];
            }

            return $body + ['end' => $closer, 'next' => $closer];
        }

        $afterCondition = isset($tokens[$clausePtr]['parenthesis_closer']) === true
            ? ($tokens[$clausePtr]['parenthesis_closer'] + 1)
            : ($clausePtr + 1);
        // findEndOfStatement() reads the token it is handed, so it has to start
        // on the statement's first real token, never the whitespace before it.
        $bodyStart = $phpcsFile->findNext(Tokens::$emptyTokens, $afterCondition, null, true);

        if ($bodyStart === false || in_array($tokens[$bodyStart]['code'], self::NESTING_STATEMENTS, true) === true) {
            return null;
        }

        // A colon here is an alternative-syntax clause whose `endif` never
        // arrived: the tokenizer leaves such a clause with no scope at all, and
        // reading its body as a brace-less statement would report a chain in a
        // file PHP itself refuses to parse. No brace-less body can open on one.
        if ($tokens[$bodyStart]['code'] === T_COLON) {
            return null;
        }

        $bodyEnd = $phpcsFile->findEndOfStatement($bodyStart);

        if ($bodyEnd <= $bodyStart || $tokens[$bodyEnd]['code'] !== T_SEMICOLON) {
            return null;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($bodyEnd + 1), null, true);

        return [
            'bodyStart' => $bodyStart,
            'bodyEnd' => $bodyEnd,
            'end' => $bodyEnd,
            'next' => $next === false ? null : $next,
        ];
    }

    /**
     * A body reduced to the tokens that carry code: each one's type and its
     * exact content, in order. Whitespace and comments are dropped, so
     * reformatting or re-commenting one of two identical bodies does not hide
     * the duplication.
     *
     * Content is compared as well as type, because this standard is about two
     * branches producing the same *result* — `return 1;` and `return 2;` share
     * every token type and are not the same body.
     *
     * Null for an empty body: two of those are trivially identical, and
     * combining a pair of empty branches removes nothing.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function bodySignature(array $tokens, int $bodyStart, int $bodyEnd): ?string
    {
        $signature = '';

        for ($pointer = $bodyStart; $pointer <= $bodyEnd; $pointer++) {
            if (isset($tokens[$pointer]) === false) {
                break;
            }

            if (isset(Tokens::$emptyTokens[$tokens[$pointer]['code']]) === true) {
                continue;
            }

            $signature .= $tokens[$pointer]['code'] . ':' . $tokens[$pointer]['content'] . "\0";
        }

        return $signature === '' ? null : $signature;
    }

    /**
     * Whether a body's last statement leaves the enclosing scope
     * unconditionally.
     *
     * The body has to end *at a semicolon* for that question to have an answer:
     * a body ending on a brace ends in a nested block, loop, or conditional, so
     * whatever exit it contains is reached conditionally at best.
     *
     * findStartOfStatement() is clamped to the body, because a body that opens
     * no scope of its own is part of the `if` statement rather than a statement
     * beside it: asked about the `;` of `if ($a) return;` it walks back to the
     * `if`, which is not an exit and would silence every brace-less guard
     * clause in the language.
     */
    private function bodyExits(File $phpcsFile, int $bodyStart, int $bodyEnd): bool
    {
        $tokens = $phpcsFile->getTokens();
        $last = $phpcsFile->findPrevious(Tokens::$emptyTokens, $bodyEnd, $bodyStart, true);

        if ($last === false || $tokens[$last]['code'] !== T_SEMICOLON) {
            return false;
        }

        $statement = max($phpcsFile->findStartOfStatement($last), $bodyStart);
        $statement = $phpcsFile->findNext(Tokens::$emptyTokens, $statement, $last, true);

        return $statement !== false && in_array($tokens[$statement]['code'], self::EXIT_STATEMENTS, true);
    }

    /**
     * The run of adjacent separate plain `if` statements the given chain heads,
     * every one of them carrying the same exiting body.
     *
     * The head's own body is checked first, so a non-qualifying `if` costs one
     * signature and no forward walk. A statement that is not a plain `if`, an
     * `if` that continues into `elseif`/`else`, and any body that differs or
     * does not exit all end the run — and none of them is consumed, so each
     * gets its own turn as a head, which is what lets `A A B B` report two
     * groups rather than one.
     *
     * An incomplete chain is refused as a *candidate*: its clauses were read,
     * but the chain continues past them into something the sniff could not
     * read, so the `if` carries a continuation and is not a plain one.
     *
     * The same chain needs no refusing as the run's *head*, and asking would be
     * a branch no input can reach. An incomplete chain stopped at a clause it
     * could not read, which means the chain continued — so the token after the
     * prefix's recorded end is the `elseif` or `else` it stopped on, never an
     * `if`. The walk below therefore ends on its first step, of its own accord.
     *
     * @param array{
     *     clauses: array<int, array{pointer: int, signature: string|null, exits: bool}>,
     *     end: int,
     *     complete: bool
     * } $chain
     *
     * @return array<int, array{pointer: int, signature: string|null, exits: bool}>
     */
    private function collectRun(File $phpcsFile, array $chain): array
    {
        $head = $chain['clauses'][0];
        $run = [$head];

        if ($head['signature'] === null || $head['exits'] === false) {
            return $run;
        }

        if ($this->isBracelessBody($phpcsFile, $head['pointer']) === true) {
            return $run;
        }

        $end = $chain['end'];

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if ($next === false || $phpcsFile->getTokens()[$next]['code'] !== T_IF) {
                return $run;
            }

            $candidate = $this->collectChain($phpcsFile, $next);

            if ($candidate === null || $candidate['complete'] === false || count($candidate['clauses']) !== 1) {
                return $run;
            }

            $clause = $candidate['clauses'][0];

            if ($clause['exits'] === false || $clause['signature'] !== $head['signature']) {
                return $run;
            }

            $run[] = $clause;
            $end = $candidate['end'];
        }
    }

    /**
     * Warns on every maximal run of two or more adjacent chain branches that
     * share a body.
     *
     * An `else` carries a null signature and so can never join a run, which is
     * both correct — it has no condition to combine — and what stops a chain
     * ending `elseif ($b) { X } else { X }` from being reported as combinable.
     *
     * @param array<int, array{pointer: int, signature: string|null, exits: bool}> $clauses
     */
    private function reportChain(File $phpcsFile, array $clauses): void
    {
        foreach ($this->groupBySignature($clauses) as $group) {
            $this->warnOnGroup(
                $phpcsFile,
                $group,
                'ChainBranches',
                'This branch of an if/elseif chain has the same body as %s.'
                    . ' Combine the conditions with "||"'
                    . ' (see docs/standards/conditionals-combine-where-possible.md).',
                ['the adjacent branch on line ', 'the adjacent branches on lines ']
            );
        }
    }

    /**
     * Warns on a run of adjacent separate `if` statements, once the run is long
     * enough to be one.
     *
     * @param array<int, array{pointer: int, signature: string|null, exits: bool}> $run
     */
    private function reportRun(File $phpcsFile, array $run): void
    {
        if (count($run) < 2) {
            return;
        }

        $this->warnOnGroup(
            $phpcsFile,
            $run,
            'AdjacentIfs',
            'This "if" has the same exiting body as %s.'
                . ' Combine the conditions with "||"'
                . ' (see docs/standards/conditionals-combine-where-possible.md).',
            ['the adjacent "if" on line ', 'the adjacent "if" statements on lines ']
        );
    }

    /**
     * The maximal runs of two or more consecutive clauses sharing one
     * signature. A null signature — an `else`, an empty body, one the sniff
     * does not compare — matches nothing, not even another null.
     *
     * @param array<int, array{pointer: int, signature: string|null, exits: bool}> $clauses
     *
     * @return array<int, array<int, array{pointer: int, signature: string|null, exits: bool}>>
     */
    private function groupBySignature(array $clauses): array
    {
        $groups = [];
        $current = [];

        foreach ($clauses as $clause) {
            $joins = $current !== []
                && $clause['signature'] !== null
                && $clause['signature'] === $current[count($current) - 1]['signature'];

            if ($joins === false) {
                if (count($current) > 1) {
                    $groups[] = $current;
                }

                $current = [$clause];

                continue;
            }

            $current[] = $clause;
        }

        if (count($current) > 1) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * One warning per member of a group, at that member's own keyword, naming
     * the other members' lines.
     *
     * @param array<int, array{pointer: int, signature: string|null, exits: bool}> $group
     * @param array{0: string, 1: string}                                         $leads
     */
    private function warnOnGroup(
        File $phpcsFile,
        array $group,
        string $code,
        string $message,
        array $leads
    ): void {
        $tokens = $phpcsFile->getTokens();

        foreach ($group as $position => $member) {
            $others = $group;
            unset($others[$position]);

            $phpcsFile->addWarning(
                $message,
                $member['pointer'],
                $code,
                [$this->describeMembers($tokens, $others, $leads)]
            );
        }
    }

    /**
     * The clause naming the other members of a group, as "…on line 21" or
     * "…on lines 21 and 42" — composed here, from the singular and plural lead
     * its caller supplies, rather than left to a `%s` list, so both readings
     * are English.
     *
     * @param array<int, array<string, mixed>>                                    $tokens
     * @param array<int, array{pointer: int, signature: string|null, exits: bool}> $members
     * @param array{0: string, 1: string}                                         $leads
     */
    private function describeMembers(array $tokens, array $members, array $leads): string
    {
        $lines = [];

        foreach ($members as $member) {
            $lines[] = $tokens[$member['pointer']]['line'];
        }

        $last = array_pop($lines);

        if ($lines === []) {
            return $leads[0] . $last;
        }

        return $leads[1] . implode(', ', $lines) . ' and ' . $last;
    }
}

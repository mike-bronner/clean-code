<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class CombinableConditionsSniff implements Sniff
{
    private const OPEN_TAGS = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];

    private const EXIT_STATEMENTS = [T_RETURN, T_THROW, T_CONTINUE, T_BREAK, T_EXIT];

    private const NESTING_STATEMENTS = [T_IF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_DO, T_TRY];

    private const BRACELESS_BODY_OWNERS = [T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_DECLARE];

    private array $scanCounts = [
        'run.memberSkips' => 0,
        'braceless.nestingRefusals' => 0,
        'braceless.endScans' => 0,
    ];

    public function register(): array
    {
        return self::OPEN_TAGS;
    }

    public function scanCounts(): array
    {
        return $this->scanCounts;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($phpcsFile->findPrevious(self::OPEN_TAGS, ($stackPtr - 1)) !== false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $grouped = [];

        foreach ($tokens as $pointer => $token) {
            if ($token['code'] !== T_IF) {
                continue;
            }

            if (isset($grouped[$pointer]) === true) {
                $this->scanCounts['run.memberSkips']++;

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

    private function isChainHead(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previous === false) {
            return true;
        }

        return $phpcsFile->getTokens()[$previous]['code'] !== T_ELSE;
    }

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

            if (
                $code === T_ELSE
                || $next === null
            ) {
                break;
            }

            // A spaced `else if`: the T_ELSE carries neither condition nor
            // scope, so the clause belongs to the trailing `if`.
            if ($tokens[$next]['code'] === T_ELSE) {
                $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

                if (
                    $after !== false
                    && $tokens[$after]['code'] === T_IF
                ) {
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

        if ($bodyStart === false) {
            return null;
        }

        if (in_array($tokens[$bodyStart]['code'], self::NESTING_STATEMENTS, true) === true) {
            $this->scanCounts['braceless.nestingRefusals']++;

            return null;
        }

        // A colon here is an alternative-syntax clause whose `endif` never
        // arrived: the tokenizer leaves such a clause with no scope at all, and
        // reading its body as a brace-less statement would report a chain in a
        // file PHP itself refuses to parse. No brace-less body can open on one.
        if ($tokens[$bodyStart]['code'] === T_COLON) {
            return null;
        }

        $this->scanCounts['braceless.endScans']++;
        $bodyEnd = $phpcsFile->findEndOfStatement($bodyStart);

        if (
            $bodyEnd <= $bodyStart
            || $tokens[$bodyEnd]['code'] !== T_SEMICOLON
        ) {
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

    private function bodyExits(File $phpcsFile, int $bodyStart, int $bodyEnd): bool
    {
        $tokens = $phpcsFile->getTokens();
        $last = $phpcsFile->findPrevious(Tokens::$emptyTokens, $bodyEnd, $bodyStart, true);

        if (
            $last === false
            || $tokens[$last]['code'] !== T_SEMICOLON
        ) {
            return false;
        }

        $statement = max($phpcsFile->findStartOfStatement($last), $bodyStart);
        $statement = $phpcsFile->findNext(Tokens::$emptyTokens, $statement, $last, true);

        return $statement !== false && in_array($tokens[$statement]['code'], self::EXIT_STATEMENTS, true);
    }

    private function collectRun(File $phpcsFile, array $chain): array
    {
        $head = $chain['clauses'][0];
        $run = [$head];

        if (
            $head['signature'] === null
            || $head['exits'] === false
        ) {
            return $run;
        }

        if ($this->isBracelessBody($phpcsFile, $head['pointer']) === true) {
            return $run;
        }

        $end = $chain['end'];

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if (
                $next === false
                || $phpcsFile->getTokens()[$next]['code'] !== T_IF
            ) {
                return $run;
            }

            $candidate = $this->collectChain($phpcsFile, $next);

            if (
                $candidate === null
                || $candidate['complete'] === false
                || count($candidate['clauses']) !== 1
            ) {
                return $run;
            }

            $clause = $candidate['clauses'][0];

            if (
                $clause['exits'] === false
                || $clause['signature'] !== $head['signature']
            ) {
                return $run;
            }

            $run[] = $clause;
            $end = $candidate['end'];
        }
    }

    private function reportChain(File $phpcsFile, array $clauses): void
    {
        foreach ($this->groupBySignature($clauses) as $group) {
            $this->warnOnGroup(
                $phpcsFile,
                $group,
                'ChainBranches',
                'This branch of an if/elseif chain has the same body as %s.'
                    . " Combine the conditions with \"||\""
                    . ' (see docs/standards/conditionals-combine-where-possible.md).',
                ['the adjacent branch on line ', 'the adjacent branches on lines ']
            );
        }
    }

    private function reportRun(File $phpcsFile, array $run): void
    {
        if (count($run) < 2) {
            return;
        }

        $this->warnOnGroup(
            $phpcsFile,
            $run,
            'AdjacentIfs',
            "This \"if\" has the same exiting body as %s."
                . " Combine the conditions with \"||\""
                . ' (see docs/standards/conditionals-combine-where-possible.md).',
            ["the adjacent \"if\" on line ", "the adjacent \"if\" statements on lines "]
        );
    }

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

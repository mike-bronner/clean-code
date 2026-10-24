<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class AvoidConditionalsSniff implements Sniff
{
    private const CONSTRUCTS = [
        T_IF => ['if', 'IfStatement'],
        T_ELSEIF => ['elseif', 'ElseIfStatement'],
        T_INLINE_THEN => ['ternary', 'TernaryExpression'],
        T_SWITCH => ['switch', 'SwitchStatement'],
    ];

    protected array $guardExits = [
        T_RETURN,
        T_THROW,
    ];

    protected array $branchContinuations = [
        T_ELSE,
        T_ELSEIF,
    ];

    protected array $siblingBranches = [
        T_CATCH,
        T_FINALLY,
        T_ELSE,
        T_ELSEIF,
    ];

    protected array $callables = [
        T_FUNCTION,
        T_CLOSURE,
    ];

    public function register(): array
    {
        return array_keys(self::CONSTRUCTS);
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($this->isGuardClause($phpcsFile, $stackPtr) === true) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        [$construct, $violationCode] = self::CONSTRUCTS[$tokens[$stackPtr]['code']];

        $phpcsFile->addWarning(
                "Avoid conditionals where possible: \"%s\" adds a branch, which raises cyclomatic"
                    . " complexity."
                    . ' Prefer polymorphism, a mapping array, or match where one applies.',
                $stackPtr,
                $violationCode,
                [$construct]
            );
    }

    protected function isGuardClause(File $phpcsFile, int $stackPtr): bool
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        return $token['code'] === T_IF
            && isset($token['scope_opener'], $token['scope_closer'])
            && $this->isChained($phpcsFile, $stackPtr) === false
            && $this->bodyIsOneExit($phpcsFile, $token['scope_opener'], $token['scope_closer'])
            && $this->isFollowedByStatement($phpcsFile, $stackPtr, $token['scope_closer']);
    }

    protected function isChained(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

        return in_array($tokens[$closer]['code'], $this->branchContinuations, true)
            || $this->isTokenIn($phpcsFile, $before, $this->branchContinuations)
            || $this->isTokenIn($phpcsFile, $after, $this->branchContinuations);
    }

    protected function bodyIsOneExit(File $phpcsFile, int $opener, int $closer): bool
    {
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, ($opener + 1), $closer, true);

        return $this->isTokenIn($phpcsFile, $first, $this->guardExits)
            && $this->endsBefore($phpcsFile, $first, $closer);
    }

    protected function endsBefore(File $phpcsFile, int $statement, int $closer): bool
    {
        $end = $phpcsFile->findEndOfStatement($statement);

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true) === $closer;
    }

    protected function isFollowedByStatement(File $phpcsFile, int $stackPtr, int $closer): bool
    {
        $limit = $this->enclosingCallableEnd($phpcsFile, $stackPtr);
        $skipped = [...array_values(Tokens::$emptyTokens), T_SEMICOLON];
        $next = $closer;

        do {
            $code = $phpcsFile->findNext($skipped, ($next + 1), $limit, true);
            $next = $this->skippedThrough($phpcsFile, $code);
        } while ($next !== null);

        return $code !== false;
    }

    protected function skippedThrough(File $phpcsFile, int|false $pointer): ?int
    {
        return match (true) {
            $pointer === false => null,
            $this->closesScope($phpcsFile, $pointer) => $pointer,
            default => $this->branchEnd($phpcsFile, $pointer),
        };
    }

    protected function branchEnd(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $owner = $phpcsFile->findNext(Tokens::$emptyTokens, ($pointer + 1), null, true);
        $ownerCloser = $tokens[$owner]['scope_closer'] ?? $pointer;

        return match ($this->isTokenIn($phpcsFile, $pointer, $this->siblingBranches)) {
            false => null,
            true => $tokens[$pointer]['scope_closer'] ?? $ownerCloser,
        };
    }

    protected function closesScope(File $phpcsFile, int $pointer): bool
    {
        return ($phpcsFile->getTokens()[$pointer]['scope_closer'] ?? null) === $pointer;
    }

    protected function enclosingCallableEnd(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $callables = array_filter(
                $tokens[$stackPtr]['conditions'],
                fn (int|string $code): bool => in_array($code, $this->callables, true)
            );
        $innermost = array_key_last($callables);

        return match ($innermost) {
            null => $phpcsFile->numTokens,
            default => $tokens[$innermost]['scope_closer'],
        };
    }

    protected function isTokenIn(File $phpcsFile, int|false $pointer, array $codes): bool
    {
        return $pointer !== false
            && in_array($phpcsFile->getTokens()[$pointer]['code'], $codes, true);
    }
}

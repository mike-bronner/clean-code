<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class TypeDiscriminatorDispatchSniff implements Sniff
{
    public $minimumBranches = 3;

    private const MESSAGE = 'Open-Closed principle: %d branches of this %s dispatch on the type discriminator'
        . " \"%s\", so a new variant of that type means editing this construct. Prefer polymorphism, or a"
        . ' mapping array where the branches only produce a value.';

    private const EQUALITY_OPERATORS = [
        T_IS_IDENTICAL,
        T_IS_EQUAL,
    ];

    private const SCALAR_LITERALS = [
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_TRUE,
        T_FALSE,
    ];

    private const NUMERIC_LITERALS = [
        T_LNUMBER,
        T_DNUMBER,
    ];

    private const SIGN_TOKENS = [
        T_MINUS,
        T_PLUS,
    ];

    private const PROPERTY_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
    ];

    private const CONTINUATION_KEYWORDS = [
        T_ELSEIF,
        T_ELSE,
    ];

    private const BODY_TERMINATORS = [
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_TAG,
        T_ENDIF,
        T_ENDWHILE,
        T_ENDFOR,
        T_ENDFOREACH,
        T_ENDSWITCH,
        T_ENDDECLARE,
    ];

    private array $scanCounts = [
        'bracelessNextClause.walks' => 0,
        'bracelessNextClause.steps' => 0,
    ];

    public function register(): array
    {
        return [T_SWITCH, T_IF];
    }

    public function scanCounts(): array
    {
        return $this->scanCounts;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_SWITCH) {
            $this->processSwitch($phpcsFile, $stackPtr);

            return;
        }

        $this->processIfChain($phpcsFile, $stackPtr);
    }

    private function processSwitch(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer']) === false) {
            return;
        }

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $subject = $this->discriminator($tokens, $this->significantTokens(
            $phpcsFile,
            $tokens[$stackPtr]['parenthesis_opener'] + 1,
            $tokens[$stackPtr]['parenthesis_closer'] - 1
        ));

        if ($subject === null) {
            return;
        }

        $branches = $this->switchBranches($phpcsFile, $stackPtr);

        if (
            $branches === null
            || $branches < (int) $this->minimumBranches
        ) {
            return;
        }

        $phpcsFile->addWarning(
            self::MESSAGE,
            $stackPtr,
            'SwitchDispatch',
            [$branches, 'switch', $subject]
        );
    }

    private function switchBranches(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];
        $branches = 0;

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if (
                $code === T_SWITCH
                && isset($tokens[$pointer]['scope_closer']) === true
            ) {
                $pointer = $tokens[$pointer]['scope_closer'];

                continue;
            }

            if (
                $code !== T_CASE
                && $code !== T_DEFAULT
            ) {
                continue;
            }

            if (array_key_last($tokens[$pointer]['conditions']) !== $stackPtr) {
                continue;
            }

            if (isset($tokens[$pointer]['scope_opener']) === false) {
                return null;
            }

            if ($code === T_DEFAULT) {
                $branches++;

                continue;
            }

            $label = $this->significantTokens($phpcsFile, $pointer + 1, $tokens[$pointer]['scope_opener'] - 1);

            if ($this->isScalarLiteral($tokens, $label) === false) {
                return null;
            }

            $branches++;
        }

        return $branches;
    }

    private function processIfChain(File $phpcsFile, int $stackPtr): void
    {
        if ($this->isChainHead($phpcsFile, $stackPtr) === false) {
            return;
        }

        $subjects = $this->collectSubjects($phpcsFile, $stackPtr);

        if (
            $subjects === null
            || count($subjects) < (int) $this->minimumBranches
        ) {
            return;
        }

        $subject = $this->sharedSubject($subjects);

        if ($subject === null) {
            return;
        }

        $phpcsFile->addWarning(
            self::MESSAGE,
            $stackPtr,
            'IfChain',
            [count($subjects), 'if/elseif chain', $subject]
        );
    }

    private function isChainHead(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

        if ($previous === false) {
            return true;
        }

        return $phpcsFile->getTokens()[$previous]['code'] !== T_ELSE;
    }

    private function collectSubjects(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $subjects = [];
        $pointer = $stackPtr;

        while ($pointer !== null) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_ELSE) {
                $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

                if ($next === false) {
                    return null;
                }

                if ($tokens[$next]['code'] === T_IF) {
                    $pointer = $next;

                    continue;
                }

                $subjects[] = null;

                break;
            }

            $subject = $this->conditionDiscriminator($phpcsFile, $pointer);

            if ($subject === null) {
                return null;
            }

            $subjects[] = $subject;
            $pointer = $this->nextClause($phpcsFile, $pointer);
        }

        return $subjects;
    }

    private function nextClause(File $phpcsFile, int $clausePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['scope_opener'], $tokens[$clausePtr]['scope_closer']) === true) {
            $closer = $tokens[$clausePtr]['scope_closer'];

            if ($tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET) {
                return $this->continuation($tokens, $closer);
            }

            return $this->continuation(
                $tokens,
                $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true)
            );
        }

        return $this->bracelessNextClause($phpcsFile, $clausePtr);
    }

    private function bracelessNextClause(File $phpcsFile, int $clausePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['parenthesis_closer']) === false) {
            return null;
        }

        $pointer = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            $tokens[$clausePtr]['parenthesis_closer'] + 1,
            null,
            true
        );

        $openDoBodies = 0;
        $this->scanCounts['bracelessNextClause.walks']++;

        while ($pointer !== false) {
            $this->scanCounts['bracelessNextClause.steps']++;
            $code = $tokens[$pointer]['code'];

            if ($code === T_IF) {
                return null;
            }

            if (in_array($code, self::CONTINUATION_KEYWORDS, true) === true) {
                return $pointer;
            }

            if (
                $code === T_DO
                && isset($tokens[$pointer]['scope_closer']) === false
            ) {
                ++$openDoBodies;
            }

            if ($code === T_SEMICOLON) {
                if ($openDoBodies === 0) {
                    return $this->continuation(
                        $tokens,
                        $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true)
                    );
                }

                --$openDoBodies;
            }

            if (in_array($code, self::BODY_TERMINATORS, true) === true) {
                return null;
            }

            $pointer = $phpcsFile->findNext(
                Tokens::$emptyTokens,
                $this->groupCloser($tokens, $pointer) + 1,
                null,
                true
            );
        }

        return null;
    }

    private function groupCloser(array $tokens, int $pointer): int
    {
        $token = $tokens[$pointer];
        $ownsScope = isset($token['scope_opener'], $token['scope_closer']) === true
            && ($pointer === $token['scope_opener'] || $pointer === ($token['scope_condition'] ?? null));

        if ($ownsScope === true) {
            return max($pointer, $token['scope_closer']);
        }

        if (
            isset($token['parenthesis_closer']) === true
            && $pointer === ($token['parenthesis_opener'] ?? null)
        ) {
            return max($pointer, $token['parenthesis_closer']);
        }

        if (
            isset($token['bracket_closer']) === true
            && $pointer === ($token['bracket_opener'] ?? null)
        ) {
            return max($pointer, $token['bracket_closer']);
        }

        return $pointer;
    }

    private function continuation(array $tokens, int|false $pointer): ?int
    {
        if ($pointer === false) {
            return null;
        }

        return in_array($tokens[$pointer]['code'], self::CONTINUATION_KEYWORDS, true) === true
            ? $pointer
            : null;
    }

    private function conditionDiscriminator(File $phpcsFile, int $clausePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['parenthesis_opener'], $tokens[$clausePtr]['parenthesis_closer']) === false) {
            return null;
        }

        $condition = $this->significantTokens(
            $phpcsFile,
            $tokens[$clausePtr]['parenthesis_opener'] + 1,
            $tokens[$clausePtr]['parenthesis_closer'] - 1
        );

        $operators = [];

        foreach ($condition as $index => $pointer) {
            if (in_array($tokens[$pointer]['code'], self::EQUALITY_OPERATORS, true) === true) {
                $operators[] = $index;
            }
        }

        if (count($operators) !== 1) {
            return null;
        }

        $left = array_slice($condition, 0, $operators[0]);
        $right = array_slice($condition, $operators[0] + 1);
        $subject = $this->discriminator($tokens, $left);

        if (
            $subject !== null
            && $this->isScalarLiteral($tokens, $right) === true
        ) {
            return $subject;
        }

        $subject = $this->discriminator($tokens, $right);

        return $subject !== null && $this->isScalarLiteral($tokens, $left) === true ? $subject : null;
    }

    private function discriminator(array $tokens, array $pointers): ?string
    {
        $codes = array_map(static fn (int $pointer): int|string => $tokens[$pointer]['code'], $pointers);

        $isPropertyRead = count($codes) === 3
            && $codes[0] === T_VARIABLE
            && in_array($codes[1], self::PROPERTY_OPERATORS, true) === true
            && $codes[2] === T_STRING;

        $isIndexRead = count($codes) === 4
            && $codes[0] === T_VARIABLE
            && $codes[1] === T_OPEN_SQUARE_BRACKET
            && $codes[2] === T_CONSTANT_ENCAPSED_STRING
            && $codes[3] === T_CLOSE_SQUARE_BRACKET;

        if (
            $isPropertyRead === false
            && $isIndexRead === false
        ) {
            return null;
        }

        return implode('', array_map(
            static fn (int $pointer): string => $tokens[$pointer]['content'],
            $pointers
        ));
    }

    private function isScalarLiteral(array $tokens, array $pointers): bool
    {
        if (count($pointers) === 1) {
            return in_array($tokens[$pointers[0]]['code'], self::SCALAR_LITERALS, true);
        }

        return count($pointers) === 2
            && in_array($tokens[$pointers[0]]['code'], self::SIGN_TOKENS, true) === true
            && in_array($tokens[$pointers[1]]['code'], self::NUMERIC_LITERALS, true) === true;
    }

    private function sharedSubject(array $subjects): ?string
    {
        $named = array_unique(array_filter($subjects, static fn (?string $subject): bool => $subject !== null));

        return count($named) === 1 ? (string) reset($named) : null;
    }

    private function significantTokens(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            if (
                isset($tokens[$pointer]) === true
                && isset(Tokens::$emptyTokens[$tokens[$pointer]['code']]) === false
            ) {
                $pointers[] = $pointer;
            }
        }

        return $pointers;
    }
}

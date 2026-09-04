<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowMagicNumbersSniff implements Sniff
{
    public array $ignoredNumbers = [
        '0',
        '1',
        '-1',
    ];

    private const DECLARATIVE_PARENTHESES = [
        T_FUNCTION,
        T_CLOSURE,
        T_FN,
        T_DECLARE,
    ];

    private const DECLARATION_SCOPES = [
        T_CLASS,
        T_ANON_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_ENUM,
    ];

    private const OPERAND_ENDINGS = [
        T_VARIABLE,
        T_LNUMBER,
        T_DNUMBER,
        T_STRING,
        T_CONSTANT_ENCAPSED_STRING,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    public function register(): array
    {
        return [
            T_LNUMBER,
            T_DNUMBER,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isDeclarationSite($phpcsFile, $stackPtr) === true) {
            return;
        }

        $literal = $this->signedLiteral($phpcsFile, $stackPtr);

        if ($this->isIgnored($literal) === true) {
            return;
        }

        $phpcsFile->addWarning(
            'Magic number %s is not searchable; name it with a constant '
                . '(see docs/standards/naming-semantic-naming-principles.md)',
            $stackPtr,
            'Found',
            [$literal]
        );
    }

    private function isDeclarationSite(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['attribute_opener']) === true) {
            return true;
        }

        $conditions = $tokens[$stackPtr]['conditions'];

        if (
            $conditions !== []
            && in_array(end($conditions), self::DECLARATION_SCOPES, true) === true
        ) {
            return true;
        }

        if ($this->isInsideDeclarativeParentheses($phpcsFile, $stackPtr) === true) {
            return true;
        }

        return $this->isConstValue($phpcsFile, $stackPtr);
    }

    private function isInsideDeclarativeParentheses(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis'] ?? []) as $opener) {
            if (isset($tokens[$opener]['parenthesis_owner']) === false) {
                continue;
            }

            $owner = $tokens[$opener]['parenthesis_owner'];

            if (in_array($tokens[$owner]['code'], self::DECLARATIVE_PARENTHESES, true) === true) {
                return true;
            }
        }

        return false;
    }

    private function isConstValue(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $statementStart = $phpcsFile->findPrevious(
            [
                T_CONST,
                T_SEMICOLON,
                T_OPEN_CURLY_BRACKET,
                T_CLOSE_CURLY_BRACKET,
                T_OPEN_TAG,
            ],
            ($stackPtr - 1)
        );

        return $statementStart !== false && $tokens[$statementStart]['code'] === T_CONST;
    }

    private function signedLiteral(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];

        $minusPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $minusPtr === false
            || $tokens[$minusPtr]['code'] !== T_MINUS
        ) {
            return $content;
        }

        $beforeMinus = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($minusPtr - 1), null, true);

        if (
            $beforeMinus !== false
            && in_array($tokens[$beforeMinus]['code'], self::OPERAND_ENDINGS, true) === true
        ) {
            return $content;
        }

        return "-{$content}";
    }

    private function isIgnored(string $literal): bool
    {
        $value = $this->numericValue($literal);

        foreach ($this->ignoredNumbers as $ignored) {
            if ($this->numericValue((string) $ignored) === $value) {
                return true;
            }
        }

        return false;
    }

    private function numericValue(string $literal): float
    {
        $literal = trim(str_replace('_', '', $literal));
        $sign = 1.0;

        if (str_starts_with($literal, '-') === true) {
            $sign = -1.0;
            $literal = substr($literal, 1);
        }

        $magnitude = match (true) {
            preg_match('/^0[xX][0-9a-fA-F]+$/', $literal) === 1 => (float) hexdec(substr($literal, 2)),
            preg_match('/^0[bB][01]+$/', $literal) === 1 => (float) bindec(substr($literal, 2)),
            preg_match('/^0[oO][0-7]+$/', $literal) === 1 => (float) octdec(substr($literal, 2)),
            preg_match('/^0[0-7]+$/', $literal) === 1 => (float) octdec($literal),
            default => (float) $literal,
        };

        return $sign * $magnitude;
    }
}

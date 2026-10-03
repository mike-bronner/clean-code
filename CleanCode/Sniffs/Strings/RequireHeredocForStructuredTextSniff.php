<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use MikeBronner\CleanCode\Support\StructuredText;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class RequireHeredocForStructuredTextSniff implements Sniff
{
    private const MESSAGE
        = 'Embedded %s belongs in a HereDoc, not a quoted string: the delimiter is what lets an editor'
        . ' highlight it as the language it is, and it reads without quote escaping';

    public function register(): array
    {
        return [
            T_CONSTANT_ENCAPSED_STRING,
            T_DOUBLE_QUOTED_STRING,
        ];
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint -- interface-mandated, see CONTRIBUTING.md
    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($this->opensChain($phpcsFile, $stackPtr) === false) {
            return;
        }

        $text = (new StringLiteral())->concatenated($phpcsFile->getTokens(), $stackPtr);
        $language = $this->languageOf($text);

        if ($language === null) {
            return;
        }

        $phpcsFile->addError(self::MESSAGE, $stackPtr, 'StructuredTextInString', [$language]);
    }

    private function languageOf(string $text): ?string
    {
        $structured = new StructuredText();

        $languages = [
            'markup' => $structured->isMarkup($text),
            'SQL' => $structured->isSql($text),
            'JSON or YAML' => $structured->isSerialised($text),
            'configuration' => $structured->isConfig($text),
            'markdown' => $structured->isMarkdown($text),
        ];

        foreach ($languages as $name => $matched) {
            if ($matched === true) {
                return $name;
            }
        }

        return null;
    }

    private function opensChain(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($this->isStringLiteral($tokens, $stackPtr - 1) === true) {
            return false;
        }

        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        return $previous === false
            || $tokens[$previous]['code'] !== T_STRING_CONCAT;
    }

    private function isStringLiteral(array $tokens, int $ptr): bool
    {
        return isset($tokens[$ptr]) === true
            && in_array(
                $tokens[$ptr]['code'],
                [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING],
                true
            ) === true;
    }
}

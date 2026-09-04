<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\StringLiteral;
use MikeBronner\CleanCode\Support\StructuredText;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

// Another language embedded in PHP belongs in a HEREDOC, at any length.
//
// Not only markup: HTML, XML, SQL, JSON, YAML, INI and other config blocks, and
// markdown all read as the language they are only when the delimiter names one.
// An editor highlights a HEREDOC body by its marker and a quoted string as one
// flat run of characters, so length has nothing to do with it — a one-line
// query earns the same treatment as a twenty-line template.
//
// Length is the sibling concern and belongs to
// CleanCode.Strings.MultilineStrings, which counts source lines and never looks
// at content. Between them the two sniffs own disjoint slices, so no string is
// reported twice.
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
    public function process(File $phpcsFile, $stackPtr): void
    {
        // A chain reports once, at its first fragment. Every later fragment
        // reads the same joined text, so without this the same block is
        // reported once per piece.
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

    // Names the language for the message, and answers null for prose. The order
    // is the order the checks are cheapest and most specific in.
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

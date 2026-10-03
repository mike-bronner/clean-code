<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\Markup;
use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class HtmlAttributeQuotesSniff implements Sniff
{
    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    public function register(): array
    {
        return self::STRING_TOKENS;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];

        $apostrophe = $this->phpStringDelimiter($phpcsFile, $stackPtr) === "'" ? "\\'" : "'";

        if ($this->hasApostropheAttribute($content, $apostrophe) === false) {
            return;
        }

        $fixed = $this->rewriteAttributes($content, $apostrophe);

        if ($fixed === null) {
            $phpcsFile->addError(
                'HTML attributes must use double quotes, not apostrophes; the value contains a'
                    . ' double quote or a backslash, so convert this attribute manually',
                $stackPtr,
                'Apostrophe'
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'HTML attributes must use double quotes, not apostrophes',
            $stackPtr,
            'Apostrophe'
        );

        if ($fix === false) {
            return;
        }

        $phpcsFile->fixer
            ->replaceToken($stackPtr, $fixed);
    }

    private function hasApostropheAttribute(string $content, string $apostrophe): bool
    {
        foreach ($this->tagSpans($content) as $span) {
            if (preg_match($this->attributePattern($apostrophe), $span) === 1) {
                return true;
            }
        }

        return false;
    }

    private function rewriteAttributes(string $content, string $apostrophe): ?string
    {
        $quote = $apostrophe === "\\'" ? "\"" : "\\\"";
        $unsafe = false;

        $rewritten = preg_replace_callback(
            (new Markup())->tagSpanPattern(),
            function (array $match) use ($apostrophe, $quote, &$unsafe): string {
                $span = preg_replace_callback(
                    $this->attributePattern($apostrophe),
                    function (array $attr) use ($quote, &$unsafe): string {
                        if ($this->isSafeToConvert($attr[2]) === false) {
                            $unsafe = true;

                            return $attr[0];
                        }

                        return "{$attr[1]}={$quote}{$attr[2]}{$quote}";
                    },
                    $match[0]
                );

                if ($span === null) {
                    $unsafe = true;

                    return $match[0];
                }

                return $span;
            },
            $content
        );

        return $unsafe ? null : $rewritten;
    }

    private function isSafeToConvert(string $value): bool
    {
        return strpbrk($value, "\"\\") === false;
    }

    private function tagSpans(string $content): array
    {
        if (preg_match_all((new Markup())->tagSpanPattern(), $content, $matches) === false) {
            return [];
        }

        return $matches[0];
    }

    private function attributePattern(string $apostrophe): string
    {
        $quoted = preg_quote($apostrophe, '#');

        return "#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\\s*=\\s*{$quoted}([^']*){$quoted}#";
    }

    private function phpStringDelimiter(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $stackPtr;

        while (
            $opener > 0
            && in_array($tokens[$opener - 1]['code'], self::STRING_TOKENS, true) === true
        ) {
            $opener--;
        }

        return (new StringLiteral())->delimiter($tokens[$opener]['content']) ?? "\"";
    }
}

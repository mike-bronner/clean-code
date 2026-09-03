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

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];

        // The escaped-apostrophe sequence as it appears in this PHP string
        // context: a literal `'` inside a double-quoted string, `\'` inside a
        // single-quoted one.
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
        // Double-quoted PHP context escapes the replacement quotes (`\"`);
        // single-quoted context takes them literally (`"`).
        $quote = $apostrophe === "\\'" ? "\"" : '\\"';
        $unsafe = false;

        // Audited, unguarded on purpose: a failed span read returns null, and
        // null is already this method's "unsafe, leave the file alone"
        // sentinel — the same value the return below hands back. Safe by
        // circumstance rather than by construction, so it is written down here:
        // change that sentinel and this call needs a guard of its own.
        $rewritten = preg_replace_callback(
            Markup::tagSpanPattern(),
            function (array $match) use ($apostrophe, $quote, &$unsafe): string {
                $span = preg_replace_callback(
                    $this->attributePattern($apostrophe),
                    static function (array $attr) use ($quote, &$unsafe): string {
                        if (self::isSafeToConvert($attr[2]) === false) {
                            $unsafe = true;

                            return $attr[0];
                        }

                        return $attr[1] . '=' . $quote . $attr[2] . $quote;
                    },
                    $match[0]
                );

                // The attribute read gave out inside this span. Casting the
                // null to a string would replace the whole tag with '' and say
                // nothing, so the span is kept as written and the rewrite is
                // marked unsafe — the same answer an unconvertible value earns
                // above, and the one the caller reads as "report the violation,
                // fix nothing".
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

    private static function isSafeToConvert(string $value): bool
    {
        return strpbrk($value, '"\\') === false;
    }

    private function tagSpans(string $content): array
    {
        if (preg_match_all(Markup::tagSpanPattern(), $content, $matches) === false) {
            return [];
        }

        return $matches[0];
    }

    private function attributePattern(string $apostrophe): string
    {
        $quoted = preg_quote($apostrophe, '#');

        return '#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\\s*=\\s*' . $quoted . '([^\']*)' . $quoted . '#';
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

        return StringLiteral::delimiter($tokens[$opener]['content']) ?? "\"";
    }
}

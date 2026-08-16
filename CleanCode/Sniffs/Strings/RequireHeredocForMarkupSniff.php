<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Strings;

use MikeBronner\CleanCode\Support\Markup;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the Strings standard's "define HTML or other renderable code with
 * HereDocs" rule (#25).
 *
 * A regular single- or double-quoted string literal that carries HTML markup
 * is flagged: such content belongs in a HereDoc, where it reads without quote
 * escaping and survives multi-line growth. Compliant HereDoc/NowDoc bodies
 * tokenize as `T_HEREDOC`/`T_NOWDOC` (not the encapsed-string tokens this
 * sniff registers), so they never trip it.
 *
 * Detection-only by design: converting an inline string to a HereDoc changes
 * the surrounding statement's layout (a new dedented closing marker, no
 * trailing concatenation), which is a structural edit the standard leaves to
 * the developer — so no auto-fixer is provided.
 */
class RequireHeredocForMarkupSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (Markup::containsHtmlElement($tokens[$stackPtr]['content']) === false) {
            return;
        }

        $phpcsFile->addError(
            'Embed HTML/markup in a HereDoc, not a quoted string, so it renders without quote'
                . ' escaping and reads clearly',
            $stackPtr,
            'MarkupInString'
        );
    }
}

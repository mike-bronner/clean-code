<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids procedural code in a source file: "only implement classes, never
 * procedural code" (Testing: Development Process (TDD), #57 / #129).
 *
 * The file's top level may hold only a `declare`, a `namespace`, `use`
 * imports, comments, attributes, class modifiers, and exactly one `class`,
 * `interface`, `trait`, or `enum` declaration. Every other top-level construct
 * — a call, an assignment, a control structure, a standalone function, a
 * `const`, a `return`, markup — is reported once, at its own line.
 *
 * Stricter than PSR1.Files.SideEffects, which only forbids *mixing* a
 * declaration with side effects: a file that is nothing but procedural code
 * declares no symbol, so PSR-1 stays silent on it while this sniff reports
 * every statement in it.
 *
 * Scoping is a ruleset concern, not a sniff concern. rules.xml restricts the
 * sniff to `src/` and `app/` with `<include-pattern>`, because entry points,
 * config files, route files and pre-Laravel-9 migrations are legitimately
 * procedural. The sniff itself never looks at the file's path.
 *
 * Two deliberate silences, both stated rather than implied:
 *
 * - A file with no top-level declaration at all is reported only for the
 *   procedural statements it contains. A file that declares nothing and
 *   executes nothing — an empty file, a comment-only placeholder — holds no
 *   procedural code to point at, so there is nothing to report.
 * - A closing tag is left to PSR12.Files.ClosingTag, which owns it. Only the
 *   markup *after* one is procedural, and whitespace-only markup (the newline
 *   a `?>` at the end of a file leaves behind) is not reported.
 *
 * Detection only. Wrapping loose statements in a class is a design decision —
 * which class, which method, which visibility — so there is no mechanical
 * rewrite to offer.
 */
class NoProceduralCodeSniff implements Sniff
{
    /**
     * The tokens a file can begin with. The sniff runs once per file, on
     * whichever of these comes first: a `.php` file need not open with
     * `<?php`, and a file that is nothing but markup has no open tag at all.
     */
    private const FILE_ENTRY_TOKENS = [
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_INLINE_HTML,
    ];

    /**
     * The class-like declarations a source file is allowed to carry. Exactly
     * one of them, in any combination.
     */
    private const DECLARATION_TOKENS = [
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    /**
     * Preamble keywords whose statement ends at either a semicolon or an
     * opening brace. The braced forms (`namespace App { … }`,
     * `declare(ticks=1) { … }`) are descended into rather than skipped: their
     * body *is* the file's top level, so skipping the block would let any
     * procedural code inside it through unreported.
     */
    private const PREAMBLE_TOKENS = [
        T_DECLARE,
        T_NAMESPACE,
    ];

    /**
     * Tokens that carry no statement of their own at the top level.
     * T_CLOSE_CURLY_BRACKET is here for the closer of a braced namespace,
     * which is the only curly brace the walk can reach — every declaration is
     * jumped over via its own scope closer.
     */
    private const IGNORED_TOKENS = [
        T_ABSTRACT,
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_TAG,
        T_FINAL,
        T_OPEN_TAG,
        T_READONLY,
        T_SEMICOLON,
    ];

    /**
     * Keywords that continue a compound statement already reported. Without
     * them an `if … else` or a `try … catch … finally` would be reported once
     * per clause instead of once per statement.
     */
    private const CONTINUATION_TOKENS = [
        T_CATCH,
        T_ELSE,
        T_ELSEIF,
        T_FINALLY,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return self::FILE_ENTRY_TOKENS;
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->findPrevious(self::FILE_ENTRY_TOKENS, ($stackPtr - 1)) !== false) {
            return;
        }

        $declarations = [];
        $tokens = $phpcsFile->getTokens();
        $pointer = 0;

        while (($pointer = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true)) !== false) {
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::DECLARATION_TOKENS, true) === true) {
                $declarations[] = $pointer;
                $pointer = ($this->endOfDeclaration($phpcsFile, $pointer) + 1);

                continue;
            }

            if (in_array($code, self::PREAMBLE_TOKENS, true) === true) {
                $pointer = ($this->endOfPreamble($phpcsFile, $pointer) + 1);

                continue;
            }

            if ($code === T_USE) {
                $pointer = ($phpcsFile->findEndOfStatement($pointer) + 1);

                continue;
            }

            if ($code === T_ATTRIBUTE) {
                $pointer = ($tokens[$pointer]['attribute_closer'] + 1);

                continue;
            }

            if ($this->isIgnorable($phpcsFile, $pointer) === true) {
                $pointer++;

                continue;
            }

            $phpcsFile->addError(
                'Top-level %s is procedural code; a source file declares exactly one class,'
                    . ' interface, trait, or enum and nothing else',
                $pointer,
                'ProceduralStatement',
                [$this->constructLabel($phpcsFile, $pointer)]
            );

            $pointer = ($this->endOfStatement($phpcsFile, $pointer) + 1);
        }

        $this->reportAdditionalDeclarations($phpcsFile, $declarations);
    }

    /**
     * Reports every declaration after the first, so a file carrying several
     * class-like declarations is flagged at each extra one rather than once
     * for the file.
     *
     * @param array<int, int> $declarations
     */
    private function reportAdditionalDeclarations(File $phpcsFile, array $declarations): void
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_slice($declarations, 1) as $pointer) {
            $phpcsFile->addError(
                'A source file declares exactly one class, interface, trait, or enum;'
                    . ' %s %s is an additional declaration',
                $pointer,
                'MultipleDeclarations',
                [
                    strtolower($tokens[$pointer]['content']),
                    (string) $phpcsFile->getDeclarationName($pointer),
                ]
            );
        }
    }

    /**
     * Whether the token at $pointer carries no top-level statement.
     *
     * Markup is the one conditional case: the newline a closing tag leaves at
     * the end of a file tokenizes as inline HTML exactly like real markup
     * does, so whitespace-only content is passed over.
     */
    private function isIgnorable(File $phpcsFile, int $pointer): bool
    {
        $token = $phpcsFile->getTokens()[$pointer];

        if ($token['code'] === T_INLINE_HTML) {
            return trim($token['content']) === '';
        }

        return in_array($token['code'], self::IGNORED_TOKENS, true);
    }

    /**
     * The last token of a class-like declaration. The scope closer is absent
     * only for a declaration the tokenizer never saw terminated — a file being
     * edited — and everything after that keyword is the unclosed body, so the
     * walk ends at the file rather than reading the body as top level and
     * reporting the class's own name as procedural code.
     */
    private function endOfDeclaration(File $phpcsFile, int $pointer): int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        return $token['scope_closer'] ?? $this->endOfFile($phpcsFile);
    }

    /**
     * The last token of a `declare`/`namespace` preamble: its semicolon, or
     * the opening brace of its braced form. Returning the brace is what makes
     * the walk continue *inside* a braced namespace instead of over it.
     *
     * Neither terminator exists in a file truncated mid-preamble, which ends
     * the walk for the same reason as an unterminated declaration: the name
     * that follows the keyword is part of the preamble, not a statement.
     */
    private function endOfPreamble(File $phpcsFile, int $pointer): int
    {
        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], ($pointer + 1));

        return $end === false ? $this->endOfFile($phpcsFile) : $end;
    }

    /**
     * The last token in the file. Returned by the two truncation paths above,
     * so the walk's `+ 1` puts the pointer past the end and stops it.
     */
    private function endOfFile(File $phpcsFile): int
    {
        return ($phpcsFile->numTokens - 1);
    }

    /**
     * The last token of the statement starting at $pointer, including the
     * clauses that continue it: `else`/`elseif`, `catch`/`finally`, and the
     * `while (…)` tail of a `do` block — which is told apart from a `while`
     * loop by having no scope of its own.
     *
     * The loop always advances: findEndOfStatement() never returns a pointer
     * below its own start, and each continuation starts after the previous
     * end.
     */
    private function endOfStatement(File $phpcsFile, int $pointer): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $phpcsFile->findEndOfStatement($pointer);

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if ($next === false) {
                return $end;
            }

            $code = $tokens[$next]['code'];

            if ($code === T_SEMICOLON) {
                $end = $next;

                continue;
            }

            $isDoWhileTail = $code === T_WHILE && isset($tokens[$next]['scope_opener']) === false;

            if (in_array($code, self::CONTINUATION_TOKENS, true) === false && $isDoWhileTail === false) {
                return $end;
            }

            $end = $phpcsFile->findEndOfStatement($next);
        }
    }

    /**
     * A short label naming the offending construct, so the report says which
     * statement to move. Everything but markup is quoted verbatim from the
     * source; a call keeps its parentheses so `helper()` does not read as a
     * bare name.
     */
    private function constructLabel(File $phpcsFile, int $pointer): string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$pointer]['code'] === T_INLINE_HTML) {
            return 'markup';
        }

        $content = $tokens[$pointer]['content'];

        if ($tokens[$pointer]['code'] === T_STRING) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($pointer + 1), null, true);

            if ($next !== false && $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
                $content .= '()';
            }
        }

        return '"' . $content . '"';
    }
}

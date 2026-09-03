<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NoProceduralCodeSniff implements Sniff
{
    private const FILE_ENTRY_TOKENS = [
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_INLINE_HTML,
    ];

    private const DECLARATION_TOKENS = [
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    private const PREAMBLE_TOKENS = [
        T_DECLARE,
        T_NAMESPACE,
    ];

    private const IGNORED_TOKENS = [
        T_ABSTRACT,
        T_CLOSE_CURLY_BRACKET,
        T_CLOSE_TAG,
        T_FINAL,
        T_OPEN_TAG,
        T_READONLY,
        T_SEMICOLON,
    ];

    private const CONTINUATION_TOKENS = [
        T_CATCH,
        T_ELSE,
        T_ELSEIF,
        T_FINALLY,
    ];

    public function register(): array
    {
        return self::FILE_ENTRY_TOKENS;
    }

    public function process(File $phpcsFile, $stackPtr): void
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

    private function isIgnorable(File $phpcsFile, int $pointer): bool
    {
        $token = $phpcsFile->getTokens()[$pointer];

        if ($token['code'] === T_INLINE_HTML) {
            return trim($token['content']) === '';
        }

        return in_array($token['code'], self::IGNORED_TOKENS, true);
    }

    private function endOfDeclaration(File $phpcsFile, int $pointer): int
    {
        $token = $phpcsFile->getTokens()[$pointer];

        return $token['scope_closer'] ?? $this->endOfFile($phpcsFile);
    }

    private function endOfPreamble(File $phpcsFile, int $pointer): int
    {
        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], ($pointer + 1));

        return $end === false ? $this->endOfFile($phpcsFile) : $end;
    }

    private function endOfFile(File $phpcsFile): int
    {
        return ($phpcsFile->numTokens - 1);
    }

    private function endOfStatement(File $phpcsFile, int $pointer): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $this->endOfClause($phpcsFile, $pointer);

        while (true) {
            $isContinuation = in_array($tokens[$end]['code'], self::CONTINUATION_TOKENS, true);
            $next = $isContinuation === true
                ? $end
                : $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if ($next === false) {
                return $end;
            }

            $code = $tokens[$next]['code'];

            if ($code === T_SEMICOLON) {
                $end = $next;

                continue;
            }

            $isDoWhileTail = $code === T_WHILE && isset($tokens[$next]['scope_opener']) === false;

            if (
                in_array($code, self::CONTINUATION_TOKENS, true) === false
                && $isDoWhileTail === false
            ) {
                return $end;
            }

            $clause = $this->endOfClause($phpcsFile, $next);

            if ($clause <= $end) {
                return $end;
            }

            $end = $clause;
        }
    }

    private function endOfClause(File $phpcsFile, int $pointer): int
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($pointer + 1), null, true);

        if (
            $next !== false
            && $phpcsFile->getTokens()[$next]['code'] === T_IF
        ) {
            return $this->endOfClause($phpcsFile, $next);
        }

        return $phpcsFile->findEndOfStatement($pointer);
    }

    private function constructLabel(File $phpcsFile, int $pointer): string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$pointer]['code'] === T_INLINE_HTML) {
            return 'markup';
        }

        $content = $tokens[$pointer]['content'];

        if ($tokens[$pointer]['code'] === T_STRING) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($pointer + 1), null, true);

            if (
                $next !== false
                && $tokens[$next]['code'] === T_OPEN_PARENTHESIS
            ) {
                $content .= '()';
            }
        }

        return "\"" . $content . "\"";
    }
}

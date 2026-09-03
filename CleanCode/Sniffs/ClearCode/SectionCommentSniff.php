<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class SectionCommentSniff implements Sniff
{
    private const TRANSPARENT_SCOPES = [
        T_IF,
        T_ELSE,
        T_ELSEIF,
        T_FOR,
        T_FOREACH,
        T_WHILE,
        T_DO,
        T_SWITCH,
        T_CASE,
        T_DEFAULT,
        T_TRY,
        T_CATCH,
        T_FINALLY,
    ];

    private const FUNCTION_LIKE = [
        T_FUNCTION,
        T_CLOSURE,
        T_FN,
    ];

    private const STATEMENT_BOUNDARY = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    private const CONTINUING_SCOPES = [
        T_CLOSURE,
        T_ANON_CLASS,
        T_MATCH,
        T_DO,
    ];

    private const CONTINUATION_KEYWORDS = [
        T_ELSE,
        T_ELSEIF,
        T_CATCH,
        T_FINALLY,
    ];

    public array $debtMarkers = [
        'TODO',
        'FIXME',
        'HACK',
        'XXX',
    ];

    public array $formatterDirectives = [
        '@formatter:off',
        '@formatter:on',
        'prettier-ignore',
    ];

    public function register(): array
    {
        return [T_COMMENT];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $comment = $tokens[$stackPtr];

        if ($this->isSelfContainedSingleLine($comment['content']) === false) {
            return;
        }

        if ($this->isExcludedByASiblingStandard($comment['content']) === true) {
            return;
        }

        if ($this->ownsAFunctionBody($comment['conditions']) === false) {
            return;
        }

        if ($this->standsAloneOnItsLine($phpcsFile, $stackPtr, $comment['line']) === false) {
            return;
        }

        if ($this->headsARunAtAStatementBoundary($phpcsFile, $stackPtr, $comment['conditions']) === false) {
            return;
        }

        if ($this->introducesABlock($phpcsFile, $stackPtr, $comment['conditions']) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'Section-labelling comment (%s): extract the block it introduces into a method named after it'
                . ' (see docs/standards/clear-code-encapsulate-each-concept-in-a-method.md)',
            $stackPtr,
            'Found',
            [trim($comment['content'])]
        );
    }

    private function isSelfContainedSingleLine(string $content): bool
    {
        $written = trim($content);

        if (
            str_starts_with($written, '//') === true
            || str_starts_with($written, '#') === true
        ) {
            return true;
        }

        return str_starts_with($written, '/*') === true && str_ends_with($written, '*/') === true;
    }

    private function isExcludedByASiblingStandard(string $content): bool
    {
        foreach ($this->formatterDirectives as $directive) {
            if (
                $directive !== ''
                && stripos($content, $directive) !== false
            ) {
                return true;
            }
        }

        $markers = [];

        foreach ($this->debtMarkers as $marker) {
            if ($marker !== '') {
                $markers[] = preg_quote($marker, '/');
            }
        }

        if ($markers === []) {
            return false;
        }

        return preg_match('/\b(?:' . implode('|', $markers) . ')\b/i', $content) === 1;
    }

    private function ownsAFunctionBody(array $conditions): bool
    {
        foreach (array_reverse($conditions) as $scope) {
            if (in_array($scope, self::TRANSPARENT_SCOPES, true) === true) {
                continue;
            }

            return in_array($scope, self::FUNCTION_LIKE, true);
        }

        return false;
    }

    private function standsAloneOnItsLine(File $phpcsFile, int $stackPtr, int $commentLine): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);

        if (
            $before === false
            || $this->endLine($tokens[$before]) === $commentLine
        ) {
            return false;
        }

        $after = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        return $after === false || $tokens[$after]['line'] !== $commentLine;
    }

    private function headsARunAtAStatementBoundary(File $phpcsFile, int $stackPtr, array $conditions): bool
    {
        $boundary = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($boundary === false) {
            return false;
        }

        return $this->followsAStatementBoundary($phpcsFile, $boundary, $conditions) === true
            && $this->headsItsRun($phpcsFile, $boundary, $stackPtr) === true;
    }

    private function headsItsRun(File $phpcsFile, int $boundary, int $stackPtr): bool
    {
        for ($pointer = ($boundary + 1); $pointer < $stackPtr; $pointer++) {
            if ($this->isALabelLine($phpcsFile, $pointer) === true) {
                return false;
            }
        }

        return true;
    }

    private function isALabelLine(File $phpcsFile, int $stackPtr): bool
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        if ($token['code'] !== T_COMMENT) {
            return false;
        }

        if ($this->isSelfContainedSingleLine($token['content']) === false) {
            return false;
        }

        if ($this->isExcludedByASiblingStandard($token['content']) === true) {
            return false;
        }

        return $this->standsAloneOnItsLine($phpcsFile, $stackPtr, $token['line']);
    }

    private function followsAStatementBoundary(File $phpcsFile, int $boundary, array $conditions): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (in_array($tokens[$boundary]['code'], self::STATEMENT_BOUNDARY, true) === true) {
            return $this->finishesWhatItCloses($tokens, $boundary);
        }

        return $this->opensTheEnclosingScope($tokens, $boundary, $conditions);
    }

    private function finishesWhatItCloses(array $tokens, int $boundary): bool
    {
        if ($tokens[$boundary]['code'] !== T_CLOSE_CURLY_BRACKET) {
            return true;
        }

        $owner = ($tokens[$boundary]['scope_condition'] ?? null);

        if ($owner === null) {
            return true;
        }

        return in_array($tokens[$owner]['code'], self::CONTINUING_SCOPES, true) === false;
    }

    private function opensTheEnclosingScope(array $tokens, int $boundary, array $conditions): bool
    {
        if ($conditions === []) {
            return false;
        }

        $owner = array_key_last($conditions);

        return ($tokens[$owner]['scope_opener'] ?? null) === $boundary;
    }

    private function endLine(array $token): int
    {
        return ((int) $token['line'] + substr_count(rtrim((string) $token['content'], "\r\n"), "\n"));
    }

    private function introducesABlock(File $phpcsFile, int $stackPtr, array $conditions): bool
    {
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        if (in_array($tokens[$next]['code'], self::CONTINUATION_KEYWORDS, true) === true) {
            return false;
        }

        return $tokens[$next]['conditions'] === $conditions;
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Controversial;

use MikeBronner\CleanCode\Support\ParameterDeclaration;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class SuperglobalsSniff implements Sniff
{
    private const SUPERGLOBALS = [
        '$GLOBALS',
        '$_SERVER',
        '$HTTP_SERVER_VARS',
        '$_GET',
        '$HTTP_GET_VARS',
        '$_POST',
        '$HTTP_POST_VARS',
        '$_FILES',
        '$HTTP_POST_FILES',
        '$_COOKIE',
        '$HTTP_COOKIE_VARS',
        '$_SESSION',
        '$HTTP_SESSION_VARS',
        '$_REQUEST',
        '$_ENV',
        '$HTTP_ENV_VARS',
    ];

    private const CODE = 'Found';

    public function register(): array
    {
        return [T_VARIABLE, T_DOUBLE_QUOTED_STRING, T_HEREDOC];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        if ($token['code'] === T_VARIABLE) {
            $this->processVariable($phpcsFile, $stackPtr);

            return;
        }

        $this->processInterpolation($phpcsFile, $stackPtr, $token['content']);
    }

    private function processVariable(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $name = $tokens[$stackPtr]['content'];

        if (in_array($name, self::SUPERGLOBALS, true) === false) {
            return;
        }

        $conditions = $tokens[$stackPtr]['conditions'];

        if (
            $conditions !== []
            && in_array(end($conditions), Tokens::$ooScopeTokens, true) === true
            && (new ParameterDeclaration())->isPlainParameter($phpcsFile, $stackPtr) === false
        ) {
            return;
        }

        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $previous !== false
            && $tokens[$previous]['code'] === T_DOUBLE_COLON
        ) {
            return;
        }

        $this->report($phpcsFile, $stackPtr, $name);
    }

    private function processInterpolation(File $phpcsFile, int $stackPtr, string $content): void
    {
        $matched = preg_match_all($this->interpolationPattern(), $content, $matches);

        if ($matched === false) {
            return;
        }

        if ($matched === 0) {
            return;
        }

        foreach ($matches['name'] as $name) {
            $this->report($phpcsFile, $stackPtr, "\${$name}");
        }
    }

    private function interpolationPattern(): string
    {
        $names = array_map(
            static fn (string $superglobal): string => preg_quote(ltrim($superglobal, '$'), '/'),
            self::SUPERGLOBALS
        );

        return '/(?<!\\\\)(?:\\\\\\\\)*\K\$\{?(?P<name>' . implode('|', $names) . ')\b/';
    }

    private function report(File $phpcsFile, int $stackPtr, string $name): void
    {
        $phpcsFile->addError(
            'Superglobal %s must not be accessed directly; inject the framework request abstraction instead',
            $stackPtr,
            self::CODE,
            [$name]
        );
    }
}

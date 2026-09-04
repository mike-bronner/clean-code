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

    public function process(File $phpcsFile, $stackPtr): void
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

        // A variable whose innermost enclosing scope is a class-like body is a
        // property declaration, not a read: `public $_GET = [];` names a member.
        // A read inside a method has the method as its innermost condition.
        //
        // A parameter list opens no scope of its own — PHPCS starts the
        // method's scope at its `{` — so a parameter arrives here carrying the
        // class as its innermost condition, indistinguishable by position from
        // a member declared in the class body. Position alone is therefore not
        // enough: only a *promoted* parameter declares a property, and a plain
        // one is an ordinary local binding that must be reported exactly as the
        // same parameter in a global function is.
        $conditions = $tokens[$stackPtr]['conditions'];

        if (
            $conditions !== []
            && in_array(end($conditions), Tokens::$ooScopeTokens, true) === true
            && (new ParameterDeclaration())->isPlainParameter($phpcsFile, $stackPtr) === false
        ) {
            return;
        }

        // `self::$_POST` and `Holder::$_POST` resolve to a static property of
        // that class; the name never reaches the superglobal. `$request->$_GET`
        // is not exempt for the same reason — there the variable *is* read, to
        // supply the property name.
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

        // Its own exit, kept apart from the "nothing interpolated" one below.
        // This read can genuinely fail — interpolationPattern()'s leading
        // `(?:\\\\)*` is a quantified group, so a long enough run of
        // backslashes in the string exhausts PCRE's recursion limit, measured
        // at a million of them — and it reports that with false, not 0. The
        // `=== 0` test this replaces let false through under a strict
        // comparison, so the failure fell into the loop written to be skipped
        // and read a `name` key that is empty, or absent when the pattern never
        // compiled. Nothing can be reported off a string
        // that was not read, so a superglobal interpolated into it goes
        // unreported rather than crashing the run.
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

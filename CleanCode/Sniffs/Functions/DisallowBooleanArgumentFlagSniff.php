<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use MikeBronner\CleanCode\Helpers\Declarations;
use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Support\ConditionOperatorOwnership;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowBooleanArgumentFlagSniff implements Sniff
{
    public string $exceptions = '';

    public string $ignorepattern = '';

    private const CLASS_LIKE_TOKENS = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    private const PEST_TEST_FUNCTIONS = ['it', 'test'];

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls
    ) {
    }

    public function register(): array
    {
        return [T_CLOSURE, T_FN, T_FUNCTION];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        $name = $code === T_FUNCTION ? (new Declarations)->name($phpcsFile, $stackPtr) : null;

        if (
            $this->isIgnoredName($name) === true
            || $this->isPestTestBody($phpcsFile, $stackPtr) === true
        ) {
            return;
        }

        $className = $this->enclosingClassName($phpcsFile, $stackPtr);

        if (
            $className !== null
            && $this->isExceptedClass($className) === true
        ) {
            return;
        }

        $subject = $this->describe($code, $name, $className);

        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            if ($this->isBooleanFlag($parameter) === false) {
                continue;
            }

            $phpcsFile->addError(
                    'The %s has a boolean flag argument %s, which is a certain sign of a '
                        . 'Single Responsibility Principle violation; extract each branch the '
                        . 'flag selects into its own method '
                        . '(see resources/boost/skills/clean-code/references/'
                        . 'cleancode-booleanargumentflag.md)',
                    $parameter['token'],
                    'Found',
                    [$subject, $parameter['name']]
                );
        }
    }

    private function isIgnoredName(?string $name): bool
    {
        $pattern = trim($this->ignorepattern);

        return $name !== null
            && $pattern !== ''
            && preg_match($pattern, $name) === 1;
    }

    private function isPestTestBody(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $opener = array_key_last($tokens[$stackPtr]['nested_parenthesis'] ?? []);

        if (
            $opener === null
            || $this->isPestTestCall($phpcsFile, $opener) === false
        ) {
            return false;
        }

        $closer = $tokens[$opener]['parenthesis_closer'];
        $commas = (new ConditionOperatorOwnership)->findTopLevelTokens(
                $phpcsFile,
                ($opener + 1),
                ($closer - 1),
                [T_COMMA]
            );
        $bodyStart = $phpcsFile->findNext(
                [...Tokens::$emptyTokens, T_STATIC],
                (($commas[0] ?? $closer) + 1),
                null,
                true
            );

        return $bodyStart === $stackPtr;
    }

    private function isPestTestCall(File $phpcsFile, int $opener): bool
    {
        $callee = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($opener - 1), null, true);

        if (
            $callee === false
            || $this->functionCalls->isGlobalFunctionCall($phpcsFile, $callee) === false
        ) {
            return false;
        }

        $calleeName = strtolower($this->functionCalls->calleeName($phpcsFile, $callee));

        return in_array($calleeName, self::PEST_TEST_FUNCTIONS, true);
    }

    private function isExceptedClass(string $className): bool
    {
        $names = array_map('trim', explode(',', $this->exceptions));

        return in_array($className, $names, true);
    }

    private function enclosingClassName(File $phpcsFile, int $stackPtr): ?string
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $ptr => $code) {
            if (in_array($code, self::CLASS_LIKE_TOKENS, true) === true) {
                return (new Declarations)->name($phpcsFile, $ptr);
            }
        }

        return null;
    }

    private function describe(int|string $code, ?string $name, ?string $className): string
    {
        return match (true) {
            $code === T_CLOSURE => 'closure',
            $code === T_FN => 'arrow function',
            $name === null => 'function',
            $className !== null => "method {$name}()",
            default => "function {$name}()",
        };
    }

    private function isBooleanFlag(array $parameter): bool
    {
        if ($parameter['variable_length'] === true) {
            return false;
        }

        return $this->isBooleanType((string) $parameter['type_hint']) === true
            || $this->isBooleanDefault($parameter['default'] ?? null) === true;
    }

    private function isBooleanType(string $typeHint): bool
    {
        $normalized = ltrim(strtolower(preg_replace('/\s+/', '', $typeHint) ?? $typeHint), '?');
        $types = array_values(array_diff(explode('|', $normalized), ['null', '']));

        return $types === ['bool'];
    }

    private function isBooleanDefault(?string $default): bool
    {
        return in_array(strtolower(trim((string) $default)), ['true', 'false'], true);
    }
}

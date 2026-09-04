<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Arrays;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ConvertToCollectionSniff implements Sniff
{
    private const ARRAY_PREFIX_LENGTH = 6;

    public array $arrayFunctions = [
        'array_filter' => 'filter',
        'array_map' => 'map',
        'array_reduce' => 'reduce',
    ];

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls()
    ) {
    }

    public function register(): array
    {
        return [T_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $functionCalls = $this->functionCalls;

        $tokens = $phpcsFile->getTokens();

        // The open-parenthesis test comes first because it is the cheapest way
        // to discard the great majority of T_STRING tokens, which are not
        // calls at all — the configured map is only assembled for call sites.
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $next === false
            || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $replacements = $this->replacements();
        $function = strtolower($tokens[$stackPtr]['content']);

        if (isset($replacements[$function]) === false) {
            return;
        }

        if ($functionCalls->isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addWarning(
            '%s() manipulates a native array; use collect()->%s() instead',
            $stackPtr,
            'Found',
            [$tokens[$stackPtr]['content'], $replacements[$function]]
        );
    }

    private function replacements(): array
    {
        $replacements = [];

        foreach ($this->arrayFunctions as $key => $value) {
            $isListEntry = is_int($key) === true;
            $function = strtolower($isListEntry === true ? $value : (string) $key);
            $method = $isListEntry === true ? $this->collectionMethod($function) : $value;

            $replacements[$function] = $method;
        }

        return $replacements;
    }

    private function collectionMethod(string $function): string
    {
        if (str_starts_with($function, 'array_') === false) {
            return $function;
        }

        return substr($function, self::ARRAY_PREFIX_LENGTH);
    }
}

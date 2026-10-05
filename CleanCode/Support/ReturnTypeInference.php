<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class ReturnTypeInference
{
    private const LITERAL_TYPES = [
        T_LNUMBER => 'int',
        T_DNUMBER => 'float',
        T_CONSTANT_ENCAPSED_STRING => 'string',
        T_DOUBLE_QUOTED_STRING => 'string',
        T_START_HEREDOC => 'string',
        T_TRUE => 'true',
        T_FALSE => 'false',
        T_NULL => 'null',
    ];

    private const NAMED_LITERALS = [
        'true' => 'true',
        'false' => 'false',
        'null' => 'null',
    ];

    public function infer(File $phpcsFile, int $functionPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$functionPtr]['scope_opener'], $tokens[$functionPtr]['scope_closer']) === false) {
            return null;
        }

        $inherited = (new InheritedMembers)->declaredReturnType($phpcsFile, $functionPtr);

        if ($inherited !== null) {
            return $inherited;
        }

        $returns = $this->returnExpressions($phpcsFile, $functionPtr);

        if ($returns === null) {
            return null;
        }

        if ($returns === []) {
            return null;
        }

        $type = $this->unionOf($phpcsFile, $functionPtr, $returns);

        return $type === 'void' ? null : $type;
    }

    private function returnExpressions(File $phpcsFile, int $functionPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$functionPtr]['scope_opener'];
        $closer = $tokens[$functionPtr]['scope_closer'];
        $found = [];

        for ($ptr = ($opener + 1); $ptr < $closer; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_RETURN) {
                continue;
            }

            if ($this->nearestFunctionScope($tokens, $ptr) !== $functionPtr) {
                continue;
            }

            $expression = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $closer, true);

            if ($expression === false) {
                return null;
            }

            $found[] = $tokens[$expression]['code'] === T_SEMICOLON ? 'void' : $expression;
        }

        return $found;
    }

    private function nearestFunctionScope(array $tokens, int $ptr): ?int
    {
        foreach (array_reverse($tokens[$ptr]['conditions'], true) as $owner => $code) {
            if (
                $code === T_FUNCTION
                || $code === T_CLOSURE
                || $code === T_FN
            ) {
                return $owner;
            }
        }

        return null;
    }

    private function unionOf(File $phpcsFile, int $functionPtr, array $returns): ?string
    {
        $types = [];
        $bare = false;

        foreach ($returns as $return) {
            if ($return === 'void') {
                $bare = true;

                continue;
            }

            $type = $this->expressionType($phpcsFile, $functionPtr, $return);

            if ($type === null) {
                return null;
            }

            $types[$type] = true;
        }

        if ($types === []) {
            return 'void';
        }

        if ($bare === true) {
            $types['null'] = true;
        }

        return $this->normalise(array_keys($types));
    }

    private function expressionType(File $phpcsFile, int $functionPtr, int $ptr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

        if (
            $after === false
            || $tokens[$after]['code'] !== T_SEMICOLON
        ) {
            return null;
        }

        $code = $tokens[$ptr]['code'];

        if (isset(self::LITERAL_TYPES[$code]) === true) {
            return self::LITERAL_TYPES[$code];
        }

        if ($code === T_STRING) {
            return self::NAMED_LITERALS[strtolower($tokens[$ptr]['content'])] ?? null;
        }

        if ($code === T_VARIABLE) {
            return $this->variableType($phpcsFile, $functionPtr, $tokens[$ptr]['content']);
        }

        return null;
    }

    private function variableType(File $phpcsFile, int $functionPtr, string $variable): ?string
    {
        if ($variable === '$this') {
            return 'static';
        }

        foreach ($phpcsFile->getMethodParameters($functionPtr) as $parameter) {
            if ($parameter['name'] !== $variable) {
                continue;
            }

            $hint = trim($parameter['type_hint']);

            return $hint === '' ? null : $hint;
        }

        return null;
    }

    private function normalise(array $types): ?string
    {
        sort($types);

        if ($types === ['false', 'true']) {
            return 'bool';
        }

        if (count($types) === 1) {
            return $types[0];
        }

        if (
            count($types) === 2
            && in_array('null', $types, true) === true
        ) {
            $other = $types[0] === 'null' ? $types[1] : $types[0];

            return str_starts_with($other, '?') ? null : "?{$other}";
        }

        return implode('|', $types);
    }
}

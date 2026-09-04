<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

// The return type a declaration can be *proven* to have, or null when it cannot.
//
// Every rule here answers from something already declared or already literal.
// Nothing guesses: a body whose returns hand back a variable of unknown type
// answers null, and the caller reports rather than rewrites. A wrong answer is
// not a lint error in the consumer's code, it is a TypeError thrown at runtime,
// so silence is the only safe default.
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

        $inherited = (new InheritedMembers())->declaredReturnType($phpcsFile, $functionPtr);

        if ($inherited !== null) {
            return $inherited;
        }

        $returns = $this->returnExpressions($phpcsFile, $functionPtr);

        if ($returns === null) {
            return null;
        }

        // A declaration that returns nothing is the Slevomat sniff's own case:
        // it already infers `void` without an annotation, reports it as the
        // more specific MissingNativeTypeHint, and fixes it. Answering here
        // would take that case over and report it under a worse code.
        if ($returns === []) {
            return null;
        }

        $type = $this->unionOf($phpcsFile, $functionPtr, $returns);

        return $type === 'void' ? null : $type;
    }

    // Every `return` that belongs to this declaration, as a pointer to the first
    // token of its expression. A bare `return;` contributes 'void'. Null means
    // the walk found something it cannot account for.
    //
    // @return array<int, int|string>|null
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

            // A return inside a nested closure, arrow function, or anonymous
            // class belongs to that declaration, not this one.
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

    /**
     * @param array<int, int|string> $returns
     */
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

        // Only bare returns: the declaration hands back nothing at all. Mixed
        // with a value return, each bare one yields null, so null joins the
        // union rather than replacing it.
        if ($types === []) {
            return 'void';
        }

        if ($bare === true) {
            $types['null'] = true;
        }

        return $this->normalise(array_keys($types));
    }

    // The type of one return expression, or null when it is not provable. The
    // expression must be a single token followed by the semicolon: anything
    // longer is an operation whose result this cannot read off the source.
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

    // Only two variables are provable from the declaration alone: $this, and a
    // parameter that carries a declared type. A local assigned from a call
    // needs the callee's type, which this does not resolve.
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

    /**
     * @param array<int, string> $types
     */
    private function normalise(array $types): ?string
    {
        sort($types);

        if ($types === ['false', 'true']) {
            return 'bool';
        }

        if (count($types) === 1) {
            return $types[0];
        }

        // A union of a single type with null is written with the shorthand PHP
        // and this ruleset both prefer everywhere else.
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

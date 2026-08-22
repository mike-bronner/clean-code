<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

use PHP_CodeSniffer\Files\File;

/**
 * Tells a plain parameter apart from a promoted one, for a variable PHPCS
 * reports as sitting directly in a class's scope.
 *
 * PHP_CodeSniffer builds a token's 'conditions' from brace scopes, and a
 * function's own scope does not open until its `{`. A method's parameter list
 * is written before that brace, so every parameter token carries the *class* as
 * its innermost condition — positionally indistinguishable from a property
 * declared in the class body. `conditions` alone therefore cannot answer "is
 * this a member declaration?", and reading it as if it could has produced a bug
 * in this repository twice, in opposite directions: DisallowAlwaysOnEagerLoading
 * reported an ordinary `$with` parameter as an always-on eager-loading property
 * (a false positive, PR #251), and Superglobals exempted an ordinary parameter
 * named after a superglobal as if it were a member (a false negative, PR #296).
 * Three sniffs had each hand-rolled their own answer before this class existed.
 *
 * The resolution both questions run on, which is PHP_CodeSniffer's own from
 * getMemberProperties() (Files/File.php): take the innermost parenthesis pair
 * the variable sits in, require a T_FUNCTION to own it, then read that
 * parameter's 'property_visibility' from getMethodParameters(). Only a promoted
 * parameter carries that key, so it is what separates a parameter that declares
 * a property from one that only takes an argument. getMemberProperties() is not
 * called directly because it answers by throwing, and its parse-error branch
 * would emit a warning under a foreign sniff code.
 *
 * Both polarities are published, and neither is the negation of the other,
 * because "not a plain parameter" and "a promoted parameter" are different
 * statements: a variable that is not a parameter *at all* — a call argument or
 * a property hook's own parameter, both of which PHPCS also reports as directly
 * class-scoped, since it opens no scope for a hook — is neither. Nine such
 * variables sit in tests/fixtures/TooManyFieldsSniff/property-hooks.php alone,
 * and reading `! isPlainParameter()` as "promoted" would count every one of them
 * as a declared field. Each caller asks for the polarity it actually means.
 *
 * Consumers: CleanCode.Models.DisallowAlwaysOnEagerLoading and
 * CleanCode.Controversial.Superglobals ask isPlainParameter() (both exempt a
 * plain parameter from a member-declaration rule), and
 * CleanCode.Metrics.TooManyFields asks isPromotedParameter() (only a promoted
 * parameter declares a field).
 */
final class ParameterDeclaration
{
    /**
     * Whether the variable is an ordinary parameter of a method — one that
     * takes an argument and declares nothing.
     *
     * False for everything that is not a parameter of a named function: a
     * property in a class body (no 'nested_parenthesis' at all), a closure or
     * arrow-function parameter, a `use ($var)` clause variable, a call
     * argument, a property hook's parameter, and a variable in a method body.
     * A parameter list the tokenizer never closed reaches neither branch: PHPCS
     * records no 'nested_parenthesis' on a token inside an unmatched pair, so
     * an unfinished signature answers false here rather than throwing.
     */
    public static function isPlainParameter(File $phpcsFile, int $variablePtr): bool
    {
        $ownerPtr = self::owningFunction($phpcsFile, $variablePtr);

        if ($ownerPtr === null) {
            return false;
        }

        $parameter = self::parameterAt($phpcsFile, $ownerPtr, $variablePtr);

        // A variable inside a function's own parameter list that
        // getMethodParameters() does not report back is treated as plain: it
        // declares no property either way, which is the answer both exempting
        // callers need. Defensive only — a parameter default must be a constant
        // expression, so a parameter's own name token is the only variable that
        // can stand in a parameter list.
        return $parameter === null || isset($parameter['property_visibility']) === false;
    }

    /**
     * Whether the variable is a constructor-promoted parameter, and so declares
     * a property from the parameter list.
     *
     * True only for a parameter getMethodParameters() reports with a property
     * visibility. Asymmetric visibility counts: PHPCS sets property_visibility
     * for `private(set) int $x` written on its own, so PHP 8.4's shapes are
     * read as the promotions they are.
     */
    public static function isPromotedParameter(File $phpcsFile, int $variablePtr): bool
    {
        $ownerPtr = self::owningFunction($phpcsFile, $variablePtr);

        if ($ownerPtr === null) {
            return false;
        }

        $parameter = self::parameterAt($phpcsFile, $ownerPtr, $variablePtr);

        return $parameter !== null && isset($parameter['property_visibility']) === true;
    }

    /**
     * The T_FUNCTION whose parameter list the variable sits in, or null when it
     * sits in no parameter list of one.
     *
     * The T_FUNCTION test is what makes the getMethodParameters() call in
     * parameterAt() provably safe: that helper throws on a token that is not a
     * function, a closure, an arrow function or a `use` clause. Restricting to
     * T_FUNCTION also carries meaning of its own — only a named function can
     * promote a property — which is why a closure or arrow-function parameter
     * is rejected here rather than resolved and then discarded.
     */
    private static function owningFunction(File $phpcsFile, int $variablePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$variablePtr]['nested_parenthesis']) === true) {
            return null;
        }

        $openers = array_keys($tokens[$variablePtr]['nested_parenthesis']);
        $ownerPtr = $tokens[array_pop($openers)]['parenthesis_owner'] ?? null;

        if ($ownerPtr === null || $tokens[$ownerPtr]['code'] !== T_FUNCTION) {
            return null;
        }

        return $ownerPtr;
    }

    /**
     * The parameter the variable names, out of the function's parameter list,
     * or null when the list holds no parameter at that token.
     *
     * @return array<string, mixed>|null
     */
    private static function parameterAt(File $phpcsFile, int $ownerPtr, int $variablePtr): ?array
    {
        foreach ($phpcsFile->getMethodParameters($ownerPtr) as $parameter) {
            if ($parameter['token'] === $variablePtr) {
                return $parameter;
            }
        }

        return null;
    }
}

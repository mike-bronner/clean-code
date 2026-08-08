<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Controllers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a model resolved by hand from a controller action's own parameter.
 *
 * Partial enforcement of the "Controllers: Route Model Binding" standard
 * (docs/standards/controllers-route-model-binding.md): a controller action
 * takes the model as a type-hinted parameter and lets Laravel resolve it, so
 * SomeModel::find($id) inside the action — where $id is one of the action's
 * own parameters, and therefore injected from a route segment — says the
 * parameter should have been the model itself.
 *
 * Only this negative shape is token-visible. The standard's positive
 * requirement is a *missing* parameter, measured against the route table in
 * routes/*.php, so it stays with code review.
 *
 * The sniff cannot read the route definition and cannot tell an Eloquent
 * model from any other class carrying a static find(), so it emits warnings
 * rather than errors. It only ever speaks inside a class whose name ends in
 * Controller, which is why rules.xml needs no path scoping for it.
 */
class ManualModelResolutionSniff implements Sniff
{
    /**
     * Static finders that resolve a single model by key, lowercased because
     * PHP method names are case-insensitive.
     *
     * @var array<string>
     */
    private const RESOLUTION_METHODS = [
        'find',
        'findorfail',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_DOUBLE_COLON];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($methodPtr === false || $tokens[$methodPtr]['code'] !== T_STRING) {
            return;
        }

        $method = strtolower($tokens[$methodPtr]['content']);

        if (in_array($method, self::RESOLUTION_METHODS, true) === false) {
            return;
        }

        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if ($openPtr === false || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($tokens[$receiverPtr]['code'] !== T_STRING) {
            return;
        }

        $functionPtr = $this->controllerActionPointer($phpcsFile, $stackPtr);
        $argumentPtr = $this->firstArgumentVariable($phpcsFile, $openPtr);

        if ($functionPtr === null || $argumentPtr === null) {
            return;
        }

        $parameters = array_column($phpcsFile->getMethodParameters($functionPtr), 'name');

        if (in_array($tokens[$argumentPtr]['content'], $parameters, true) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'Model %s resolved by hand from %s, a parameter of %s(); type-hint the model on the '
                . 'action signature instead and let route-model binding resolve it '
                . '(see docs/standards/controllers-route-model-binding.md)',
            $methodPtr,
            'Found',
            [
                $tokens[$receiverPtr]['content'],
                $tokens[$argumentPtr]['content'],
                $phpcsFile->getDeclarationName($functionPtr),
            ]
        );
    }

    /**
     * Pointer to the public *Controller method enclosing the token, null when
     * the token is somewhere else.
     *
     * Both conditions are read innermost-first. An anonymous class tokenizes
     * as T_ANON_CLASS and a closure as T_CLOSURE, so neither answers a
     * T_CLASS or T_FUNCTION lookup: code inside one is judged by the named
     * class and named method around it, which is what the standard is about.
     *
     * A plain named function declared inside a method body answers the
     * T_FUNCTION lookup though, and it is not the action: a route never binds
     * into it and it inherits nothing from the scope around it, so its
     * parameters are its own. isMethod() is what separates the two.
     */
    private function controllerActionPointer(File $phpcsFile, int $stackPtr): ?int
    {
        $classPtr = $phpcsFile->getCondition($stackPtr, T_CLASS, false);

        if ($classPtr === false) {
            return null;
        }

        if (str_ends_with($phpcsFile->getDeclarationName($classPtr), 'Controller') === false) {
            return null;
        }

        $functionPtr = $phpcsFile->getCondition($stackPtr, T_FUNCTION, false);

        if ($functionPtr === false) {
            return null;
        }

        if ($this->isMethod($phpcsFile, $functionPtr) === false) {
            return null;
        }

        if ($phpcsFile->getMethodProperties($functionPtr)['scope'] !== 'public') {
            return null;
        }

        return $functionPtr;
    }

    /**
     * Whether the function is declared directly in a class body rather than
     * inside another function.
     *
     * The declaration's own innermost condition answers it: a method's is the
     * class-like token it belongs to, while a function nested in a method, a
     * closure or another function has that enclosing function as its
     * innermost condition instead.
     *
     * T_CLASS and T_ANON_CLASS are the only class-like scopes reachable here,
     * because the caller has already required a `*Controller` T_CLASS
     * ancestor: a trait, an interface or an enum can be declared inside a
     * method *body*, and a method of one of those is not the routed action
     * either, so leaving them out is the answer this rule wants.
     */
    private function isMethod(File $phpcsFile, int $functionPtr): bool
    {
        $conditions = $phpcsFile->getTokens()[$functionPtr]['conditions'];
        $scope = end($conditions);

        return $scope === T_CLASS || $scope === T_ANON_CLASS;
    }

    /**
     * Pointer to the call's first argument when that argument is a bare
     * variable, null otherwise.
     *
     * A bare variable is the only shape the standard speaks about: a literal,
     * a property read ($this->id), or any longer expression is not one of the
     * action's parameters and so cannot become a bound model. Requiring the
     * next token to close the argument is what rules those out — $this->id
     * opens with a variable too.
     *
     * A named argument carries the same variable behind a label, so
     * find(id: $id) is the same manual resolution as find($id) and the label
     * is stepped over. PHP_CodeSniffer only emits T_PARAM_NAME when the next
     * non-empty token is the label's colon, which is why the two tokens are
     * skipped together without re-checking the colon.
     */
    private function firstArgumentVariable(File $phpcsFile, int $openPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        $argumentPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        if ($argumentPtr !== false && $tokens[$argumentPtr]['code'] === T_PARAM_NAME) {
            $colonPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($argumentPtr + 1), null, true);
            $argumentPtr = $colonPtr === false
                ? false
                : $phpcsFile->findNext(Tokens::$emptyTokens, ($colonPtr + 1), null, true);
        }

        if ($argumentPtr === false || $tokens[$argumentPtr]['code'] !== T_VARIABLE) {
            return null;
        }

        $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($argumentPtr + 1), null, true);

        if ($afterPtr === false) {
            return null;
        }

        $after = $tokens[$afterPtr]['code'];

        if ($after !== T_COMMA && $after !== T_CLOSE_PARENTHESIS) {
            return null;
        }

        return $argumentPtr;
    }
}

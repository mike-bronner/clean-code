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
 *
 * This file is written to pass rules.xml — the standard it belongs to — which
 * shapes how it reads. Guard chains are match(true) expressions rather than
 * if statements, because CleanCode.Conditionals.AvoidConditionals asks for
 * exactly that substitution, and the token stream is read through the File API
 * (findNext, getTokensAsString, getCondition) rather than the token array,
 * because CleanCode.Arrays.ArrayAccessors forbids direct element access and its
 * data_get() replacement is a Laravel helper this package does not ship.
 * Arrays are folded by hand rather than through array_map()/array_filter() for
 * the same reason: CleanCode.Arrays.ConvertToCollection asks for collect(),
 * another helper this package does not ship.
 *
 * tests/Standards/ManualModelResolutionTest.php asserts that, so a sibling
 * standard landing later cannot falsify it unnoticed — which is what
 * CleanCode.Arrays.ConvertToCollection did before the assertion existed.
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
     * PHP reserves the `__` prefix for magic methods. A route never binds into
     * one — the single exception is __invoke(), which *is* the routed action of
     * a single-action controller.
     */
    private const MAGIC_PREFIX = '__';

    private const ROUTED_MAGIC_METHOD = '__invoke';

    /**
     * Class-like scopes a method can be declared directly in. A trait, an
     * interface or an enum can be declared inside a method body, and a method
     * of one of those is not the routed action either, so they are left out.
     *
     * @var array<int|string>
     */
    private const CLASS_SCOPES = [
        T_CLASS,
        T_ANON_CLASS,
    ];

    /**
     * Scopes that make a nested declaration something other than a method.
     *
     * @var array<int|string>
     */
    private const FUNCTION_SCOPES = [
        T_FUNCTION,
        T_CLOSURE,
    ];

    /**
     * Stands in for "no enclosing scope of this kind" while scopes are folded
     * together. PHPCS numbers tokens from zero, so a negative sentinel loses
     * every max() against a real pointer.
     */
    private const NO_POINTER = -1;

    /**
     * Tokens that end a call's first argument, so the argument is the bare
     * variable that precedes them and nothing longer.
     *
     * @var array<int|string>
     */
    private const ARGUMENT_ENDS = [
        T_COMMA,
        T_CLOSE_PARENTHESIS,
    ];

    /**
     * The report format string. Written as a NOWDOC because
     * CleanCode.Strings.MultilineStrings rejects a string concatenated across
     * lines, and folded back to one line by message() before it is reported —
     * every other sniff here emits a single-line message, and the CSV and
     * checkstyle reports put one violation on one line.
     */
    private const MESSAGE = <<<'MESSAGE'
        Model %s resolved by hand from %s, a parameter of %s(); type-hint the model on the
        action signature instead and let route-model binding resolve it
        (see docs/standards/controllers-route-model-binding.md)
        MESSAGE;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_DOUBLE_COLON];
    }

    /**
     * The native int hint SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * asks for on $stackPtr cannot be written: PHP_CodeSniffer's Sniff
     * interface declares the parameter untyped, and narrowing an inherited
     * untyped parameter is a fatal error, so the hint would stop the sniff
     * loading at all. The return hint has no such constraint and is written.
     *
     * @param int $stackPtr
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- see above
    public function process(File $phpcsFile, $stackPtr): void
    {
        $methodPtr = $this->resolutionMethodPointer($phpcsFile, $stackPtr);
        $argumentPtr = $this->firstArgumentVariablePointer($phpcsFile, $methodPtr);
        $receiverPtr = $this->modelNamePointer($phpcsFile, $stackPtr);
        $actionPtr = $this->controllerActionPointer($phpcsFile, $stackPtr);

        match (true) {
            $methodPtr === null => null,
            $receiverPtr === null => null,
            $actionPtr === null => null,
            $this->isOwnParameter($phpcsFile, $argumentPtr, $actionPtr) === false => null,
            default => $phpcsFile->addWarning(
                $this->message(),
                $methodPtr,
                'Found',
                [
                    $this->contentOf($phpcsFile, $receiverPtr),
                    $this->contentOf($phpcsFile, (int) $argumentPtr),
                    $phpcsFile->getDeclarationName($actionPtr),
                ]
            ),
        };
    }

    /**
     * Pointer to the finder being called, null when the token after `::` is
     * not one of the resolution methods invoked as a call.
     *
     * The open-parenthesis check is what separates a call from a class
     * constant of the same name: `resolveWith(User::Find, $id)` puts a comma,
     * a variable and a closing parenthesis after the constant, which is the
     * token run an argument list would produce.
     */
    private function resolutionMethodPointer(File $phpcsFile, int $stackPtr): ?int
    {
        $methodPtr = $this->nextSignificant($phpcsFile, $stackPtr);
        $openPtr = $this->nextSignificant($phpcsFile, $methodPtr);

        return match (true) {
            $this->isToken($phpcsFile, $methodPtr, T_STRING) === false => null,
            $this->isResolutionMethod($phpcsFile, $methodPtr) === false => null,
            $this->isToken($phpcsFile, $openPtr, T_OPEN_PARENTHESIS) === false => null,
            default => $methodPtr,
        };
    }

    private function isResolutionMethod(File $phpcsFile, ?int $methodPtr): bool
    {
        return match ($methodPtr) {
            null => false,
            default => in_array(
                strtolower($this->contentOf($phpcsFile, $methodPtr)),
                self::RESOLUTION_METHODS,
                true
            ),
        };
    }

    /**
     * Pointer to the class name the finder is called on, null when the
     * receiver is not a name at all.
     *
     * A variable class name (`$model::find($id)`) cannot be resolved to a
     * model, and `self::` / `static::` / `parent::` tokenize as their own
     * keywords rather than T_STRING, so a controller resolving through its own
     * scope is not resolving a model either.
     */
    private function modelNamePointer(File $phpcsFile, int $stackPtr): ?int
    {
        $receiverPtr = $this->previousSignificant($phpcsFile, $stackPtr);

        return match ($this->isToken($phpcsFile, $receiverPtr, T_STRING)) {
            false => null,
            default => $receiverPtr,
        };
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
     */
    private function firstArgumentVariablePointer(File $phpcsFile, ?int $methodPtr): ?int
    {
        $openPtr = $this->nextSignificant($phpcsFile, $methodPtr);
        $labelPtr = $this->nextSignificant($phpcsFile, $openPtr);
        $argumentPtr = $this->pastArgumentLabel($phpcsFile, $labelPtr);
        $afterPtr = $this->nextSignificant($phpcsFile, $argumentPtr);

        return match (true) {
            $this->isToken($phpcsFile, $argumentPtr, T_VARIABLE) === false => null,
            $this->isToken($phpcsFile, $afterPtr, self::ARGUMENT_ENDS) === false => null,
            default => $argumentPtr,
        };
    }

    /**
     * Steps a named argument's label so `find(id: $id)` reads as the same
     * manual resolution as `find($id)`.
     *
     * PHP_CodeSniffer only spells a label T_PARAM_NAME when the next non-empty
     * token is the label's colon, which is why the two tokens are skipped
     * together without re-checking the colon.
     */
    private function pastArgumentLabel(File $phpcsFile, ?int $argumentPtr): ?int
    {
        return match ($this->isToken($phpcsFile, $argumentPtr, T_PARAM_NAME)) {
            false => $argumentPtr,
            default => $this->nextSignificant(
                $phpcsFile,
                $this->nextSignificant($phpcsFile, $argumentPtr)
            ),
        };
    }

    /**
     * Whether the argument names one of the action's own parameters, which is
     * what makes it a route segment rather than ordinary local data.
     */
    private function isOwnParameter(File $phpcsFile, ?int $argumentPtr, int $actionPtr): bool
    {
        $parameters = array_column($phpcsFile->getMethodParameters($actionPtr), 'name');

        return match ($argumentPtr) {
            null => false,
            default => in_array($this->contentOf($phpcsFile, $argumentPtr), $parameters, true),
        };
    }

    /**
     * Pointer to the public *Controller action enclosing the token, null when
     * the token is somewhere else.
     *
     * Conditions are read innermost-first. An anonymous class tokenizes as
     * T_ANON_CLASS and a closure as T_CLOSURE, so neither answers a T_CLASS or
     * T_FUNCTION lookup: code inside one is judged by the named class and
     * named method around it, which is what the standard is about.
     *
     * A plain named function declared inside a method body answers the
     * T_FUNCTION lookup though, and it is not the action: a route never binds
     * into it and it inherits nothing from the scope around it, so its
     * parameters are its own. isMethod() is what separates the two.
     */
    private function controllerActionPointer(File $phpcsFile, int $stackPtr): ?int
    {
        $classPtr = $this->conditionPointer($phpcsFile, $stackPtr, T_CLASS);
        $functionPtr = $this->conditionPointer($phpcsFile, $stackPtr, T_FUNCTION);

        return match (true) {
            $classPtr === null => null,
            $this->isControllerClass($phpcsFile, $classPtr) === false => null,
            $functionPtr === null => null,
            $this->isMethod($phpcsFile, $functionPtr) === false => null,
            $this->isPublic($phpcsFile, $functionPtr) === false => null,
            $this->isRoutable($phpcsFile, $functionPtr) === false => null,
            default => $functionPtr,
        };
    }

    private function isControllerClass(File $phpcsFile, int $classPtr): bool
    {
        return str_ends_with((string) $phpcsFile->getDeclarationName($classPtr), 'Controller');
    }

    private function isPublic(File $phpcsFile, int $functionPtr): bool
    {
        return $phpcsFile->getMethodProperties($functionPtr)['scope'] === 'public';
    }

    /**
     * Whether a route can bind into the method at all.
     *
     * Laravel routes to a named action or to __invoke(); every other magic
     * method is called by the engine, so its parameters never carry a route
     * segment. A constructor is the case this rules out most often — its
     * parameters come from the container, promoted or not.
     */
    private function isRoutable(File $phpcsFile, int $functionPtr): bool
    {
        $name = strtolower((string) $phpcsFile->getDeclarationName($functionPtr));

        return match (true) {
            $name === self::ROUTED_MAGIC_METHOD => true,
            default => str_starts_with($name, self::MAGIC_PREFIX) === false,
        };
    }

    /**
     * Whether the function is declared directly in a class body rather than
     * inside another function.
     *
     * Conditions nest, so the ancestor that opens last is the innermost one:
     * comparing the nearest class-like ancestor against the nearest
     * function-like one answers which of the two the declaration sits in. A
     * method's innermost scope is the class-like token it belongs to, while a
     * function nested in a method, a closure or another function has that
     * enclosing function as its innermost scope instead.
     */
    private function isMethod(File $phpcsFile, int $functionPtr): bool
    {
        $classPtr = $this->innermostConditionPointer($phpcsFile, $functionPtr, self::CLASS_SCOPES);
        $enclosingPtr = $this->innermostConditionPointer(
            $phpcsFile,
            $functionPtr,
            self::FUNCTION_SCOPES
        );

        return match ($classPtr) {
            null => false,
            default => $classPtr > ($enclosingPtr ?? self::NO_POINTER),
        };
    }

    /**
     * Pointer to the nearest enclosing scope of any of the given types, null
     * when the token is inside none of them.
     *
     * Folded with max() rather than mapped and filtered, because
     * CleanCode.Arrays.ConvertToCollection rejects array_map() and
     * array_filter() in favour of collect(), a Laravel helper this package does
     * not ship — the same constraint CleanCode.Arrays.ArrayAccessors already
     * puts on the rest of this file.
     *
     * @param array<int|string> $types
     */
    private function innermostConditionPointer(File $phpcsFile, int $stackPtr, array $types): ?int
    {
        $innermost = self::NO_POINTER;

        foreach ($types as $type) {
            $innermost = max(
                $innermost,
                $this->conditionPointer($phpcsFile, $stackPtr, $type) ?? self::NO_POINTER
            );
        }

        return match ($innermost) {
            self::NO_POINTER => null,
            default => $innermost,
        };
    }

    private function conditionPointer(File $phpcsFile, int $stackPtr, int|string $type): ?int
    {
        return match ($pointer = $phpcsFile->getCondition($stackPtr, $type, false)) {
            false => null,
            default => $pointer,
        };
    }

    /**
     * The next non-empty token after the pointer, null at end of file or when
     * the pointer it is asked to advance from is itself absent.
     */
    private function nextSignificant(File $phpcsFile, ?int $stackPtr): ?int
    {
        return match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true)
            ),
        };
    }

    private function previousSignificant(File $phpcsFile, ?int $stackPtr): ?int
    {
        return match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true)
            ),
        };
    }

    /**
     * Whether the token at the pointer is of one of the given types. Reading
     * it through a one-token findNext() window keeps the type test off the
     * token array.
     *
     * @param array<int|string>|int|string $types
     */
    private function isToken(File $phpcsFile, ?int $stackPtr, array|int|string $types): bool
    {
        return match ($stackPtr) {
            null => false,
            default => $phpcsFile->findNext($types, $stackPtr, ($stackPtr + 1)) !== false,
        };
    }

    private function contentOf(File $phpcsFile, int $stackPtr): string
    {
        return $phpcsFile->getTokensAsString($stackPtr, 1);
    }

    private function message(): string
    {
        return str_replace("\n", ' ', self::MESSAGE);
    }

    private function orNull(int|false $pointer): ?int
    {
        return match ($pointer) {
            false => null,
            default => $pointer,
        };
    }
}

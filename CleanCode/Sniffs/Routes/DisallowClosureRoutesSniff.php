<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids a closure or arrow function as a route action.
 *
 * The enforceable slice of Routes: Conventions (Do / Do Not) (#65) — see
 * docs/standards/routes-conventions-do-do-not.md. A closure action cannot be
 * serialized, so it breaks `php artisan route:cache`; the standard's "Do Not"
 * is unconditional, which is why this reports an error rather than a warning.
 *
 * Only the route-registration verbs are checked. `Route::group()` — whether
 * called statically or reached through a chain — is deliberately absent from
 * ROUTE_ACTION_METHODS: a group callback is not a route action and caches
 * fine, so it is never flagged.
 *
 * Each of those verbs takes exactly one callable parameter, the action, so
 * *any* closure sitting directly in the argument list is that action. Only a
 * direct argument counts: the scan walks the argument list itself and steps
 * over every nested parenthesis, bracket and scope, so a closure inside
 * another call (`array_map(fn () => …, …)`), inside an array, or inside the
 * body of the action closure is not a second violation.
 *
 * **Boundary — no symbol resolution.** A sniff sees one file's tokens, so it
 * cannot know which `Route` symbol is imported. The check is the Laravel
 * facade convention on the name alone: the class segment immediately before
 * `::` must spell `Route`, whether written bare (`Route::get`), imported, or
 * fully qualified (`\Illuminate\Support\Facades\Route::get`). An unrelated
 * class also named `Route` therefore false-positives, and a router held in a
 * variable (`$router->get(…)`) is never seen at all. Both are accepted in a
 * Laravel-standards ruleset; docs/standards/routes-conventions-do-do-not.md
 * records them.
 */
class DisallowClosureRoutesSniff implements Sniff
{
    /**
     * The Route facade methods whose action argument becomes a cached route.
     *
     * `group` is absent on purpose — its callback is not a route action.
     */
    private const ROUTE_ACTION_METHODS = [
        'any',
        'delete',
        'fallback',
        'get',
        'match',
        'options',
        'patch',
        'post',
        'put',
    ];

    /**
     * Tokens that open a nested construct the argument scan must step over,
     * in the order they are probed. A closure carries both a scope and a
     * parameter list, so `scope_closer` is read first to clear the whole
     * construct in one jump.
     */
    private const NESTED_CLOSERS = [
        'scope_closer',
        'parenthesis_closer',
        'bracket_closer',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_STRING];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $method = strtolower($tokens[$stackPtr]['content']);

        if (in_array($method, self::ROUTE_ACTION_METHODS, true) === false) {
            return;
        }

        $opener = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $opener === false
            || $tokens[$opener]['code'] !== T_OPEN_PARENTHESIS
            || isset($tokens[$opener]['parenthesis_closer']) === false
        ) {
            return;
        }

        if ($this->resolvesToRouteFacade($phpcsFile, $stackPtr) === false) {
            return;
        }

        $this->reportClosureArguments(
            $phpcsFile,
            $opener,
            $tokens[$opener]['parenthesis_closer'],
            $tokens[$stackPtr]['content']
        );
    }

    /**
     * Whether the call whose method name sits at $namePtr is rooted in the
     * `Route` facade.
     *
     * Two shapes reach a route verb. A static call names the class directly
     * (`Route::get`). A chained call reaches it through an earlier call in the
     * same chain (`Route::middleware('auth')->get`), so the object operator
     * case hops back over the previous call's parentheses to that call's own
     * name and asks the same question again.
     */
    private function resolvesToRouteFacade(File $phpcsFile, int $namePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($namePtr - 1), null, true);

        if ($previous === false) {
            return false;
        }

        if ($tokens[$previous]['code'] === T_DOUBLE_COLON) {
            return $this->isRouteClassName($phpcsFile, $previous);
        }

        $chained = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];

        if (in_array($tokens[$previous]['code'], $chained, true) === false) {
            return false;
        }

        $closer = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previous - 1), null, true);

        if (
            $closer === false
            || $tokens[$closer]['code'] !== T_CLOSE_PARENTHESIS
            || isset($tokens[$closer]['parenthesis_opener']) === false
        ) {
            return false;
        }

        $callName = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($tokens[$closer]['parenthesis_opener'] - 1),
            null,
            true
        );

        if ($callName === false || $tokens[$callName]['code'] !== T_STRING) {
            return false;
        }

        return $this->resolvesToRouteFacade($phpcsFile, $callName);
    }

    /**
     * Whether the class named immediately before the `::` at $doubleColonPtr
     * is `Route`.
     *
     * PHPCS splits a qualified name back into T_STRING/T_NS_SEPARATOR tokens,
     * so the token before `::` is the last segment on its own — `Route` for
     * `\Illuminate\Support\Facades\Route` exactly as for a bare `Route`. PHP
     * class names are case insensitive, so the comparison is too.
     */
    private function isRouteClassName(File $phpcsFile, int $doubleColonPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($doubleColonPtr - 1),
            null,
            true
        );

        if ($classPtr === false || $tokens[$classPtr]['code'] !== T_STRING) {
            return false;
        }

        return strtolower($tokens[$classPtr]['content']) === 'route';
    }

    /**
     * Reports every argument of the call that is itself a closure.
     *
     * A named argument (`action: fn () => …`) and a static closure
     * (`static function () {}`) both put tokens in front of the closure, so
     * each argument is unwrapped past a `name:` label and a `static` modifier
     * before its kind is read.
     */
    private function reportClosureArguments(
        File $phpcsFile,
        int $opener,
        int $closer,
        string $method
    ): void {
        $tokens = $phpcsFile->getTokens();
        $argument = $phpcsFile->findNext(Tokens::$emptyTokens, ($opener + 1), $closer, true);

        while ($argument !== false) {
            $action = $this->unwrapArgument($phpcsFile, $argument, $closer);
            $isClosure = $action !== false
                && in_array($tokens[$action]['code'], [T_CLOSURE, T_FN], true) === true;

            if ($isClosure === true) {
                $phpcsFile->addError(
                    'Route::%s() must not take a closure action; a closure cannot be serialized by'
                        . ' route:cache — point the route at a controller instead',
                    $action,
                    'ClosureAction',
                    [$method]
                );
            }

            $comma = $this->nextArgumentSeparator($phpcsFile, $argument, $closer);

            if ($comma === false) {
                return;
            }

            $argument = $phpcsFile->findNext(Tokens::$emptyTokens, ($comma + 1), $closer, true);
        }
    }

    /**
     * The token that decides an argument's kind: the argument itself, minus a
     * leading `name:` label and a leading `static` modifier.
     *
     * @return int|false
     */
    private function unwrapArgument(File $phpcsFile, int $argument, int $closer)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$argument]['code'] === T_PARAM_NAME) {
            $colon = $phpcsFile->findNext(Tokens::$emptyTokens, ($argument + 1), $closer, true);

            if ($colon === false || $tokens[$colon]['code'] !== T_COLON) {
                return false;
            }

            $argument = $phpcsFile->findNext(Tokens::$emptyTokens, ($colon + 1), $closer, true);

            if ($argument === false) {
                return false;
            }
        }

        if ($tokens[$argument]['code'] !== T_STATIC) {
            return $argument;
        }

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($argument + 1), $closer, true);
    }

    /**
     * The comma separating this argument from the next, or false when this is
     * the last one.
     *
     * The scan steps over every nested construct it meets, so a comma inside a
     * nested call, array, closure body or match expression never reads as an
     * argument boundary of *this* call.
     *
     * @return int|false
     */
    private function nextArgumentSeparator(File $phpcsFile, int $start, int $closer)
    {
        $tokens = $phpcsFile->getTokens();

        for ($pointer = $start; $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] === T_COMMA) {
                return $pointer;
            }

            $nestedCloser = $this->nestedCloserAt($phpcsFile, $pointer);

            if ($nestedCloser !== false) {
                $pointer = $nestedCloser;
            }
        }

        return false;
    }

    /**
     * Where the nested construct opening at $pointer ends, or false when no
     * construct opens there.
     *
     * @return int|false
     */
    private function nestedCloserAt(File $phpcsFile, int $pointer)
    {
        $token = $phpcsFile->getTokens()[$pointer];

        foreach (self::NESTED_CLOSERS as $closerKey) {
            $closer = $token[$closerKey] ?? $pointer;

            if ($closer > $pointer) {
                return $closer;
            }
        }

        return false;
    }
}

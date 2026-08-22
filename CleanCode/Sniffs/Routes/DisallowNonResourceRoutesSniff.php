<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Warns on a route registered with an HTTP-verb call instead of a resource
 * route.
 *
 * Partial enforcement of "Routes: Conventions (Do / Do Not)" (#65) —
 * docs/standards/routes-conventions-do-do-not.md — whose first Do bullet asks
 * for resource routes pointing at RESTful controllers (#248). Whether the
 * controller a route points at is really RESTful is a claim about another file,
 * but one half of the bullet is plain single-file token content: a route
 * registered as `Route::get(...)` rather than `Route::resource(...)` is outside
 * the convention. That is the slice enforced here.
 *
 * The heuristic: a static call on a `Route` receiver whose method is one of
 * get, post, put, patch, delete, options, any or match registers a route with
 * a verb. `Route::resource()`, `Route::apiResource()`, `Route::resources()` and
 * `Route::apiResources()` are the compliant shapes and are not in the watched
 * list, so they never report. Neither are the modifiers that only wrap or
 * configure other routes — `group()`, `middleware()`, `prefix()`, `name()`,
 * `domain()`, `controller()` — nor `fallback()`, which registers the
 * no-match handler rather than a resource.
 *
 * The verb is matched on the token's *content* between `::` and `(` rather than
 * on its token code, because PHP_CodeSniffer re-labels a reserved word in that
 * position as T_STRING and the watched list contains one (`match`). Nothing
 * other than a static method call can put a bare name there, so the position
 * alone settles what the token is. The comparison is case-insensitive, PHP
 * method names being case-insensitive, and the receiver is compared on its
 * trailing namespace segment, so `Route`, `\Route` and
 * `Illuminate\Support\Facades\Route` all read as the facade while `ApiRoute`
 * and `Router` do not.
 *
 * Scope: the sniff inspects a file only when its path matches one of
 * $routeFilePatterns (fnmatch globs, defaulting to any path holding a `routes`
 * directory segment). PHP_CodeSniffer sees one file at a time and has no notion
 * of "a routes file", so the filename is the only available signal, and a
 * verb call outside a route file is left alone entirely. Two consequences worth
 * knowing: piped input reports the path as `STDIN`, which matches no default
 * glob, so the sniff says nothing about it; and the glob matches a `routes`
 * segment anywhere in the absolute path PHP_CodeSniffer hands over, so a
 * checkout living under a directory called `routes` widens the gate to the
 * whole project. A project in that position retunes the property.
 *
 * Warning severity, not error, because the standard itself allows an exception
 * ("this should be very rare") and a sniff cannot tell a legitimate rare
 * exception from a controller that should have been RESTful. Detection only:
 * turning a verb route into a resource route means writing the seven RESTful
 * actions, so there is no mechanical rewrite and no autofixed fixture.
 *
 * Boundaries, all six also recorded in the standard's doc:
 *
 * - **Special-action routes (false positive).** The standard permits rare
 *   special-action routes, and those are registered with a verb call. Every
 *   one of them reports; warning severity is what keeps that from failing a
 *   build. #249 covers the complementary check that such a route at least
 *   points at an invokable controller.
 * - **`fallback()` and framework-provided registrations (false positive).**
 *   `fallback` is deliberately outside the watched list for this reason. Other
 *   package-provided macros that register a route through a verb call are not
 *   distinguishable from an application's own at token level.
 * - **Routes registered outside a route file (false negative).** A verb call
 *   in a service provider or a package boot method never matches
 *   $routeFilePatterns and is never seen. Widening the gate trades this for a
 *   much higher false-positive rate.
 * - **The controller's actual shape (false negative).** "Points to a RESTful
 *   controller" is a claim about another file. This sniff reads the
 *   registration call only; whether the target controller implements the seven
 *   RESTful actions needs project-wide symbol resolution and stays with code
 *   review.
 * - **Symbol resolution.** Like #174, the sniff assumes the Laravel `Route`
 *   facade convention. It cannot resolve which `Route` symbol an import
 *   actually binds, so an unrelated class named `Route` reports and an aliased
 *   facade (`use Route as Web;`) does not.
 * - **Fluent-chained verb calls (false negative).**
 *   `Route::middleware(...)->get(...)` and `Route::prefix(...)->post(...)` are
 *   not `Route::` static calls — the verb sits after `->` on the returned
 *   registrar — so this token-level heuristic does not see them.
 *
 * Fixtured in tests/fixtures/DisallowNonResourceRoutesSniff/ and covered by
 * tests/Standards/DisallowNonResourceRoutesTest.php. Those fixtures cannot sit
 * where the generic contract sweep drives them, because tests/fixtures/ holds
 * no `routes` segment and so matches no default glob; the sniff is held out of
 * that sweep and its own test stages the fixtures under a `routes` directory
 * instead.
 */
class DisallowNonResourceRoutesSniff implements Sniff
{
    /**
     * Path globs (fnmatch syntax) that mark a file as a route file. The sniff
     * inspects nothing outside them. Configurable from a ruleset via
     * <property name="routeFilePatterns" type="array" .../>.
     *
     * @var array<string>
     */
    public array $routeFilePatterns = [
        '*/routes/*',
    ];

    /**
     * The trailing receiver segment that marks the Laravel routing facade,
     * lowercased for comparison. PHP class names are case-insensitive.
     */
    private const ROUTE_FACADE = 'route';

    /**
     * The static methods that register a route with an HTTP verb, lowercased
     * for comparison. Everything absent from this list is left alone — the
     * resource-route registrars, the wrapping modifiers, and fallback().
     */
    private const ROUTE_VERBS = [
        'any',
        'delete',
        'get',
        'match',
        'options',
        'patch',
        'post',
        'put',
    ];

    /**
     * Every token a receiver's class name can arrive as.
     *
     * Only T_STRING is reachable today: PHP_CodeSniffer 3.x deliberately
     * "undoes" PHP 8's single qualified-name tokens back to the pre-8.0
     * T_STRING/T_NS_SEPARATOR spelling (Tokenizers/PHP.php, the
     * PHP_VERSION_ID >= 80000 branch), so the token immediately before `::` is
     * always a plain T_STRING already carrying just the trailing segment. The
     * three T_NAME_* codes are defensive, against a future release that stops
     * undoing it — at which point `Illuminate\Support\Facades\Route::get()`
     * would arrive as one token and the trailing-segment split below is what
     * keeps it recognised instead of silently unflagged. Neither the codes nor
     * the split can be pinned by a fixture under this PHPCS, and the sniff's
     * test says so outright rather than implying coverage. PHP's floor here is
     * 8.1, so all three constants are defined.
     */
    private const RECEIVER_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
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
        if ($this->isRouteFile($phpcsFile->getFilename()) === false) {
            return;
        }

        if ($this->isRouteFacade($phpcsFile, $stackPtr) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $verbPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($verbPtr === false) {
            return;
        }

        if (in_array(strtolower($tokens[$verbPtr]['content']), self::ROUTE_VERBS, true) === false) {
            return;
        }

        if ($this->registersRoute($phpcsFile, $verbPtr) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'Route::%s() registers a route with an HTTP verb; the standard asks for resource '
                . 'routes pointing at RESTful controllers (Route::resource(), Route::apiResource()), '
                . 'with an invokable-controller special-action route as a rare exception '
                . '(see docs/standards/routes-conventions-do-do-not.md)',
            $verbPtr,
            'Found',
            [$tokens[$verbPtr]['content']]
        );
    }

    /**
     * Whether the file's path matches one of the configured route-file globs.
     *
     * Windows separators are normalised to forward slashes on both sides, so
     * one glob spelling matches on either platform. The match is
     * case-sensitive: the directory Laravel ships is `routes`, and folding case
     * buys nothing a project cannot get by adding its own glob.
     */
    private function isRouteFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->routeFilePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the receiver before `::` is the routing facade.
     *
     * Only the trailing namespace segment is compared, because an import
     * cannot be resolved from one file's tokens: `Route`, `\Route` and
     * `Illuminate\Support\Facades\Route` are the same facade, and comparing
     * whole names would miss two of the three. The comparison is on a whole
     * segment rather than a substring, so `Router` and `ApiRoute` do not match.
     *
     * A receiver that is not a name at all — `$router::get()`, `static::`,
     * `self::` — falls out here, as does a `::` with nothing before it in a
     * file the tokenizer caught mid-edit.
     */
    private function isRouteFacade(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($receiverPtr === false) {
            return false;
        }

        if (in_array($tokens[$receiverPtr]['code'], self::RECEIVER_TOKENS, true) === false) {
            return false;
        }

        $segments = explode('\\', $tokens[$receiverPtr]['content']);

        return strtolower((string) end($segments)) === self::ROUTE_FACADE;
    }

    /**
     * Whether the matched verb is a call that really registers a route.
     *
     * Two things separate one from a name that merely reads like one:
     *
     * - an open parenthesis has to follow, so a class-constant read
     *   (`Route::GET`) is not mistaken for a registration;
     * - the argument list must not be PHP 8.1's first-class-callable `...`,
     *   which builds a Closure and registers nothing. The same guard is
     *   carried by CleanCode.ControlStructures.DisallowCountInLoopExpression
     *   for the same reason, in the same shape: the `...` counts only when the
     *   parenthesis closes straight after it, so an argument spread
     *   (`Route::get(...$definition)`) stays a real registration.
     */
    private function registersRoute(File $phpcsFile, int $verbPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($verbPtr + 1), null, true);

        if ($openPtr === false || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS) {
            return false;
        }

        return $this->isFirstClassCallable($phpcsFile, $openPtr) === false;
    }

    /**
     * Whether the argument list opening at $openerPtr is a first-class-callable
     * reference — an ellipsis and nothing else.
     *
     * Both halves matter. Without the ellipsis check an ordinary call would
     * read as a reference; without the closing-parenthesis check an argument
     * spread would, and `Route::get(...$definition)` registers a route just as
     * `Route::get('/photos', $action)` does.
     */
    private function isFirstClassCallable(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ellipsisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), null, true);

        if ($ellipsisPtr === false || $tokens[$ellipsisPtr]['code'] !== T_ELLIPSIS) {
            return false;
        }

        $afterEllipsisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsisPtr + 1), null, true);

        return $afterEllipsisPtr !== false && $tokens[$afterEllipsisPtr]['code'] === T_CLOSE_PARENTHESIS;
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a special-action route whose controller is not invokable (Routes:
 * Conventions (Do / Do Not), #65, focused slice #249) —
 * docs/standards/routes-conventions-do-do-not.md.
 *
 * The standard says: "for special action routes, use invokable controllers;
 * this should be very rare". The *action argument* of a verb registration
 * carries the shape half of that:
 *
 *   - `Route::get('/x', ArchiveController::class)` — invokable, compliant, and
 *     never flagged;
 *   - `Route::get('/x', [PostController::class, 'archive'])` — a named method
 *     on a shared controller, flagged;
 *   - `Route::get('/x', 'PostController@archive')` — the legacy string
 *     spelling of the same thing, flagged.
 *
 * The action is read at the verb's own argument position — third for
 * `match()`, second for every other verb — or by name when the call writes
 * `action: …`, which identifies the argument whatever order the names are in.
 *
 * An action naming one of the seven RESTful methods (index, create, store,
 * show, edit, update, destroy) is deliberately NOT flagged. That shape is a
 * resource route written out longhand, which is
 * CleanCode.Routes.NonResourceRegistration's (#248) diagnostic about the verb
 * call itself; reporting it here too would double-report one line.
 *
 * Scope: the sniff inspects a file only when its path matches one of
 * $routeFilePatterns (fnmatch globs, defaulting to any path holding a `routes`
 * directory segment). Without that gate every `Route::verb()` call in a
 * service provider, a package boot method or a test would be in scope, which
 * is a false-positive flood rather than a reading of the standard. A file
 * PHPCS has no path for (piped input, reported as STDIN) matches no glob and
 * is therefore silent too.
 *
 * Warnings, not errors: the standard's "very rare" carve-out is a judgement,
 * so a codebase that deliberately groups several related non-RESTful actions
 * on one controller must not fail its build over it. Detection only —
 * converting an action to an invokable controller means creating that class
 * and moving the method into it, which is not a mechanical rewrite, so there
 * is no fixer and no autofixed.php fixture.
 *
 * Known limits, all by design and all recorded in the standard's doc:
 *
 * - **Symbol resolution.** Like #174 and #248, the receiver is matched on the
 *   literal token `Route`, so the sniff assumes the Laravel facade convention.
 *   An aliased import (`use Router as Route`, or `use App\Route`) cannot be
 *   resolved from one file's tokens. The upside of matching the literal is
 *   that an unrelated `Http::get()` or `Cache::get()` can never be mistaken
 *   for a route registration.
 * - **A bare `::class` action passes on shape alone.** Whether that class
 *   really declares `__invoke()` lives in another file.
 * - **Frequency is invisible.** A routes file holding thirty invokable
 *   special-action routes clears every check here while plainly breaking the
 *   bullet's "very rare"; that judgement stays with code review.
 * - **A dynamic action is skipped, not guessed at.** `[$controller, 'x']`,
 *   `[PostController::class, $method]`, `[PostController::class, self::X]` and
 *   `"PostController@{$method}"` are all unreadable at token level.
 * - **Only the two documented shapes are read.** An associative
 *   `['uses' => ...]` action, a closure or arrow function (#174's shape), and
 *   anything else are left alone rather than read positionally.
 * - **A chained registration is not seen.** In
 *   `Route::middleware('auth')->get('/x', [PostController::class, 'archive'])`
 *   the verb is called on the object the first call returned, with `->` and
 *   not `::`, so the receiver check never reaches it. Walking back up a chain
 *   to its head would have to distinguish the facade from any other fluent
 *   builder, which one file's tokens do not settle.
 *
 * Fixtured in tests/fixtures/NonInvokableSpecialActionSniff/ and covered by
 * tests/Standards/NonInvokableSpecialActionTest.php. The fixtures are staged
 * into a `routes` directory outside the repository before processing, because
 * the file gate above decides from the path alone.
 */
class NonInvokableSpecialActionSniff implements Sniff
{
    /**
     * The receiver a route registration is written against. Compared
     * case-sensitively and as one literal token: a class name is not the same
     * kind of thing as a method name, and folding case here would let
     * `ROUTE::get()` through while widening the match buys nothing.
     */
    private const ROUTE_FACADE = 'Route';

    /**
     * The name Laravel's Router gives the action parameter of every verb it
     * declares, which is what a named argument spells: `action: [...]`.
     *
     * Matched exactly. PHP resolves a named argument against the parameter's
     * own spelling, so `Action:` names no parameter of the call at all and is
     * a call PHP rejects rather than a second spelling of this one.
     */
    private const ACTION_PARAMETER = 'action';

    /**
     * The verb methods that register a route, mapped to the 1-based position
     * of their action argument.
     *
     * `match()` takes the HTTP methods first (`match($methods, $uri, $action)`)
     * so its action sits one place further along than every other verb's.
     * Reading position 2 for it would inspect the URI instead.
     *
     * Keys are lower case; the lookup lowers the written verb, because PHP
     * method names are case-insensitive and `Route::GET()` calls the same
     * method as `Route::get()`.
     *
     * @var array<string, int>
     */
    private const ACTION_POSITIONS = [
        'get' => 2,
        'post' => 2,
        'put' => 2,
        'patch' => 2,
        'delete' => 2,
        'options' => 2,
        'any' => 2,
        'match' => 3,
    ];

    /**
     * The seven RESTful actions. An action naming one of these is a resource
     * route written longhand — #248's diagnostic, not this sniff's.
     *
     * Compared case-sensitively, unlike the verb above: this is a convention
     * about how the method is *named*, and `Index`/`STORE` do not follow it.
     *
     * @var array<int, string>
     */
    private const RESTFUL_ACTIONS = [
        'index',
        'create',
        'store',
        'show',
        'edit',
        'update',
        'destroy',
    ];

    /**
     * Every token a class name can be made of, in either tokenisation: the
     * pre-8.0 spelling PHP_CodeSniffer backfills to (T_STRING joined by
     * T_NS_SEPARATOR) and PHP 8's single qualified-name tokens, in case a
     * future PHPCS stops backfilling.
     *
     * @var array<int, int|string>
     */
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    /**
     * A PHP method name. Applied to both halves of the two recognised action
     * shapes, so a string that merely holds an `@` cannot be read as one.
     */
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';

    /**
     * A possibly namespaced PHP class name, leading separator optional.
     */
    private const CLASS_NAME_PATTERN
        = '/^\\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/';

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

        $registration = $this->routeRegistration($phpcsFile, $stackPtr);

        if ($registration === null) {
            return;
        }

        [$verbPtr, $position] = $registration;
        $argument = $this->actionArgument($phpcsFile, $verbPtr, $position);

        if ($argument === null) {
            return;
        }

        [$start, $end] = $argument;
        $method = $this->targetMethod($phpcsFile, $start, $end);

        if ($method === null || in_array($method, self::RESTFUL_ACTIONS, true) === true) {
            return;
        }

        $phpcsFile->addWarning(
            'A special action route should point to an invokable controller; this one targets'
                . ' %s() on a shared controller (see'
                . ' docs/standards/routes-conventions-do-do-not.md)',
            $start,
            'Found',
            [$method]
        );
    }

    /**
     * Whether the file's path matches one of the configured route-file globs.
     *
     * Windows separators are normalised to forward slashes on both sides, so
     * one glob spelling matches on either platform. fnmatch's `*` spans `/`
     * here (FNM_PATHNAME is not passed), which is what lets the shipped
     * default (a star, `/routes/`, a star — spelled out in $routeFilePatterns
     * rather than here, because the closing pair would end this comment)
     * reach a nested `routes/admin/panel.php`.
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
     * The verb token and the 1-based position of its action argument, or null
     * when the `::` at $stackPtr is not a `Route` verb registration.
     *
     * The receiver has to be the literal token `Route` immediately before the
     * `::`, which is what keeps `Http::get()`, `Cache::get()` and every other
     * facade out. `\Route::get()` still matches: PHP_CodeSniffer backfills the
     * leading separator to its own T_NS_SEPARATOR token, so the token directly
     * before the `::` is still T_STRING `Route`.
     *
     * @return array{0: int, 1: int}|null
     */
    private function routeRegistration(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($receiverPtr === false || $tokens[$receiverPtr]['code'] !== T_STRING) {
            return null;
        }

        if ($tokens[$receiverPtr]['content'] !== self::ROUTE_FACADE) {
            return null;
        }

        $verbPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($verbPtr === false || $tokens[$verbPtr]['code'] !== T_STRING) {
            return null;
        }

        $position = self::ACTION_POSITIONS[strtolower($tokens[$verbPtr]['content'])] ?? null;

        return $position === null ? null : [$verbPtr, $position];
    }

    /**
     * The `[start, end]` token span of the call's action argument, or null
     * when there is nothing to read there.
     *
     * An argument written as `action: …` is read by its name, wherever it
     * sits: a name identifies the argument outright, so neither the verb's
     * position map nor the order the names are written in comes into it.
     * Only when the call names nothing does position decide, and then only
     * across the arguments that are actually positional.
     *
     * Null covers two cases, both of them "skip rather than guess": the verb
     * is not called at all (`Route::get;`), and the call names no action and
     * carries fewer positional arguments than the action's position —
     * `Route::get($uri)` or `Route::match($methods, $uri)` register nothing to
     * inspect.
     *
     * @return array{0: int, 1: int}|null
     */
    private function actionArgument(File $phpcsFile, int $verbPtr, int $position): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($verbPtr + 1), null, true);

        if ($openPtr === false || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS) {
            return null;
        }

        $arguments = array_map(
            fn (array $range): array => $this->labelledArgument($phpcsFile, $range[0], $range[1]),
            $this->argumentRanges($phpcsFile, $openPtr)
        );

        foreach ($arguments as $argument) {
            if ($argument['label'] === self::ACTION_PARAMETER) {
                return [$argument['start'], $argument['end']];
            }
        }

        $positional = array_values(
            array_filter($arguments, static fn (array $argument): bool => $argument['label'] === null)
        );
        $action = $positional[$position - 1] ?? null;

        return $action === null ? null : [$action['start'], $action['end']];
    }

    /**
     * The `[start, end]` span of each argument between $openPtr and its
     * closer, a named argument's own label included in its span.
     *
     * Commas are only counted at the call's own depth: every group opener
     * (parentheses, arrays, subscripts, braces, attributes) is jumped straight
     * to its closer, so a comma inside a nested array or a nested call cannot
     * shift an argument's position.
     *
     * A name needs no rule of its own here. It opens the span of the argument
     * it belongs to, exactly as a positional argument's first token does, and
     * labelledArgument() below is what separates the two.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function argumentRanges(File $phpcsFile, int $openPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $closePtr = $tokens[$openPtr]['parenthesis_closer'] ?? null;

        if ($closePtr === null) {
            return [];
        }

        $ranges = [];
        $start = null;
        $end = null;

        for ($ptr = ($openPtr + 1); $ptr < $closePtr; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_COMMA) {
                if ($start !== null) {
                    $ranges[] = [$start, $end];
                }

                $start = null;

                continue;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $end = $this->groupCloser($tokens, $ptr);
            $start ??= $ptr;
            $ptr = $end;
        }

        if ($start !== null) {
            $ranges[] = [$start, $end];
        }

        return $ranges;
    }

    /**
     * The name the argument spanning $start to $end is written under — null
     * when it is positional — and the span of its value with any label taken
     * off the front.
     *
     * Stripping the label is what makes `action: [Foo::class, 'archive']`
     * present the same span as the positional spelling of it, so one reading
     * covers both and a violation is reported at its action rather than at its
     * name.
     *
     * A named argument opens on three significant tokens in a fixed order: the
     * name, its colon, then the value. PHP_CodeSniffer only spells a name
     * T_PARAM_NAME when the next significant token is that colon, so the colon
     * needs no check of its own and the value is simply the third of them. A
     * name with no value after it at all is not a call PHP accepts; that span
     * falls back to its own end, which reads as no recognised action shape.
     *
     * @return array{label: string|null, start: int, end: int}
     */
    private function labelledArgument(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] !== T_PARAM_NAME) {
            return ['label' => null, 'start' => $start, 'end' => $end];
        }

        $meaningful = $this->meaningfulTokens($phpcsFile, $start, $end);

        return [
            'label' => $tokens[$start]['content'],
            'start' => $meaningful[2] ?? $end,
            'end' => $end,
        ];
    }

    /**
     * The closing token of the group $ptr opens, or $ptr itself when it opens
     * nothing. Every closer PHPCS records lives under a different key, so all
     * four are consulted rather than assuming one spelling.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function groupCloser(array $tokens, int $ptr): int
    {
        foreach (['parenthesis_closer', 'bracket_closer', 'attribute_closer', 'scope_closer'] as $key) {
            if (isset($tokens[$ptr][$key]) === true && $tokens[$ptr][$key] > $ptr) {
                return $tokens[$ptr][$key];
            }
        }

        return $ptr;
    }

    /**
     * The controller method the action argument between $start and $end names,
     * or null when the argument is not one of the two recognised non-invokable
     * shapes.
     *
     * Null is the answer for everything else, deliberately: a bare
     * `PostController::class` (invokable, compliant), a closure or arrow
     * function (#174's shape), an associative `['uses' => ...]` action, and
     * every dynamic spelling of either recognised shape.
     */
    private function targetMethod(File $phpcsFile, int $start, int $end): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] === T_OPEN_SHORT_ARRAY || $tokens[$start]['code'] === T_ARRAY) {
            return $this->methodFromArrayAction($phpcsFile, $start, $end);
        }

        $meaningful = $this->meaningfulTokens($phpcsFile, $start, $end);

        if (count($meaningful) !== 1 || $tokens[$start]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        return $this->methodFromStringAction($this->literalValue($tokens[$start]['content']));
    }

    /**
     * The method named by an array action, or null when the array is not
     * literally `[SomeController::class, 'method']`.
     *
     * Both halves have to be literal. A non-literal first element
     * (`[$controller, 'x']`) hides which class is targeted, and a non-literal
     * second element (`[PostController::class, $method]`, or `self::ACTION`)
     * hides the method name itself — the very thing being classified.
     *
     * Requiring both halves to be literal is also what leaves the associative
     * `['uses' => ...]` action alone: a `key => value` element is neither a
     * bare `::class` constant nor a lone string token, so that shape is never
     * read by index. See elementRanges() below.
     */
    private function methodFromArrayAction(File $phpcsFile, int $start, int $end): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $tokens[$start]['code'] === T_ARRAY
            ? ($tokens[$start]['parenthesis_opener'] ?? null)
            : $start;

        if ($openPtr === null || $this->groupCloser($tokens, $openPtr) !== $end) {
            return null;
        }

        $elements = $this->elementRanges($phpcsFile, $openPtr, $end);

        if (count($elements) !== 2) {
            return null;
        }

        if ($this->isClassConstant($phpcsFile, $elements[0][0], $elements[0][1]) === false) {
            return null;
        }

        [$methodStart, $methodEnd] = $elements[1];
        $meaningful = $this->meaningfulTokens($phpcsFile, $methodStart, $methodEnd);

        if (count($meaningful) !== 1 || $tokens[$methodStart]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $method = $this->literalValue($tokens[$methodStart]['content']);

        return preg_match(self::IDENTIFIER_PATTERN, $method) === 1 ? $method : null;
    }

    /**
     * The `[start, end]` span of each element of the array between $openPtr
     * and $closePtr.
     *
     * An associative element (`'uses' => …`) needs no guard of its own. Its
     * range swallows the key and the `=>` along with the value, which breaks
     * both literal-element checks in the caller — a `key => value` pair is
     * never a bare `SomeClass::class` constant, and never a lone string token
     * either. The associative action shape therefore falls out with every
     * other unrecognised shape rather than through a rule of its own.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function elementRanges(File $phpcsFile, int $openPtr, int $closePtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $ranges = [];
        $start = null;
        $end = null;

        for ($ptr = ($openPtr + 1); $ptr < $closePtr; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_COMMA) {
                if ($start !== null) {
                    $ranges[] = [$start, $end];
                }

                $start = null;

                continue;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $end = $this->groupCloser($tokens, $ptr);
            $start ??= $ptr;
            $ptr = $end;
        }

        if ($start !== null) {
            $ranges[] = [$start, $end];
        }

        return $ranges;
    }

    /**
     * Whether the tokens between $start and $end spell a literal
     * `SomeClass::class` constant.
     *
     * The name run ahead of the `::` is checked token by token rather than
     * assumed to be one token, because PHP_CodeSniffer backfills PHP 8's
     * single qualified-name tokens into the pre-8.0 T_STRING/T_NS_SEPARATOR
     * spelling: `\App\PostController::class` arrives as four name tokens.
     * `$class::class`, `static::class` and `self::class` all fail here — none
     * of them names a controller a reader can resolve.
     */
    private function isClassConstant(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $meaningful = $this->meaningfulTokens($phpcsFile, $start, $end);

        if (count($meaningful) < 3) {
            return false;
        }

        $keywordPtr = (int) array_pop($meaningful);
        $operatorPtr = (int) array_pop($meaningful);

        if ($tokens[$operatorPtr]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        if (strtolower($tokens[$keywordPtr]['content']) !== 'class') {
            return false;
        }

        foreach ($meaningful as $ptr) {
            if (in_array($tokens[$ptr]['code'], self::NAME_TOKENS, true) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * The method named by the legacy `'PostController@archive'` string action,
     * or null when the string is not that shape.
     *
     * Exactly one `@` is required, with a class name on the left and a method
     * name on the right. A plain `'PostController'` names no method to
     * classify, a string carrying several `@` is not a spelling Laravel
     * resolves, and requiring both sides to be real identifiers is what stops
     * an ordinary string that happens to hold an `@` — an email address passed
     * as a default, say — being read as a controller action.
     */
    private function methodFromStringAction(string $action): ?string
    {
        if (substr_count($action, '@') !== 1) {
            return null;
        }

        [$controller, $method] = explode('@', $action);

        if (preg_match(self::CLASS_NAME_PATTERN, $controller) !== 1) {
            return null;
        }

        return preg_match(self::IDENTIFIER_PATTERN, $method) === 1 ? $method : null;
    }

    /**
     * The value of a quoted string literal, the outer quote pair removed.
     *
     * No unescaping follows, and none is needed: the two halves this sniff
     * reads out of a literal are a class name and a method name, and neither
     * can contain an escape sequence and still be a valid PHP identifier — the
     * identifier patterns above reject anything that tries.
     */
    private function literalValue(string $content): string
    {
        return substr($content, 1, -1);
    }

    /**
     * The pointers of the non-whitespace, non-comment tokens between $start
     * and $end inclusive.
     *
     * @return array<int, int>
     */
    private function meaningfulTokens(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            if (isset(Tokens::$emptyTokens[$tokens[$ptr]['code']]) === true) {
                continue;
            }

            $pointers[] = $ptr;
        }

        return $pointers;
    }
}

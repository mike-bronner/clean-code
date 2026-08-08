<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the token-visible slice of the "Dependency Injection" standard.
 *
 * A constructor's job is to *receive* collaborators, not to build them, so a
 * `new` expression inside a `__construct` body is the one signal of hard-wiring
 * over injection that a single-file token scan can see. Each one is reported as
 * a **warning**, not an error: value objects, DTOs, and default collaborator
 * instances are legitimately constructed inline, so the sniff points at
 * injection candidates rather than mandating a fix. Whether a given collaborator
 * warrants injection stays with code review.
 *
 * Reported at the `new` keyword, one warning per `new`, under the single code
 * `Found`. Detection only — replacing an instantiation with an injected
 * parameter changes the class's public signature and every call site, which is
 * not a mechanical rewrite.
 *
 * Deliberately **not** reported, and why:
 *
 * - Anything in a **thrown expression** — raising an exception is not
 *   dependency construction. The whole operand of a `throw` is jumped, not just
 *   a `new` sitting directly after the keyword, so `throw (new X())`,
 *   `throw match (...) { ... => new X() }`, `throw $cond ? new A() : new B()`
 *   and an exception's own arguments (`throw new Wrapper(new Cause())`) are all
 *   silent. The jump stops where the operand does: in `$x = $c ? throw new E()
 *   : new Mailer()` the `new Mailer()` is still reported, because it belongs to
 *   the ternary, not to the `throw`.
 * - `new` outside a constructor body — ordinary methods are far too noisy
 *   (factories, named constructors, collections, dates) to flag at the token
 *   level, and a constructor's *parameter list* is where an inline default
 *   collaborator (`= new NullLogger()`, PHP 8.1 new-in-initializers) belongs.
 *   Only the token range between the body's braces is scanned.
 * - `new` inside a closure, arrow function, or anonymous class declared in a
 *   constructor body — that code runs later, or belongs to another type. A
 *   lazily-built collaborator (`$this->make = fn () => new Mailer();`) is the
 *   deferred construction the standard asks for, not the coupling it forbids,
 *   so every nested scope is jumped over rather than walked into. The `new` of
 *   the anonymous class itself is still reported: that instantiation does
 *   happen in the constructor.
 * - A declaration named `__construct` that is **not a method** — a plain
 *   function carrying that name (legal at file scope, and PHPCS lints whatever
 *   paths a consumer points it at) has no class to inject into, so the
 *   declaration is only treated as a constructor when its immediately enclosing
 *   scope is a class-like one.
 *
 * The cross-file half of the standard — correlating an instantiated class with
 * a constructor type hint elsewhere in the project — is out of reach for PHPCS,
 * which hands a sniff one file at a time. See
 * docs/standards/dependency-injection.md.
 */
class DisallowConstructorInstantiationSniff implements Sniff
{
    /**
     * Scopes whose direct member functions are methods, so a `__construct`
     * declared in one is a real constructor.
     *
     * @var array<int, int|string>
     */
    private const CLASS_LIKE_SCOPES = [T_CLASS, T_ANON_CLASS, T_TRAIT, T_INTERFACE, T_ENUM];

    /**
     * Bracket and brace closers. Reaching one while walking a thrown
     * expression means the expression's own container ended.
     *
     * @var array<int, int|string>
     */
    private const GROUP_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || strtolower($name) !== '__construct') {
            return;
        }

        // A function is only a constructor when a class-like scope holds it
        // directly. A plain `function __construct()` at file scope, or nested
        // inside another function, injects into nothing.
        $enclosing = $tokens[$stackPtr]['conditions'];

        if (!in_array(end($enclosing), self::CLASS_LIKE_SCOPES, true)) {
            return;
        }

        // An abstract or interface constructor has no body to scan.
        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $closer = $tokens[$stackPtr]['scope_closer'];

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            // Everything a `throw` raises is exception construction, however it
            // is spelled — jump the whole operand rather than inspecting the
            // single token that follows the keyword.
            if ($tokens[$pointer]['code'] === T_THROW) {
                $pointer = $this->endOfThrownExpression($phpcsFile, $pointer, $closer);

                continue;
            }

            if ($tokens[$pointer]['code'] === T_NEW) {
                $this->reportInstantiation($phpcsFile, $pointer);

                continue;
            }

            // Nested scopes (closures, arrow functions, anonymous classes and
            // their methods) run on their own terms — jump the whole scope.
            // Conditionals and loops carry a scope too, but their bodies are
            // constructor code, so only declarations are skipped.
            if (
                $this->isNestedDeclaration($tokens[$pointer]['code'])
                && isset($tokens[$pointer]['scope_closer'])
            ) {
                $pointer = $tokens[$pointer]['scope_closer'];
            }
        }
    }

    /**
     * @param int|string $code
     */
    private function isNestedDeclaration($code): bool
    {
        return in_array($code, [T_FUNCTION, T_CLOSURE, T_FN, T_ANON_CLASS], true);
    }

    /**
     * The last token of what a `throw` raises.
     *
     * Bracketed and braced groups are jumped whole, so only a terminator
     * belonging to the throw's *own* level ends the operand: a `;`, a `,`
     * (a match arm), a closer of the container the throw sits in, or a ternary
     * `:` with no `?` of its own opened since the keyword — the last of which
     * is what keeps `$x = $c ? throw new E() : new Mailer()` reporting the
     * `new Mailer()`.
     */
    private function endOfThrownExpression(File $phpcsFile, int $throwPtr, int $closer): int
    {
        $tokens = $phpcsFile->getTokens();
        $openTernaries = 0;

        for ($pointer = $throwPtr + 1; $pointer < $closer; $pointer++) {
            $groupCloser = $this->groupCloser($tokens[$pointer], $pointer);

            if ($groupCloser !== null) {
                $pointer = $groupCloser;

                continue;
            }

            $code = $tokens[$pointer]['code'];

            if ($code === T_INLINE_THEN) {
                $openTernaries++;

                continue;
            }

            if ($code === T_INLINE_ELSE && $openTernaries > 0) {
                $openTernaries--;

                continue;
            }

            if (
                $code === T_SEMICOLON
                || $code === T_COMMA
                || $code === T_INLINE_ELSE
                || in_array($code, self::GROUP_CLOSERS, true)
            ) {
                return $pointer;
            }
        }

        return $closer - 1;
    }

    /**
     * The closing token of a group this one opens, or null when it opens none.
     *
     * @param array<string, mixed> $token
     */
    private function groupCloser(array $token, int $pointer): ?int
    {
        foreach (['parenthesis_closer', 'bracket_closer', 'scope_closer'] as $key) {
            if (isset($token[$key]) && $token[$key] > $pointer) {
                return (int) $token[$key];
            }
        }

        return null;
    }

    private function reportInstantiation(File $phpcsFile, int $newPtr): void
    {
        $phpcsFile->addWarning(
            'Constructing a collaborator inside __construct() hard-wires it; inject it as a'
                . ' constructor parameter so it can be resolved through IoC'
                . ' (see docs/standards/dependency-injection.md)',
            $newPtr,
            'Found'
        );
    }
}

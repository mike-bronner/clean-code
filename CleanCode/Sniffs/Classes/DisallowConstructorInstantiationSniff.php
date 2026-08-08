<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

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
 * - `throw new ...` — raising an exception is not dependency construction. A
 *   `T_NEW` whose preceding non-empty token is `T_THROW` is skipped.
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
 *
 * The cross-file half of the standard — correlating an instantiated class with
 * a constructor type hint elsewhere in the project — is out of reach for PHPCS,
 * which hands a sniff one file at a time. See
 * docs/standards/dependency-injection.md.
 */
class DisallowConstructorInstantiationSniff implements Sniff
{
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

        // An abstract or interface constructor has no body to scan.
        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $closer = $tokens[$stackPtr]['scope_closer'];

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
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

    private function reportInstantiation(File $phpcsFile, int $newPtr): void
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $newPtr - 1, null, true);

        if ($previous !== false && $phpcsFile->getTokens()[$previous]['code'] === T_THROW) {
            return;
        }

        $phpcsFile->addWarning(
            'Constructing a collaborator inside __construct() hard-wires it; inject it as a'
                . ' constructor parameter so it can be resolved through IoC'
                . ' (see docs/standards/dependency-injection.md)',
            $newPtr,
            'Found'
        );
    }
}

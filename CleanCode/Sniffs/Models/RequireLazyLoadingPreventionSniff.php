<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags an application service provider that never enables Laravel's
 * lazy-loading safety check.
 *
 * Partial enforcement of the "Models: Eager Loading" standard
 * (docs/standards/models-eager-loading.md). The standard itself — is this
 * query loading the right relationships at the right place? — is not
 * token-visible, but its runtime backstop is: with
 * Model::preventLazyLoading() switched on, accessing a relationship that was
 * not eager loaded throws LazyLoadingViolationException instead of degrading
 * into a silent N+1. The presence of that call is plain single-file token
 * content, so a sniff can verify the enforcement is actually turned on.
 *
 * This is an absence check, and the reported "location" is the whole class,
 * so the warning is attached to the class declaration.
 *
 * Scope decisions:
 *
 * - Matched by class name (default: AppServiceProvider), not by parent class.
 *   Every Laravel application and package carries several classes extending
 *   ServiceProvider — route, event, auth, package providers — and none of
 *   them is expected to enable the check. Keying off `extends ServiceProvider`
 *   would warn on all of them. Apps that enable the check from a different
 *   provider add that class to $serviceProviderClasses.
 * - The call is accepted anywhere in the class body, not only inside boot().
 *   Delegating from boot() to a private helper (`$this->configureModels()`)
 *   is a common provider idiom, and the check is equally enabled either way.
 * - Model::shouldBeStrict() satisfies the check: Laravel's strict mode
 *   enables preventLazyLoading() as part of what it turns on.
 * - Warning severity, not error. Packages and non-application codebases have
 *   no such provider at all, and a project opts in by ruleset inclusion.
 */
class RequireLazyLoadingPreventionSniff implements Sniff
{
    /**
     * Names of the classes that must enable the safety check. Configurable
     * from a ruleset via <property name="serviceProviderClasses" type="array"
     * .../> for applications that boot the check from another provider.
     *
     * @var array<string>
     */
    public array $serviceProviderClasses = [
        'AppServiceProvider',
    ];

    /**
     * Static Model methods that switch the safety check on, lowercased for
     * comparison. PHP method names are case-insensitive.
     */
    private const PREVENTION_METHODS = [
        'preventlazyloading',
        'shouldbestrict',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || $this->isWatchedProvider($name) === false) {
            return;
        }

        if ($this->hasPreventionCall($phpcsFile, $stackPtr) === true) {
            return;
        }

        $phpcsFile->addWarning(
            '%s does not enable Laravel\'s lazy-loading safety check; call '
                . 'Model::preventLazyLoading() (or Model::shouldBeStrict()) so a missing '
                . 'eager load fails loudly instead of running silent N+1 queries '
                . '(see docs/standards/models-eager-loading.md)',
            $stackPtr,
            'Missing',
            [$name]
        );
    }

    private function isWatchedProvider(string $name): bool
    {
        return in_array(
            strtolower($name),
            array_map('strtolower', $this->serviceProviderClasses),
            true
        );
    }

    /**
     * Looks for a static call to one of the prevention methods inside the
     * class body.
     *
     * A truncated class carries no scope_closer, in which case the search runs
     * to the end of the file — there is no following class body to stray into,
     * because the tokenizer never closed this one.
     */
    private function hasPreventionCall(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$classPtr]['scope_closer'] ?? null;
        $ptr = $classPtr;

        while (($ptr = $phpcsFile->findNext(T_STRING, ($ptr + 1), $end)) !== false) {
            $method = strtolower($tokens[$ptr]['content']);

            if (in_array($method, self::PREVENTION_METHODS, true) === false) {
                continue;
            }

            // A T_STRING inside a class body always has a preceding non-empty
            // token — the class keyword at the very least — so findPrevious()
            // cannot fail here and needs no guard.
            $operatorPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if ($tokens[$operatorPtr]['code'] !== T_DOUBLE_COLON) {
                continue;
            }

            $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $end, true);

            if ($afterPtr !== false && $tokens[$afterPtr]['code'] === T_OPEN_PARENTHESIS) {
                return true;
            }
        }

        return false;
    }
}

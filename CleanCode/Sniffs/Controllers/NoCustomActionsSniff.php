<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Controllers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a public method in a *Controller class that is neither a Laravel
 * resource action nor an accepted magic method.
 *
 * Partial enforcement of the "Controllers: No Business Logic" standard
 * (docs/standards/controllers-no-business-logic.md). The standard's core rule
 * — controllers only move a request to a response — is a semantic judgement no
 * token scan can make, but its second rule is narrower: controllers are RESTful
 * or invokable, with no custom actions. A class name, a method name, and a
 * visibility modifier are all plain single-file tokens, so that slice is
 * decidable.
 *
 * Only public methods declared directly in the controller's own body are
 * actions. A method of an anonymous class, or a named function, written inside
 * an action belongs to that nested declaration and is left alone.
 *
 * Warning severity, not error. The check is convention-based (see below), so it
 * must not fail a consumer's build on a class it has merely misread.
 *
 * Boundaries — accepted, by design:
 *
 * - Convention-dependent. Only a class whose name ends in `Controller` is
 *   examined, so a routed controller named otherwise is missed. The naming
 *   convention is itself part of the standard.
 * - Routing is invisible. The sniff cannot prove the class is routed, so a
 *   plain class suffixed `Controller` is examined as if it were one.
 * - Traits are invisible. A method mixed in by a trait is declared in another
 *   file, and a sniff reads one file at a time. An interface or trait whose
 *   own name ends in `Controller` is not examined either: only T_CLASS is
 *   registered, an interface method is a contract rather than a route, and a
 *   trait's methods belong to whichever class mixes them in.
 * - Protected and private methods are never flagged. The rule speaks about
 *   routable actions, and a routable action has to be public.
 * - An unterminated class body is passed over in silence. PHPCS resolves no
 *   scope for it, so nothing can be attributed to the class (see
 *   actionPointers()).
 */
class NoCustomActionsSniff implements Sniff
{
    /**
     * Extra method names this controller may declare, on top of the resource
     * actions and magic methods below. Configurable from a ruleset via
     * <property name="allowedMethods" type="array" .../> for framework hooks
     * such as Laravel 11's HasMiddleware::middleware().
     *
     * Empty by default: nothing beyond the standard's own list is allowed
     * until a consuming ruleset says so.
     *
     * @var array<string>
     */
    public array $allowedMethods = [];

    /**
     * The seven Laravel resource actions plus the two accepted magic methods,
     * lowercased for comparison. PHP method names are case-insensitive.
     */
    private const ALWAYS_ALLOWED = [
        '__construct',
        '__invoke',
        'create',
        'destroy',
        'edit',
        'index',
        'show',
        'store',
        'update',
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
        $className = $phpcsFile->getDeclarationName($stackPtr);

        if ($className === null || $this->isController($className) === false) {
            return;
        }

        foreach ($this->actionPointers($phpcsFile, $stackPtr) as $actionPtr) {
            $this->reportCustomAction($phpcsFile, $actionPtr);
        }
    }

    private function isController(string $className): bool
    {
        return str_ends_with(strtolower($className), 'controller');
    }

    /**
     * Every public method declared directly in the class's own body.
     *
     * Membership is decided by the innermost enclosing scope, not by position.
     * A method of an anonymous class, or a named function, written inside an
     * action carries that declaration as its innermost condition rather than
     * the class, so it is not an action of this controller. The class's
     * scope_closer only bounds the scan.
     *
     * An unterminated class body yields nothing. PHPCS builds no scope map for
     * a class it never sees closed — no opener, no closer, and an empty
     * conditions list on the methods written inside it — so no method resolves
     * to this class and the sniff stays silent. That is the safe answer for a
     * file whose structure the tokenizer could not resolve.
     *
     * @return array<int, int>
     */
    private function actionPointers(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$classPtr]['scope_closer'] ?? null;
        $pointers = [];
        $ptr = $classPtr;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $end)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            if ($phpcsFile->getMethodProperties($ptr)['scope'] !== 'public') {
                continue;
            }

            $pointers[] = $ptr;
        }

        return $pointers;
    }

    /**
     * A method declaration PHPCS hands over mid-edit can have no name yet, so
     * there is nothing to judge and nothing to report.
     */
    private function reportCustomAction(File $phpcsFile, int $actionPtr): void
    {
        $method = $phpcsFile->getDeclarationName($actionPtr);

        if ($method === null || $this->isAllowed($method) === true) {
            return;
        }

        $phpcsFile->addWarning(
            'Public method %s() is not a RESTful resource action; a controller should be '
                . 'RESTful or invokable, so extract the custom action into its own controller '
                . '(see docs/standards/controllers-no-business-logic.md)',
            $actionPtr,
            'Found',
            [$method]
        );
    }

    private function isAllowed(string $method): bool
    {
        $method = strtolower($method);

        if (in_array($method, self::ALWAYS_ALLOWED, true) === true) {
            return true;
        }

        return in_array($method, array_map('strtolower', $this->allowedMethods), true);
    }
}

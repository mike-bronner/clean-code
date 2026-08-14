<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Controversial;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's Controversial/Superglobals rule
 * (docs/phpmd/controversial-superglobals.md).
 *
 * Reading a superglobal directly couples the code to PHP's request environment:
 * the value cannot be substituted in a test, its shape is unvalidated, and every
 * caller has to trust that whatever wrote it did so correctly. A framework's
 * request object encapsulates all three concerns, so the remedy is to inject it
 * rather than reach for the global.
 *
 * ## What is flagged
 *
 * The sixteen names PHPMD 2.15.0 carries in its own `$superglobals` list — the
 * nine superglobals PHP 8 actually defines:
 *
 *   $GLOBALS, $_SERVER, $_GET, $_POST, $_FILES, $_COOKIE, $_SESSION,
 *   $_REQUEST, $_ENV
 *
 * plus the seven PHP 4 long-form aliases (`register_long_arrays`) that PHP 5.4
 * removed but PHPMD still reports:
 *
 *   $HTTP_SERVER_VARS, $HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_POST_FILES,
 *   $HTTP_COOKIE_VARS, $HTTP_SESSION_VARS, $HTTP_ENV_VARS
 *
 * The aliases are kept for parity. On PHP 8 they are ordinary undefined
 * variables rather than superglobals, so the diagnostic is arguably even more
 * useful there than PHPMD's: code still reading `$HTTP_POST_VARS` is not merely
 * coupled to the request environment, it is reading nothing at all.
 *
 * Matching is case sensitive, because PHP variable names are: `$_get` is a
 * different variable from `$_GET` and neither PHPMD nor this sniff reports it.
 *
 * Both spellings of an access are covered — the bare variable, and the
 * interpolated form inside a double-quoted string or a heredoc, which PHPMD
 * reports too. A nowdoc and a single-quoted string do not interpolate, so
 * neither is registered.
 *
 * ## What is not flagged
 *
 * - A *declaration* of a class member that happens to carry a superglobal's
 *   name (`public $_GET = [];`). It declares a property; it does not read the
 *   superglobal. PHPMD is silent on it too. The PHP 8 promoted-constructor
 *   spelling declares the same property from the parameter list and is exempt
 *   with it. Only a long-form alias can be written that way: PHP refuses to
 *   compile a parameter named after one of the nine real superglobals,
 *   promoted or not.
 *
 *   A *plain* parameter is not exempt, even though PHPCS gives it the same
 *   innermost condition as a member declared in the class body: a parameter
 *   list opens no scope of its own. It declares no property, so it is reported
 *   exactly as the same parameter in a global function is. isPlainParameter()
 *   is what tells the two apart.
 * - A static property access spelled `self::$_POST` or `Holder::$_POST`. The
 *   `::` fixes the name to a member of that class, so no superglobal is reached.
 *   This is the one shape where the sniff is deliberately *narrower* than
 *   PHPMD, whose parser matches the variable's image without looking left.
 * - An object property read as `$request->_GET`, which PHPCS tokenises as an
 *   identifier rather than a variable.
 *
 * ## Configuration
 *
 * None. PHPMD's rule has no threshold and no configurable property, so there is
 * nothing to tune and this sniff exposes no public property either.
 *
 * Detection only, matching PHPMD: replacing a superglobal read with an injected
 * request abstraction depends on which abstraction the project uses and on what
 * the value is for, so there is no safe mechanical rewrite.
 */
class SuperglobalsSniff implements Sniff
{
    /**
     * The sixteen names PHPMD 2.15.0's Controversial/Superglobals rule carries,
     * in its own order: the nine PHP 8 superglobals followed by the seven PHP 4
     * long-form aliases.
     */
    private const SUPERGLOBALS = [
        '$GLOBALS',
        '$_SERVER',
        '$HTTP_SERVER_VARS',
        '$_GET',
        '$HTTP_GET_VARS',
        '$_POST',
        '$HTTP_POST_VARS',
        '$_FILES',
        '$HTTP_POST_FILES',
        '$_COOKIE',
        '$HTTP_COOKIE_VARS',
        '$_SESSION',
        '$HTTP_SESSION_VARS',
        '$_REQUEST',
        '$_ENV',
        '$HTTP_ENV_VARS',
    ];

    /**
     * The one message code every violation carries, so a consuming ruleset can
     * silence the rule with a single <exclude>.
     */
    private const CODE = 'Found';

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_VARIABLE, T_DOUBLE_QUOTED_STRING, T_HEREDOC];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        if ($token['code'] === T_VARIABLE) {
            $this->processVariable($phpcsFile, $stackPtr);

            return;
        }

        $this->processInterpolation($phpcsFile, $stackPtr, $token['content']);
    }

    /**
     * Reports a bare superglobal variable, unless it is a member declaration or
     * a static property access — neither of which reads the superglobal.
     */
    private function processVariable(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $name = $tokens[$stackPtr]['content'];

        if (in_array($name, self::SUPERGLOBALS, true) === false) {
            return;
        }

        // A variable whose innermost enclosing scope is a class-like body is a
        // property declaration, not a read: `public $_GET = [];` names a member.
        // A read inside a method has the method as its innermost condition.
        //
        // A parameter list opens no scope of its own — PHPCS starts the
        // method's scope at its `{` — so a parameter arrives here carrying the
        // class as its innermost condition, indistinguishable by position from
        // a member declared in the class body. Position alone is therefore not
        // enough: only a *promoted* parameter declares a property, and a plain
        // one is an ordinary local binding that must be reported exactly as the
        // same parameter in a global function is.
        $conditions = $tokens[$stackPtr]['conditions'];

        if (
            $conditions !== []
            && in_array(end($conditions), Tokens::$ooScopeTokens, true) === true
            && $this->isPlainParameter($phpcsFile, $stackPtr) === false
        ) {
            return;
        }

        // `self::$_POST` and `Holder::$_POST` resolve to a static property of
        // that class; the name never reaches the superglobal. `$request->$_GET`
        // is not exempt for the same reason — there the variable *is* read, to
        // supply the property name.
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($previous !== false && $tokens[$previous]['code'] === T_DOUBLE_COLON) {
            return;
        }

        $this->report($phpcsFile, $stackPtr, $name);
    }

    /**
     * Whether the variable is a plain — that is, non-promoted — parameter of a
     * method, which the class-scope exemption must not cover.
     *
     * The parenthesis test is PHP_CodeSniffer's own, from getMemberProperties()
     * (Files/File.php): take the innermost pair the token sits in and ask
     * whether a function owns it. That helper is not called directly here
     * because it answers by throwing, and its parse-error branch would emit a
     * warning under this sniff's code.
     *
     * Promotion is the whole distinction, and it is why this returns "plain
     * parameter" rather than "parameter": `__construct(public $HTTP_GET_VARS =
     * [])` declares the property from the parameter list, so it is the same
     * declaration as `public $HTTP_GET_VARS = [];` written in the class body
     * and is exempt with it. A plain parameter declares nothing. Only a
     * promoted parameter carries property_visibility, so getMethodParameters()
     * is what separates the two — the same technique the sibling
     * DisallowAlwaysOnEagerLoading sniff uses against this same PHPCS
     * ambiguity, which bit that sniff in the opposite direction.
     *
     * Which guards here are load-bearing, by mutation:
     *
     * - The nested_parenthesis test is load-bearing. A member declared in the
     *   class body carries no such key at all, so dropping the test hands
     *   array_keys() a null and raises "Argument #1 ($array) must be of type
     *   array, null given" on every property fixture.
     * - The $ownerPtr and T_FUNCTION tests are defensive only, and removing
     *   them leaves the suite green. A parameter default must be a constant
     *   expression, so no variable but a parameter's own name token can stand
     *   inside a parameter list, and a closure or arrow function in a class is
     *   written inside a method body, whose own scope has opened by then. They
     *   are kept because they are what makes the getMethodParameters() call
     *   provably safe: that helper throws on a token that is not a function.
     *   This is how the sibling DisallowAlwaysOnEagerLoading sniff treats its
     *   own defensive guard.
     */
    private function isPlainParameter(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $openers = array_keys($tokens[$stackPtr]['nested_parenthesis']);
        $ownerPtr = $tokens[array_pop($openers)]['parenthesis_owner'] ?? null;

        if ($ownerPtr === null || $tokens[$ownerPtr]['code'] !== T_FUNCTION) {
            return false;
        }

        foreach ($phpcsFile->getMethodParameters($ownerPtr) as $parameter) {
            if ($parameter['token'] === $stackPtr && isset($parameter['property_visibility']) === true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reports every superglobal interpolated into a double-quoted string or a
     * heredoc line. PHPCS emits one T_HEREDOC token per line, so reporting at
     * the token keeps the line exact even for a multi-line heredoc.
     */
    private function processInterpolation(File $phpcsFile, int $stackPtr, string $content): void
    {
        if (preg_match_all($this->interpolationPattern(), $content, $matches) === 0) {
            return;
        }

        foreach ($matches['name'] as $name) {
            $this->report($phpcsFile, $stackPtr, '$' . $name);
        }
    }

    /**
     * Matches an interpolated superglobal: `$_GET`, `{$_GET['k']}`, `${_GET}`.
     *
     * The leading `(?<!\\)(?:\\\\)*` consumes a complete run of escape pairs so
     * that `\$_GET` — where the backslash cancels the interpolation — does not
     * match, while `\\$_GET` — an escaped backslash followed by a live
     * interpolation — still does. A trailing \b stops a longer name that merely
     * starts with a superglobal's spelling from matching.
     *
     * Both parities are pinned by
     * tests/fixtures/SuperglobalsSniff/escape-pairs.php, which carries runs of
     * one through four backslashes; PHP and PHPMD agree with the sniff on all
     * four.
     */
    private function interpolationPattern(): string
    {
        $names = array_map(
            static fn (string $superglobal): string => preg_quote(ltrim($superglobal, '$'), '/'),
            self::SUPERGLOBALS
        );

        return '/(?<!\\\\)(?:\\\\\\\\)*\K\$\{?(?P<name>' . implode('|', $names) . ')\b/';
    }

    private function report(File $phpcsFile, int $stackPtr, string $name): void
    {
        $phpcsFile->addError(
            'Superglobal %s must not be accessed directly; inject the framework request abstraction instead',
            $stackPtr,
            self::CODE,
            [$name]
        );
    }
}

<?php

/**
 * The sniff's whole silence contract. Every block below contains a construct
 * the sniff registers on — a variable, a double-quoted string, a heredoc — in
 * a form it must leave alone, so this fixture keeps discriminating even if the
 * sniff stopped reporting entirely. PHPMD 2.15.0 is silent on all of it too.
 */

class RequestHandler
{
    /**
     * A member declaration that happens to carry a superglobal's name. It
     * declares a property; nothing reads the superglobal. Slevomat's
     * DisallowSuperGlobalVariable reports both of these, which is one of the
     * two reasons it could not be wired in instead of this sniff.
     */
    public $_GET = [];

    public static $_POST = [];

    /**
     * The PHP 8 spelling of the same declaration: a promoted constructor
     * parameter declares the property from the parameter list, so it is the
     * same member declaration as the two above and is exempt with them. Both
     * the typed and the untyped spelling are here, because promotion allows
     * either.
     *
     * Position is not what earns the exemption. A parameter list opens no
     * scope of its own, so a *plain* parameter reaches the sniff carrying this
     * same class condition while declaring nothing at all — and it is
     * reported. parameters.php carries both verdicts side by side; what
     * separates them is property_visibility, not the condition stack.
     *
     * The names are long-form aliases rather than `$_GET` and `$_POST`
     * because a parameter cannot be named after one of the nine real
     * superglobals at all: PHP rejects `function f($_GET)` outright with
     * "Cannot re-assign auto-global variable", promoted or not. The seven
     * PHP 4 aliases are ordinary variables on PHP 8, so they are the only half
     * of the sniff's name list this shape can be written in at all. PHPMD is
     * silent here too.
     */
    public function __construct(public $HTTP_GET_VARS = [], protected array $HTTP_POST_VARS = [])
    {
    }

    public function encapsulated($request): array
    {
        // The remedy the rule asks for: the request arrives injected.
        $query = $request->query();
        $body = $request->input('name');

        // An object property whose name matches a superglobal. PHPCS tokenises
        // the name after -> as an identifier, never as a variable.
        $viaProperty = $request->_GET;
        $viaNullsafe = $request?->_SERVER;

        return [$query, $body, $viaProperty, $viaNullsafe];
    }

    public function nearMissNames(): array
    {
        // PHP variable names are case sensitive, and so is the rule.
        $_get = 'lower case is a different variable';
        $_post = 'and so is this';

        // A name that merely starts with a superglobal's spelling.
        $_GETTER = 'not $_GET';
        $_ENVIRONMENT = 'not $_ENV';

        return [$_get, $_post, $_GETTER, $_ENVIRONMENT];
    }

    public function nonInterpolatingStrings(): array
    {
        // Single quotes never interpolate.
        $single = 'reads $_GET literally';

        // A backslash cancels the interpolation, so no superglobal is read.
        $escaped = "reads \$_POST literally";

        // A nowdoc is the heredoc that does not interpolate.
        $nowdoc = <<<'TXT'
            reads $_SESSION and $_COOKIE literally
            TXT;

        // A heredoc that interpolates something harmless still has to be
        // walked, and still has to come back clean.
        $name = 'world';
        $heredoc = <<<TXT
            hello $name, no superglobal here
            TXT;

        // Longer names inside a string, guarded by the word boundary.
        $longerName = "$_GETTER and $_ENVIRONMENT";

        return [$single, $escaped, $nowdoc, $heredoc, $longerName];
    }
}

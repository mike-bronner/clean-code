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

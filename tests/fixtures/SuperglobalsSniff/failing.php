<?php

/**
 * Every shape the sniff flags, kept inside a function or a method so that the
 * parity claim in tests/Standards/SuperglobalsTest.php holds against PHPMD:
 * PHPMD's rule is MethodAware and FunctionAware, so it never inspects file
 * scope. The shapes where the two tools disagree live in divergences.php.
 */

function modernSuperglobals(): array
{
    return [
        $GLOBALS['a'],
        $_SERVER['b'],
        $_GET['c'],
        $_POST['d'],
        $_FILES['e'],
        $_COOKIE['f'],
        $_SESSION['g'],
        $_REQUEST['h'],
        $_ENV['i'],
    ];
}

function legacyLongFormAliases(): array
{
    return [
        $HTTP_SERVER_VARS['a'],
        $HTTP_GET_VARS['b'],
        $HTTP_POST_VARS['c'],
        $HTTP_POST_FILES['d'],
        $HTTP_COOKIE_VARS['e'],
        $HTTP_SESSION_VARS['f'],
        $HTTP_ENV_VARS['g'],
    ];
}

class RequestReader
{
    public function interpolated(): array
    {
        $bare = "host is $_SERVER[HTTP_HOST]";
        $braced = "name is {$_GET['name']}";
        $twicePerString = "$_POST then $_FILES";
        $liveBesideEscaped = "live $_COOKIE, literal \$_SESSION";
        $heredoc = <<<TXT
            first $_REQUEST
            second $_ENV
            TXT;

        return [$bare, $braced, $twicePerString, $liveBesideEscaped, $heredoc];
    }

    public function indirect(): array
    {
        $variableVariable = $$_GET;
        $propertyName = $this->{$_POST['field']};

        return [$variableVariable, $propertyName];
    }

    public function shellOut(): string
    {
        // Backticks interpolate like a double-quoted string, but PHPCS hands
        // the superglobal over as a plain variable rather than as part of a
        // string token, so this reaches the sniff by the bare-variable path.
        return `ls $_SERVER[PWD]`;
    }
}

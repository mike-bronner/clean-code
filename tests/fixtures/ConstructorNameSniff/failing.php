<?php

/**
 * The parity set: every shape here is reported by BOTH
 * Generic.NamingConventions.ConstructorName and PHPMD 2.15.0's
 * Naming/ConstructorWithNameAsEnclosingClass, at the same lines.
 *
 * The file stays in the global namespace deliberately. PHPMD's rule returns
 * early unless getNamespaceName() is '+global', so a namespaced class here
 * would break the parity claim this fixture exists to make; that divergence is
 * carried by divergences.php instead.
 */

declare(strict_types=1);

class PlainOldStyle
{
    public function PlainOldStyle()
    {
    }
}

abstract class AbstractOldStyle
{
    public function AbstractOldStyle()
    {
    }
}

/**
 * Both tools compare names case-insensitively — the sniff lowercases both
 * sides (ConstructorNameSniff.php), PHPMD uses strcasecmp() — so a method
 * differing from its class only in case is still a PHP4 constructor.
 */
class MixedCaseName
{
    public function MIXEDCASENAME()
    {
    }
}

<?php

/**
 * The divergence where THIS RULESET IS STRICTER than PHPMD 2.15.0: a PHP4-style
 * constructor inside a namespaced class.
 *
 * The sniff flags it; PHPMD does not, because
 * ConstructorWithNameAsEnclosingClass::apply() returns early unless
 * getNamespaceName() === '+global'. PHPMD's silence is deliberate — a
 * same-named method in a namespaced class was never treated as a constructor by
 * PHP itself — which is exactly why the extra report is worth keeping: the code
 * reads as a constructor and is not one.
 *
 * It lives in its own file because the namespace guard is file-wide: any other
 * divergence parked here would be masked by it and would look like agreement.
 * The PHPMD-only divergences are in phpmd-only-divergences.php.
 */

declare(strict_types=1);

namespace MikeBronner\Fixtures\ConstructorName;

class NamespacedOldStyle
{
    public function NamespacedOldStyle()
    {
    }
}

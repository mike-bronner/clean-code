<?php

declare(strict_types=1);

/**
 * `namespace\` resolves against the namespace block the call sits in, not
 * against the file. `compact()` is what makes that observable here: reaching
 * PHP's own compact() exempts the parameter its argument names, and reaching
 * somebody else's function of the same short name does not, so the two blocks
 * below give the identical line opposite verdicts.
 *
 * The hand-rolled copy this sniff used to carry stepped over the qualifier and
 * matched the short name, exactly as PHPMD does, so it read both lines as PHP's
 * own compact() and exempted both — measured on the pre-#320 sniff restored
 * from `main`, which reports neither line here. A sniff reading every
 * `namespace\`-qualified name as never-global would report both instead. Only
 * resolving against the enclosing block reports exactly the second.
 *
 * Only a braced block can be unnamed, and only a braced file can hold a second
 * block to contrast it with, so this case has no unbraced spelling and cannot
 * be folded into passing.php or divergences.php.
 *
 * tests/Standards/UnusedFormalParameterTest.php holds the assertion.
 */

namespace {
    // The unnamed block *is* the global namespace, so `namespace\compact()` is
    // PHP's own compact() written the long way round. It brings $unusedA into
    // the returned array, so the parameter is read and is not reported.
    function relativeCompactInTheGlobalBlock(string $unusedA): array
    {
        return namespace\compact('unusedA');
    }
}

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedFormalParameter\Relative {
    // The identical line under a named block reaches this namespace's own
    // compact(), which has no obligation to bring anything into scope, so
    // $unusedB really is dead and is reported.
    function relativeCompactInANamedBlock(string $unusedB): array
    {
        return namespace\compact('unusedB');
    }
}

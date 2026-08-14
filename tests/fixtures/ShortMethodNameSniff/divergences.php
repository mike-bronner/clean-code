<?php

/**
 * The one shape where this sniff reports and phpmd does not: a method of an
 * anonymous class.
 *
 * pdepend, which builds the tree phpmd walks, produces no method node for the
 * body of `new class { … }`. ShortMethodName is a MethodAware / FunctionAware
 * rule, so it is never handed one and stays silent whatever the threshold.
 * Verified against phpmd 2.15 with rulesets/naming.xml/ShortMethodName: this
 * file produces no output and exit code 0, while phpcs reports both methods.
 *
 * Both are reported here on purpose. This ruleset exists so phpmd does not
 * have to run, and reporting more than phpmd never leaves a real violation
 * unreported; the reverse would. Both names really are below the minimum, so
 * neither report is a false positive — only a report phpmd's tree cannot
 * reach.
 *
 * A function declared inside a method body is *not* a divergence, despite
 * looking like the same kind of nesting: pdepend does collect it, and both
 * tools report it. It is pinned as ordinary parity in failing.php instead.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortMethodName;

$anonymous = new class {
    public function zz(): void
    {
    }
};

class Holder
{
    public function outer(): void
    {
        $inner = new class {
            public function yy(): void
            {
            }
        };
    }
}

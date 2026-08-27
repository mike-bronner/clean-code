<?php

/**
 * Two different classes called Same, in two braced namespace blocks of one
 * file. Legal PHP, and the shape a short-name lookup cannot survive: the second
 * Same overwrote the first, so the sniff read Second\Same's count back for both
 * declarations and First\Same's three children were unreachable.
 *
 * The counts differ on purpose — three in the first block, two in the second —
 * so a test can tell which class each report is really about rather than only
 * that a report happened.
 */

declare(strict_types=1);

namespace Fixture\Namespaces\First {
    class Same
    {
    }

    class FirstOne extends Same
    {
    }

    class FirstTwo extends Same
    {
    }

    class FirstThree extends Same
    {
    }
}

namespace Fixture\Namespaces\Second {
    class Same
    {
    }

    class SecondOne extends Same
    {
    }

    class SecondTwo extends Same
    {
    }
}

<?php

namespace Fixtures\NoLogic;

// A free function named __construct is legal PHP but is not a class
// constructor. Its body is ordinary code and must never be inspected by the
// NoLogic sniff — the OO-scope guard skips it (issue #40).
function __construct(int $a): void
{
    doSomething($a);

    if ($a > 0) {
        echo $a;
    }
}

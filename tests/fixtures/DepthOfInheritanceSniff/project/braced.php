<?php

/**
 * A braced namespace, whose import sits one brace deep. Read as a trait `use`
 * instead of an import, the parent would go unresolved and the count would
 * collapse to 2.
 */

declare(strict_types=1);

namespace Project\Braced {
    use Project\Leaf\Leaf3;

    class Braced1 extends Leaf3
    {
    }
}

<?php

/**
 * The other interpolation spelling, `${expr}`, in the shape that shows what a
 * miscounted brace costs across a namespace boundary.
 *
 * T_DOLLAR_OPEN_CURLY_BRACES opens where a bare `}` closes, exactly as
 * T_CURLY_OPEN does. Left uncounted, the first braced namespace here closes a
 * level early, the import in the second is read as a trait `use` and dropped,
 * and `Deepest` resolves to a name nothing declares — eight parents collapsing
 * to the two an unseen parent weighs.
 *
 * The spelling is deprecated as of PHP 8.2 and still accepted throughout the
 * 8.x line this package supports, which is the reason to keep reading it: the
 * lexer hands it over whether or not the source should still be written that
 * way.
 */

declare(strict_types=1);

namespace Project\Interpolated {
    class Legacy
    {
        public function render(string $value): string
        {
            return "value: ${value}";
        }
    }
}

namespace Project\Deeper {
    use Project\Braced\Braced1;

    class Deepest extends Braced1
    {
    }
}

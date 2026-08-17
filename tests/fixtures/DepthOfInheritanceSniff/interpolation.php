<?php

/**
 * A `{$expr}` interpolation, and the resolution that depends on it being
 * counted.
 *
 * PHP's lexer hands the opening brace of an interpolation over as a token
 * (T_CURLY_OPEN) while its closing `}` arrives bare, so a reader that counts
 * only bare braces loses a level on every interpolated string. Here that loss
 * would put `Consumer`'s trait `use` at namespace level, where it reads as an
 * import binding the short name `Base5` — and `Deep extends Base5` would then
 * resolve to the trait in `Support` rather than to the six-deep class beside
 * it, leaving a real violation unreported.
 *
 * `Deep` is the whole assertion: it is reported only when the interpolation is
 * counted.
 */

declare(strict_types=1);

namespace Fixture\Interpolation\Support;

trait Base5
{
}

namespace Fixture\Interpolation;

class Base0
{
}

class Base1 extends Base0
{
}

class Base2 extends Base1
{
}

class Base3 extends Base2
{
}

class Base4 extends Base3
{
}

class Base5 extends Base4
{
}

class Formatter
{
    public function render(string $value): string
    {
        return "value: {$value}";
    }
}

class Consumer
{
    use \Fixture\Interpolation\Support\Base5;
}

class Deep extends Base5
{
}

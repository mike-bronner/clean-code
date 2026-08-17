<?php

/**
 * Code the sniff must flag, at the shipped threshold of 6.
 *
 * Three shapes reach it, and each reaches it a different way:
 *
 * - `Level6` and `Level7` sit on top of a chain declared entirely in this
 *   file, so every parent above them is resolved and weighs 1.
 * - `Grafted` has only four resolved ancestors, but the chain ends at a name
 *   declared nowhere in the analysed set. PDepend weighs a parent it never saw
 *   declared twice over, so 4 + 2 is already the threshold — the shape that
 *   makes a framework's base class reachable from ordinary application code.
 * - `SplitLine` shares its physical line with the class it extends, which is
 *   what pins the report to the right declaration when a line holds two.
 *
 * The report sits on the first modifier when a class carries one, so
 * `Level7`'s error is on its `abstract` keyword and not on the `class`
 * keyword below it, nor on the attribute above it.
 */

declare(strict_types=1);

namespace Fail;

class Level0
{
}

class Level1 extends Level0
{
}

class Level2 extends Level1
{
}

class Level3 extends Level2
{
}

class Level4 extends Level3
{
}

class Level5 extends Level4
{
}

// Six parents — the threshold is inclusive, so this is already a violation.
class Level6 extends Level5
{
}

#[\Attribute]
abstract
class
Level7
    extends
    Level6
{
}

class Graft0 extends \Vendor\Framework\Model
{
}

class Graft1 extends Graft0
{
}

class Graft2 extends Graft1
{
}

class Graft3 extends Graft2
{
}

// Four resolved parents plus an unseen one, weighed twice: six.
class Grafted extends Graft3
{
}

class SplitBase extends Level5 {} class SplitLine extends SplitBase {}

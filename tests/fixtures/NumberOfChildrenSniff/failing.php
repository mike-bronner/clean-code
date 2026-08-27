<?php

declare(strict_types=1);

namespace Fixture\Failing;

/**
 * Fifteen direct children, which is the default threshold exactly. PHPMD
 * reports at `>=`, so this is a violation and passing.php's fourteen is not.
 */
abstract class Base
{
}

class Child1 extends Base
{
}

class Child2 extends Base
{
}

class Child3 extends Base
{
}

class Child4 extends Base
{
}

class Child5 extends Base
{
}

class Child6 extends Base
{
}

class Child7 extends Base
{
}

class Child8 extends Base
{
}

class Child9 extends Base
{
}

class Child10 extends Base
{
}

class Child11 extends Base
{
}

class Child12 extends Base
{
}

class Child13 extends Base
{
}

class Child14 extends Base
{
}

class Child15 extends Base
{
}

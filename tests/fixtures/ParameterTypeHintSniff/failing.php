<?php

declare(strict_types=1);

// Violation: an ordinary method with no ancestor constraining it.
class PlainClass
{
    public function untyped($value): bool
    {
        return $value !== null;
    }
}

// Silent: PHP's php_user_filter declares filter()'s first parameters untyped,
// so writing a hint on them is a fatal narrowing error.
class InheritedFromUntypedAncestor extends php_user_filter
{
    public function onClose(): void
    {
        echo 'closed';
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        return PSFS_PASS_ON;
    }

    // Violation: the ancestor is loadable and does not declare this method, so
    // it constrains nothing and the hint can be written. Sharing the class with
    // filter() above is the point — the skip is per declaration, not per class.
    public function helper($value): bool
    {
        return $value !== null;
    }
}

// Violation: an ancestor that cannot be resolved during a lint run answers
// nothing, so the read is reported exactly as it was before.
class InheritedFromUnresolvableParent extends \Vendor\Absent\BaseClass
{
    public function handle($value): bool
    {
        return $value !== null;
    }
}

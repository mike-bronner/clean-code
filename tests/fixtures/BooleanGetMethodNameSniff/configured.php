<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\BooleanGetMethodName;

class Subscription
{
    /**
     * Parameterless: reported whichever way `checkParameterizedMethods` is set.
     *
     * @return bool
     */
    public function getActive()
    {
        return true;
    }

    /**
     * One required parameter: reported by default, exempt once
     * `checkParameterizedMethods` is on.
     *
     * @return bool
     */
    public function getBillable(string $plan)
    {
        return $plan !== '';
    }

    /**
     * A parameter with a default value still counts as a parameter — PHPMD asks
     * PDepend for the parameter *count*, not for the required ones.
     *
     * @return bool
     */
    public function getRenewable(int $months = 12)
    {
        return $months > 0;
    }

    /**
     * A variadic is a parameter too, by the same count.
     *
     * @return bool
     */
    public function getCancellable(string ...$reasons)
    {
        return $reasons !== [];
    }
}

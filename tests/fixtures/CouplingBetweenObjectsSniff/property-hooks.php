<?php

/**
 * A PHP 8.4 property hook. PHP_CodeSniffer opens no scope for a hook body, so
 * every variable written inside one reports the class as its innermost
 * condition — exactly as a declared property does.
 */

declare(strict_types=1);

namespace Hook;

class HookedProperty
{
    public \Hook\Dep\Held $value {
        get {
            $local = new \Hook\Dep\Made();

            return $local->held;
        }
        set (\Hook\Dep\Incoming $incoming) {
            $this->value = $incoming->held;
        }
    }

    private \Hook\Dep\Plain $plain;

    public function m(): void
    {
        \Hook\Dep\Called::go();
    }
}

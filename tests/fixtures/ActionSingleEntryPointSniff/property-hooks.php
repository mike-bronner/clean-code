<?php

namespace App\Actions;

// PHP 8.4 property hooks. PHP_CodeSniffer opens no scope for a hook body, so a
// named function declared inside one carries the *class* as its innermost
// enclosing condition — indistinguishable from a real method to any check that
// reads a declaration's `conditions`. Only the structural walk keeps it out.
//
// This class declares exactly one public entry point, `__invoke()`. It must
// stay silent.
class PriceOrder
{
    private float $net = 0.0;

    public float $gross {
        get => $this->net * 1.2;
        set (float $value) {
            function roundToCents(float $amount): float
            {
                return round($amount, 2);
            }

            $this->net = roundToCents($value / 1.2);
        }
    }

    public string $label {
        get {
            function formatLabel(float $amount): string
            {
                return number_format($amount, 2);
            }

            return formatLabel($this->net);
        }
    }

    public function __invoke(Order $order): float
    {
        return $this->gross;
    }
}

// The same hooks alongside a genuine second entry point, so the assertion is
// two-sided: `refund()` is reported, and neither hook helper is.
class ChargeOrder
{
    private float $net = 0.0;

    public float $gross {
        get => $this->net * 1.2;
        set (float $value) {
            function roundChargeToCents(float $amount): float
            {
                return round($amount, 2);
            }

            $this->net = roundChargeToCents($value / 1.2);
        }
    }

    public function __invoke(Order $order): float
    {
        return $this->gross;
    }

    public function refund(Order $order): float
    {
        return -$this->gross;
    }
}

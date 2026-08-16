<?php

declare(strict_types=1);

/**
 * PHP 8.4 property hooks. PHP_CodeSniffer opens no scope for a hook body, so
 * every parameter and local written inside one reaches the class-body walk
 * looking class-scoped — which is why that walk keeps only statements that
 * actually open with a visibility or property modifier.
 *
 * There is no PHPMD verdict to compare against: PHPMD 2.15.0 cannot read this
 * file at all, failing with "Unexpected token: {" at the first hook. So this is
 * a gap rather than a divergence, and the fixture pins what the sniff does
 * rather than a parity claim.
 *
 * Three names here are 21 bytes, one over the default maximum, and only one of
 * them is a field. The hook local is declared *before* the field that shares
 * its name deliberately: a walk that collected hook bodies would report the
 * local on line 30 and then de-duplicate the real field on line 46 away
 * entirely, so the field's report is what proves the filter works.
 */
class TemperatureConversion
{
    /**
     * A hooked property is still a property declaration, so it is measured like
     * any other field.
     */
    public string $formattedTemperatures {
        get {
            $celsiusDegreeReadings = 12.5;

            return (string) $celsiusDegreeReadings;
        }
    }

    /**
     * The hook's own parameter is not a field. The property name is short, so
     * the only 21-byte name in this block is the one that must stay silent.
     */
    public float $fahrenheitDegrees {
        set (float $incomingDegreeReading) {
            $this->celsiusDegreeReadings = $incomingDegreeReading;
        }
    }

    private float $celsiusDegreeReadings = 0.0;
}

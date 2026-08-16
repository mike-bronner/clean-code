<?php

/**
 * PHP 8.4 property hooks, which PHP_CodeSniffer gives no scope of their own:
 * every `$this`, hook parameter, and hook local inside one arrives at the
 * property walk with the class as its innermost condition, and the locals with
 * no enclosing parentheses either.
 *
 * The two halves are asserted together because either alone proves nothing. The
 * first class is compliant and its hook bodies are full of names that would
 * report if they were read as properties; the second and third are misordered
 * on the hooked property itself, which a sniff skipping hooked properties
 * outright would stay silent on.
 */

declare(strict_types=1);

class HookedModel extends Model
{
    public string $alpha = '';

    public string $beta {
        get => $this->alpha;
        set (string $zulu) {
            $aardvark = $zulu;
            $this->alpha = $aardvark;
        }
    }

    private string $zulu = '';
}

class MisorderedHookedModel extends Model
{
    public string $zulu {
        get => '';
    }

    public string $alpha = '';
}

// The same shape inside an anonymous class, where both fixes have to hold at
// once: the class is reached only through T_ANON_CLASS, and the hook body only
// stays out through the declaration test.
$anonymous = new class extends Model {
    public string $zulu {
        get => '';
    }

    public string $alpha = '';
};

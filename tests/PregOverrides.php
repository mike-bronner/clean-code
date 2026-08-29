<?php

/**
 * The mockable call boundary PregFailure arms.
 *
 * PHP resolves an unqualified call to a global function from inside a
 * namespace against that namespace first, and only then against the global
 * one. Every sniff in this package calls `preg_replace()`,
 * `preg_replace_callback()`, `preg_split()` and `preg_match_all()`
 * unqualified, so declaring same-named functions in the sniffs' own
 * namespaces puts a seam in front of each call without the sniffs knowing
 * about it. Production never sees this file: Composer's autoloader cannot
 * reach it, and only tests/bootstrap.php requires it.
 *
 * Each override is a straight pass-through until PregFailure arms it, so the
 * 2,600 tests that know nothing about this file keep exercising real PCRE.
 * When armed, it reports the failure the way PHP reports it — null from the
 * two replace functions, false from the two reading ones.
 *
 * `preg_match_all()` runs the real call first and then reports false. That is
 * deliberate and it is the harder case to pass: $matches is left holding a
 * complete, entirely plausible result, exactly as a real backtrack-limit
 * failure leaves it holding a plausible fragment. A guard that is deleted
 * therefore does not merely crash — the sniff carries on and answers off
 * $matches, which is the silent wrong answer every guard here exists to stop.
 *
 * One override per namespace-and-function pair actually reached by a sniff.
 * A pair not listed here is one no guard needs; adding a call site to a sniff
 * whose namespace lacks its override simply leaves that call unmockable, not
 * broken.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests {
    use MikeBronner\CleanCode\Tests\PregFailure;

    /**
     * Whether the call now being made is the one a test armed.
     *
     * @param array<int, string>|string $pattern
     */
    function pregArmed(string $function, array|string $pattern): bool
    {
        return PregFailure::armedFor($function, is_string($pattern) ? $pattern : '');
    }
}

namespace MikeBronner\CleanCode\Sniffs\Arrays {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Conditionals {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Constructors {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }

    function preg_split($pattern, $subject, $limit = -1, $flags = 0)
    {
        return pregArmed('preg_split', $pattern)
            ? false
            : \preg_split($pattern, $subject, $limit, $flags);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Controversial {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_match_all($pattern, $subject, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0)
    {
        $matched = \preg_match_all($pattern, $subject, $matches, $flags, $offset);

        return pregArmed('preg_match_all', $pattern) ? false : $matched;
    }
}

namespace MikeBronner\CleanCode\Sniffs\DeadCode {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_match_all($pattern, $subject, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0)
    {
        $matched = \preg_match_all($pattern, $subject, $matches, $flags, $offset);

        return pregArmed('preg_match_all', $pattern) ? false : $matched;
    }
}

namespace MikeBronner\CleanCode\Sniffs\Functions {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Indentation {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Livewire {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }

    function preg_match_all($pattern, $subject, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0)
    {
        $matched = \preg_match_all($pattern, $subject, $matches, $flags, $offset);

        return pregArmed('preg_match_all', $pattern) ? false : $matched;
    }
}

namespace MikeBronner\CleanCode\Sniffs\Metrics {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_split($pattern, $subject, $limit = -1, $flags = 0)
    {
        return pregArmed('preg_split', $pattern)
            ? false
            : \preg_split($pattern, $subject, $limit, $flags);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Naming {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }

    function preg_split($pattern, $subject, $limit = -1, $flags = 0)
    {
        return pregArmed('preg_split', $pattern)
            ? false
            : \preg_split($pattern, $subject, $limit, $flags);
    }

    function preg_match_all($pattern, $subject, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0)
    {
        $matched = \preg_match_all($pattern, $subject, $matches, $flags, $offset);

        return pregArmed('preg_match_all', $pattern) ? false : $matched;
    }
}

namespace MikeBronner\CleanCode\Sniffs\Routes {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace_callback($pattern, $callback, $subject, $limit = -1, &$count = null, $flags = 0)
    {
        return pregArmed('preg_replace_callback', $pattern)
            ? null
            : \preg_replace_callback($pattern, $callback, $subject, $limit, $count, $flags);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Strings {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace_callback($pattern, $callback, $subject, $limit = -1, &$count = null, $flags = 0)
    {
        return pregArmed('preg_replace_callback', $pattern)
            ? null
            : \preg_replace_callback($pattern, $callback, $subject, $limit, $count, $flags);
    }

    function preg_split($pattern, $subject, $limit = -1, $flags = 0)
    {
        return pregArmed('preg_split', $pattern)
            ? false
            : \preg_split($pattern, $subject, $limit, $flags);
    }
}

namespace MikeBronner\CleanCode\Sniffs\Testing {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }

    function preg_split($pattern, $subject, $limit = -1, $flags = 0)
    {
        return pregArmed('preg_split', $pattern)
            ? false
            : \preg_split($pattern, $subject, $limit, $flags);
    }
}

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return pregArmed('preg_replace', $pattern)
            ? null
            : \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }
}

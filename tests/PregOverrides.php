<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests {
    use MikeBronner\CleanCode\Tests\PregFailure;

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
        if (pregArmed('preg_match_all', $pattern)) {
            return false;
        }

        return \preg_match_all($pattern, $subject, $matches, $flags, $offset);
    }
}

namespace MikeBronner\CleanCode\Sniffs\DeadCode {
    use function MikeBronner\CleanCode\Tests\pregArmed;

    function preg_match_all($pattern, $subject, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0)
    {
        if (pregArmed('preg_match_all', $pattern)) {
            return false;
        }

        return \preg_match_all($pattern, $subject, $matches, $flags, $offset);
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
        if (pregArmed('preg_match_all', $pattern)) {
            return false;
        }

        return \preg_match_all($pattern, $subject, $matches, $flags, $offset);
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
        if (pregArmed('preg_match_all', $pattern)) {
            return false;
        }

        return \preg_match_all($pattern, $subject, $matches, $flags, $offset);
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

<?php

/**
 * Every shape CleanCode.Conditionals.DisallowElse reports, one per `else`
 * keyword. The exact lines are pinned in
 * tests/Standards/DisallowElseTest.php.
 */

declare(strict_types=1);

// A file-scope else. PHPMD's rule is method/function-aware and misses this
// one; the sniff reports it, because the else is just as avoidable here.
if (PHP_INT_SIZE === 8) {
    $architecture = '64-bit';
} else {
    $architecture = '32-bit';
}

class FailingConditionals
{
    public function plainElse(bool $flag): int
    {
        if ($flag) {
            $result = 1;
        } else {
            $result = 2;
        }

        return $result;
    }

    public function elseAfterEarlyReturn(bool $flag): int
    {
        if ($flag) {
            return 1;
        } else {
            return 2;
        }
    }

    public function elseIfChain(bool $flag, bool $other): int
    {
        if ($flag) {
            $result = 1;
        } elseif ($other) {
            $result = 2;
        } else {
            $result = 3;
        }

        return $result;
    }

    public function elseSpaceIfChain(bool $flag, bool $other): int
    {
        if ($flag) {
            $result = 1;
        } else if ($other) {
            $result = 2;
        } else {
            $result = 3;
        }

        return $result;
    }

    public function nestedElse(bool $flag, bool $other): int
    {
        if ($flag) {
            if ($other) {
                $result = 1;
            } else {
                $result = 2;
            }
        } else {
            $result = 3;
        }

        return $result;
    }

    public function bracelessElse(bool $flag): int
    {
        if ($flag)
            $result = 1;
        else
            $result = 2;

        return $result;
    }

    public function alternativeSyntaxElse(bool $flag): int
    {
        if ($flag):
            $result = 1;
        else:
            $result = 2;
        endif;

        return $result;
    }

    public function elseInsideClosure(bool $flag): callable
    {
        return static function () use ($flag): int {
            if ($flag) {
                return 1;
            } else {
                return 2;
            }
        };
    }
}

<?php

/**
 * The AC bullet "phpcbf rewrites simple else/elseif cases without changing
 * runtime behavior", made executable. Every shape here is one the fixer
 * either rewrites or deliberately declines, and the file returns a closure
 * rather than declaring a class so this fixture and the fixer's output can
 * both be loaded into one process and compared.
 *
 * tests/Standards/DisallowElseTest.php runs the fixer over this file live and
 * asserts the rewritten closure answers every input exactly as this one does.
 * behaviour.fixed.php is the committed copy of that output.
 */

declare(strict_types=1);

return static function (bool $flag, bool $other, array $items): array {
    $classify = static function (bool $a, bool $b): string {
        if ($a) {
            return 'a';
        }
        if ($b) {
            return 'b';
        }
        return 'neither';
    };

    $spaced = static function (bool $a, bool $b): string {
        if ($a) {
            return 'a';
        }
        if ($b) {
            return 'b';
        }

        return 'neither';
    };

    $kept = static function (array $values): array {
        $result = [];

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $result[] = $value;
        }

        return $result;
    };

    $counted = static function (array $values): int {
        $seen = 0;

        foreach ($values as $value) {
            if ($value === false) {
                break;
            }
            $seen++;
        }

        return $seen;
    };

    /**
     * The shape the fixer must decline: the `elseif` branch terminates but
     * the `if` branch does not, so unwrapping the `else` would let a true
     * $a fall through into `$result = 3` and return 3 instead of 1. If the
     * chain-wide walk in precedingBranchesTerminate() is ever weakened to
     * check only the immediately preceding branch, the fixer rewrites this
     * and the assertion over these inputs fails.
     */
    $priority = static function (bool $a, bool $b): int {
        if ($a) {
            $result = 1;
        } elseif ($b) {
            return 2;
        } else {
            $result = 3;
        }

        return $result;
    };

    return [
        'classify' => $classify($flag, $other),
        'spaced' => $spaced($flag, $other),
        'kept' => $kept($items),
        'counted' => $counted($items),
        'priority' => $priority($flag, $other),
    ];
};

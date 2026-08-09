<?php

declare(strict_types=1);

namespace App\Fixtures;

// One class, weighted method count 13. Silent at the default maximum of 50,
// silent at a maximum of 14, and reported at a maximum of 13 — the same
// at-or-above comparison PHPMD makes, exercised from the configured side.

class ModestClass
{
    public function varied(int $a, int $b, array $items): int
    {
        if ($a && $b) {
            foreach ($items as $item) {
                $a += $item ? 1 : 0;
            }
        } elseif ($a || $b) {
            for ($i = 0; $i < $b; $i++) {
                $a--;
            }
        } else {
            while ($a > 0) {
                $a--;
            }
        }

        do {
            $b--;
        } while ($b > 0);

        try {
            switch ($a) {
                case 1:
                    break;
                case 2:
                    break;
                default:
                    break;
            }
        } catch (\Throwable) {
            $a = 0;
        } finally {
            $b = 0;
        }

        return $a + $b;
    }
}

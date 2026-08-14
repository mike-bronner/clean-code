<?php

declare(strict_types=1);

namespace App\Fixtures;

// Divergence 3 — PHP 8.4 property modifiers.
// This sniff counts all 16 fields below. PHPMD 2.15.0 reports nothing for this
// file at all: PDepend, its parser, understands neither a `final` property nor
// asymmetric visibility, and gives up on the whole file rather than on the one
// declaration. The shapes therefore live here rather than in failing.php, where
// they would silence PHPMD's verdict on every other class in the file.
class Settings
{
    final int $locked1 = 1;
    final int $locked2 = 2;
    final int $locked3 = 3;
    final int $locked4 = 4;
    final int $locked5 = 5;
    final int $locked6 = 6;
    final int $locked7 = 7;
    final int $locked8 = 8;

    private(set) int $guarded1 = 1;
    private(set) int $guarded2 = 2;
    private(set) int $guarded3 = 3;
    private(set) int $guarded4 = 4;
    private(set) int $guarded5 = 5;
    private(set) int $guarded6 = 6;
    private(set) int $guarded7 = 7;
    private(set) int $guarded8 = 8;
}

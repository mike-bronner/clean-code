<?php

/**
 * Entry point for the #229 dogfood ratchet.
 *
 *   composer dogfood                  # check; exits 1 when the baseline is out of date
 *   composer dogfood -- --generate    # re-record the baseline from the current tree
 *
 * This file declares nothing and only executes: PSR-1 forbids doing both in one
 * file, and `composer lint` enforces that over this tree. The behaviour lives in
 * DogfoodRunner, and the comparison it drives in DogfoodBaseline.
 *
 * The two classes are required directly rather than autoloaded so that the
 * script still runs when the autoloader has not been dumped — which is exactly
 * the state a contributor is in when a fresh clone fails the gate and they want
 * to know why.
 */

declare(strict_types=1);

require_once __DIR__ . '/DogfoodBaseline.php';
require_once __DIR__ . '/DogfoodRunner.php';

exit(MikeBronner\CleanCode\Tools\DogfoodRunner::run(
    dirname(__DIR__),
    in_array('--generate', array_slice($argv, 1), true)
));

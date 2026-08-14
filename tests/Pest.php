<?php

/**
 * Pest configuration.
 *
 * The sniff-driving helpers are plain functions rather than a base TestCase:
 * nothing here is stateful across a test's lifecycle, so there is no behaviour
 * a class would express that a function does not. See tests/Helpers.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

afterEach(function (): void {
    purgeStagedFixtures();
});

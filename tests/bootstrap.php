<?php

/**
 * Test bootstrap.
 *
 * PHP_CodeSniffer's own test bootstrap is load-bearing here and is not merely
 * a convenience: it defines the PHP_CODESNIFFER_CBF and
 * PHP_CODESNIFFER_VERBOSITY constants that src/Config.php and src/Ruleset.php
 * dereference unconditionally, and it registers the PHPCS autoloader that
 * resolves PHP_CodeSniffer\Tests\ConfigDouble — the class every helper in
 * tests/Helpers.php builds its Config from. Neither is reachable through
 * Composer's autoloader, because squizlabs/php_codesniffer ships no autoload
 * section for its own test namespace.
 *
 * tests/Sniffs.php is loaded here rather than from tests/Pest.php because the
 * constants it declares are read by more than one suite's datasets, and PHPUnit
 * resolves every data provider while it builds the suite — before it has
 * necessarily loaded the test file that would otherwise declare them.
 *
 * tests/PregOverrides.php declares functions in the sniffs' own namespaces, so
 * it has to be loaded before any sniff runs — a require from a suite file would
 * come too late for the suites loaded before it. It is a pass-through until a
 * test arms tests/PregFailure.php, which is what lets #376's guards be tested
 * for the branch they take rather than trusted for the comment above them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/squizlabs/php_codesniffer/tests/bootstrap.php';
require_once __DIR__ . '/Sniffs.php';
require_once __DIR__ . '/PregFailure.php';
require_once __DIR__ . '/PregOverrides.php';

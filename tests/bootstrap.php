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
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/squizlabs/php_codesniffer/tests/bootstrap.php';
require_once __DIR__ . '/Sniffs.php';

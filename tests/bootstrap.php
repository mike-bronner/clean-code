<?php

/**
 * PHPUnit bootstrap for the sniff unit-test harness.
 *
 * Loads PHP_CodeSniffer's own test bootstrap, then registers this package's
 * standard with the harness: AbstractSniffUnitTest locates a standard and its
 * tests through the two globals below, keyed by unit-test class name. Test
 * classes are discovered automatically, so adding a new sniff test requires
 * no changes here — see CONTRIBUTING.md for the layout convention.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/squizlabs/php_codesniffer/tests/bootstrap.php';

// Accumulator globals the harness appends to while running; PHPCS's own test
// suite initialises these, so a standalone standard has to do it itself.
$GLOBALS['PHP_CODESNIFFER_SNIFF_CODES'] = [];
$GLOBALS['PHP_CODESNIFFER_FIXABLE_CODES'] = [];
$GLOBALS['PHP_CODESNIFFER_SNIFF_CASE_FILES'] = [];

$standardDir = dirname(__DIR__) . '/CleanCode';
$testsDir = $standardDir . '/Tests/';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir));

foreach ($iterator as $file) {
    if (substr($file->getFilename(), -12) !== 'UnitTest.php') {
        continue;
    }

    $relativeClass = substr($file->getPathname(), strlen($testsDir), -4);
    $testClass = 'MikeBronner\\CleanCode\\Tests\\'
        . str_replace(DIRECTORY_SEPARATOR, '\\', $relativeClass);

    $GLOBALS['PHP_CODESNIFFER_STANDARD_DIRS'][$testClass] = $standardDir;
    $GLOBALS['PHP_CODESNIFFER_TEST_DIRS'][$testClass] = $testsDir;
}

<?php

/**
 * Token constants newer than the PHP_CodeSniffer this package installs.
 *
 * PHP_CodeSniffer publishes a `T_*` constant for every token its own tokenizer
 * can emit, and back-fills the ones the running PHP is too old to define —
 * `src/Util/Tokens.php` guards each with `if (defined('T_X') === false)` and
 * gives it a `'PHPCS_T_X'` string, a value no native token code can collide
 * with because every native one is an integer. This file does the same thing in
 * the other direction: PHP 8.5 emits two tokens that PHP_CodeSniffer 3.13.6 has
 * no constant for at all, on any PHP version.
 *
 * The two are the whole difference between PHP 8.4 and PHP 8.5, measured rather
 * than recalled. Both interpreters were asked for every `T_*` constant they
 * publish, with PHP_CodeSniffer loaded so its own additions are counted too:
 *
 *     php -r 'require "vendor/autoload.php";
 *             require "vendor/squizlabs/php_codesniffer/autoload.php";
 *             new \PHP_CodeSniffer\Util\Tokens();
 *             foreach (["tokenizer", "user"] as $g) {
 *                 foreach (array_keys(get_defined_constants(true)[$g] ?? []) as $n) {
 *                     if (str_starts_with($n, "T_")) { echo $n, "\n"; }
 *                 }
 *             }' | sort
 *
 * run under PHP 8.4.24 and PHP 8.5.9 and compared with `comm`. PHP 8.4 answers
 * 237 names, PHP 8.5 answers 239, and the difference is exactly `T_PIPE` and
 * `T_VOID_CAST` in the added direction with nothing removed. Without
 * PHP_CodeSniffer loaded the counts are 151 and 153 and the difference is the
 * same two, so neither is an artefact of what PHP_CodeSniffer itself defines.
 *
 * PHP_CodeSniffer passes both through with their native integer codes and their
 * real names — a token it has no case for keeps whatever `token_get_all()` gave
 * it — so a sniff on PHP 8.5 does see them. It just cannot name them, and a
 * sniff that lists token constants in a class constant names them at class-load
 * time, where an undefined one is a fatal error rather than a missed match.
 * Hence the guards below, which make the name safe to write on PHP 8.1 and 8.4
 * as well, where the token itself never occurs.
 *
 * Loaded through Composer's `files` autoloader, which is the whole of what this
 * needs and not merely the convenient half. PHP_CodeSniffer's own autoloader
 * includes the Composer one at `vendor/autoload.php` before it resolves its
 * first class, so the constants are defined before any sniff class is loaded on
 * a `vendor/bin/phpcs` run; and `tests/bootstrap.php` requires the same file
 * first, so they are defined before any test reads the token catalogue. This
 * package is `type: phpcodesniffer-standard` and requires the Composer plugin
 * that registers it, so there is no supported install with no Composer
 * autoloader for a `require_once` in the sniff to cover — and one there would
 * make that file both declare a symbol and cause a side effect, which is the
 * PSR-1 rule `composer lint` enforces over this tree.
 *
 * @see https://www.php.net/manual/en/migration85.new-features.php
 */

declare(strict_types=1);

if (defined('T_VOID_CAST') === false) {
    define('T_VOID_CAST', 'PHPCS_T_VOID_CAST');
}

if (defined('T_PIPE') === false) {
    define('T_PIPE', 'PHPCS_T_PIPE');
}

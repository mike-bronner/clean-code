<?php

/**
 * Tests CleanCode/Support/BackportedTokens.php, which defines the two token
 * constants PHP 8.5 emits and PHP_CodeSniffer 3.13.6 has no name for.
 *
 * The file has two branches and they run on different interpreters. Below PHP
 * 8.5 the constants are absent and the file defines them; on PHP 8.5 they are
 * native and the file has to leave them alone. Only the first branch is
 * exercised by the sniffs and tests that read the catalogue, and only on the
 * versions where it fires — on 8.5, the version the guards exist for, nothing
 * in the suite reaches the second branch at all. Deleting `defined()` there
 * would change no value on any version and no other assertion would move.
 *
 * So the file is loaded twice in a subprocess of its own, which puts the second
 * branch on every interpreter: whatever the first load leaves defined, the
 * second load finds already defined. A bare define() over an existing constant
 * raises "Constant ... already defined" and keeps the original value, so the
 * branch is asserted from both sides — the diagnostic that a missing guard
 * raises, and the value it would have to leave untouched anyway.
 *
 * A subprocess rather than a second require() here, because Composer's `files`
 * autoloader has already loaded this file into the test process: a load in
 * process would exercise the guard against constants this process defined at
 * boot, which is the same measurement with the first branch out of reach and
 * nothing to compare the values against.
 *
 * backportedTokensDoubleLoad(), which runs that subprocess, lives in
 * tests/Helpers.php with every other helper the tests drive: PSR-1 will not
 * have a test file both declare a function and run the it() calls that are its
 * point, and `composer lint` enforces that over this tree.
 */

declare(strict_types=1);

/**
 * The guard branch. A second load raises nothing and changes nothing, which is
 * what a sniff class loading after the constants are already defined — the
 * whole of what happens on PHP 8.5 — depends on.
 *
 * Non-vacuous by mutation: removing either `defined()` check makes the second
 * load raise "Constant T_VOID_CAST already defined" and this test red, on every
 * supported interpreter rather than only on 8.5.
 */
it('leaves a token constant a second load finds already defined alone', function (): void {
    $loaded = backportedTokensDoubleLoad();

    expect($loaded['diagnostics'])->toBe([])
        ->and($loaded['second'])->toBe($loaded['first']);
});

/**
 * The other branch, and the reason the values above are worth reading rather
 * than merely comparing: on PHP 8.5 both names are the interpreter's own
 * integer token codes and the shim must not shadow them with its strings, while
 * below 8.5 nothing defines them and the strings are the whole point.
 *
 * PHP_CodeSniffer's own convention is followed for the placeholder value — a
 * `PHPCS_T_*` string, which no native token code can collide with because every
 * native one is an integer — so the assertion is on the type as much as on the
 * value.
 */
it('defines each token the running PHP does not, the way PHP_CodeSniffer does', function (): void {
    $values = backportedTokensDoubleLoad()['first'];

    if (PHP_VERSION_ID >= 80500) {
        expect($values['T_VOID_CAST'])->toBeInt()
            ->and($values['T_PIPE'])->toBeInt();

        return;
    }

    expect($values['T_VOID_CAST'])->toBe('PHPCS_T_VOID_CAST')
        ->and($values['T_PIPE'])->toBe('PHPCS_T_PIPE');
});

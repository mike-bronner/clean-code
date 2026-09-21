<?php

/**
 * Tests the `extensions` argument in the master ruleset, as PHPCS parses it.
 *
 * `$config->extensions` is not a restatement of the XML — it is the parsed
 * result PHPCS itself consults, so this pins that the argument survives the
 * ruleset parse in the shape the file writes it, and that registering Blade did
 * not displace `php`.
 *
 * What it does *not* establish, corrected 2026-09-21 after the claim was
 * measured: that the entry is why Blade views get scanned. This docblock used
 * to say `Filters\Filter::shouldProcessFile()` looks a path's extension up in
 * this map and skips the file when absent, making a view invisible without the
 * entry. That is not what it does. It builds every multi-part suffix of the
 * name — `component.blade.php` yields `blade.php` *and* `php` — and matches on
 * any of them, and `php` is in PHP_CodeSniffer's default list already. Nor does
 * the `/php` half pick the tokenizer for a view: `Files\File::__construct()`
 * pops only the last dot-segment, so it looks up `php`, never `blade.php`, and
 * defaults to the PHP tokenizer regardless.
 *
 * The entry's one real effect is that it *replaces* the default list rather
 * than extending it, so `.inc`, `.js` and `.css` stop being scanned. The
 * behaviour it was added for — a Blade view is linted end to end, through both
 * of the package's entry points — is pinned in
 * tests/Contract/InstalledStandardTest.php instead, and that test's docblock
 * records that deleting this argument leaves it green.
 */

declare(strict_types=1);

it('registers .blade.php alongside .php, both on the PHP tokenizer', function (): void {
    [$config] = buildRuleset();

    expect($config->extensions)->toBe([
        'php' => 'PHP',
        'blade.php' => 'PHP',
    ]);
});

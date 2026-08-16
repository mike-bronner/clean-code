<?php

/**
 * Tests the `extensions` argument in the master rules.xml: PHPCS scans
 * `.blade.php` views as well as plain `.php` files, so the Livewire markup
 * standard (#46) has something to read.
 *
 * `$config->extensions` is not a restatement of the XML — it is the parsed
 * result PHPCS itself consults. `Filters\Filter::shouldProcessFile()` looks a
 * candidate path's extension up in exactly this map and skips the file when it
 * is absent, which is why a Blade view is invisible to a run without the entry
 * and scanned with it.
 *
 * The value is the *tokenizer* each extension maps to, so `blade.php => PHP`
 * is the whole point: Blade views are handed to the PHP tokenizer, which is
 * what turns their markup into the T_INLINE_HTML tokens
 * CleanCode.Livewire.ComponentMarkup reads. Asserting the whole map rather
 * than one key also pins that registering Blade did not displace `php`.
 */

declare(strict_types=1);

it('registers .blade.php alongside .php, both on the PHP tokenizer', function (): void {
    [$config] = buildRuleset();

    expect($config->extensions)->toBe([
        'php' => 'PHP',
        'blade.php' => 'PHP',
    ]);
});

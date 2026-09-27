<?php

declare(strict_types=1);

it('registers .blade.php alongside .php, both on the PHP tokenizer', function (): void {
    [$config] = buildRuleset();

    expect($config->extensions)->toBe([
        'php' => 'PHP',
        'blade.php' => 'PHP',
    ]);
});

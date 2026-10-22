<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\ClassNames;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

$receivers = static function (File $file, callable $resolve): array {
    $resolved = [];

    foreach ($file->getTokens() as $pointer => $token) {
        if ($token['code'] === T_DOUBLE_COLON) {
            $namePtr = $pointer - 1;
            $resolved[$token['line']] = $resolve($file, $file->getTokens()[$namePtr]['content'], $namePtr);
        }
    }

    return $resolved;
};

it('resolves a name only through the imports of its own namespace block', function () use ($receivers): void {
    $file = parseFixture('ClassNames', 'blocks.php');

    expect($receivers($file, (new ClassNames)->resolve(...)))->toBe([
        9 => '\App\Models\User',
        10 => '\App\Models\Customer',
        11 => '\App\Models\Invoice',
        15 => '\App\Jobs\User',
        16 => '\App\Jobs\Member',
        17 => '\App\Jobs\Models\Invoice',
        18 => '\App\Models\User',
        24 => '\Vendor\Client',
        25 => '\App\Console\Client',
        29 => '\App\Support\User',
    ]);
});

it('resolves a file with no namespace or one namespace block exactly as Slevomat does', function (
    string $fixture,
    int $calls,
) use ($receivers): void {
    $file = parseFixture('ClassNames', $fixture);
    $slevomat = $receivers($file, NamespaceHelper::resolveClassName(...));

    expect($receivers($file, (new ClassNames)->resolve(...)))->toBe($slevomat)
        ->and($slevomat)->toHaveCount($calls);
})->with([
    ['no-namespace.php', 4],
    ['one-block.php', 5],
    ['open-tags.php', 2],
]);

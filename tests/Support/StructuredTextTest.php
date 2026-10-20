<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Support\StructuredText;
use MikeBronner\CleanCode\Tests\PregFailure;

dataset('line-matched structured text', [
    'markdown' => ['isMarkdown', "# Title\n- item"],
    'INI config' => ['isConfig', "[section]\nkey = value"],
    'YAML front matter' => ['isSerialised', "---\nkey: value"],
]);

it('is structured when the line match succeeds', function (string $check, string $text): void {
    expect((new StructuredText)->{$check}($text))->toBeTrue();
})->with('line-matched structured text');

it('is unstructured, silently, when the line match fails', function (string $check, string $text): void {
    [$structured, $diagnostics] = withPhpDiagnostics(static fn (): bool => PregFailure::during(
            'preg_match_all',
            static fn (): bool => (new StructuredText)->{$check}($text)
        ));

    expect($structured)->toBeFalse()
        ->and($diagnostics)->toBe([]);
})->with('line-matched structured text');

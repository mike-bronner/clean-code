<?php

declare(strict_types=1);

pest()->group('arch');

it('installs exactly one standard, named CleanCode', function (): void {
    $rulesets = array_merge(
            (array) glob(cleanCodeRoot() . '/*/ruleset.xml'),
            (array) glob(cleanCodeRoot() . '/*/*/ruleset.xml'),
            (array) glob(cleanCodeRoot() . '/*/*/*/ruleset.xml')
        );

    $shipped = array_values(array_filter(
            array_map(static fn (string $path): string => substr($path, strlen(cleanCodeRoot()) + 1), $rulesets),
            static fn (string $path): bool => str_starts_with($path, 'vendor/') === false
        ));

    sort($shipped);

    expect($shipped)->toBe(['CleanCode/ruleset.xml'])
        ->and(installedStandardNames())->toContain('CleanCode');
});

it('resolves the standard name to the ruleset this package ships', function (): void {
    $fixture = fixturePath('MultiLineStatementIndentSniff', 'failing.php');

    $byName = installedPhpcsRun('CleanCode', $fixture);
    $byPath = installedPhpcsRun(cleanCodeRoot() . '/CleanCode/ruleset.xml', $fixture);

    $sources = array_unique(array_map(
            static fn (array $message): string => explode('.', (string) $message['source'])[0],
            $byName['messages']
        ));

    sort($sources);

    expect($byName['messages'])->toBe($byPath['messages'])
        ->and($byName['status'])->toBe($byPath['status'])
        ->and($sources)->toBe([
            'CleanCode',
            'Generic',
            'PSR1',
            'PSR12',
            'SlevomatCodingStandard',
            'Squiz',
            'VariableAnalysis',
        ]);
});

it('scans Blade views whichever way the standard is named', function (string $standard): void {
    $staged = stageFixtureOutsideTests(fixturePath('ComponentMarkupSniff', 'component.blade.php'));
    $run = installedPhpcsRun($standard, dirname($staged));

    expect(array_column($run['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith('CleanCode.Livewire.ComponentMarkup.');
})->with([
    'by standard name' => ['CleanCode'],
    'by ruleset path' => [cleanCodeRoot() . '/CleanCode/ruleset.xml'],
]);

it('pins php_version to the package PHP floor', function (): void {
    $manifest = json_decode(
            (string) file_get_contents(cleanCodeRoot() . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    $constraint = (string) $manifest['require']['php'];
    $source = (string) file_get_contents(cleanCodeRoot() . '/CleanCode/ruleset.xml');

    expect(preg_match('/^\^(\d+)\.(\d+)$/', $constraint, $floor))->toBe(1);

    $ruleset = simplexml_load_string($source);

    expect($ruleset)->not->toBeFalse();

    $pinned = $ruleset->xpath('//config[@name="php_version"]');

    expect($pinned)->toHaveCount(1);

    expect((string) $pinned[0]['value'])->toBe(sprintf('%d%02d00', (int) $floor[1], (int) $floor[2]));
});

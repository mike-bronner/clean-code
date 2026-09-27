<?php

declare(strict_types=1);

$sourcesWithoutShortOpenTags = static function (string $path): array {
    $command = implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        '-d',
        'short_open_tag=Off',
        cleanCodeRoot() . '/vendor/bin/phpcs',
        '--standard=' . cleanCodeRoot() . '/CleanCode/ruleset.xml',
        '--report=csv',
        '--no-cache',
        $path,
    ]));

    exec($command . ' 2>&1', $output);

    $sources = [];

    foreach (array_slice($output, 1) as $row) {
        $fields = str_getcsv($row, ',', '"', '\\');

        if (isset($fields[5]) === true) {
            $sources[] = $fields[5];
        }
    }

    return $sources;
};

$stageFixtureAs = static function (string $path, string $filename): string {
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-nocode-', true);

    if (mkdir($directory, 0700) === false) {
        throw new RuntimeException("could not stage a fixture in {$directory}");
    }

    $target = $directory . '/' . $filename;
    stagedFixtures($target);
    copy($path, $target);

    return $target;
};

it('does not report Internal.NoCodeFound on a Blade view with no PHP tag', function () use (
    $sourcesWithoutShortOpenTags
): void {
    $view = fixturePath('ComponentMarkupSniff', 'no-php-code.blade.php');

    expect($sourcesWithoutShortOpenTags($view))->not->toContain('Internal.NoCodeFound');
});

it('still reports Internal.NoCodeFound on a .php file with no PHP tag', function () use (
    $sourcesWithoutShortOpenTags,
    $stageFixtureAs
): void {
    $staged = $stageFixtureAs(
        fixturePath('ComponentMarkupSniff', 'no-php-code.blade.php'),
        'no-php-code.php'
    );

    expect($sourcesWithoutShortOpenTags($staged))->toContain('Internal.NoCodeFound');
});

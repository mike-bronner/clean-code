<?php

/**
 * Tests the Internal.NoCodeFound suppression in the master rules.xml, the
 * other half of scanning Blade views (#46).
 *
 * Registering `blade.php/php` hands PHPCS's PHP tokenizer a file that, by
 * Blade's own design, normally carries no raw `<?php ?>` tag at all. PHPCS
 * answers that with Internal.NoCodeFound and exits 1, so without the
 * suppression, installing this ruleset would fail a consumer's CI on every
 * idiomatic Blade view — whatever the view contains, and whether or not it
 * breaks any standard. The suppression is wiring, not convenience.
 *
 * These two cases run PHPCS in a **subprocess** rather than through the
 * in-process helpers every other test here uses. PHP_CodeSniffer raises the
 * warning only when `ini_get('short_open_tag')` is false (Files/File.php), and
 * `short_open_tag` is PHP_INI_PERDIR — a test cannot turn it off in its own
 * process. Run in-process on a runtime that happens to have short tags *on*,
 * both assertions below would pass against a ruleset with no suppression at
 * all, because the warning could never fire either way. `-d short_open_tag=Off`
 * on a child process is what makes them mean the same thing everywhere.
 */

declare(strict_types=1);

/**
 * Runs the master ruleset over one file with short open tags disabled, and
 * returns the violation source codes it reported.
 *
 * @return callable(string): array<int, string>
 */
$sourcesWithoutShortOpenTags = static function (string $path): array {
    $command = implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        '-d',
        'short_open_tag=Off',
        cleanCodeRoot() . '/vendor/bin/phpcs',
        '--standard=' . cleanCodeRoot() . '/rules.xml',
        '--report=csv',
        '--no-cache',
        $path,
    ]));

    exec($command . ' 2>&1', $output);

    $sources = [];

    // CSV columns: File,Line,Column,Type,Message,Source,Severity,Fixable. A
    // message is quoted and may itself contain commas, so rows are parsed
    // rather than exploded.
    foreach (array_slice($output, 1) as $row) {
        $fields = str_getcsv($row, ',', '"', '\\');

        if (isset($fields[5]) === true) {
            $sources[] = $fields[5];
        }
    }

    return $sources;
};

/**
 * Copies a fixture to a temporary directory under a new name, so the same
 * bytes can be scanned as `.blade.php` and as `.php`. The extension is the
 * only difference between the two cases below, which is exactly what the
 * exclude-pattern keys on.
 */
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

/**
 * The paired positive, and the reason the suppression is an exclude-pattern
 * rather than <severity>0</severity>: the very same bytes saved as a plain
 * `.php` file still warn. Without this, the case above would pass just as
 * happily against a ruleset that switched the check off everywhere — which
 * would silently unlint every short-open-tag PHP file in a consumer's project.
 */
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

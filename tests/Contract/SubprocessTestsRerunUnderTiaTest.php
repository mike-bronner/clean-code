<?php

declare(strict_types=1);

const SUBPROCESS_CALLS = '/\b(?:installedPhpcs\w*|installedSniff\w*|installedStandardNames|runOutsidePackage'
    . '|exec|shell_exec|proc_open|passthru|system)\s*\(/';

it('tags every test file that runs a subprocess with the arch group', function (): void {
    $untagged = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(cleanCodeRoot() . '/tests'));

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (str_ends_with($path, 'Test.php') === false || str_contains($path, '/fixtures/') === true) {
            continue;
        }

        $source = (string) file_get_contents($path);

        if (preg_match(SUBPROCESS_CALLS, $source) === 1 && str_contains($source, "pest()->group('arch');") === false) {
            $untagged[] = $path;
        }
    }

    expect($untagged)->toBe([]);
});

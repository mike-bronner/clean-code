<?php

declare(strict_types=1);

$phpSourcesOutsideFixtures = static function (): array {
    $files = [];

    foreach (['CleanCode', 'tests'] as $directory) {
        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(cleanCodeRoot() . '/' . $directory, FilesystemIterator::SKIP_DOTS)
            );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (str_ends_with($path, '.php') && str_contains($path, '/fixtures/') === false) {
                $files[] = $path;
            }
        }
    }

    sort($files);

    return $files;
};

$nonDirectiveComments = static function (string $source): array {
    $comments = array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => $token->is([T_COMMENT, T_DOC_COMMENT])
            && preg_match('#^(//|/\*)\s*phpcs:(ignore|disable|enable)\b#', $token->text) !== 1
        );

    return array_map(static fn (PhpToken $token): int => $token->line, array_values($comments));
};

it('finds no comment in PHP under CleanCode/ or tests/ except phpcs directives', function () use (
    $phpSourcesOutsideFixtures,
    $nonDirectiveComments,
): void {
    $files = $phpSourcesOutsideFixtures();
    $offenders = [];

    foreach ($files as $path) {
        $lines = $nonDirectiveComments((string) file_get_contents($path));

        if ($lines !== []) {
            $offenders[str_replace(cleanCodeRoot() . '/', '', $path)] = $lines;
        }
    }

    expect($files)->not->toBeEmpty()
        ->and($offenders)->toBe([]);
});

it('reports every comment shape and passes a phpcs directive', function () use ($nonDirectiveComments): void {
    $source = <<<PHP
    <?php
    /** Docblock. */
    final class Example
    {
        // phpcs:ignore Generic.Files.LineLength
        public int \$value = 1; // Trailing.
        /* Block. */
    }
    PHP;

    expect($nonDirectiveComments($source))->toBe([2, 6, 7]);
});

it('keeps only one-line group labels in the XML configuration', function (string $file): void {
    preg_match_all('/<!--(.*?)-->/s', (string) file_get_contents(cleanCodeRoot() . '/' . $file), $comments);

    $labels = array_filter(
            $comments[1],
            static fn (string $comment): bool => preg_match('/^ [A-Z][A-Za-z]*( [A-Za-z]+)* $/', $comment) === 1
        );

    expect($labels)->toBe($comments[1]);
})->with(['CleanCode/ruleset.xml', 'phpcs.self.xml', 'phpunit.xml.dist']);

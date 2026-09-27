<?php

declare(strict_types=1);

it('unlinks a symlinked entry instead of deleting the directory it points at', function (): void {
    $outside = directoryOutsideEveryStagingRoot();
    $root = stagingDirectory();
    $link = $root . '/linked';
    $sibling = $root . '/sibling.php';

    stageSymlink($outside, $link);
    file_put_contents($sibling, '<?php' . "\n");

    purgeStagedFixtures();

    expect(is_dir($outside))->toBeTrue();
    expect(is_file($outside . '/keep.php'))->toBeTrue();
    expect(is_link($link))->toBeFalse();
    expect(file_exists($sibling))->toBeFalse();
    expect(file_exists($root))->toBeFalse();

    removeDirectoryOutsideEveryStagingRoot($outside);
});

it('unlinks a staging root that is itself a symlink, and leaves its target alone', function (): void {
    $outside = directoryOutsideEveryStagingRoot();
    $root = sys_get_temp_dir() . '/' . uniqid('cleancode-fixture-link-', true);

    stageSymlink($outside, $root);
    stagedFixtures($root);

    $diagnostics = diagnosticsRaisedBy(static function (): void {
        purgeStagedFixtures();
    });

    expect($diagnostics)->toBe([]);
    expect(is_dir($outside))->toBeTrue();
    expect(is_file($outside . '/keep.php'))->toBeTrue();
    expect(is_link($root))->toBeFalse();
    expect(file_exists($root))->toBeFalse();

    removeDirectoryOutsideEveryStagingRoot($outside);
});

it('unlinks a dangling symlink rather than leaving the staging root behind', function (): void {
    $root = stagingDirectory();
    $link = $root . '/dangling';

    stageSymlink($root . '/never-created', $link);

    $diagnostics = diagnosticsRaisedBy(static function (): void {
        purgeStagedFixtures();
    });

    expect($diagnostics)->toBe([]);
    expect(is_link($link))->toBeFalse();
    expect(file_exists($root))->toBeFalse();
});

it('unlinks a staging root that is itself a dangling symlink', function (): void {
    $root = sys_get_temp_dir() . '/' . uniqid('cleancode-fixture-dangling-root-', true);

    stageSymlink($root . '-never-created', $root);
    stagedFixtures($root);

    $diagnostics = diagnosticsRaisedBy(static function (): void {
        purgeStagedFixtures();
    });

    expect($diagnostics)->toBe([]);
    expect(is_link($root))->toBeFalse();
});

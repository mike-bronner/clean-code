<?php

/**
 * Tests removeStagedDirectory() in tests/Helpers.php, the teardown every suite
 * here runs after each test through purgeStagedFixtures().
 *
 * The subject is what the function does with a symlink. is_dir() answers for a
 * link's target, so the recursion used to descend through a staged link and
 * delete the target's contents — a real directory outside the staging root, and
 * vendor/ is the plausible one, because staging a PHP_CodeSniffer install is
 * exactly the case where a symlink looks like the cheap option (#380).
 *
 * The three cases below are the three shapes a link reaches the function in: an
 * entry inside a staged root, the staged root itself, and a link whose target
 * is already gone. The first two stage a real directory *outside* every staging
 * root and assert it survives — the deletion this guards against happens out
 * there, so an assertion made only inside the root could not see it. That
 * outside directory is created by hand rather than through stagingDirectory(),
 * because a registered root is handed to the very purge under test, and it is
 * removed by hand once the assertions are done so a run leaks nothing into the
 * system temp directory. The helpers that build and remove it sit beside
 * removeStagedDirectory() in tests/Helpers.php, where PSR-12 keeps them: a file
 * that both declares functions and runs tests fails composer lint.
 *
 * What each case discriminates, established by running the mutation rather than
 * by reading the code:
 *
 * - Deleting both guards reddens the first case: the recursion walks through the
 *   link and takes the outside directory with it.
 * - Deleting the entry guard alone reddens the root-is-a-symlink case: the
 *   target's file is deleted, and rmdir() warns "Not a directory" on the link.
 * - Gating an unlink() on file_exists() reddens the dangling case: the link
 *   survives, and the closing rmdir() warns that the root is not empty.
 * - Deleting the loop guard alone reddens nothing, and the second case is why:
 *   the recursive call lands on the entry guard, which unlinks that same link
 *   one frame deeper. The loop guard states the rule where the loop is read
 *   instead of leaving it to the recursion; it is not separate behaviour, and
 *   nothing here claims a test holds it in place.
 */

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
    // The ordinary entry beside the link goes, so unlinking the link cannot
    // have been an early return out of the loop that was carrying both.
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

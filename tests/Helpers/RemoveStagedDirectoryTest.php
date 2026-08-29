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
 * The four cases below are the 2x2 a link arrives in: it is either an entry
 * inside a staged root or the staged root itself, and its target is either
 * still there or already gone. The first axis picks which guard answers: the
 * one in the loop, or the function's own entry check. The second decides
 * whether an unlink() gated on the target's existence would still fire, so the
 * two dangling cases are what hold each guard's unlink unconditional — one per
 * site, neither standing in for the other. The two live-target cases stage a
 * real directory *outside* every staging root and assert it survives, because
 * the deletion this guards against happens out there and an assertion made only
 * inside the root could not see it. That outside directory is created by hand
 * rather than through stagingDirectory(), because a registered root is handed to
 * the very purge under test, and it is removed by hand once the assertions are
 * done so a run leaks nothing into the system temp directory. The helpers that
 * build and remove it sit beside removeStagedDirectory() in tests/Helpers.php,
 * where PSR-12 keeps them: a file that both declares functions and runs tests
 * fails composer lint.
 *
 * What each case discriminates — and what nothing here holds — established by
 * running every mutation below against these tests rather than by reading the
 * code:
 *
 * - Deleting the entry guard reddens both root cases: with a live target its
 *   file is deleted and rmdir() warns "Not a directory" on the link; with a
 *   dangling one the link simply survives. Deleting both guards reddens those
 *   two and the entry/live case, where the recursion walks through the link and
 *   takes the outside directory with it.
 * - Gating an unlink() on file_exists() reddens the dangling case belonging to
 *   the site that was gated, and only that one. Gating the loop's unlink strands
 *   the link in a root whose closing rmdir() then warns "Directory not empty".
 *   Gating the entry check's unlink leaks the link with no diagnostic at all,
 *   which is why that case asserts is_link() and not file_exists(): file_exists()
 *   reads through to the target, so it answers false for a link left dangling
 *   exactly as it does for one removed, and could not tell the two apart.
 * - Deleting the loop guard alone reddens nothing, and the root/live case is
 *   why: the recursive call lands on the entry guard, which unlinks that same
 *   link one frame deeper. Deleting the entry guard's return reddens nothing
 *   either, for the mirror reason — its unlink() has already taken the path, so
 *   the is_dir() check below returns on its own. Both write the rule where it is
 *   read instead of leaving it to the recursion; neither is separate behaviour,
 *   and nothing here claims a test holds either in place. The loop's continue is
 *   not in that group: deleting it reddens the entry/dangling case, because the
 *   fall-through goes on to unlink the same path a second time.
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

it('unlinks a staging root that is itself a dangling symlink', function (): void {
    $root = sys_get_temp_dir() . '/' . uniqid('cleancode-fixture-dangling-root-', true);

    stageSymlink($root . '-never-created', $root);
    stagedFixtures($root);

    $diagnostics = diagnosticsRaisedBy(static function (): void {
        purgeStagedFixtures();
    });

    expect($diagnostics)->toBe([]);
    // is_link() rather than file_exists(), which reads the target and so answers
    // false for a link that is still sitting there dangling — the one reading
    // that cannot tell "unlinked" from "left behind", which is the whole
    // distinction this case exists to make.
    expect(is_link($root))->toBeFalse();
});

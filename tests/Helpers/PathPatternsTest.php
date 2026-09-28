<?php

/**
 * Tests MikeBronner\CleanCode\Helpers\PathPatterns directly.
 *
 * The helper is the one answer to "does this file's path match any of these
 * fnmatch patterns?", shared by the sniffs that scope themselves to test files
 * through a testFilePatterns property: CleanCode.Testing.NoReflectionAccess,
 * CleanCode.Testing.NoFirstPartyMocks and CleanCode.ClearCode.SectionComment.
 * Each of those sniffs still has its own tests for its own defaults. These
 * pin the matching rule itself.
 *
 * There are no fixtures on disk here: the helper reads strings, never a token
 * stream.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\PathPatterns;

const PATH_PATTERNS_TEST_FILES = ['*/tests/*', '*/Tests/*', '*Test.php'];

it('matches a path that any one pattern matches', function (): void {
    $pathPatterns = new PathPatterns();

    expect($pathPatterns->matchesAny('/project/tests/Unit/UserTest.php', PATH_PATTERNS_TEST_FILES))->toBeTrue()
        ->and($pathPatterns->matchesAny('/project/Tests/Unit/User.php', PATH_PATTERNS_TEST_FILES))->toBeTrue()
        ->and($pathPatterns->matchesAny('/project/src/UserTest.php', PATH_PATTERNS_TEST_FILES))->toBeTrue();
});

it('does not match a path that no pattern matches', function (): void {
    $pathPatterns = new PathPatterns();

    expect($pathPatterns->matchesAny('/project/src/User.php', PATH_PATTERNS_TEST_FILES))->toBeFalse()
        ->and($pathPatterns->matchesAny('/project/src/User.php', []))->toBeFalse();
});

/**
 * The match is case-sensitive, which is why `tests` and `Tests` are both in
 * the sniffs' defaults: folding case would make `*Test.php` match `latest.php`.
 */
it('matches case-sensitively', function (): void {
    $pathPatterns = new PathPatterns();

    expect($pathPatterns->matchesAny('/project/TESTS/User.php', PATH_PATTERNS_TEST_FILES))->toBeFalse()
        ->and($pathPatterns->matchesAny('/project/src/latest.php', PATH_PATTERNS_TEST_FILES))->toBeFalse();
});

/**
 * A Windows path and a pattern written with backslashes both have `\` turned
 * into `/` before the match, so a consumer's ruleset works on either platform.
 */
it('normalises backslashes in the path and in the pattern', function (): void {
    $pathPatterns = new PathPatterns();

    expect($pathPatterns->matchesAny('C:\\project\\tests\\UserTest.php', ['*/tests/*']))->toBeTrue()
        ->and($pathPatterns->matchesAny('/project/tests/User.php', ['*\\tests\\*']))->toBeTrue();
});

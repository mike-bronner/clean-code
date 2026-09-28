<?php

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

it('matches case-sensitively', function (): void {
    $pathPatterns = new PathPatterns();

    expect($pathPatterns->matchesAny('/project/TESTS/User.php', PATH_PATTERNS_TEST_FILES))->toBeFalse()
        ->and($pathPatterns->matchesAny('/project/src/latest.php', PATH_PATTERNS_TEST_FILES))->toBeFalse();
});

it('normalises backslashes in the path and in the pattern', function (): void {
    $pathPatterns = new PathPatterns();

    expect($pathPatterns->matchesAny('C:\\project\\tests\\UserTest.php', ['*/tests/*']))->toBeTrue()
        ->and($pathPatterns->matchesAny('/project/tests/User.php', ['*\\tests\\*']))->toBeTrue();
});

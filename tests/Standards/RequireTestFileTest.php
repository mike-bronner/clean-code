<?php

declare(strict_types=1);

const REQUIRE_TEST_FILE = 'CleanCode.Testing.RequireTestFile';

const REQUIRE_TEST_FILE_MISSING = REQUIRE_TEST_FILE . '.Missing';

const FLAT_FIXTURE_SOURCE_ROOT = ['RequireTestFileSniff'];

const STAGED_CLASS = "<?php\n\ndeclare(strict_types=1);\n\nclass Widget\n{\n}\n";

const STAGED_COMPANION = "<?php\n\ndeclare(strict_types=1);\n";

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_TEST_FILE);
});

it('leaves every exempt declaration alone', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'passing.php', function ($sniff): void {
        $sniff->sourceDirectories = FLAT_FIXTURE_SOURCE_ROOT;
    });

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns on every concrete class with no companion test', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'failing.php', function ($sniff): void {
        $sniff->sourceDirectories = FLAT_FIXTURE_SOURCE_ROOT;
    });

    expect(warningTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
        ['line' => 21, 'column' => 7, 'source' => REQUIRE_TEST_FILE_MISSING],
        ['line' => 29, 'column' => 10, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);
});

it('says nothing about a file whose companion exists or that is exempt', function (string $fixture): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a companion beside the source root' => 'src/Covered.php',
    'a mirrored companion below it' => 'src/Nested/Deep.php',
    'a companion named for the file, not the class' => 'src/Renamed.php',
    'an anonymous class in a covered file' => 'src/Registry.php',
    'an interface' => 'src/Contract.php',
    'a trait' => 'src/Helper.php',
    'an enum' => 'src/Status.php',
    'an abstract class' => 'src/BaseModel.php',
    'a readonly abstract class' => 'src/ReadonlyBase.php',
]);

it('warns on a concrete class whose companion is absent', function (string $fixture, int $line): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, $fixture);

    expect(warningTuples($file))->toBe([
        ['line' => $line, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);
})->with([
    'no test anywhere' => ['src/Untested.php', 10],
    'a same-named test at the wrong level' => ['src/Nested/Orphan.php', 14],
    'a test below the configured test root' => ['src/Split.php', 14],
    'framework scaffolding, not yet excluded' => ['src/Migrations/CreateUsersTable.php', 13],
    'a class under app/' => ['app/Legacy.php', 12],
    'a project with no test directory at all' => ['standalone/src/Lonely.php', 14],
]);

it('anchors the project root on the source directory closest to the file', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'srv/app/nested-project/src/Widget.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('finds a companion under a name holding a glob metacharacter', function (array $files): void {
    $file = analyzeWithSniffs([REQUIRE_TEST_FILE], stageProjectOutsideTests($files));

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'a bracket group in the source-relative directory' => [[
        'src/Foo[Bar]/Widget.php' => STAGED_CLASS,
        'tests/Foo[Bar]/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group above the source root' => [[
        'proj[1]/src/Widget.php' => STAGED_CLASS,
        'proj[1]/tests/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group in the file name' => [[
        'src/Odd[Name].php' => STAGED_CLASS,
        'tests/Odd[Name]Test.php' => STAGED_COMPANION,
    ]],
]);

it('does not let a glob metacharacter match a directory that is not the companion', function (array $files): void {
    $file = analyzeWithSniffs([REQUIRE_TEST_FILE], stageProjectOutsideTests($files));

    expect(warningTuples($file))->toBe([
        ['line' => 5, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);
})->with([
    'an asterisk spanning a different directory name' => [[
        'src/Od*d/Widget.php' => STAGED_CLASS,
        'tests/Odad/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a question mark spanning a different character' => [[
        'src/Od?d/Widget.php' => STAGED_CLASS,
        'tests/Odad/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group matching one of its own characters' => [[
        'src/Foo[Bar]/Widget.php' => STAGED_CLASS,
        'tests/FooB/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'an asterisk spanning a different file name' => [[
        'src/Od*d.php' => STAGED_CLASS,
        'tests/OdadTest.php' => STAGED_COMPANION,
    ]],
    'a question mark spanning a different character of a file name' => [[
        'src/Od?d.php' => STAGED_CLASS,
        'tests/OdadTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group in a file name matching one of its own characters' => [[
        'src/Foo[Bar].php' => STAGED_CLASS,
        'tests/FooBTest.php' => STAGED_COMPANION,
    ]],
    'an asterisk spanning a different project root' => [[
        'proj*d/src/Widget.php' => STAGED_CLASS,
        'projad/tests/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a question mark spanning a different character of a project root' => [[
        'proj?d/src/Widget.php' => STAGED_CLASS,
        'projad/tests/WidgetTest.php' => STAGED_COMPANION,
    ]],
    'a bracket group above the source root matching one of its own characters' => [[
        'proj[1]/src/Widget.php' => STAGED_CLASS,
        'proj1/tests/WidgetTest.php' => STAGED_COMPANION,
    ]],
]);

it('scopes itself out of a directory not named in the source list', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'app/Legacy.php', function ($sniff): void {
        $sniff->sourceDirectories = ['src'];
    });

    expect($file->getWarnings())->toBe([]);
});

it('finds a companion in a split suite through a wildcard test root', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'src/Split.php', function ($sniff): void {
        $sniff->testDirectory = 'tests/*';
    });

    expect($file->getWarnings())->toBe([]);
});

it('resolves the companion through the configured path template', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'src/Nested/Orphan.php', function ($sniff): void {
        $sniff->testPathTemplate = '{name}Test.php';
    });

    expect($file->getWarnings())->toBe([]);
});

it('honours an exclude pattern configured from a ruleset', function (array $patterns, array $expected): void {
    $file = analyzeFixtureWithRulesetProperties(
        REQUIRE_TEST_FILE,
        'src/Migrations/CreateUsersTable.php',
        ['excludePatterns' => $patterns]
    );

    expect(warningTuples($file))->toBe($expected);
})->with([
    'a matching pattern' => [['*/Migrations/*'], []],
    'a pattern that matches nothing here' => [
        ['*/Providers/*'],
        [['line' => 13, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING]],
    ],
]);

it('says nothing when the file has no path to resolve a companion from', function (): void {
    $fixture = fixturePath(sniffFixtureDirectory(REQUIRE_TEST_FILE), 'src/Untested.php');

    expect(warningTuples(analyzeWithSniffs([REQUIRE_TEST_FILE], $fixture)))->toBe([
        ['line' => 10, 'column' => 1, 'source' => REQUIRE_TEST_FILE_MISSING],
    ]);

    $piped = analyzeStdinSource([REQUIRE_TEST_FILE], (string) file_get_contents($fixture));

    expect($piped->getErrors())->toBe([])
        ->and($piped->getWarnings())->toBe([]);
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(REQUIRE_TEST_FILE, 'src/Untested.php');

    expect($file->getWarningCount())->toBe(1)
        ->and($file->getFixableCount())->toBe(0);
});

it('names the file, the expected path, the standard, and the boundary', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(REQUIRE_TEST_FILE, 'src/Untested.php')->getWarnings());
    $message = $messages[10][0];
    $expectedPath = fixturePath(sniffFixtureDirectory(REQUIRE_TEST_FILE), 'tests/UntestedTest.php');

    expect($message)->toContain('Untested.php has none')
        ->and($message)->toContain($expectedPath)
        ->and($message)->toContain('docs/standards/testing-development-process-tdd.md')
        ->and($message)->toContain('#57')
        ->and($message)->toContain('Existence only');
});

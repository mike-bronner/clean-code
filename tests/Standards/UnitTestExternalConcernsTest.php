<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Testing\UnitTestExternalConcernsSniff;

pest()->group('arch');

const UNIT_EXTERNAL = 'CleanCode.Testing.UnitTestExternalConcerns';

const UNIT_EXTERNAL_TRAIT = UNIT_EXTERNAL . '.DatabaseTrait';

const UNIT_EXTERNAL_FAKE = UNIT_EXTERNAL . '.FacadeFake';

const UNIT_EXTERNAL_HTTP = UNIT_EXTERNAL . '.HttpRequest';

const UNIT_EXTERNAL_FLAT_SCOPE = ['unitTestPath' => 'tests/fixtures'];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNIT_EXTERNAL);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'passing.php', UNIT_EXTERNAL_FLAT_SCOPE);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every external concern on the failing fixture', function (): void {
    $file = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'failing.php', UNIT_EXTERNAL_FLAT_SCOPE);

    expect(warningTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 8, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 9, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 10, 'column' => 36, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 11, 'column' => 47, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 22, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 24, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 35, 'column' => 12, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 36, 'column' => 14, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 37, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 38, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 39, 'column' => 21, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 40, 'column' => 14, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 41, 'column' => 16, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 42, 'column' => 41, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 47, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 48, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 49, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 50, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 51, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 52, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 53, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 54, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 55, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 56, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 57, 'column' => 17, 'source' => UNIT_EXTERNAL_HTTP],
        ['line' => 62, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 63, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

it('exercises every watched trait, facade and request method', function (string $constant): void {
    $file = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'failing.php', UNIT_EXTERNAL_FLAT_SCOPE);
    $named = [];

    foreach ($file->getWarnings() as $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                preg_match(
                        '/: (?:(\w+) stands|(\w+)::fake\(\)|\$this->(\w+)\(\))/',
                        $message['message'],
                        $matches
                    );

                $named[] = strtolower(implode('', array_slice($matches, 1)));
            }
        }
    }

    $family = (new ReflectionClassConstant(UnitTestExternalConcernsSniff::class, $constant))->getValue();

    expect($family)->not->toBe([])
        ->and($named)->not->toContain('');

    foreach ($family as $member) {
        expect($named)->toContain($member);
    }
})->with([
    'the database traits' => 'DATABASE_TRAITS',
    'the fakeable facades' => 'FAKEABLE_FACADES',
    'the HTTP-kernel methods' => 'HTTP_KERNEL_METHODS',
]);

it('reads a facade behind a relative or a rooted name', function (): void {
    $file = analyzeFixture(UNIT_EXTERNAL, 'tests/Unit/qualified-facades.php');

    expect(warningTuples($file))->toBe([
        ['line' => 13, 'column' => 23, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 14, 'column' => 41, 'source' => UNIT_EXTERNAL_FAKE],
    ]);
});

it('flags a test under the shipped unit-suite directory', function (): void {
    $file = analyzeFixture(UNIT_EXTERNAL, 'tests/Unit/external-concerns.php');

    expect(warningTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 11, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 15, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 17, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

it('leaves an identical file outside the unit suite alone', function (string $fixture): void {
    $file = analyzeFixture(UNIT_EXTERNAL, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'the feature suite' => 'tests/Feature/external-concerns.php',
    'a nested test root below the unit suite' => 'tests/Unit/tests/Feature/nested-root.php',
    'a directory that only starts with the suite name' => 'tests/UnitOfWork/whole-segment.php',
]);

it('matches the unit-suite directory case-insensitively', function (): void {
    $file = analyzeFixture(
            UNIT_EXTERNAL,
            'tests/Unit/lowercase-suite.php',
            static function (object $sniff): void {
                $sniff->unitTestPath = 'TESTS/UNIT';
            }
        );

    expect(warningTuples($file))->toBe([
        ['line' => 7, 'column' => 5, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 11, 'column' => 9, 'source' => UNIT_EXTERNAL_TRAIT],
        ['line' => 15, 'column' => 13, 'source' => UNIT_EXTERNAL_FAKE],
        ['line' => 17, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

it('skips a line the author has suppressed', function (): void {
    $file = analyzeFixture(UNIT_EXTERNAL, 'tests/Unit/suppressed.php');

    expect(warningTuples($file))->toBe([
        ['line' => 19, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ]);
});

it('inspects only what the configured unit-test path reaches', function (): void {
    $shipped = analyzeFixture(UNIT_EXTERNAL, 'failing.php');

    expect($shipped->getErrors())->toBe([])
        ->and($shipped->getWarnings())->toBe([]);

    $configured = analyzeFixtureWithRulesetProperties(UNIT_EXTERNAL, 'failing.php', UNIT_EXTERNAL_FLAT_SCOPE);

    expect($configured->getWarningCount())->toBe(28);
});

it('inspects nothing when the configured path is empty', function (): void {
    $file = analyzeFixture(
            UNIT_EXTERNAL,
            'tests/Unit/external-concerns.php',
            static function (object $sniff): void {
                $sniff->unitTestPath = '';
            }
        );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('names the sibling suite from the configured path', function (): void {
    $file = analyzeFixture(
            UNIT_EXTERNAL,
            'tests/Unit/external-concerns.php',
            static function (object $sniff): void {
                $sniff->unitTestPath = 'Tests/Unit';
            }
        );

    $messages = $file->getWarnings()[17][16];

    expect($messages[0]['message'])->toContain('Tests/Feature/');
});

it('says nothing when there is no path to read', function (): void {
    $source = (string) file_get_contents(
            fixturePath(sniffFixtureDirectory(UNIT_EXTERNAL), 'tests/Unit/external-concerns.php')
        );

    $piped = analyzeStdinSource([UNIT_EXTERNAL], $source);

    expect($piped->getErrors())->toBe([])
        ->and($piped->getWarnings())->toBe([]);
});

it('reads the test-case receiver case-sensitively', function (): void {
    $template = <<<'PHP'
        <?php

        class ReceiverCaseTest
        {
            public function testItReachesTheHttpKernel(): void
            {
                RECEIVER->getJson('/orders');
            }
        }
        PHP;

    $stage = static fn (string $receiver, string $name): string => stageProjectOutsideTests(
            ['tests/Unit/' . $name => str_replace('RECEIVER', $receiver, $template)]
        );

    $canonical = analyzeWithSniffs([UNIT_EXTERNAL], $stage('$this', 'CanonicalReceiverTest.php'));
    $miscased = analyzeWithSniffs([UNIT_EXTERNAL], $stage('$This', 'MiscasedReceiverTest.php'));

    expect(warningTuples($canonical))->toBe([
        ['line' => 7, 'column' => 16, 'source' => UNIT_EXTERNAL_HTTP],
    ])
        ->and($miscased->getErrors())->toBe([])
        ->and($miscased->getWarnings())->toBe([]);
});

it('reports the violation end to end through the installed package', function (): void {
    $fixtures = sniffFixtureDirectory(UNIT_EXTERNAL);

    $inSuite = installedSniffRun(
            UNIT_EXTERNAL,
            stageFixtureOutsideTests(fixturePath($fixtures, 'failing.php'), 'tests/Unit')
        );
    $outsideSuite = installedSniffRun(
            UNIT_EXTERNAL,
            stageFixtureOutsideTests(fixturePath($fixtures, 'failing.php'), 'tests/Feature')
        );
    $compliant = installedSniffRun(
            UNIT_EXTERNAL,
            stageFixtureOutsideTests(fixturePath($fixtures, 'passing.php'), 'tests/Unit')
        );

    expect(array_count_values(array_column($inSuite['messages'], 'source')))->toBe([
        UNIT_EXTERNAL_TRAIT => 7,
        UNIT_EXTERNAL_FAKE => 9,
        UNIT_EXTERNAL_HTTP => 12,
    ])
        ->and(array_unique(array_column($inSuite['messages'], 'type')))->toBe(['WARNING'])
        ->and($inSuite['status'])->toBe(2)
        ->and($outsideSuite['messages'])->toBe([])
        ->and($outsideSuite['status'])->toBe(0)
        ->and($compliant['messages'])->toBe([])
        ->and($compliant['status'])->toBe(0);
});

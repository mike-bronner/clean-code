<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;
use MikeBronner\CleanCode\Sniffs\Testing\NoFirstPartyMocksSniff;

const FIRST_PARTY_MOCKS = 'CleanCode.Testing.NoFirstPartyMocks';

const FIRST_PARTY_MOCKS_WARNING = FIRST_PARTY_MOCKS . '.Found';

const FIRST_PARTY_MOCK_LINES = [13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 35];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(FIRST_PARTY_MOCKS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(array_keys(violationSourcesByLine($file->getWarnings())))->toBe(FIRST_PARTY_MOCK_LINES)
        ->and(violationSourcesByLine($file->getWarnings()))
        ->each->toBe([FIRST_PARTY_MOCKS_WARNING]);
});

it('reports at the class reference rather than the mock creator', function (): void {
    expect(warningTuples(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')))
        ->toContain(['line' => 13, 'column' => 27, 'source' => FIRST_PARTY_MOCKS_WARNING])
        ->toContain(['line' => 14, 'column' => 37, 'source' => FIRST_PARTY_MOCKS_WARNING])
        ->toContain(['line' => 15, 'column' => 34, 'source' => FIRST_PARTY_MOCKS_WARNING]);
});

it('names the resolved class and the standard in the warning message', function (): void {
    $warnings = analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings();

    expect($warnings[13][27][0]['message'])
        ->toContain('App\Models\User')
        ->toContain('mock only interfaces you do not control')
        ->toContain('resources/boost/guidelines/testing-guidelines.md')
        ->and($warnings[17][21][0]['message'])->toContain('App\Services\Payments')
        ->and($warnings[19][32][0]['message'])->toContain('App\Tests\Unit\Support\Clock')
        ->and($warnings[22][36][0]['message'])->toContain('APP\Models\User')
        ->and($warnings[15][34][0]['message'])->toContain('Mocking App\Models\User,')
        ->and($warnings[24][26][0]['message'])->toContain('Mocking App\Models\User,')
        ->and($warnings[27][39][0]['message'])->toContain('Mocking App\Tests\Unit\Support\Clock,');
});

it('collapses an escaped separator in either quote style', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'escaped-literals.php');
    $messages = violationMessagesByLine($shipped->getWarnings());

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([10, 11, 15, 19])
        ->and($messages[10][0])->toContain('Mocking App\Models\User,')
        ->and($messages[11][0])->toContain('Mocking App\Models\User,')
        ->and($messages[15][0])->toContain('Mocking App\Models\User,')
        ->and($messages[19][0])->toContain('Mocking App\Services\Payments,');

    $narrowed = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'escaped-literals.php',
        ['firstPartyNamespaces' => ['App\Models']]
    );

    expect($narrowed->getErrors())->toBe([])
        ->and(array_keys($narrowed->getWarnings()))->toBe([10, 11, 15]);
});

it('ignores the empty clause a trailing comma leaves in a group import', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'group-import-trailing-comma.php');

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([13, 14, 22, 27])
        ->and(violationMessagesByLine($shipped->getWarnings())[27][0])
        ->toContain('Mocking App\Tests\Unit\Models,');

    $vendor = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'group-import-trailing-comma.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = ['Vendor'];
        }
    );

    expect($vendor->getErrors())->toBe([])
        ->and(array_keys($vendor->getWarnings()))->toBe([15, 16]);
});

it('reads a function or const keyword before it prefixes a group clause', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'group-import-mixed-keywords.php');

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([24, 25, 29, 30, 31, 32]);

    $vendor = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'group-import-mixed-keywords.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = ['Vendor'];
        }
    );

    expect($vendor->getErrors())->toBe([])
        ->and(array_keys($vendor->getWarnings()))->toBe([19]);
});

it('keeps every segment written past an import alias', function (): void {
    $shipped = analyzeFixture(FIRST_PARTY_MOCKS, 'import-resolution.php');
    $messages = violationMessagesByLine($shipped->getWarnings());

    expect($shipped->getErrors())->toBe([])
        ->and(array_keys($shipped->getWarnings()))->toBe([17, 18, 19])
        ->and($messages[17][0])->toContain('Mocking App\Models\Comment,')
        ->and($messages[18][0])->toContain('Mocking App\Models\Comment\Draft,')
        ->and($messages[19][0])->toContain('Mocking App\Enums\Status,');

    $narrowed = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'import-resolution.php',
        ['firstPartyNamespaces' => ['App\Models\Comment']]
    );

    expect($narrowed->getErrors())->toBe([])
        ->and(array_keys($narrowed->getWarnings()))->toBe([17, 18]);

    $vendor = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'import-resolution.php',
        ['firstPartyNamespaces' => ['Vendor\Sdk\Client']]
    );

    expect($vendor->getErrors())->toBe([])
        ->and(array_keys($vendor->getWarnings()))->toBe([24]);
});

it('treats a class under any configured namespace root as first-party', function (): void {
    $configured = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'configured.php',
        ['firstPartyNamespaces' => ['Vendor', 'App']]
    );

    expect($configured->getErrors())->toBe([])
        ->and(array_keys($configured->getWarnings()))->toBe([10, 11]);
});

it('is a no-op until its first-party namespaces are configured', function (): void {
    expect((new NoFirstPartyMocksSniff())->firstPartyNamespaces)->toBe([]);

    $unconfigured = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'failing.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = [];
        }
    );

    expect($unconfigured->getWarnings())->toBe([])
        ->and($unconfigured->getErrors())->toBe([])
        ->and(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings())
        ->toHaveCount(count(FIRST_PARTY_MOCK_LINES));
});

it('exposes configurable namespace and mock-creator lists', function (): void {
    expect(array_keys(analyzeFixture(FIRST_PARTY_MOCKS, 'configured.php')->getWarnings()))
        ->toBe([10]);

    $retunedNamespaces = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->firstPartyNamespaces = ['Vendor'];
        }
    );

    expect(array_keys($retunedNamespaces->getWarnings()))->toBe([11]);

    $retunedCreators = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->mockCreators = ['double'];
        }
    );

    expect(array_keys($retunedCreators->getWarnings()))->toBe([15]);
});

it('accepts its namespace list from a consuming ruleset in XML', function (): void {
    $configured = analyzeWithConfiguredRuleset(
        FIRST_PARTY_MOCKS,
        'configured.php',
        ['firstPartyNamespaces' => ['Vendor']]
    );

    expect(array_keys($configured->getWarnings()))->toBe([11]);
});

it('exposes a configurable test-file pattern that gates the whole rule', function (): void {
    $retuned = analyzeFixture(
        FIRST_PARTY_MOCKS,
        'failing.php',
        static function (object $sniff): void {
            $sniff->testFilePatterns = ['*/production/*'];
        }
    );

    expect($retuned->getWarnings())->toBe([])
        ->and($retuned->getErrors())->toBe([])
        ->and(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings())
        ->toHaveCount(count(FIRST_PARTY_MOCK_LINES));
});

it('never inspects a file outside a test path', function (): void {
    $staged = analyzeWithSniffs(
        [FIRST_PARTY_MOCKS],
        stageFixtureOutsideTests(fixturePath('NoFirstPartyMocksSniff', 'failing.php'))
    );

    expect($staged->getWarnings())->toBe([])
        ->and($staged->getErrors())->toBe([])
        ->and(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')->getWarnings())
        ->toHaveCount(count(FIRST_PARTY_MOCK_LINES));
});

it('reads imports from the file header only', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'class-body-use.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            14 => [FIRST_PARTY_MOCKS_WARNING],
        ]);
});

it('honours per-line phpcs:ignore suppression', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'suppressed.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            19 => [FIRST_PARTY_MOCKS_WARNING],
            20 => [FIRST_PARTY_MOCKS_WARNING],
        ]);
});

it('resolves self, static and parent to the class the call sits in', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'scope-keywords.php');
    $warnings = $file->getWarnings();

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            69 => [FIRST_PARTY_MOCKS_WARNING],
            70 => [FIRST_PARTY_MOCKS_WARNING],
            71 => [FIRST_PARTY_MOCKS_WARNING],
            72 => [FIRST_PARTY_MOCKS_WARNING],
        ])
        ->and($warnings[69][37][0]['message'])->toContain('Mocking App\Tests\Unit\UserServiceTest,')
        ->and($warnings[70][42][0]['message'])->toContain('Mocking App\Tests\Unit\UserServiceTest,')
        ->and($warnings[71][36][0]['message'])->toContain('Mocking App\Models\User,')
        ->and($warnings[72][36][0]['message'])->toContain('Mocking App\Tests\Unit\UserServiceTest,');
});

it('handles a truncated statement without falling over', function (string $fixture): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and(array_keys($file->getWarnings()))->toBe([7, 8]);
})->with([
    ['unterminated-member.php'],
    ['unterminated-call.php'],
    ['unterminated-class-constant.php'],
]);

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php');

    expect($file->getWarningCount())->toBe(count(FIRST_PARTY_MOCK_LINES))
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reads an unsplittable import clause as one piece', function (): void {
    $expected = allViolationSourcesByLine(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_split',
            static fn (): array => allViolationSourcesByLine(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/\s+as\s+/i'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});

it('matches an import whose whitespace cannot be collapsed', function (): void {
    $expected = allViolationSourcesByLine(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_replace',
            static fn (): array => allViolationSourcesByLine(analyzeFixture(FIRST_PARTY_MOCKS, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/\s+/'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});

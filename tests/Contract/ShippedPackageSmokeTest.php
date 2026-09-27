<?php

declare(strict_types=1);

dataset('shipped error sniffs', shippedSmokeSniffs(SWEPT_SNIFFS));

dataset('shipped warning sniffs', shippedSmokeSniffs(SWEPT_WARNING_SNIFFS));

dataset('every shipped sniff', array_merge(
    shippedSmokeSniffs(SWEPT_SNIFFS),
    shippedSmokeSniffs(SWEPT_WARNING_SNIFFS)
));

dataset('shipped smoke exclusions', SHIPPED_SMOKE_EXCLUSIONS);

it('reports an error on its failing fixture through the installed package', function (string $sniffCode): void {
    $run = installedSniffFixtureRun($sniffCode, 'failing.php');

    expect(array_column($run['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith($sniffCode . '.')
        ->and(array_unique(array_column($run['messages'], 'type')))->toBe(['ERROR'])
        ->and($run['status'])->toBe(expectedFailingStatus($sniffCode));
})->with('shipped error sniffs');

it('reports a warning on its failing fixture through the installed package', function (string $sniffCode): void {
    $run = installedSniffFixtureRun($sniffCode, 'failing.php');

    expect(array_column($run['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith($sniffCode . '.')
        ->and(array_unique(array_column($run['messages'], 'type')))->toBe(['WARNING'])
        ->and($run['status'])->toBe(expectedFailingStatus($sniffCode));
})->with('shipped warning sniffs');

it('stays silent on its passing fixture through the installed package', function (string $sniffCode): void {
    $run = installedSniffFixtureRun($sniffCode, 'passing.php');

    expect($run['messages'])->toBe([])
        ->and($run['status'])->toBe(0);
})->with('every shipped sniff');

it('sweeps every custom sniff the swept datasets carry', function (): void {
    $swept = array_values(array_filter(
        array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS),
        static fn (string $code): bool => str_starts_with($code, 'CleanCode.')
    ));

    $reached = array_merge(
        shippedSmokeSniffs(SWEPT_SNIFFS),
        shippedSmokeSniffs(SWEPT_WARNING_SNIFFS),
        SHIPPED_SMOKE_EXCLUSIONS
    );

    sort($swept);
    sort($reached);

    expect($reached)->toBe($swept)
        ->and(SHIPPED_SMOKE_EXCLUSIONS)->toBe(['CleanCode.Metrics.CyclomaticComplexity']);
});

it('keeps every excluded sniff justified', function (string $sniffCode): void {
    $failing = installedSniffFixtureRun($sniffCode, 'failing.php');
    $passing = installedSniffFixtureRun($sniffCode, 'passing.php');

    expect(array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS))->toContain($sniffCode)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0)
        ->and(array_column($failing['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith($sniffCode . '.');
})->with('shipped smoke exclusions');

it('runs against a phpcs binary that is really installed', function (): void {
    expect(is_file(cleanCodeRoot() . '/vendor/bin/phpcs'))->toBeTrue();
});

it('throws rather than reporting silence when phpcs cannot run', function (): void {
    installedPhpcsRun(
        sys_get_temp_dir() . '/cleancode-standard-that-cannot-exist.xml',
        fixturePath('ShortClassNameSniff', 'passing.php')
    );
})->throws(RuntimeException::class);

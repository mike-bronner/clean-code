<?php

declare(strict_types=1);

pest()->group('arch');

const NO_DEAD_CODE_SNIFFS = 'Squiz.PHP.CommentedOutCode,'
    . 'CleanCode.DeadCode.UnusedFormalParameter,'
    . 'SlevomatCodingStandard.Namespaces.UnusedUses,'
    . 'CleanCode.DeadCode.UnusedPrivateElements';

$noDeadCodeMessages = static function (string $fixture): array {
    return installedPhpcsRun(
        cleanCodeRoot() . '/CleanCode/ruleset.xml',
        __DIR__ . '/fixtures/' . $fixture,
        ['--sniffs=' . NO_DEAD_CODE_SNIFFS, '--extensions=inc']
    )['messages'];
};

$noDeadCodePositions = static function (string $source) use ($noDeadCodeMessages): array {
    $positions = [];

    foreach ($noDeadCodeMessages('violations.inc') as $message) {
        if ($message['source'] === $source) {
            $positions[] = [$message['line'], $message['column']];
        }
    }

    return $positions;
};

it('produces zero violations on the clean fixture', function () use ($noDeadCodeMessages): void {
    expect($noDeadCodeMessages('clean.inc'))->toBe([]);
});

it('flags commented-out code at the correct line', function () use ($noDeadCodePositions): void {
    expect($noDeadCodePositions('Squiz.PHP.CommentedOutCode.Found'))->toBe([[16, 9]]);
});

it('flags an unused private property at the correct line', function () use ($noDeadCodePositions): void {
    expect($noDeadCodePositions('CleanCode.DeadCode.UnusedPrivateElements.UnusedProperty'))->toBe([[12, 20]]);
});

it('flags an unused private method at the correct line', function () use ($noDeadCodePositions): void {
    expect($noDeadCodePositions('CleanCode.DeadCode.UnusedPrivateElements.UnusedMethod'))->toBe([[24, 22]]);
});

it('flags an unused parameter at the correct line', function () use ($noDeadCodePositions): void {
    expect($noDeadCodePositions('CleanCode.DeadCode.UnusedFormalParameter.Found'))->toBe([[14, 46]]);
});

it('flags an unused import at the correct line', function () use ($noDeadCodePositions): void {
    expect($noDeadCodePositions('SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse'))->toBe([[7, 1]]);
});

it('does not flag explanatory comments or docblocks', function () use ($noDeadCodeMessages): void {
    expect($noDeadCodeMessages('edge-cases.inc'))->toBe([]);
});

it('removes the unused import under phpcbf', function (): void {
    $staged = stageFixtureOutsideTests(__DIR__ . '/fixtures/fixable.inc');

    runOutsidePackage(implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        cleanCodeRoot() . '/vendor/bin/phpcbf',
        '--standard=' . cleanCodeRoot() . '/CleanCode/ruleset.xml',
        '--sniffs=' . NO_DEAD_CODE_SNIFFS,
        '--extensions=inc',
        '--no-cache',
        $staged,
    ])));

    expect(file_get_contents($staged))->toBe(file_get_contents(__DIR__ . '/fixtures/fixable.inc.fixed'));
});

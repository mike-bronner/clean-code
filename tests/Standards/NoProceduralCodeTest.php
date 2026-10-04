<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

pest()->group('arch');

const PROCEDURAL = 'CleanCode.Files.NoProceduralCode';

const PROCEDURAL_STATEMENT = PROCEDURAL . '.ProceduralStatement';

const PROCEDURAL_DECLARATIONS = PROCEDURAL . '.MultipleDeclarations';

$sourceRun = static fn (string $fixture, string $subdirectory = 'src') => analyzeWithSniffs(
        [PROCEDURAL],
        stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', $fixture), $subdirectory)
    );

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PROCEDURAL);
});

it('produces no violations on the compliant fixture', function () use ($sourceRun): void {
    $file = $sourceRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every top-level construct at its own line', function () use ($sourceRun): void {
    $file = $sourceRun('failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 9, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 11, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 13, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 15, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 21, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 25, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 29, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 33, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 38, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 46, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 51, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 53, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 55, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 58, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
        ]);
});

it('flags top-level code after every continuation-clause shape', function (
    string $fixture,
    array $lines
) use ($sourceRun): void {
    expect(violationTuples($sourceRun($fixture)))->toBe(array_map(
            static fn (int $line): array => ['line' => $line, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            $lines
        ));
})->with([
    'spaced else if' => ['continuation-spaced-else-if.php', [7, 17, 19]],
    'merged elseif' => ['continuation-elseif.php', [7, 15]],
    'alternative syntax' => ['continuation-alternative-syntax.php', [7, 15, 19]],
    'brace-less clauses' => ['continuation-braceless.php', [7, 14]],
]);

it('terminates on a truncated continuation clause', function (
    string $fixture,
    array $tuples
) use ($sourceRun): void {
    expect(violationTuples($sourceRun($fixture)))->toBe(array_map(
            static fn (array $tuple): array => [
                'line' => $tuple[0],
                'column' => $tuple[1],
                'source' => PROCEDURAL_STATEMENT,
            ],
            $tuples
        ));
})->with([
    'braced else' => ['truncated-else.php', [[3, 1], [5, 1]]],
    'alternative-syntax elseif' => ['truncated-elseif.php', [[3, 1], [5, 1], [6, 5]]],
    'catch' => ['truncated-catch.php', [[3, 1], [5, 1]]],
    'finally' => ['truncated-finally.php', [[3, 1], [5, 1]]],
]);

it('flags a file opened with a short echo tag', function () use ($sourceRun): void {
    expect(violationTuples($sourceRun('short-echo-tag.php')))->toBe([
        ['line' => 1, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
    ]);
});

it('reports a purely procedural file the whole Slevomat standard passes', function () use ($sourceRun): void {
    $slevomatSources = static function (LocalFile $file): array {
        $sources = [];

        foreach (allViolationSourcesByLine($file) as $line => $lineSources) {
            foreach ($lineSources as $source) {
                if (str_starts_with($source, 'SlevomatCodingStandard.') === true) {
                    $sources[$line][] = $source;
                }
            }
        }

        return $sources;
    };

    $staged = stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', 'slevomat-clean.php'), 'src');
    $control = stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', 'no-declaration.php'), 'src');

    expect($slevomatSources(analyzeWithStandard('SlevomatCodingStandard', $staged)))->toBe([])
        ->and($slevomatSources(analyzeWithMasterRuleset($staged)))->toBe([])
        ->and($slevomatSources(analyzeWithStandard('SlevomatCodingStandard', $control)))->toBe([
            3 => ['SlevomatCodingStandard.TypeHints.DeclareStrictTypes.IncorrectStrictTypesFormat'],
            7 => ['SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified'],
            11 => ['SlevomatCodingStandard.ControlStructures.RequireYodaComparison.RequiredYodaComparison'],
        ])
        ->and(violationTuples($sourceRun('slevomat-clean.php')))->toBe([
            ['line' => 7, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 9, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
        ]);
});

it('names the offending construct in the message', function () use ($sourceRun): void {
    $errors = $sourceRun('failing.php')->getErrors();

    expect($errors[15][1][0]['message'])->toContain('"if"')
        ->and($errors[13][1][0]['message'])->toContain('"$user"')
        ->and($errors[51][1][0]['message'])->toContain('"present()"')
        ->and($errors[53][1][0]['message'])->toContain('"echo"')
        ->and($errors[58][1][0]['message'])->toContain('markup');
});

it('passes over declaration modifiers and a trailing empty statement', function () use ($sourceRun): void {
    $file = $sourceRun('modifiers.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('passes over the whitespace a closing tag leaves behind', function () use ($sourceRun): void {
    $file = $sourceRun('closing-tag.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('stops at a truncated construct without misreading it', function (
    string $fixture,
    int $line
) use ($sourceRun): void {
    expect(violationTuples($sourceRun($fixture)))->toBe([
        ['line' => $line, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
    ]);
})->with([
    ['unterminated.php', 5],
    ['unterminated-namespace.php', 3],
]);

it('reports a purely procedural file that PSR-1 passes', function () use ($sourceRun): void {
    $staged = stageFixtureOutsideTests(
            fixturePath('NoProceduralCodeSniff', 'no-declaration.php'),
            'src'
        );

    $sideEffects = analyzeWithSniffs(['PSR1.Files.SideEffects'], $staged);

    expect($sideEffects->getErrors())->toBe([])
        ->and($sideEffects->getWarnings())->toBe([])
        ->and(violationTuples($sourceRun('no-declaration.php')))->toBe([
            ['line' => 9, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 11, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
        ]);
});

it('flags every declaration after the first', function () use ($sourceRun): void {
    $file = $sourceRun('multiple-declarations.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 11, 'column' => 7, 'source' => PROCEDURAL_DECLARATIONS],
            ['line' => 15, 'column' => 1, 'source' => PROCEDURAL_DECLARATIONS],
            ['line' => 19, 'column' => 1, 'source' => PROCEDURAL_DECLARATIONS],
        ]);
});

it('names the additional declaration in the message', function () use ($sourceRun): void {
    $errors = $sourceRun('multiple-declarations.php')->getErrors();

    expect($errors[11][7][0]['message'])->toContain('class UserPresenter')
        ->and($errors[19][1][0]['message'])->toContain('enum Tone');
});

it('reads the body of a braced namespace as the top level', function () use ($sourceRun): void {
    expect(violationTuples($sourceRun('braced-namespace.php')))->toBe([
        ['line' => 6, 'column' => 5, 'source' => PROCEDURAL_STATEMENT],
    ]);
});

it('flags a file that is nothing but markup', function () use ($sourceRun): void {
    expect(violationTuples($sourceRun('markup-only.php')))->toBe([
        ['line' => 1, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
    ]);
});

it('leaves a file with no code alone', function (string $fixture) use ($sourceRun): void {
    $file = $sourceRun($fixture);
    $warnings = [];

    foreach (violationSourcesByLine($file->getWarnings()) as $sources) {
        $warnings = array_merge($warnings, $sources);
    }

    $ours = array_filter($warnings, static fn (string $source): bool => str_starts_with($source, PROCEDURAL));

    expect($file->getErrors())->toBe([])
        ->and(array_values($ours))->toBe([]);
})->with(['empty.php', 'comments-only.php']);

it('is scoped to source directories', function () use ($sourceRun): void {
    expect($sourceRun('failing.php', 'src')->getErrorCount())->toBe(14)
        ->and($sourceRun('failing.php', 'app')->getErrorCount())->toBe(14)
        ->and($sourceRun('failing.php', 'config')->getErrorCount())->toBe(0)
        ->and($sourceRun('failing.php', '')->getErrorCount())->toBe(0);
});

it('reports detection-only errors', function () use ($sourceRun): void {
    $file = $sourceRun('failing.php');

    expect($file->getErrorCount())->toBe(14)
        ->and($file->getWarningCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, 14, false));
});

it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('NoProceduralCodeSniff', 'failing.php');

    $inSource = installedSniffRun(PROCEDURAL, stageFixtureOutsideTests($failing, 'src'));
    $outsideSource = installedSniffRun(PROCEDURAL, stageFixtureOutsideTests($failing, 'config'));
    $passing = installedSniffRun(
            PROCEDURAL,
            stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', 'passing.php'), 'src')
        );

    expect(array_column($inSource['messages'], 'source'))->toHaveCount(14)
        ->each->toStartWith(PROCEDURAL . '.')
        ->and(array_unique(array_column($inSource['messages'], 'type')))->toBe(['ERROR'])
        ->and($inSource['status'])->toBe(2)
        ->and($outsideSource['messages'])->toBe([])
        ->and($outsideSource['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});

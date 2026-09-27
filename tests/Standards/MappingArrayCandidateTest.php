<?php

declare(strict_types=1);

const MAPPING_ARRAY_CANDIDATE = 'CleanCode.Conditionals.MappingArrayCandidate';

const MAPPING_ARRAY_CANDIDATE_CHAIN = MAPPING_ARRAY_CANDIDATE . '.IfChain';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MAPPING_ARRAY_CANDIDATE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns once per qualifying chain, at its leading if', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 32, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 46, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 60, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 75, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 91, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(6);
});

it('names the branch count and the subject variable', function (): void {
    $messages = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php')->getWarnings();

    expect($messages[20][9][0]['message'])->toContain('3 branches')->toContain('"$code"')
        ->and($messages[75][9][0]['message'])->toContain('4 branches')->toContain('"$kind"');
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'failing.php');

    expect($file->getWarningCount())->toBe(6)
        ->and($file->getFixableCount())->toBe(0);
});

it('warns once on every continuation shape', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 28, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 39, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 49, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 59, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 70, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 83, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 94, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 108, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 127, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
        ['line' => 150, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ]);
});

it('judges a nested chain on its own bodies, not its parent', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'shapes.php');

    expect(array_keys($file->getWarnings()))->not->toContain(122);
});

it('terminates silently on a truncated chain', function (string $fixture): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, $fixture);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
})->with([
    'truncated-braced.php',
    'truncated-alternative.php',
    'truncated-braceless.php',
    'truncated-value.php',
]);

it('stays linear on deeply nested chains', function (): void {
    $levels = 2400;
    [$source, $chainLine] = nestedChainFixture($levels);
    $fixture = stageGeneratedFixture('nested.php', $source);

    $sniff = sniffInstance(MAPPING_ARRAY_CANDIDATE);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([MAPPING_ARRAY_CANDIDATE], $fixture);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect(warningTuples($file))->toBe([
        ['line' => $chainLine, 'column' => 9, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ])
        ->and($counted['braceless.headRefusals'])->toBe(
            ($levels - 1),
            'every nested level but the innermost is decided on its first token'
        )
        ->and($counted['braceless.endScans'])->toBe(
            1,
            'only the innermost body, which is a statement head, is walked to its end'
        );
});

it('stays silent on a chain below the default minimum', function (): void {
    $file = analyzeFixture(MAPPING_ARRAY_CANDIDATE, 'threshold.php');

    expect($file->getWarnings())->toBe([]);
});

it('reports that same chain once minimumBranches is lowered', function (): void {
    $file = analyzeFixture(
        MAPPING_ARRAY_CANDIDATE,
        'threshold.php',
        static function (object $sniff): void {
            $sniff->minimumBranches = '2';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 5, 'source' => MAPPING_ARRAY_CANDIDATE_CHAIN],
    ]);
});

it('leaves the chain to no other sniff in the ruleset', function (): void {
    $sources = allViolationSourcesByLine(
        analyzeWithMasterRuleset(fixturePath('MappingArrayCandidateSniff', 'failing.php'))
    );

    $atChainHeads = array_intersect_key($sources, array_flip([20, 32, 46, 60, 75, 91]));

    expect($atChainHeads)->toHaveCount(6);

    foreach ($atChainHeads as $line => $reported) {
        expect($reported)->toBe(
            ['CleanCode.Conditionals.AvoidConditionals.IfStatement', MAPPING_ARRAY_CANDIDATE_CHAIN],
            'unexpected sources on line ' . $line
        );
    }
});

it('registers on if alone, so switch and match are never inspected', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[MAPPING_ARRAY_CANDIDATE]];

    expect($sniff->register())->toBe([T_IF]);
});

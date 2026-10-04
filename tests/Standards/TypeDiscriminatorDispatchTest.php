<?php

declare(strict_types=1);

const TYPE_DISCRIMINATOR_DISPATCH = 'CleanCode.Conditionals.TypeDiscriminatorDispatch';

const TYPE_DISCRIMINATOR_SWITCH = TYPE_DISCRIMINATOR_DISPATCH . '.SwitchDispatch';

const TYPE_DISCRIMINATOR_IF = TYPE_DISCRIMINATOR_DISPATCH . '.IfChain';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TYPE_DISCRIMINATOR_DISPATCH);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('stays silent on every near-miss shape', function (int $line): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'passing.php');

    expect(array_keys($file->getWarnings()))->not->toContain($line);
})->with([
    'plain-variable switch subject' => 74,
    'plain-variable if discriminator' => 86,
    'class constant as a case label' => 97,
    'bare constant as a case label' => 109,
    'variable as a case label' => 121,
    'variable as an if condition operand' => 144,
    'class constant as an if condition operand' => 162,
    'bare constant as an if condition operand' => 179,
    'non-literal operand written on the left' => 201,
    'compound boolean condition' => 214,
    'instanceof condition' => 225,
    'range condition' => 236,
    'not-identical condition' => 247,
    'called discriminator' => 258,
    'parenthesised condition' => 269,
    'two-case switch' => 280,
    'stacked pair below the threshold' => 292,
    'two-branch if chain' => 303,
    'match expression' => 312,
    'same property on two variables' => 321,
    'switch (true)' => 332,
    'index read two hops deep, switch' => 347,
    'index read two hops deep, if' => 362,
    'property read two hops deep, switch' => 373,
    'property read two hops deep, if' => 385,
    'positional index' => 399,
    'static property read' => 411,
    'two braced constructs merely adjacent' => 428,
    'three brace-less constructs merely adjacent' => 449,
    'nested brace-less if taking the continuations' => 464,
    'nested braced if taking the continuations' => 484,
    'nested if behind a brace-less loop' => 506,
    'nested if two brace-less loops in' => 529,
    'a swallowed clause on another discriminator' => 553,
    'a swallowed clause after a brace-less do/while' => 578,
]);

it('warns once per qualifying construct, at its head', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 56, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 73, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 85, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 99, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 112, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 123, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 135, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 149, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 166, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 182, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 210, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 233, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 256, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 276, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 300, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 322, 'column' => 9, 'source' => TYPE_DISCRIMINATOR_IF],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(16);
});

it('names the principle and interpolates the discriminator', function (int $line, string $discriminator): void {
    $warnings = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php')->getWarnings();

    expect($warnings[$line][9][0]['message'])
        ->toContain('Open-Closed')
        ->toContain('"' . $discriminator . '"');
})->with([
    'switch on a property' => [56, '$shape->type'],
    'switch on an index' => [73, "\$row['type']"],
    'if on a property' => [85, '$shape->type'],
    'if on an index' => [99, "\$row['type']"],
    'nullsafe property read' => [135, '$shape?->type'],
]);

it('counts each case label and the default as one branch', function (int $line): void {
    $warnings = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php')->getWarnings();

    expect($warnings[$line][9][0]['message'])->toContain('3 branches');
})->with([
    'stacked labels sharing one body' => 112,
    'default written first' => 123,
]);

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'failing.php');

    expect($file->getWarningCount())->toBe(16)
        ->and($file->getFixableCount())->toBe(0);
});

it('warns once on every continuation spelling', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 23, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 34, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 45, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 55, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
        ['line' => 66, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 85, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
    ]);
});

it('reports each nested switch on its own arms alone', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'nested-switch.php');

    expect(warningTuples($file))->toBe([
        ['line' => 18, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 20, 'column' => 13, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 45, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 51, 'column' => 13, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 68, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 70, 'column' => 13, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 72, 'column' => 21, 'source' => TYPE_DISCRIMINATOR_SWITCH],
    ]);
});

it('counts only its own arms at every level of nesting', function (int $line, int $branches): void {
    $warnings = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'nested-switch.php')->getWarnings();
    $column = array_key_first($warnings[$line]);

    expect($warnings[$line][$column][0]['message'])->toContain($branches . ' branches');
})->with([
    'outer of an outer/nested pair' => [18, 3],
    'nested of an outer/nested pair' => [20, 5],
    'outer, nested switch closing last' => [45, 3],
    'nested written as the final arm' => [51, 4],
    'outermost of three levels' => [68, 3],
    'middle of three levels' => [70, 4],
    'innermost of three levels' => [72, 5],
]);

it('terminates silently on a file it cannot parse', function (string $fixture): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, $fixture);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
})->with([
    'malformed-subject.php',
    'malformed-case.php',
    'malformed-default.php',
    'truncated-switch.php',
    'truncated-braced.php',
    'truncated-braceless.php',
    'truncated-alternative.php',
    'truncated-block.php',
    'truncated-endif.php',
    'truncated-nested-switch.php',
]);

it('stays silent on constructs below the default minimum', function (): void {
    $file = analyzeFixture(TYPE_DISCRIMINATOR_DISPATCH, 'threshold.php');

    expect($file->getWarnings())->toBe([]);
});

it('reports those same constructs once minimumBranches is lowered', function (): void {
    $file = analyzeFixture(
            TYPE_DISCRIMINATOR_DISPATCH,
            'threshold.php',
            static function (object $sniff): void {
                $sniff->minimumBranches = '2';
            }
        );

    expect(warningTuples($file))->toBe([
        ['line' => 15, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_SWITCH],
        ['line' => 27, 'column' => 5, 'source' => TYPE_DISCRIMINATOR_IF],
    ]);
});

it('ships minimumBranches at three', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TYPE_DISCRIMINATOR_DISPATCH]];

    expect($sniff->minimumBranches)->toBe(3);
});

it('stays linear on a deep stack of nested brace-less clauses', function (): void {
    $levels = 4000;
    $source = "<?php\n\nfunction deeplyNested(object \$shape): int\n{\n"
        . str_repeat("    if (\$shape->type === 'circle')\n", $levels)
        . "    return 1;\n}\n";
    $fixture = stageGeneratedFixture('nested-braceless.php', $source);

    $sniff = sniffInstance(TYPE_DISCRIMINATOR_DISPATCH);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([TYPE_DISCRIMINATOR_DISPATCH], $fixture);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect($file->getWarnings())->toBe([])
        ->and($counted['bracelessNextClause.walks'])->toBe(
            $levels,
            'every nested clause is dispatched as a chain head of its own'
        )
        ->and($counted['bracelessNextClause.steps'])->toBe(
            ($levels + 2),
            'each walk stops one token into its body, the innermost `return 1;` in three'
        );
});

it('registers on switch and if alone, so match is never inspected', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TYPE_DISCRIMINATOR_DISPATCH]];

    expect($sniff->register())->toBe([T_SWITCH, T_IF]);
});

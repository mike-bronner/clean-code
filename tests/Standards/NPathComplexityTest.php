<?php

declare(strict_types=1);

const NPATH_COMPLEXITY = 'CleanCode.Metrics.NPathComplexity';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NPATH_COMPLEXITY);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('measures each counting rule exactly as PHPMD does', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))->toBe([
        'atOneBelowTheMinimum' => 199,
        'uncountedConstructs' => 2,
        'abstractMethod' => 1,
        'anonymousClassBody' => 1,
        'inlineClosures' => 2,
        'doWhileLoop' => 2,
        'tryBlocksSum' => 3,
        'elseIfChain' => 3,
        'alternativeSyntax' => 4,
        'nestedNamedFunction' => 2,
        'nestedInner' => 2,
        'forIgnoresInitAndUpdateClauses' => 2,
        'forCountsItsConditionClause' => 4,
        'standaloneWhileLoop' => 3,
        'shortTernaryDoublesItsCondition' => 4,
        'logicalKeywordOperators' => 5,
        'throwYieldAndContinue' => 3,
        'matchArmTernaryMultiplies' => 2,
        'matchArmBooleanIsUncounted' => 1,
        'ternaryConditionStopsAtTheFirstOperator' => 5,
        'ternaryConditionIncludesAParenthesisedGroup' => 6,
        'returnTernaryCountsItsConditionTwice' => 4,
        'assignedTernaryCountsItsConditionOnce' => 3,
        'closureInStatementPositionIsWalked' => 8,
        'closureInExpressionPositionIsNotWalked' => 1,
        'matchArmBooleanCountsInACondition' => 4,
        'matchArmBooleanCountsInAReturn' => 2,
        'switchWithAMatchSubject' => 3,
        'switchWithAMatchSubjectAndNoBoolean' => 2,
        'alternativeSyntaxSwitchWithAMatchSubject' => 3,
        'bracelessDoWrappingALoop' => 4,
        'bracelessIfWrappingALoop' => 3,
        'bracelessWhileWrappingAnIf' => 3,
        'bracelessForWrappingAnIf' => 3,
        'bracelessForeachWrappingAnIf' => 3,
        'bracelessElseWrappingALoop' => 3,
        'bracelessIfWrappingABracelessIf' => 3,
        'nestedSwitchInACaseBody' => 3,
        'bracelessIfWithABuriedTernary' => 3,
        'bracedIfWithABuriedTernary' => 3,
        'bracelessIfWithATernaryAssigned' => 3,
        'bracedIfWithATernaryAssigned' => 3,
        'bracelessWhileWithABuriedTernary' => 3,
        'bracedWhileWithABuriedTernary' => 3,
        'bracelessForWithABuriedTernary' => 3,
        'bracedForWithABuriedTernary' => 3,
        'bracelessForeachWithABuriedTernary' => 3,
        'bracedForeachWithABuriedTernary' => 3,
        'bracelessDoWithABuriedTernary' => 3,
        'bracedDoWithABuriedTernary' => 3,
        'bracelessElseWithABuriedTernary' => 3,
        'bracedElseWithABuriedTernary' => 3,
        'bracelessElseIfWithABuriedTernary' => 4,
        'bracedElseIfWithABuriedTernary' => 4,
        'bracelessIfWithTwoBuriedTernaries' => 5,
        'bracedIfWithTwoBuriedTernaries' => 5,
        'bracelessIfWithABuriedBooleanTernary' => 4,
        'bracedIfWithABuriedBooleanTernary' => 4,
        'bracelessIfElseBothWithBuriedTernaries' => 4,
        'bracedIfElseBothWithBuriedTernaries' => 4,
        'bracelessIfWrappingABracelessIfWithABuriedTernary' => 4,
        'bracedIfWrappingABracedIfWithABuriedTernary' => 4,
        'bracelessIfWithABuriedTernaryEchoed' => 3,
        'bracedIfWithABuriedTernaryEchoed' => 3,
        'bracelessBodyFollowedByAnotherStatement' => 6,
        'bracedBodyFollowedByAnotherStatement' => 6,
        'bracelessBodyHoldingAClosure' => 3,
        'bracedBodyHoldingAClosure' => 3,
    ]);
});

it('measures a braceless body of every construct that can own one', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    $measured = measuredNPathComplexities($file);

    expect($measured)
        ->toHaveKeys([
            'bracelessDoWrappingALoop',
            'bracelessIfWrappingALoop',
            'bracelessWhileWrappingAnIf',
            'bracelessForWrappingAnIf',
            'bracelessForeachWrappingAnIf',
            'bracelessElseWrappingALoop',
            'bracelessIfWrappingABracelessIf',
        ])
        ->and($measured['bracelessDoWrappingALoop'])->toBe(4)
        ->and($measured['bracelessIfWrappingALoop'])->toBe(3)
        ->and($measured['bracelessWhileWrappingAnIf'])->toBe(3)
        ->and($measured['bracelessForWrappingAnIf'])->toBe(3)
        ->and($measured['bracelessForeachWrappingAnIf'])->toBe(3)
        ->and($measured['bracelessElseWrappingALoop'])->toBe(3)
        ->and($measured['bracelessIfWrappingABracelessIf'])->toBe(3);
});

it('measures a braceless body the same as a braced one when its token is buried', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    $measured = measuredNPathComplexities($file);

    $pairs = [
        'IfWithABuriedTernary' => 3,
        'IfWithATernaryAssigned' => 3,
        'WhileWithABuriedTernary' => 3,
        'ForWithABuriedTernary' => 3,
        'ForeachWithABuriedTernary' => 3,
        'DoWithABuriedTernary' => 3,
        'ElseWithABuriedTernary' => 3,
        'ElseIfWithABuriedTernary' => 4,
        'IfWithTwoBuriedTernaries' => 5,
        'IfWithABuriedBooleanTernary' => 4,
        'IfElseBothWithBuriedTernaries' => 4,
        'IfWithABuriedTernaryEchoed' => 3,
    ];

    foreach ($pairs as $shape => $expected) {
        $braceless = 'braceless' . $shape;
        $braced = 'braced' . $shape;

        expect($measured)->toHaveKeys([$braceless, $braced])
            ->and($measured[$braceless])->toBe($expected)
            ->and($measured[$braced])->toBe($expected);
    }

    expect($measured)->toHaveKeys([
        'bracelessIfWrappingABracelessIfWithABuriedTernary',
        'bracedIfWrappingABracedIfWithABuriedTernary',
    ])
        ->and($measured['bracelessIfWrappingABracelessIfWithABuriedTernary'])->toBe(4)
        ->and($measured['bracedIfWrappingABracedIfWithABuriedTernary'])->toBe(4);

    expect($measured)->toHaveKeys([
        'bracelessBodyFollowedByAnotherStatement',
        'bracedBodyFollowedByAnotherStatement',
        'bracelessBodyHoldingAClosure',
        'bracedBodyHoldingAClosure',
    ])
        ->and($measured['bracelessBodyFollowedByAnotherStatement'])->toBe(6)
        ->and($measured['bracedBodyFollowedByAnotherStatement'])->toBe(6)
        ->and($measured['bracelessBodyHoldingAClosure'])->toBe(3)
        ->and($measured['bracedBodyHoldingAClosure'])->toBe(3);
});

it('keeps a nested switch\'s labels out of the enclosing switch', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))->toHaveKey('nestedSwitchInACaseBody')
        ->and(measuredNPathComplexities($file)['nestedSwitchInACaseBody'])->toBe(3);
});

it('never reports a method declared in an interface', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))->not->toHaveKey('interfaceMethod');
});

it('scores a switch with no labels as zero, matching PDepend', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 0;
    });

    expect(measuredNPathComplexities($file))->toHaveKey('labellessSwitch')
        ->and(measuredNPathComplexities($file)['labellessSwitch'])->toBe(0);
});

it('finds the labels of a switch the tokenizer built no scope for', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(measuredNPathComplexities($file))
        ->toHaveKeys([
            'switchWithAMatchSubject',
            'switchWithAMatchSubjectAndNoBoolean',
            'alternativeSyntaxSwitchWithAMatchSubject',
        ])
        ->and(measuredNPathComplexities($file)['switchWithAMatchSubject'])->toBe(3)
        ->and(measuredNPathComplexities($file)['switchWithAMatchSubjectAndNoBoolean'])->toBe(2)
        ->and(measuredNPathComplexities($file)['alternativeSyntaxSwitchWithAMatchSubject'])->toBe(3);
});

it('flags every over-complex callable in the failing fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 22, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 66, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 115, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 159, 'column' => 1, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
        ['line' => 227, 'column' => 12, 'source' => NPATH_COMPLEXITY . '.MinimumExceeded'],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports the same values PHPMD reports on the failing fixture', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(measuredNPathComplexities($file))->toBe([
        'multipliesSequentialBranches' => 256,
        'switchLabelsMultiply' => 200,
        'keywordXorAndReturnChain' => 204,
        'closureBodiesBelongToTheEnclosingCallable' => 207,
        'elseIfWithSpace' => 204,
    ]);
});

it('reports at the minimum and stays silent one below it', function (): void {
    $failing = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');
    $passing = analyzeFixture(NPATH_COMPLEXITY, 'passing.php');

    expect(measuredNPathComplexities($failing))->toHaveKey('switchLabelsMultiply')
        ->and(measuredNPathComplexities($failing)['switchLabelsMultiply'])->toBe(200)
        ->and(measuredNPathComplexities($passing))->toBe([]);
});

it('treats the minimum as inclusive at any configured value', function (): void {
    $atValue = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 199;
    });
    $aboveValue = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 200;
    });

    expect(measuredNPathComplexities($atValue))->toBe(['atOneBelowTheMinimum' => 199])
        ->and(measuredNPathComplexities($aboveValue))->toBe([]);
});

it('accepts the minimum from a ruleset property', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        NPATH_COMPLEXITY,
        'passing.php',
        ['minimum' => '199']
    );

    expect(measuredNPathComplexities($file))->toBe(['atOneBelowTheMinimum' => 199]);
});

it('reports without offering a fix', function (): void {
    $file = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');

    expect(violationFixableFlags($file))->not->toContain(true);
});

it('names the callable kind the way PHPMD does', function (): void {
    $functions = analyzeFixture(NPATH_COMPLEXITY, 'failing.php');
    $methods = analyzeFixture(NPATH_COMPLEXITY, 'passing.php', function ($sniff): void {
        $sniff->minimum = 1;
    });

    expect(violationMessages($functions))->toContain(
        'The function multipliesSequentialBranches() has an NPath complexity of 256, at or '
            . 'above the configured minimum of 200; break it into smaller pieces (see '
            . 'docs/phpmd/codesize-npathcomplexity.md)'
    )->and(violationMessages($methods))->toContain(
        'The method abstractMethod() has an NPath complexity of 1, at or above the configured '
            . 'minimum of 1; break it into smaller pieces (see '
            . 'docs/phpmd/codesize-npathcomplexity.md)'
    );
});

it('saturates instead of overflowing on an astronomically branching callable', function (): void {
    $fixture = stageGeneratedFixture('overflow.php', sequentialBranchFixture(70));

    $file = analyzeWithSniffs([NPATH_COMPLEXITY], $fixture);

    expect(violationMessages($file))->toBe([
        'The function manyBranches() has an NPath complexity of at least ' . PHP_INT_MAX
            . ', at or above the configured minimum of 200; break it into smaller pieces '
            . '(see docs/phpmd/codesize-npathcomplexity.md)',
    ]);
});

it('stays linear on a long chain of ternaries', function (): void {
    $links = 4800;
    $fixture = stageGeneratedFixture('ternaries.php', ternaryChainFixture($links));

    $sniff = sniffInstance(NPATH_COMPLEXITY);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([NPATH_COMPLEXITY], $fixture);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect(measuredNPathComplexities($file))->toBe(['chainedTernaries' => (2 * $links)])
        ->and($counted['expressionEnd.scans'])->toBe(
            1,
            'the whole chain resolves in one forward walk, not one per link'
        )
        ->and($counted['expressionEnd.hits'])->toBe(
            ($links - 1),
            'every later link answers from the terminator that walk recorded'
        );
});

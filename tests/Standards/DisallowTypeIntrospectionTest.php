<?php

declare(strict_types=1);

const TYPE_INTROSPECTION_SNIFF = 'CleanCode.Classes.DisallowTypeIntrospection';

const TYPE_INTROSPECTION_INSTANCEOF = TYPE_INTROSPECTION_SNIFF . '.InstanceOf';

const TYPE_INTROSPECTION_FUNCTION = TYPE_INTROSPECTION_SNIFF . '.IntrospectionFunction';

const TYPE_INTROSPECTION_VIOLATIONS = [
    [11, 20],
    [13, 26],
    [22, 23],
    [28, 20],
    [29, 20],
    [29, 50],
    [37, 25],
    [48, 23],
    [59, 29],
    [68, 35],
    [83, 24],
    [93, 53],
    [100, 24],
    [110, 29],
    [125, 20],
    [138, 78],
    [150, 81],
    [168, 27],
    [175, 20],
    [187, 60],
    [208, 35],
    [227, 31],
];

const TYPE_INTROSPECTION_INLINE_METHOD_VIOLATIONS = [
    [31, 28, TYPE_INTROSPECTION_INSTANCEOF],
    [52, 31, TYPE_INTROSPECTION_INSTANCEOF],
    [70, 28, TYPE_INTROSPECTION_INSTANCEOF],
    [90, 33, TYPE_INTROSPECTION_INSTANCEOF],
    [111, 24, TYPE_INTROSPECTION_FUNCTION],
    [133, 21, TYPE_INTROSPECTION_FUNCTION],
    [159, 20, TYPE_INTROSPECTION_INSTANCEOF],
    [183, 20, TYPE_INTROSPECTION_INSTANCEOF],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TYPE_INTROSPECTION_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('produces no violations on the polymorphic rewrite', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'polymorphic-rewrite.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags instanceof driving a branch at its exact position', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'failing.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => TYPE_INTROSPECTION_INSTANCEOF,
            ],
            TYPE_INTROSPECTION_VIOLATIONS
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('flags introspection functions driving a branch at their exact position', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'introspection-functions.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => TYPE_INTROSPECTION_FUNCTION,
            ],
            [
                [11, 13],
                [20, 16],
                [25, 17],
                [36, 13],
                [37, 13],
                [44, 13],
                [53, 13],
                [64, 16],
                [76, 18],
                [89, 23],
                [104, 13],
            ]
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('names the offending call in the message', function (): void {
    $errors = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'introspection-functions.php')->getErrors();

    expect($errors[11][13][0]['message'])->toContain('get_class()')
        ->and($errors[37][13][0]['message'])->toContain('is_subclass_of()');
});

it('does not flag a check inside any function body written in a condition', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'function-scopes.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('still flags a branch inside that same function body', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'function-scope-branches.php');

    $expected = array_map(
            static fn (array $violation): array => [
                'line' => $violation[0],
                'column' => $violation[1],
                'source' => $violation[2],
            ],
            TYPE_INTROSPECTION_INLINE_METHOD_VIOLATIONS
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

const TYPE_INTROSPECTION_HOOK_VIOLATIONS = [
    [51, 30],
    [60, 29],
    [66, 30],
    [75, 35],
    [91, 42],
    [145, 20],
    [174, 38],
];

it('confines a branch check to the property hook body it is written in', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'property-hooks.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => TYPE_INTROSPECTION_INSTANCEOF,
            ],
            TYPE_INTROSPECTION_HOOK_VIOLATIONS
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('does not treat an imported name as the global introspection function', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'shadowed-by-import.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => TYPE_INTROSPECTION_FUNCTION,
            ],
            [
                [64, 13],
                [77, 13],
            ]
        );

    expect(violationTuples($file))->toBe($expected);
});

it('does not treat a name declared as a function in the file as the global one', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'shadowed-by-declaration.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => TYPE_INTROSPECTION_FUNCTION,
            ],
            [
                [37, 13],
                [51, 13],
                [69, 13],
            ]
        );

    expect(violationTuples($file))->toBe($expected);
});

it('stays linear on long boolean chains of checks', function (): void {
    $links = 2400;
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'final class Chain', '{'];
    $lines[] = '    public function decide(object $value): string';
    $lines[] = '    {';

    $chainStart = count($lines) + 1;
    $lines[] = '        return $value instanceof Thing';

    for ($link = 1; $link < $links; $link++) {
        $lines[] = '            || $value instanceof Thing';
    }

    $lines[] = '            ? \'a\'';
    $lines[] = '            : \'b\';';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    public function describe(object $value): bool';
    $lines[] = '    {';
    $lines[] = '        return $value instanceof Thing';

    for ($link = 1; $link < $links; $link++) {
        $lines[] = '            || $value instanceof Thing';
    }

    $lines = array_merge($lines, ['            || $value instanceof Other;', '    }', '}', '']);
    $fixture = stageGeneratedFixture('boolean-chain.php', implode("\n", $lines));

    $sniff = sniffInstance(TYPE_INTROSPECTION_SNIFF);
    $before = $sniff->cacheCounts();
    $file = analyzeWithSniffs([TYPE_INTROSPECTION_SNIFF], $fixture);
    $counted = cacheCountsDelta($before, $sniff->cacheCounts());

    $expected = [];

    for ($link = 0; $link < $links; $link++) {
        $expected[] = [
            'line' => $chainStart + $link,
            'column' => 23,
            'source' => TYPE_INTROSPECTION_INSTANCEOF,
        ];
    }

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([])
        ->and($counted['ternaryDecisions.builds'])->toBe(
            1,
            'the forward scans resolve in one backward pass over the file, not one per check'
        )
        ->and($counted['ternaryDecisions.hits'])->toBe(
            (2 * $links),
            'every check after the first answers from that one pass'
        );
});

it('resolves the scope of a check in every one of many sibling bodies', function (): void {
    $methods = 50;
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'final class Dispatcher', '{'];
    $expected = [];

    for ($method = 0; $method < $methods; $method++) {
        $lines[] = "    public function decide{$method}(object \$value, array \$rows): string";
        $lines[] = '    {';

        $lines[] = '        if (array_filter($rows, fn (object $row): bool => $row instanceof Thing)) {';
        $lines[] = "            return 'rows';";
        $lines[] = '        }';
        $lines[] = '';
        $expected[] = ['line' => count($lines) + 1, 'column' => 20, 'source' => TYPE_INTROSPECTION_INSTANCEOF];
        $lines[] = '        if ($value instanceof Thing) {';
        $lines[] = "            return 'value';";
        $lines[] = '        }';
        $lines[] = '';
        $lines[] = "        return 'none';";
        $lines[] = '    }';
        $lines[] = '';
    }

    $lines = array_merge($lines, ['}', '']);
    $fixture = stageGeneratedFixture('sibling-bodies.php', implode("\n", $lines));
    $file = analyzeWithSniffs([TYPE_INTROSPECTION_SNIFF], $fixture);

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('keeps its indexes from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        use function Vendor\get_class;

        $label = get_class($value) ? 'one' : 'two';

        PHP;

    $sourceB = <<<'PHP'
        <?php

        use function Vendor\str_repeat;

        $label = get_class($value) ? 'one' : 'two';

        PHP;

    $first = analyzeStdinSource([TYPE_INTROSPECTION_SNIFF], $sourceA);
    $second = analyzeStdinSource([TYPE_INTROSPECTION_SNIFF], $sourceB);
    $third = analyzeStdinSource([TYPE_INTROSPECTION_SNIFF], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($first->getErrors()))->toBe([])
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 5, 'column' => 10, 'source' => TYPE_INTROSPECTION_FUNCTION],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([]);
});

it('builds its indexes once per stream, not once per read', function (): void {
    $sniff = sniffInstance(TYPE_INTROSPECTION_SNIFF);

    foreach ([2, 4, 8] as $checks) {
        $source = "<?php\n\n";

        for ($check = 0; $check < $checks; $check++) {
            $source .= "\$label{$check} = get_class(\$value) ? 'one' : 'two';\n";
        }

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([TYPE_INTROSPECTION_SNIFF], $source);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe($checks, "n={$checks}: every check is still reported")
            ->and($counted['indexes.builds'])->toBe(
                1,
                "n={$checks}: the indexes are built once for the stream, not once per read"
            )
            ->and($counted['indexes.hits'])->toBe(
                (3 * $checks) - 1,
                "n={$checks}: every read after the first answers from the indexes already built"
            );
    }
});

const TYPE_INTROSPECTION_BRACED_OPERAND_VIOLATIONS = [
    [47, 15],
    [47, 42],
    [66, 15],
    [85, 23],
    [90, 11],
    [95, 23],
    [97, 11],
    [102, 23],
    [105, 11],
    [114, 23],
    [114, 54],
];

it('crosses a braced body written inside an expression', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'expression-bodies.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => TYPE_INTROSPECTION_INSTANCEOF,
            ],
            TYPE_INTROSPECTION_BRACED_OPERAND_VIOLATIONS
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('reports every violation as non-fixable', function (string $fixture): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, $fixture);

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
})->with([
    'expression-bodies.php',
    'failing.php',
    'function-scope-branches.php',
    'introspection-functions.php',
    'property-hooks.php',
    'shadowed-by-declaration.php',
    'shadowed-by-import.php',
]);

it('stays silent where a brace has no closer to cross to', function (): void {
    $source = <<<'PHP'
        <?php

        $label = $value instanceof Failure && new class {

        PHP;

    $file = analyzeStdinSource([TYPE_INTROSPECTION_SNIFF], $source);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('binds a use-function import to its own namespace block only', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'import-blocks.php');

    expect(violationTuples($file))->toBe([
        [
            'line' => 44,
            'column' => 17,
            'source' => TYPE_INTROSPECTION_FUNCTION,
        ],
    ]);
});

it('reads a by-reference declaration as a declaration, not a call', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('resolves a namespace-relative name against the enclosing block, unnamed included', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'namespace-blocks.php');

    expect(violationTuples($file))->toBe([
        ['line' => 22, 'column' => 17, 'source' => TYPE_INTROSPECTION_FUNCTION],
        ['line' => 56, 'column' => 17, 'source' => TYPE_INTROSPECTION_FUNCTION],
    ])->and($file->getWarnings())->toBe([]);
});

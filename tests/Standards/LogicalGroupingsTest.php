<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const LOGICAL_GROUPINGS = 'CleanCode.Indentation.LogicalGroupings';

const LOGICAL_GROUPINGS_NOT_INDENTED = LOGICAL_GROUPINGS . '.GroupNotIndented';

const LOGICAL_GROUPINGS_MISALIGNED = LOGICAL_GROUPINGS . '.MisalignedGroupedCondition';

const LOGICAL_GROUPINGS_GLUED_LINES = [402, 418, 436];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LOGICAL_GROUPINGS);
});

it('flags every violation at its exact line, column, and code', function (): void {
    $file = analyzeFixture(LOGICAL_GROUPINGS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 18, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 19, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 31, 'column' => 15, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 32, 'column' => 15, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 44, 'column' => 21, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 45, 'column' => 21, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 58, 'column' => 19, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 72, 'column' => 17, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 73, 'column' => 17, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 88, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 89, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 101, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 102, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 115, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 116, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 131, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 132, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 142, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 143, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 242, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 246, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 259, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 261, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 279, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 280, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 295, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 296, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 308, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 309, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 327, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 328, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 345, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 346, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 361, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 362, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 379, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 380, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 402, 'column' => 23, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 418, 'column' => 23, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
        ['line' => 419, 'column' => 13, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ['line' => 436, 'column' => 17, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
    ])->and($file->getWarnings())->toBe([]);
});

it('derives the expected indent from the immediate parent group', function (): void {
    $file = analyzeFixture(LOGICAL_GROUPINGS, 'failing.php');

    expect(violationMessagesByLine($file->getErrors())[72])->toBe([
        'Grouped condition must be indented one level deeper than its enclosing'
        . ' condition; expected 20 spaces, found 16',
    ]);
});

it('reports a first condition glued to the group opener, and gives it its own line', function (): void {
    $file = analyzeFixture(LOGICAL_GROUPINGS, 'failing.php');
    $reported = violationMessagesByLine($file->getErrors());
    $fixed = explode(PHP_EOL, autofixedContents($file));

    expect($reported[LOGICAL_GROUPINGS_GLUED_LINES[0]])->toBe([
        'The first condition of a parenthesized group must start on its own line,'
        . ' indented one level deeper than its enclosing condition; expected 16 spaces',
    ])->and($fixed[(LOGICAL_GROUPINGS_GLUED_LINES[0] - 1)])->toBe('            || (')
        ->and($fixed[LOGICAL_GROUPINGS_GLUED_LINES[0]])->toBe('                $this->isActive');
});

it('inserts the break when no spacing separates the opener from the condition', function (): void {
    $file = analyzeFixture(LOGICAL_GROUPINGS, 'failing.php');
    $reported = violationMessagesByLine($file->getErrors());
    $fixed = explode(PHP_EOL, autofixedContents($file));
    $glued = LOGICAL_GROUPINGS_GLUED_LINES[2];

    $opener = (($glued - 1) + count(array_filter(
        LOGICAL_GROUPINGS_GLUED_LINES,
        static fn (int $line): bool => $line < $glued
    )));

    expect($reported[$glued])->toBe([
        'The first condition of a parenthesized group must start on its own line,'
        . ' indented one level deeper than its enclosing condition; expected 16 spaces',
    ])->and($fixed[$opener])->toBe('            || (')
        ->and($fixed[($opener + 1)])->toBe('                $this->isActive');
});

it('stays linear as groupings nest', function (): void {
    $count = 600;

    $build = function (string $opener, int $levels): string {
        $lines = ['<?php', '', 'final class Scale', '{', '    public function nested(): void', '    {'];
        $lines[] = '        if (';

        for ($level = 0; $level < $levels; $level++) {
            $indent = (12 + (4 * $level));
            $lines[] = str_repeat(' ', ($level === 0 ? $indent : ($indent - 2))) . '$this->a' . $level;
            $lines[] = str_repeat(' ', $indent) . $opener;
        }

        $lines[] = str_repeat(' ', ((12 + (4 * $levels)) - 2)) . '$this->first';
        $lines[] = str_repeat(' ', (12 + (4 * $levels))) . '&& $this->second';

        for ($level = ($levels - 1); $level >= 0; $level--) {
            $lines[] = str_repeat(' ', (12 + (4 * $level))) . ')';
        }

        $tail = ['        ) {', '            $this->grant();', '        }', '    }', '}', ''];

        return implode("\n", array_merge($lines, $tail));
    };

    buildRuleset([LOGICAL_GROUPINGS]);

    $sniff = sniffInstance(LOGICAL_GROUPINGS);

    $measure = function (string $name, string $source) use ($sniff): array {
        $fixture = stageGeneratedFixture($name, $source);
        $before = $sniff->cacheCounts();
        $file = analyzeWithSniffs([LOGICAL_GROUPINGS], $fixture);

        return [cacheCountsDelta($before, $sniff->cacheCounts()), violationTuples($file)];
    };

    $sizes = [150, 300, 600];
    $counts = [];
    $violations = [];

    foreach ($sizes as $levels) {
        [$counts[$levels], $violations[$levels]] = $measure(
            "nested-groupings-{$levels}.php",
            $build('&& (', $levels)
        );
    }

    [$skippedCounts, $skippedViolations] = $measure('nested-calls.php', $build('&& check(', $count));

    $expected = [];

    for ($level = 1; $level < $count; $level++) {
        $expected[] = [
            'line' => (8 + (2 * $level)),
            'column' => ((4 * $level) + 11),
            'source' => LOGICAL_GROUPINGS_NOT_INDENTED,
        ];
    }

    $expected[] = [
        'line' => (8 + (2 * $count)),
        'column' => ((4 * $count) + 11),
        'source' => LOGICAL_GROUPINGS_NOT_INDENTED,
    ];

    expect($violations[$count])->toBe($expected)
        ->and($skippedViolations)->toBe([]);

    foreach ($sizes as $levels) {
        expect($violations[$levels])->toHaveCount(
            $levels,
            "n={$levels}: one violation per level, and every level reached"
        );
    }

    foreach (array_slice($sizes, 1) as $levels) {
        $previous = intdiv($levels, 2);
        $grown = ($counts[$levels]['conditionWalk.steps'] / $counts[$previous]['conditionWalk.steps']);

        expect($grown)->toBeLessThan(
            2.8,
            "steps from n={$previous} to n={$levels} grow by {$counts[$levels]['conditionWalk.steps']}"
            . "/{$counts[$previous]['conditionWalk.steps']}, which a walk into each nested region cannot do"
        );
    }

    foreach ($sizes as $levels) {
        expect($counts[$levels]['conditionWalk.jumps'])->toBe(
            ($levels - 1),
            "n={$levels}: each nested grouping is crossed whole once,"
            . ' by the walk of the group holding it'
        )->and($counts[$levels]['conditionWalk.steps'])->toBe(
            ((33 * $levels) + 16),
            "n={$levels}: the walks touch a fixed number of tokens per level,"
            . ' not the condition below it'
        );
    }

    expect($skippedCounts['conditionWalk.steps'])->toBe(
        13,
        'the control is refused whole: its cost does not scale with its nesting'
    )->and($skippedCounts['conditionWalk.jumps'])->toBe(
        0,
        'a call is never entered, so no nested region inside one is ever crossed'
    );
});

$stackedGroupings = function (string $opener, int $leading, int $stacked): array {
    $lines = ['<?php', '', 'final class Stacked', '{', '    public function run(): bool', '    {'];
    $line = '        if (';

    for ($lead = 1; $lead <= $leading; $lead++) {
        $line .= '$this->p' . $lead . ' && ';
    }

    $columns = [];

    for ($level = 1; $level <= $stacked; $level++) {
        if ($level > 1) {
            $columns[] = (strlen($line) + 1);
        }

        $line .= '$this->a' . $level . ' ' . $opener;
    }

    $lines[] = $line;
    $lines[] = str_repeat(' ', 12) . '$this->first';
    $lines[] = str_repeat(' ', 12) . '&& $this->second';
    $lines[] = str_repeat(' ', 8) . str_repeat(')', $stacked) . ') {';
    $lines[] = '            return true;';
    $lines[] = '        }';
    $lines[] = '';
    $lines[] = '        return false;';
    $lines[] = '    }';
    $lines[] = '}';
    $lines[] = '';

    return [implode("\n", $lines), $columns];
};

it('stays linear as group openers stack on one line', function () use ($stackedGroupings): void {
    [$groupedSource, $reported] = $stackedGroupings('&& (', 2000, 600);
    [$halfSource] = $stackedGroupings('&& (', 1000, 300);
    [$controlSource] = $stackedGroupings('&& check(', 2000, 600);

    buildRuleset([LOGICAL_GROUPINGS]);

    $sniff = sniffInstance(LOGICAL_GROUPINGS);

    $measure = function (string $name, string $source) use ($sniff): array {
        $fixture = stageGeneratedFixture($name, $source);
        $before = $sniff->cacheCounts();
        $file = analyzeWithSniffs([LOGICAL_GROUPINGS], $fixture);

        return [
            cacheCountsDelta($before, $sniff->cacheCounts()),
            violationTuples($file),
            violationMessagesByLine($file->getErrors()),
        ];
    };

    [$groupedCounts, $groupedViolations, $groupedMessages] = $measure('stacked-groupings.php', $groupedSource);
    [$halfCounts, $halfViolations] = $measure('stacked-groupings-half.php', $halfSource);
    [$skippedCounts, $skippedViolations] = $measure('stacked-calls.php', $controlSource);

    $expected = array_map(
        static fn (int $column): array => [
            'line' => 7,
            'column' => $column,
            'source' => LOGICAL_GROUPINGS_NOT_INDENTED,
        ],
        $reported
    );

    expect($groupedViolations)->toBe($expected)
        ->and($skippedViolations)->toBe([])
        ->and($halfViolations)->toHaveCount(299, 'the half-size file reaches every level too')
        ->and(array_values(array_unique($groupedMessages[7])))->toBe([
            'The first condition of a parenthesized group must start on its own line,'
            . ' indented one level deeper than its enclosing condition; expected 12 spaces',
        ]);

    expect($groupedCounts['lineStarts.steps'] / $halfCounts['lineStarts.steps'])->toBeLessThan(
        2.6,
        "doubling the line grows the tokens examined by {$groupedCounts['lineStarts.steps']}"
        . "/{$halfCounts['lineStarts.steps']}, which a walk back along it cannot do"
    );

    expect($groupedCounts['lineStarts.hits'])->toBe(
        599,
        'the index is read once per reported group and built once for the stream'
    )->and($groupedCounts['lineStarts.steps'])->toBe(
        600,
        'the 600 readings examine 600 tokens between them — one each, from the index,'
        . ' not an ever-growing prefix of the line the openers stack on'
    )->and($halfCounts['lineStarts.steps'])->toBe(
        300,
        'and 300 readings examine 300, on a line half as long'
    );

    expect($skippedCounts['lineStarts.hits'])->toBe(
        0,
        'a call is refused before any line start is asked for'
    )->and($skippedCounts['lineStarts.steps'])->toBe(
        0,
        'so no token is examined on its behalf'
    );
});

it('reindents a stack of same-line openers through the multi-pass fixer', function () use ($stackedGroupings): void {
    [$source] = $stackedGroupings('&& (', 0, 6);
    $file = analyzeWithSniffs([LOGICAL_GROUPINGS], stageGeneratedFixture('stacked-fixable.php', $source));
    $fixed = autofixedContents($file);
    $refixed = analyzeWithSniffs([LOGICAL_GROUPINGS], stageGeneratedFixture('stacked-fixed.php', $fixed));

    expect($fixed)->toBe(<<<'PHP'
    <?php

    final class Stacked
    {
        public function run(): bool
        {
            if ($this->a1 && (
                $this->a2 && (
                    $this->a3 && (
                        $this->a4 && (
                            $this->a5 && (
                                $this->a6 && (
                                    $this->first
                                    && $this->second
            ))))))) {
                return true;
            }

            return false;
        }
    }

    PHP)->and(violationTuples($refixed))->toBe([]);
});

it('classifies nothing inside a nested region whose closer was never recorded', function (string $source): void {
    $file = analyzeWithSniffs([LOGICAL_GROUPINGS], stageGeneratedFixture('unresolved-region.php', $source));

    expect(violationTuples($file))->toBe([])
        ->and(autofixedContents($file))->toBe($source);
})->with([
    'unresolved region before the group\'s first boolean' => [
        <<<'PHP'
        <?php

        class Unresolved
        {
            public function run(array $data, bool $a, bool $b, bool $c): bool
            {
                if (
                    $a
                    || (
                            $data[
                        $b && $c
                    )
                ) {
                    return true;
                }

                return false;
            }
        }

        PHP,
    ],
    'unresolved region after it' => [
        <<<'PHP'
        <?php

        class Unresolved
        {
            public function run(array $data, bool $a, bool $b, bool $c, bool $d): bool
            {
                if (
                    $a
                    || (
                        $b
                        && $c
                        && $data[
                                    $d
                    )
                ) {
                    return true;
                }

                return false;
            }
        }

        PHP,
    ],
]);

it('moves the reported condition lines and nothing else', function (): void {
    $before = file(fixturePath('LogicalGroupingsSniff', 'failing.php'));
    $after = file(fixturePath('LogicalGroupingsSniff', 'autofixed.php'));

    foreach (LOGICAL_GROUPINGS_GLUED_LINES as $line) {
        $index = ($line - 1);
        $joined = rtrim($after[$index], "\r\n") . $after[($index + 1)];

        array_splice($after, $index, 2, [$joined]);
    }

    expect($after)->toHaveCount(count($before));

    $changed = array_keys(array_filter(
        $before,
        static fn (string $line, int $index): bool => $line !== $after[$index],
        ARRAY_FILTER_USE_BOTH
    ));

    expect(array_map(static fn (int $index): int => ($index + 1), $changed))->toBe(
        array_column(violationTuples(analyzeFixture(LOGICAL_GROUPINGS, 'failing.php')), 'line')
    );
});

it('keeps its line-start index from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        if (
        !!$alpha
                && ($beta
                || $gamma)
        ) {
            $one = 1;
        }

        PHP;

    $sourceB = <<<'PHP'
        <?php

        if (
        $alpha
            && ($beta
            || $gamma)
        ) {
            $one = !!1;
        }

        PHP;

    $first = analyzeStdinSource([LOGICAL_GROUPINGS], $sourceA);
    $second = analyzeStdinSource([LOGICAL_GROUPINGS], $sourceB);
    $third = analyzeStdinSource([LOGICAL_GROUPINGS], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 5, 'column' => 9, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
            ['line' => 6, 'column' => 5, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ])
        ->and(violationMessagesByLine($second->getErrors()))->toBe([
            5 => [
                'The first condition of a parenthesized group must start on its own line, '
                    . 'indented one level deeper than its enclosing condition; expected 8 spaces',
            ],
            6 => [
                "Condition in a parenthesized group must align with the group's first "
                    . 'condition; expected 8 spaces, found 4',
            ],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([
            ['line' => 5, 'column' => 13, 'source' => LOGICAL_GROUPINGS_NOT_INDENTED],
            ['line' => 6, 'column' => 9, 'source' => LOGICAL_GROUPINGS_MISALIGNED],
        ])
        ->and(violationMessagesByLine($third->getErrors()))->toBe([
            5 => [
                'The first condition of a parenthesized group must start on its own line, '
                    . 'indented one level deeper than its enclosing condition; expected 12 spaces',
            ],
            6 => [
                "Condition in a parenthesized group must align with the group's first "
                    . 'condition; expected 12 spaces, found 8',
            ],
        ]);
});

it('builds its line-start index once per stream, not once per read', function (): void {
    $sniff = sniffInstance(LOGICAL_GROUPINGS);

    foreach ([2, 4, 8] as $size) {
        $groups = '';

        for ($index = 0; $index < $size; $index++) {
            $groups .= "if (\n    \$alpha{$index}\n    && (\$beta{$index}\n"
                . "    || \$gamma{$index})\n) {\n    \$one{$index} = 1;\n}\n\n";
        }

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([LOGICAL_GROUPINGS], "<?php\n\n" . $groups);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe(2 * $size, "n={$size}: every group is still reported")
            ->and($counted['lineStarts.builds'])->toBe(
                1,
                "n={$size}: the index is built once for the stream, not once per read"
            )
            ->and($counted['lineStarts.hits'])->toBe(
                $size - 1,
                "n={$size}: every read after the first answers from the index already built"
            );
    }
});

it('fixes a group whose leading break cannot be read the same way', function (): void {
    $expected = autofixedContents(analyzeFixture(LOGICAL_GROUPINGS, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): string {
        return PregFailure::during(
            'preg_replace',
            static fn (): string => autofixedContents(analyzeFixture(LOGICAL_GROUPINGS, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/[^\r\n]+$/'
        );
    });

    $reports = allViolationSourcesByLine(analyzeFixture(LOGICAL_GROUPINGS, 'failing.php'));

    expect($degraded)->toBe($expected)
        ->and($reports)->not->toBe([])
        ->and($diagnostics)->toBe([]);
});

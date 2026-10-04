<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const ARRAY_ACCESSORS = 'CleanCode.Arrays.ArrayAccessors';

const ARRAY_ACCESSORS_READ = 'Direct array element access on %1$s is not allowed; use data_get(%1$s, ...) '
    . 'so a missing element falls back instead of erroring';

$arrayAccessorsStaircaseSource = static function (string $shape, int $size): string {
    $opener = $shape === 'array-literals' ? '[$x%d[\'key\'], ' : 'wrap($x%d[\'key\'], ';
    $closer = $shape === 'array-literals' ? ']' : ')';
    $body = '';

    for ($index = 1; $index <= $size; $index++) {
        $body .= sprintf($opener, $index);
    }

    return "<?php\n\n\$out = " . $body . 'null' . str_repeat($closer, $size) . ";\n";
};

$arrayAccessorsStaircaseCounts = static function (
    string $shape,
    int $size
) use ($arrayAccessorsStaircaseSource): array {
    [$config, $ruleset] = buildRuleset([ARRAY_ACCESSORS]);

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-staircase-', true) . '.php';
    file_put_contents($path, $arrayAccessorsStaircaseSource($shape, $size));
    $limit = ini_get('memory_limit');
    ini_set('memory_limit', '2G');

    $sniff = sniffInstance(ARRAY_ACCESSORS);

    try {
        $file = new LocalFile($path, $ruleset, $config);
        $file->parse();

        $before = $sniff->cacheCounts();
        $file->process();
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());
        $reported = $file->getErrorCount();
    } finally {
        unlink($path);
    }

    $file->cleanUp();
    unset($file);
    gc_collect_cycles();

    $units = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824];
    $limit = $limit === false ? '-1' : trim($limit);
    $limitBytes = (int) $limit < 0
        ? null
        : ((int) $limit * ($units[strtolower(substr($limit, -1))] ?? 1));

    if ($limitBytes !== null && memory_get_usage(true) < $limitBytes) {
        ini_set('memory_limit', $limit);
    }

    return [$reported, $counted];
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ARRAY_ACCESSORS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('does not flag boundary constructs', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'boundaries.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        9 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        10 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        11 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        18 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        19 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        20 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        27 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        31 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        36 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        43 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        44 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        52 => [
            ARRAY_ACCESSORS . '.DirectArrayAccess',
            ARRAY_ACCESSORS . '.DirectArrayAccess',
        ],
        53 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        54 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        56 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        60 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        72 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        75 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        78 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        81 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        84 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        85 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        86 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        87 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        106 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        109 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        112 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        115 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        116 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        117 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        118 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        125 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        143 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        159 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        181 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        185 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        207 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        213 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        242 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        248 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        272 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        273 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        293 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        296 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
    ]);
});

it('reports an accessor chain once at its root', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[10])->toHaveCount(1)
        ->and($errors[19])->toHaveCount(1)
        ->and($errors[36])->toHaveCount(1)
        ->and($errors[43])->toHaveCount(1)
        ->and(array_key_first($errors[36]))->toBe(17)
        ->and(array_key_first($errors[43]))->toBe(23);
});

it('reports a static property chain at the property', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[44])->toHaveCount(1)
        ->and(array_key_first($errors[44]))->toBe(29);
});

it('rewrites a static read through a qualified class name in full', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'qualified-static.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ArrayAccessorsSniff', 'qualified-static.fixed.php')));
});

it('reports an unterminated chain without falling over', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'unterminated.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        10 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        20 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
    ]);
});

it('steps over a closer whose opener was never typed', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'stray-closer.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        15 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        17 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        19 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        21 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        23 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        25 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
    ])->and($file->getErrorCount())->toBe(6, 'every read survives the stray closer');
});

it('decides enclosing constructs in linear time', function (
    string $shape,
    int $size,
    int $expectedSteps,
    int $expectedHops
): void {
    $source = $shape === 'nesting'
        ? "<?php\n\n\$out = " . str_repeat('$target[', $size) . '$key' . str_repeat(']', $size) . ";\n"
        : "<?php\n\nfunction sink(\$row): void\n{\n"
            . str_repeat("    \$value = \$row['key'];\n", $size)
            . "}\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-scale-', true) . '.php';
    file_put_contents($path, $source);

    $sniff = sniffInstance(ARRAY_ACCESSORS);
    $before = $sniff->cacheCounts();

    try {
        $file = analyzeWithSniffs([ARRAY_ACCESSORS], $path);
    } finally {
        unlink($path);
    }

    $counted = cacheCountsDelta($before, $sniff->cacheCounts());

    expect($file->getErrorCount())->toBe($size, 'every read is still reported')
        ->and($counted['enclosureVerdict.walks'])->toBe(
            $size,
            "{$shape}: one walk per read, and every read walked"
        )
        ->and($counted['enclosureVerdict.steps'])->toBe(
            $expectedSteps,
            "{$shape}: the outward steps stay linear in n={$size}"
        )
        ->and($counted['decidingStep.hops'])->toBe(
            $expectedHops,
            "{$shape}: a transparent run is crossed once for the file, not once per read"
        );
})->with([
    'n reads in one body' => ['reads', 4000, 4000, 1],
    'n levels of computed offset' => ['nesting', 2000, 1999, 0],
]);

it('crosses a staggered staircase once per read at every size', function (string $shape) use (
    $arrayAccessorsStaircaseCounts
): void {
    foreach ([500, 1000, 2000, 4000] as $size) {
        [$errors, $counted] = $arrayAccessorsStaircaseCounts($shape, $size);

        expect($errors)->toBe($size, "{$shape} at n={$size} reports every read")
            ->and($counted['enclosureVerdict.walks'])->toBe(
                $size,
                "{$shape} at n={$size}: one walk per read, and every read walked"
            )
            ->and($counted['decidingStep.hops'])->toBe(
                $size,
                "{$shape} at n={$size}: each read crosses the one construct above it, not the run"
            )
            ->and($counted['decidingStep.hits'])->toBe(
                ($size - 1),
                "{$shape} at n={$size}: every crossing but the first reads back a recorded answer"
            );
    }
})->with([
    'nested call arguments' => 'calls',
    'nested array literals' => 'array-literals',
]);

it('grows linearly across each doubling of a staggered staircase', function (string $shape) use (
    $arrayAccessorsStaircaseCounts
): void {
    $sizes = [500, 1000, 2000, 4000];
    $hops = [];

    foreach ($sizes as $size) {
        [$errors, $counted] = $arrayAccessorsStaircaseCounts($shape, $size);

        expect($errors)->toBe($size, "{$shape} at n={$size} reports every read");

        $hops[$size] = $counted['decidingStep.hops'];
    }

    foreach (array_slice($sizes, 1) as $size) {
        $previous = intdiv($size, 2);

        expect($hops[$size] / $hops[$previous])->toBeLessThan(
            2.5,
            "{$shape}: crossings from n={$previous} to n={$size} grow by"
            . " {$hops[$size]}/{$hops[$previous]}, which a quadratic walk cannot do"
        );
    }
})->with([
    'nested call arguments' => 'calls',
    'nested array literals' => 'array-literals',
]);

it('decides a staggered staircase exactly as the hop-by-hop walk did', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'staggered-nesting.php')->getErrors();
    $columnsByLine = [
        25 => [13], 26 => [29, 59],
        34 => [17, 33, 50],
        58 => [31, 60],
        70 => [44, 85],
        88 => [26],
        100 => [24],
        124 => [25, 52],
    ];

    foreach ($columnsByLine as $line => $columns) {
        expect(array_keys($errors[$line]))->toBe($columns, "line {$line} columns");
    }

    expect(array_sum(array_map('count', $errors)))->toBe(14, 'no read gained or lost');
});

it('stops a staggered walk at an unterminated construct partway up it', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'staggered-unterminated.php')->getErrors();

    expect(array_keys($errors[19]))->toBe([2, 35, 65])
        ->and(array_sum(array_map('count', $errors)))->toBe(3, 'every read past the opener survives');
});

it('does not report a property read through a dynamic member', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors)->not->toHaveKey(45)
        ->and($errors[43])->toHaveCount(1)
        ->and(array_key_first($errors[43]))->toBe(23);
});

it('still flags reads that sit beside writes', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect(array_keys($errors[52]))->toBe([19, 37])
        ->and(array_key_first($errors[53]))->toBe(17)
        ->and(array_key_first($errors[54]))->toBe(26)
        ->and(array_key_first($errors[56]))->toBe(18)
        ->and(array_key_first($errors[60]))->toBe(29)
        ->and($errors[53])->toHaveCount(1)
        ->and($errors[54])->toHaveCount(1)
        ->and($errors[56])->toHaveCount(1)
        ->and($errors[60])->toHaveCount(1);
});

it('flags computed indexes inside write targets', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    $columnsByLine = [72 => 35, 75 => 35, 78 => 36, 81 => 46, 84 => 18, 85 => 22, 86 => 25, 87 => 29];

    foreach ($columnsByLine as $line => $column) {
        expect($errors[$line])->toHaveCount(1, "line {$line} reports once")
            ->and(array_key_first($errors[$line]))->toBe($column, "line {$line} column");
    }
});

it('flags offsets spanning braces and statements', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    $columnsByLine = [
        106 => 61, 109 => 72, 112 => 79, 115 => 44, 116 => 48,
        117 => 55, 118 => 59, 125 => 32, 293 => 62, 296 => 73,
    ];

    foreach ($columnsByLine as $line => $column) {
        expect($errors[$line])->toHaveCount(1, "line {$line} reports once")
            ->and(array_key_first($errors[$line]))->toBe($column, "line {$line} column");
    }
});

it('reports a read past a nested foreach clause in a closure', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[181] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$rows')])
        ->and($messages[185] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$trailing')])
        ->and(array_intersect_key($messages, array_flip(range(175, 199))))
        ->toHaveKeys([181, 185])
        ->toHaveCount(2, 'no other line in the method reports');
});

it('reports a read past a nested foreach clause in an anonymous class', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[207] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$anonRows')])
        ->and($messages[213] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$anonTrailing')])
        ->and(array_intersect_key($messages, array_flip(range(201, 228))))
        ->toHaveKeys([207, 213])
        ->toHaveCount(2, 'no other line in the method reports');
});

it('reports reads past sibling nested foreach clauses', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[242] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$firstTrailing')])
        ->and($messages[248] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$secondTrailing')])
        ->and(array_intersect_key($messages, array_flip(range(230, 263))))
        ->toHaveKeys([242, 248])
        ->toHaveCount(2, 'no other line in the method reports');
});

it('does not report a foreach target whose offset holds a nested foreach clause', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors());

    expect($messages[272] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$trapRows')])
        ->and($messages[273] ?? [])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$trapOffset')])
        ->and(array_intersect_key($messages, array_flip(range(266, 285))))
        ->toHaveKeys([272, 273])
        ->toHaveCount(2, 'no other line in the method reports');
});

it('does not flag write targets nested inside an accessor offset', function (): void {
    expect(analyzeFixture(ARRAY_ACCESSORS, 'boundaries.php')->getErrors())->toBe([]);
});

it('records a tokenizer scope defect rather than working around it', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'tokenizer-limits.php')->getErrors();

    expect(array_keys($errors[30]))->toBe([10, 18], 'the defect adds a false positive')
        ->and(array_keys($errors[43]))->toBe([18], 'the same statement is correct without it');
});

it('still reports the read in a malformed assignment', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'malformed.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        11 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        12 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
        19 => [ARRAY_ACCESSORS . '.DirectArrayAccess'],
    ]);
});

it('still flags a read beside an existence check', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[143])->toHaveCount(1)
        ->and(array_key_first($errors[143]))->toBe(43);
});

it('reports a variable-variable chain at its sigil', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'failing.php')->getErrors();

    expect($errors[159])->toHaveCount(1)
        ->and(array_key_first($errors[159]))->toBe(16)
        ->and($errors[159][16][0]['message'])->toContain('data_get($$name, ...)');
});

it('does not report a file ending on a bare variable', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'trailing-variable.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('offers every reported read as fixable', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'failing.php');

    expect($file->getErrorCount())->toBe(45)
        ->and($file->getFixableCount())->toBe(45);
});

it('rewrites every read to a data_get() call when fixed', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ArrayAccessorsSniff', 'autofixed.php')));
});

it('produces no violations on the autofixed fixture', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'autofixed.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(0);
});

it('chooses the dotted path only for identifier-shaped segments', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'path-forms.php');
    $emitted = [];

    foreach (explode("\n", autofixedContents($file)) as $line) {
        if (preg_match('/(data_get\(.*?\)),$/', trim($line), $matches) === 1) {
            $emitted[] = $matches[1];
        }
    }

    expect($emitted)->toBe([
        "data_get(\$payload, 'plain')",
        "data_get(\$payload, 'address.city')",
        "data_get(\$payload, ['k.with.dot'])",
        "data_get(\$payload, ['has space'])",
        "data_get(\$payload, ['has-dash'])",
        "data_get(\$payload, [''])",
        'data_get($payload, [0])',
        'data_get($payload, [$index])',
        "data_get(\$payload, 'row.reference')",
        "data_get(\$payload, ['row', \$field])",
    ]);
});

it('keeps a static property qualifier inside the rewritten subject', function (): void {
    expect(file_get_contents(fixturePath('ArrayAccessorsSniff', 'autofixed.php')))
        ->toContain("data_get(self::\$registry, 'key')");
});

it('reports a by-reference argument without offering to fix it', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'by-reference.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(2);
});

it('leaves a by-value argument of a by-reference function fixable', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'by-reference.php');
    $fixable = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                $fixable[$line] = $error['fixable'];
            }
        }
    }

    ksort($fixable);

    expect($fixable)->toBe([
        9 => false,
        10 => false,
        11 => true,
        12 => true,
    ]);
});

it('keeps its enclosure map from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        $one = isset($alpha['beta']);
        $two = $gamma['delta'];

        PHP;

    $sourceB = <<<'PHP'
        <?php

        $one = strlen($alpha['beta']);
        $two = $gamma['delta'];

        PHP;

    $first = analyzeStdinSource([ARRAY_ACCESSORS], $sourceA);
    $second = analyzeStdinSource([ARRAY_ACCESSORS], $sourceB);
    $third = analyzeStdinSource([ARRAY_ACCESSORS], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 3, 'column' => 15, 'source' => ARRAY_ACCESSORS . '.DirectArrayAccess'],
            ['line' => 4, 'column' => 8, 'source' => ARRAY_ACCESSORS . '.DirectArrayAccess'],
        ])
        ->and(violationMessagesByLine($second->getErrors()))->toBe([
            3 => [sprintf(ARRAY_ACCESSORS_READ, '$alpha')],
            4 => [sprintf(ARRAY_ACCESSORS_READ, '$gamma')],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([
            ['line' => 4, 'column' => 8, 'source' => ARRAY_ACCESSORS . '.DirectArrayAccess'],
        ])
        ->and(violationMessagesByLine($third->getErrors()))->toBe([
            4 => [sprintf(ARRAY_ACCESSORS_READ, '$gamma')],
        ]);
});

it('builds its enclosure map once per stream, not once per read', function (): void {
    $sniff = sniffInstance(ARRAY_ACCESSORS);

    foreach ([2, 4, 8] as $size) {
        $reads = '';

        for ($index = 0; $index < $size; $index++) {
            $reads .= "\$one{$index} = \$alpha{$index}['beta'];\n";
        }

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([ARRAY_ACCESSORS], "<?php\n\n" . $reads);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe($size, "n={$size}: every read is still reported")
            ->and($counted['enclosureMap.builds'])->toBe(
                1,
                "n={$size}: the map is built once for the stream, not once per read"
            )
            ->and($counted['enclosureMap.hits'])->toBe(
                (2 * $size) - 1,
                "n={$size}: every read after the first answers from the map already built"
            );
    }
});

it('reports an element read behind properties once, at its root', function (): void {
    $errors = analyzeFixture(ARRAY_ACCESSORS, 'element-after-property.php')->getErrors();
    $element = [ARRAY_ACCESSORS . '.DirectArrayAccess'];

    expect(violationSourcesByLine($errors))->toBe(array_fill(10, 7, $element));

    foreach ($errors as $line => $columns) {
        expect(array_keys($columns))->toBe([$line === 15 ? 19 : 13], "line {$line} reports at its root");
    }
});

it('names the property read as the data_get subject of an element read behind it', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(ARRAY_ACCESSORS, 'element-after-property.php')->getErrors());

    expect($messages[10])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$order->tags')])
        ->and($messages[11])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$order?->tags')])
        ->and($messages[13])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$order->customer->tags')])
        ->and($messages[14])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$order->{$name}')])
        ->and($messages[16])->toBe([sprintf(ARRAY_ACCESSORS_READ, '$payload')]);
});

it('rewrites an element read behind properties with the property read as the subject', function (): void {
    $file = analyzeFixture(ARRAY_ACCESSORS, 'element-after-property.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ArrayAccessorsSniff', 'element-after-property.fixed.php')));
});

it('does not report a truncated property read', function (string $source): void {
    $file = analyzeStdinSource([ARRAY_ACCESSORS], "<?php\n\n" . $source);

    expect($file->getErrors())->toBe([]);
})->with([
    'one hop at the operator' => ['$value = $order->'],
    'one hop in an open brace' => ["\$value = \$order->{\n"],
    'two hops at the operator' => ['$value = $order->customer->'],
]);

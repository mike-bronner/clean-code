<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;
use PHP_CodeSniffer\Files\LocalFile;

const UNUSED_FORMAL_PARAMETER = 'CleanCode.DeadCode.UnusedFormalParameter';

const UNUSED_FORMAL_PARAMETER_ERROR = UNUSED_FORMAL_PARAMETER . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNUSED_FORMAL_PARAMETER);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every unused formal parameter in the failing fixture', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 29, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 30, 'column' => 27, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 35, 'column' => 33, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 42, 'column' => 32, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 48, 'column' => 38, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 59, 'column' => 37, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 65, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 71, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 77, 'column' => 33, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 83, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 91, 'column' => 38, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 112, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 122, 'column' => 41, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 128, 'column' => 37, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 136, 'column' => 48, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 144, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 154, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 164, 'column' => 34, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 175, 'column' => 40, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 184, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 192, 'column' => 42, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 198, 'column' => 39, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 211, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 222, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 243, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 252, 'column' => 44, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 260, 'column' => 40, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 270, 'column' => 50, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 278, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 287, 'column' => 48, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 294, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 307, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 317, 'column' => 44, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 325, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 333, 'column' => 40, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 344, 'column' => 43, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 349, 'column' => 45, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 354, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 362, 'column' => 42, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 369, 'column' => 43, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 378, 'column' => 45, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 400, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 411, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 423, 'column' => 42, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 439, 'column' => 50, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 446, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 453, 'column' => 54, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 463, 'column' => 45, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 476, 'column' => 51, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 492, 'column' => 47, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 504, 'column' => 51, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 522, 'column' => 23, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 541, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 554, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
    ]);
});

it('resolves a same-file ancestor within its own namespace', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'namespaces.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('resolves a same-file ancestor named through a qualified name', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'qualified-ancestors.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports the documented divergences, and only those', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 21, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 26, 'column' => 27, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 32, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 62, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 89, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 114, 'column' => 12, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 124, 'column' => 29, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 158, 'column' => 39, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 168, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 194, 'column' => 34, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 212, 'column' => 33, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
    ]);
});

it('names the construct and the parameter in the message', function (): void {
    $errors = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php')->getErrors();

    expect($errors[23][29][0]['message'])
        ->toContain('function plainUnused()')
        ->toContain('$unusedA')
        ->toContain('#[\\Override]')
        ->toContain('@inheritdoc');

    expect($errors[91][38][0]['message'])->toContain('method ownMethod()');
});

it('names a closure and an arrow function as what they are', function (): void {
    $errors = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'divergences.php')->getErrors();

    expect($errors[21][35][0]['message'])->toContain('The closure never reads');
    expect($errors[26][27][0]['message'])->toContain('The arrow function never reads');
});

it('reports every violation as unfixable', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php');

    expect(violationFixableFlags($file))->each->toBeFalse();
});

it('leaves the failing fixture untouched when the fixer runs', function (): void {
    $fixture = __DIR__ . '/../fixtures/UnusedFormalParameterSniff/failing.php';
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php');

    expect(autofixedContents($file))->toBe(file_get_contents($fixture));
});


it('indexes same-file ancestors once per file, not once per method', function (): void {
    $delta = static function (array $before, array $after): array {
        $counted = [];

        foreach ($after as $counter => $count) {
            $counted[$counter] = $count - $before[$counter];
        }

        return $counted;
    };

    [$config, $ruleset] = buildRuleset([UNUSED_FORMAL_PARAMETER]);
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[UNUSED_FORMAL_PARAMETER]];

    foreach ([250, 500, 1000] as $size) {
        $accessors = '';

        for ($index = 0; $index < $size; $index++) {
            $accessors .= "    public function getThing{$index}(): int\n"
                . "    {\n        return {$index};\n    }\n\n";
        }

        $source = "<?php\n\nclass Big\n{\n" . $accessors
            . "    public function unusedOne(int \$unused): int\n    {\n        return 1;\n    }\n}\n";

        $path = sys_get_temp_dir() . '/' . uniqid('cleancode-ufp-scale-', true) . '.php';
        file_put_contents($path, $source);
        $before = $sniff->cacheCounts();

        try {
            $file = new LocalFile($path, $ruleset, $config);
            $file->process();
            $reported = $file->getErrorCount();
        } finally {
            unlink($path);
        }

        $counted = $delta($before, $sniff->cacheCounts());

        expect($reported)->toBe(1, "n={$size} still reports the one unused parameter")
            ->and($counted['declarations.builds'])->toBe(
                1,
                "n={$size}: the index is built once for the file, not once per method"
            )
            ->and($counted['declarations.hits'])->toBe(
                (2 * $size) + 1,
                "n={$size}: every read after the first answers from the index already built"
            );
    }
});

it('indexes an ancestor once per file, not once per descendant method', function (): void {
    $shapes = [
        'overriding' => ['name' => 'getThing', 'reports' => false, 'traits' => false],
        'extending' => ['name' => 'ownThing', 'reports' => true, 'traits' => true],
    ];

    $delta = static function (array $before, array $after): array {
        $counted = [];

        foreach ($after as $counter => $count) {
            $counted[$counter] = $count - $before[$counter];
        }

        return $counted;
    };

    [$config, $ruleset] = buildRuleset([UNUSED_FORMAL_PARAMETER]);
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[UNUSED_FORMAL_PARAMETER]];

    foreach ($shapes as $shape => $spec) {
        foreach ([250, 500, 1000] as $size) {
            $base = '';
            $derived = '';

            for ($index = 0; $index < $size; $index++) {
                $base .= "    public function getThing{$index}(): int\n"
                    . "    {\n        return {$index};\n    }\n\n";
                $derived .= "    public function {$spec['name']}{$index}(int \$unused{$index}): int\n"
                    . "    {\n        return {$index};\n    }\n\n";
            }

            $source = "<?php\n\nclass Base\n{\n" . $base . "}\n\n"
                . "class Derived extends Base\n{\n" . $derived . "}\n";

            $path = sys_get_temp_dir() . '/' . uniqid('cleancode-ufp-ancestor-', true) . '.php';
            file_put_contents($path, $source);
            $before = $sniff->cacheCounts();

            try {
                $file = new LocalFile($path, $ruleset, $config);
                $file->process();
                $reported = $file->getErrorCount();
            } finally {
                unlink($path);
            }

            $counted = $delta($before, $sniff->cacheCounts());
            $byAncestor = $sniff->cacheCountsByClass();

            $traitReads = $spec['traits'] === true
                ? ['builds' => 1, 'hits' => $size - 1]
                : ['builds' => 0, 'hits' => 0];

            expect($reported)->toBe(
                $spec['reports'] === true ? $size : 0,
                "{$shape} n={$size}: the override exemption still resolves"
            )
                ->and($counted['methodNames.builds'])->toBe(
                    1,
                    "{$shape} n={$size}: the ancestor's method list is read once for the file"
                )
                ->and($counted['methodNames.hits'])->toBe(
                    $size - 1,
                    "{$shape} n={$size}: every later descendant method answers from that read"
                )
                ->and(array_values($byAncestor['methodNames']))->toBe(
                    [['builds' => 1, 'hits' => $size - 1]],
                    "{$shape} n={$size}: one ancestor, its method list built once"
                )
                ->and($counted['traitNames.builds'])->toBe(
                    $traitReads['builds'],
                    "{$shape} n={$size}: the ancestor's trait list is read once, or never reached"
                )
                ->and($counted['traitNames.hits'])->toBe(
                    $traitReads['hits'],
                    "{$shape} n={$size}: every later descendant method answers from that read"
                )
                ->and(array_values($byAncestor['traitNames']))->toBe(
                    $spec['traits'] === true ? [['builds' => 1, 'hits' => $size - 1]] : [],
                    "{$shape} n={$size}: one ancestor, its trait list built once, or never reached"
                );
        }
    }
});

it('keeps its class-like index from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        class Base
        {
            public function run($alpha)
            {
                return $alpha;
            }
        }

        class Kid extends Base
        {
            public function run($alpha)
            {
                return 1;
            }
        }

        PHP;

    $sourceB = <<<'PHP'
        <?php

        class Base
        {
            public function walk($alpha)
            {
                return $alpha;
            }
        }

        class Kid extends Base
        {
            public function run($alpha)
            {
                return 1;
            }
        }

        PHP;

    $first = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $sourceA);
    $second = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $sourceB);
    $third = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 13, 'column' => 25, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ])
        ->and(violationMessagesByLine($second->getErrors()))->toBe([
            13 => [
                'The method run() never reads its parameter $alpha; remove it from the '
                    . 'signature, or mark the method as an override with #[\\Override] or '
                    . '@inheritdoc if the signature is imposed from outside '
                    . '(see resources/boost/guidelines/no-dead-code.md)',
            ],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([]);
});

it('builds its class-like index once per STDIN stream, not once per read', function (): void {
    $sniff = sniffInstance(UNUSED_FORMAL_PARAMETER);

    foreach ([2, 4, 8] as $size) {
        $methods = '';

        for ($index = 0; $index < $size; $index++) {
            $methods .= "    public function take{$index}(int \$unused{$index}): int\n    {\n"
                . "        return {$index};\n    }\n\n";
        }

        $source = "<?php\n\nclass Big\n{\n" . $methods . "}\n";

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $source);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe($size, "n={$size}: every unused parameter is still reported")
            ->and($counted['declarations.builds'])->toBe(
                1,
                "n={$size}: the index is built once for the stream, not once per read"
            )
            ->and($counted['declarations.hits'])->toBe(
                (2 * $size) - 1,
                "n={$size}: every read after the first answers from the index already built"
            );
    }
});

it('resolves a namespace-relative exempting call against the enclosing block', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'namespace-blocks.php');

    expect(violationTuples($file))->toBe([
        ['line' => 40, 'column' => 50, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
    ])->and($file->getWarnings())->toBe([]);
});

it('collects no interpolated name when the string cannot be read', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_match_all',
            static fn (): array => violationSourcesByLine(
                analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php')->getErrors()
            ),
            static fn (string $pattern): bool => str_contains($pattern, '(?P<name>')
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});

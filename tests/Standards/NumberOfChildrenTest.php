<?php

declare(strict_types=1);

const NUMBER_OF_CHILDREN = 'CleanCode.Metrics.NumberOfChildren';

const NUMBER_OF_CHILDREN_ERROR = 'CleanCode.Metrics.NumberOfChildren.Found';

const NUMBER_OF_CHILDREN_PROJECT = __DIR__ . '/../fixtures/NumberOfChildrenSniff/project';

const NUMBER_OF_CHILDREN_BASE = NUMBER_OF_CHILDREN_PROJECT . '/Base.php';

const NUMBER_OF_CHILDREN_INTERPOLATION = __DIR__ . '/../fixtures/NumberOfChildrenSniff/interpolation';

const NUMBER_OF_CHILDREN_NAMESPACES = __DIR__ . '/../fixtures/NumberOfChildrenSniff/namespaces';

const NUMBER_OF_CHILDREN_ANONYMOUS = __DIR__ . '/../fixtures/NumberOfChildrenSniff/anonymous';

const NUMBER_OF_CHILDREN_SEMI_RESERVED = __DIR__ . '/../fixtures/NumberOfChildrenSniff/semireserved';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NUMBER_OF_CHILDREN);
});

it('ships PHPMD\'s own default threshold', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[NUMBER_OF_CHILDREN]];

    expect((int) $sniff->minimum)->toBe(15);
});

it('reports a parent that has reached the threshold, on its declaration line', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
});

it('quotes the count and the threshold the way PHPMD does', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'failing.php');
    $messages = violationMessagesByLine($file->getErrors());

    expect($messages[11][0])->toBe(
        'The class Base has 15 children.'
            . ' Consider to rebalance this class hierarchy to keep number of children under 15.'
    );
});

it('stays silent one child below the threshold, and on every near miss', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'passing.php');

    expect(violationTuples($file))->toBe([]);
});

it('treats the threshold as inclusive', function (): void {
    $reported = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '14');
    $silent = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '15');

    expect(violationTuples($reported))->toBe([
        ['line' => 12, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationTuples($silent))->toBe([]);
});

it('counts no anonymous class as a child, however the declaration is spelled', function (): void {
    $file = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '1');

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
        ['line' => 16, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[12][0])->toContain('has 14 children');
});

it('counts a named readonly class as the child it is', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'readonly/Named.php');

    expect(violationTuples($file))->toBe([
        ['line' => 17, 'column' => 19, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[17][0])->toContain('has 15 children');
});

it('counts children declared in other files of the run', function (): void {
    $file = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        NUMBER_OF_CHILDREN_PROJECT,
        NUMBER_OF_CHILDREN_BASE
    );

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[12][0])->toContain('has 15 children');
});

it('counts each spelling of the parent name, and only its own share', function (array $paths, int $total): void {
    $reported = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        $paths,
        NUMBER_OF_CHILDREN_BASE,
        static function (object $sniff) use ($total): void {
            $sniff->minimum = $total;
        }
    );
    $silent = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        $paths,
        NUMBER_OF_CHILDREN_BASE,
        static function (object $sniff) use ($total): void {
            $sniff->minimum = ($total + 1);
        }
    );

    expect(violationMessagesByLine($reported->getErrors())[12][0])->toContain("has {$total} children");
    expect(violationTuples($silent))->toBe([]);
})->with([
    'own file only' => [[NUMBER_OF_CHILDREN_BASE], 5],
    'plain import' => [[NUMBER_OF_CHILDREN_BASE, NUMBER_OF_CHILDREN_PROJECT . '/nested/Imported.php'], 9],
    'aliased import' => [[NUMBER_OF_CHILDREN_BASE, NUMBER_OF_CHILDREN_PROJECT . '/nested/Aliased.php'], 8],
    'fully qualified' => [[NUMBER_OF_CHILDREN_BASE, NUMBER_OF_CHILDREN_PROJECT . '/nested/Qualified.php'], 8],
]);

it('resolves a group import\'s alias to its own fully qualified target', function (): void {
    $file = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        NUMBER_OF_CHILDREN_PROJECT,
        NUMBER_OF_CHILDREN_BASE,
        static function (object $sniff): void {
            $sniff->minimum = 1;
        }
    );
    $messages = violationMessagesByLine($file->getErrors());

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
        ['line' => 16, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect($messages[12][0])->toContain('The class Base has 15 children');
    expect($messages[16][0])->toContain('The class Same1 has 1 children');
});

it('keeps a trait use written after an interpolated string out of the import map', function (): void {
    $lowered = static function (object $sniff): void {
        $sniff->minimum = 1;
    };
    $bare = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        NUMBER_OF_CHILDREN_INTERPOLATION,
        NUMBER_OF_CHILDREN_INTERPOLATION . '/Bare.php',
        $lowered
    );
    $anchor = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        NUMBER_OF_CHILDREN_INTERPOLATION,
        NUMBER_OF_CHILDREN_INTERPOLATION . '/Interpolated.php',
        $lowered
    );

    expect(violationTuples($bare))->toBe([]);
    expect(violationTuples($anchor))->toBe([
        ['line' => 90, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
});

it('keeps a trait use inside an anonymous class body out of the import map', function (
    string $spelling,
    array $anchors
): void {
    $paths = [NUMBER_OF_CHILDREN_ANONYMOUS . '/Bare.php', NUMBER_OF_CHILDREN_ANONYMOUS . '/' . $spelling];
    $lowered = static function (object $sniff): void {
        $sniff->minimum = 1;
    };
    $bare = analyzeProjectFixture(NUMBER_OF_CHILDREN, $paths, $paths[0], $lowered);
    $spelled = analyzeProjectFixture(NUMBER_OF_CHILDREN, $paths, $paths[1], $lowered);

    expect(violationTuples($bare))->toBe([]);
    expect(violationTuples($spelled))->toBe($anchors);
})->with([
    'closure argument' => ['Closure.php', [
        ['line' => 25, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]],
    'match argument' => ['Matched.php', [
        ['line' => 20, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]],
    'interpolated argument' => ['Interpolated.php', [
        ['line' => 20, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]],
    'readonly spelling' => ['Readonly.php', [
        ['line' => 26, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]],
    'attributed spelling' => ['Attributed.php', [
        ['line' => 22, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]],
    'nested anonymous classes' => ['Nested.php', [
        ['line' => 29, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
        ['line' => 37, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]],
]);

it('keeps a semi-reserved member name from swallowing a later import', function (
    string $fixture,
    int $local,
    int $imported
): void {
    $path = NUMBER_OF_CHILDREN_SEMI_RESERVED . '/' . $fixture;
    $file = analyzeProjectFixture(NUMBER_OF_CHILDREN, $path, $path, static function (object $sniff): void {
        $sniff->minimum = 1;
    });

    expect(violationTuples($file))->toBe([
        ['line' => $local, 'column' => 5, 'source' => NUMBER_OF_CHILDREN_ERROR],
        ['line' => $imported, 'column' => 5, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[$local][0])->toContain('has 1 children');
    expect(violationMessagesByLine($file->getErrors())[$imported][0])->toContain('has 2 children');
})->with([
    'method name' => ['Method.php', 34, 52],
    'constant name' => ['Constant.php', 26, 44],
    'constant fetch' => ['Fetch.php', 32, 50],
    'enum case name' => ['EnumCase.php', 22, 40],
    'trait alias name' => ['Alias.php', 32, 50],
    'class constant fetch' => ['ClassConstant.php', 33, 51],
]);

it('tells two same-named classes in different namespace blocks apart', function (): void {
    $path = NUMBER_OF_CHILDREN_NAMESPACES . '/Collide.php';
    $overThree = analyzeProjectFixture(NUMBER_OF_CHILDREN, $path, $path, static function (object $sniff): void {
        $sniff->minimum = 3;
    });
    $overTwo = analyzeProjectFixture(NUMBER_OF_CHILDREN, $path, $path, static function (object $sniff): void {
        $sniff->minimum = 2;
    });

    expect(violationTuples($overThree))->toBe([
        ['line' => 17, 'column' => 5, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($overThree->getErrors())[17][0])->toContain('has 3 children');
    expect(violationTuples($overTwo))->toBe([
        ['line' => 17, 'column' => 5, 'source' => NUMBER_OF_CHILDREN_ERROR],
        ['line' => 35, 'column' => 5, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($overTwo->getErrors())[17][0])->toContain('has 3 children');
    expect(violationMessagesByLine($overTwo->getErrors())[35][0])->toContain('has 2 children');
});

it('tells two same-named classes on one line apart', function (): void {
    $path = NUMBER_OF_CHILDREN_NAMESPACES . '/SameLine.php';
    $overThree = analyzeProjectFixture(NUMBER_OF_CHILDREN, $path, $path, static function (object $sniff): void {
        $sniff->minimum = 3;
    });
    $overTwo = analyzeProjectFixture(NUMBER_OF_CHILDREN, $path, $path, static function (object $sniff): void {
        $sniff->minimum = 2;
    });

    expect(violationTuples($overThree))->toBe([
        ['line' => 13, 'column' => 68, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($overThree->getErrors())[13][0])->toContain('has 3 children');
    expect(violationTuples($overTwo))->toBe([
        ['line' => 13, 'column' => 68, 'source' => NUMBER_OF_CHILDREN_ERROR],
        ['line' => 13, 'column' => 219, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($overTwo->getErrors())[13][0])->toContain('has 3 children');
    expect(violationMessagesByLine($overTwo->getErrors())[13][1])->toContain('has 2 children');
});

it('reads a group import without recursing once per brace', function (): void {
    $children = implode("\n\n", array_map(
        static fn (int $index): string => "class Child{$index} extends Base\n{\n}",
        range(1, 15)
    ));
    $project = stageProjectOutsideTests([
        'Base.php' => "<?php\n\nnamespace Fixture\\Groups;\n\nclass Base\n{\n}\n\n" . $children . "\n",
        'Nested.php' => "<?php\n\nuse " . str_repeat('A\\{', 20000) . ";\n",
    ]);
    [$stdout, $stderr, $status] = runOutsidePackage(implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        '-d',
        'memory_limit=128M',
        cleanCodeRoot() . '/vendor/bin/phpcs',
        '--standard=CleanCode',
        '--sniffs=' . NUMBER_OF_CHILDREN,
        '--report=json',
        '--no-cache',
        dirname($project),
    ])));
    $report = json_decode($stdout, true);
    $reported = [];

    foreach (($report['files'] ?? []) as $path => $file) {
        foreach ($file['messages'] as $message) {
            $reported[] = basename((string) $path) . ':' . $message['line'] . ' ' . $message['message'];
        }
    }

    expect($stderr)->not->toContain('Allowed memory size');
    expect($status)->toBe(2);
    expect($reported)->toBe([
        'Base.php:5 The class Base has 15 children.'
            . ' Consider to rebalance this class hierarchy to keep number of children under 15.',
    ]);
});

it('indexes a packed line of declarations once, not once per declaration', function (): void {
    $children = implode("\n\n", array_map(
        static fn (int $index): string => "class Child{$index} extends Base\n{\n}",
        range(1, 15)
    ));
    $packed = implode('', array_map(
        static fn (int $index): string => "class Packed{$index}{}",
        range(1, 8000)
    ));
    $project = stageProjectOutsideTests([
        'Base.php' => "<?php\n\nnamespace Fixture\\Packed;\n\nclass Base\n{\n}\n\n" . $children . "\n",
        'Packed.php' => '<?php ' . $packed . "\n",
    ]);
    [$stdout, , $status] = runOutsidePackage(implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        cleanCodeRoot() . '/vendor/bin/phpcs',
        '--standard=' . stageOrdinalDiagnosticRuleset(),
        '--sniffs=' . NUMBER_OF_CHILDREN,
        '--runtime-set',
        'cleancode_ordinal_index_diagnostic',
        '1',
        '--report=json',
        '--no-cache',
        dirname($project),
    ])));
    $report = json_decode($stdout, true);
    $reported = [];
    $indexed = [];

    foreach (($report['files'] ?? []) as $path => $file) {
        foreach ($file['messages'] as $message) {
            if ($message['source'] === NUMBER_OF_CHILDREN . '.OrdinalIndex') {
                $indexed[basename((string) $path)] = $message['message'];

                continue;
            }

            $reported[] = basename((string) $path) . ':' . $message['line'] . ' ' . $message['message'];
        }
    }

    ksort($indexed);

    expect($status)->toBe(2);
    expect($reported)->toBe([
        'Base.php:5 The class Base has 15 children.'
            . ' Consider to rebalance this class hierarchy to keep number of children under 15.',
    ]);
    expect($indexed)->toBe([
        'Base.php' => 'Ordinal index: 1 builds, 16 reads over this file.',
        'Packed.php' => 'Ordinal index: 1 builds, 8000 reads over this file.',
    ]);
});

it('leaves a run\'s file list unbuilt when it is walked by key', function (): void {
    $project = stageProjectOutsideTests(['Base.php' => "<?php\n\nclass Base\n{\n}\n"]);
    [$config, $ruleset] = buildRuleset([NUMBER_OF_CHILDREN], true);
    $config->files = [dirname($project)];

    $built = static function (PHP_CodeSniffer\Files\FileList $list): array {
        $property = new ReflectionProperty(PHP_CodeSniffer\Files\FileList::class, 'files');

        return $property->getValue($list);
    };

    $byKey = new PHP_CodeSniffer\Files\FileList($config, $ruleset);

    for ($byKey->rewind(); $byKey->valid() === true; $byKey->next()) {
        expect($byKey->key())->toBe($project);
    }

    $byValue = new PHP_CodeSniffer\Files\FileList($config, $ruleset);

    foreach ($byValue as $listed) {
        expect($listed)->toBeInstanceOf(PHP_CodeSniffer\Files\LocalFile::class);
    }

    expect($built($byKey))->toBe([$project => null]);
    expect($built($byValue)[$project])->toBeInstanceOf(PHP_CodeSniffer\Files\LocalFile::class);
});

it('takes a run\'s file list by key, building no file for any of it', function (): void {
    $project = stageProjectOutsideTests(['Base.php' => "<?php\n\nclass Base\n{\n}\n"]);
    [$config, $ruleset] = buildRuleset([NUMBER_OF_CHILDREN], true);
    $config->files = [dirname($project)];

    $listed = new class ($config, $ruleset) extends PHP_CodeSniffer\Files\FileList {
        public int $built = 0;

        #[ReturnTypeWillChange]
        public function current()
        {
            $this->built++;

            return parent::current();
        }
    };

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[NUMBER_OF_CHILDREN]];
    $walk = new ReflectionMethod($sniff, 'listedPaths');

    expect($walk->invoke($sniff, $listed))->toBe([$project]);
    expect($listed->built)->toBe(0);

    foreach ($listed as $ignored) {
        expect($ignored)->toBeInstanceOf(PHP_CodeSniffer\Files\LocalFile::class);
    }

    expect($listed->built)->toBe(1);
});

it('reports a parent whose declaration line a fixer loop has moved', function (): void {
    $children = static fn (int $from, int $to): string => implode("\n\n", array_map(
        static fn (int $index): string => "class Child{$index} extends Base\n{\n}",
        range($from, $to)
    ));
    $project = stageProjectOutsideTests([
        'Base.php' => "<?php\n\n\n\nclass Base\n{\n}\n\n" . $children(1, 5) . "\n",
        'Children.php' => "<?php\n\n" . $children(6, 15) . "\n",
    ]);
    [$config, $ruleset] = buildRuleset([NUMBER_OF_CHILDREN, 'CleanCode.WhiteSpace.BlankLines'], true);
    $config->files = [dirname($project)];

    $file = new PHP_CodeSniffer\Files\LocalFile($project, $ruleset, $config);
    $file->process();

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => 'CleanCode.WhiteSpace.BlankLines.ConsecutiveBlankLines'],
        ['line' => 5, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);

    $file->fixer->fixFile();

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[3][0])->toContain('has 15 children');
});

it('resolves the subject against the buffer when the run lints one under a real path', function (): void {
    $children = static fn (int $from, int $to): string => implode("\n\n", array_map(
        static fn (int $index): string => "class Child{$index} extends Base\n{\n}",
        range($from, $to)
    ));
    $project = stageProjectOutsideTests([
        'Base.php' => "<?php\n\nclass Base\n{\n}\n\n" . $children(1, 5) . "\n",
        'Children.php' => "<?php\n\n" . $children(6, 15) . "\n",
    ]);
    [$config, $ruleset] = buildRuleset([NUMBER_OF_CHILDREN], true);
    $config->files = [dirname($project)];
    $config->stdinPath = $project;

    $buffer = "<?php\n\n// an edit that is not saved yet\n\nclass Base\n{\n}\n\n" . $children(1, 5) . "\n";
    $file = new PHP_CodeSniffer\Files\DummyFile($buffer, $ruleset, $config);
    $file->process();

    expect(tuplesFromMessages($file->getErrors()))->toBe([
        ['line' => 5, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[5][0])->toContain('has 15 children');
});

it('resolves parents fully qualified, keeping a same-named class separate', function (): void {
    $file = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        NUMBER_OF_CHILDREN_PROJECT,
        NUMBER_OF_CHILDREN_PROJECT . '/Decoy.php',
        static function (object $sniff): void {
            $sniff->minimum = 3;
        }
    );

    expect(violationMessagesByLine($file->getErrors())[12][0])->toContain('has 3 children');
});

it('sees only the file in hand when the run has no project paths', function (): void {
    $path = NUMBER_OF_CHILDREN_BASE;
    $default = analyzeWithSniffs([NUMBER_OF_CHILDREN], $path);
    $lowered = analyzeWithSniffs([NUMBER_OF_CHILDREN], $path, static function (object $sniff): void {
        $sniff->minimum = 5;
    });

    expect(violationTuples($default))->toBe([]);
    expect(violationMessagesByLine($lowered->getErrors())[12][0])->toContain('has 5 children');
});

it('says nothing about piped input', function (): void {
    $source = "<?php\nclass Base {}\n"
        . implode("\n", array_map(static fn (int $i): string => "class Child{$i} extends Base {}", range(1, 15)));
    $file = analyzeStdinSource([NUMBER_OF_CHILDREN], $source);

    expect(tuplesFromMessages($file->getErrors()))->toBe([]);
});

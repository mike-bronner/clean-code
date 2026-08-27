<?php

/**
 * CleanCode.Metrics.NumberOfChildren — PHPMD's Design/NumberOfChildren.
 *
 * Every claim this file makes about PHPMD was measured against a live PHPMD
 * 2.15.0 (PDepend 2.16.2) run, not read off phpmd.org. Three of those
 * measurements contradict a plausible reading of the rule, and each is pinned
 * here:
 *
 *   - The threshold is *inclusive*. PHPMD's rule class reports when
 *     `$nocc >= $threshold`, so a class with exactly `minimum` children is
 *     already reported. #110's acceptance criteria ask for the opposite —
 *     "child count exactly at threshold → no violation" — and the tool this
 *     package replaces does not behave that way. passing.php's fourteen and
 *     failing.php's fifteen are the boundary pair at the shipped default.
 *   - The count is *cross-file*. A live run over a directory reports a parent
 *     for children declared in other files; the same run over the parent's file
 *     alone counts only the children in it. Both halves are pinned below,
 *     because the second is what a single-file `phpcs` invocation does.
 *   - Only `extends` counts, and only directly. Interface implementors,
 *     grandchildren, and anonymous subclasses are all silent — measured, and
 *     each carried by passing.php.
 *
 * docs/phpmd/design-numberofchildren.md records the full mapping.
 */

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

/**
 * The violating half of the boundary pair. Fifteen children is the shipped
 * default exactly, and PHPMD reports at `>=`, so this file is a violation while
 * passing.php's fourteen is not.
 *
 * The report lands on the parent's declaration line, not on any child's.
 */
it('reports a parent that has reached the threshold, on its declaration line', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
});

/**
 * The message quotes the count and the threshold in PHPMD's own wording. A live
 * PHPMD 2.15.0 run emits "The class Base has 3 children. Consider to rebalance
 * this class hierarchy to keep number of children under 3." for a parent of
 * three at a threshold of three; this is the same sentence with this file's
 * numbers.
 */
it('quotes the count and the threshold the way PHPMD does', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'failing.php');
    $messages = violationMessagesByLine($file->getErrors());

    expect($messages[11][0])->toBe(
        'The class Base has 15 children.'
            . ' Consider to rebalance this class hierarchy to keep number of children under 15.'
    );
});

/**
 * The silent half of the boundary pair, and the near-miss shapes with it:
 * fourteen real children, sixteen interface implementors, a grandchild, an
 * interface extending an interface, and anonymous subclasses in all three
 * spellings — `new class`, `new readonly class`, and an attribute between the
 * two. None of them is a direct named child.
 *
 * A live PHPMD run reports on none of them either, apart from
 * `new readonly class`: PDepend 2.16.2 cannot parse that spelling at all, so
 * PHPMD has no verdict on it to match. Silence is what the rule means for an
 * anonymous class, whichever way it is written.
 */
it('stays silent one child below the threshold, and on every near miss', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'passing.php');

    expect(violationTuples($file))->toBe([]);
});

/**
 * One child either side of the shipped default, driven through the property the
 * way a consuming ruleset sets it. Fourteen children reported at `minimum` 14
 * and silent at 15 is the inclusive threshold stated twice — the same fixture
 * flips verdict on the boundary alone, so neither assertion can be satisfied by
 * a sniff that simply never fires or always does.
 */
it('treats the threshold as inclusive', function (): void {
    $reported = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '14');
    $silent = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '15');

    expect(violationTuples($reported))->toBe([
        ['line' => 12, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationTuples($silent))->toBe([]);
});

/**
 * Silence on its own proves nothing, so the anonymous near misses are read back
 * at a threshold of one, where every class with a child of its own reports.
 * Two do: Base with its fourteen, and Child1 with the grandchild under it. The
 * three anonymous bases do not, and each of them carries fifteen anonymous
 * subclasses — the shipped threshold exactly, one base per spelling.
 *
 * `readonly` and an attribute both sit between `new` and `class`, so for those
 * two spellings the token in front of the declaration is not `new` and the
 * anonymous check misses it. Both are dropped a step later instead, for
 * declaring no name. This holds the outcome, which is the same for all three,
 * rather than the route each takes to it.
 */
it('counts no anonymous class as a child, however the declaration is spelled', function (): void {
    $file = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '1');

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 10, 'source' => NUMBER_OF_CHILDREN_ERROR],
        ['line' => 16, 'column' => 1, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[12][0])->toContain('has 14 children');
});

/**
 * The other half of that pair. `readonly` in front of a *named* class declares
 * an ordinary child, and a live PHPMD run counts it as one.
 *
 * Reading the modifier as the mark of an anonymous class — the shortest way to
 * make the case above pass — drops all fifteen children here and leaves the
 * file silent, so the two cases cannot both be satisfied by one wrong answer.
 */
it('counts a named readonly class as the child it is', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'readonly/Named.php');

    expect(violationTuples($file))->toBe([
        ['line' => 17, 'column' => 19, 'source' => NUMBER_OF_CHILDREN_ERROR],
    ]);
    expect(violationMessagesByLine($file->getErrors())[17][0])->toContain('has 15 children');
});

/**
 * The rule's whole reason for existing: the parent is reported for children it
 * cannot see from its own file.
 *
 * Fixture\Project\Base has fifteen children across four files and four
 * spellings — five in its own file, four through a plain import, three through
 * an aliased one, and three by fully qualified name. Only the five are visible
 * in Base.php itself, so a sniff that read one file could never reach fifteen.
 */
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

/**
 * Which spelling contributes what. The run is narrowed to Base.php plus one
 * contributing file at a time, so the count read back off Base is its own five
 * plus exactly that file's share: four through a plain import, three through an
 * aliased one, three by fully qualified name. Narrowed to Base.php alone it is
 * the five and nothing else.
 *
 * Each case asserts the pair either side of its total. A spelling that stopped
 * resolving would drop the count and flip the first assertion; one that
 * over-counted — by folding in another file's children, or by counting a child
 * twice — would flip the second. Neither can be satisfied by a sniff that never
 * fires or always does.
 */
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

/**
 * The group-brace shape `use Fixture\Project\{Same1 as Ignored};` is a
 * resolution path of its own, and Aliased.php's GroupAliased is its one
 * consumer. The child lands on Fixture\Project\Same1 — declared on line 16 of
 * Base.php, which is where it is read back.
 *
 * Landing anywhere else leaves line 16 silent: on the alias as written, on a
 * Fixture\Project\Nested\Ignored if the group's prefix were dropped, or on
 * Fixture\Project\Base if the group were folded into the plain
 * `use … as Root` above it. Base's own fifteen is asserted alongside, so the
 * lowered threshold cannot pass by reporting everything.
 */
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

/**
 * PHP's tokenizer hands the opening brace of `{$expr}` and `${expr}` over as an
 * array token while their closing `}` stays a bare one, so a parse that tracks
 * only the bare braces loses a level per interpolation, closes the enclosing
 * class body early, and reads a trait `use` written after that point as a
 * namespace import. The leaked alias binds the trait's short name to a global
 * one, and a following `extends` of that name is counted against whatever class
 * happens to carry it — here the three in Bare.php, which have no children at
 * all.
 *
 * Interpolated.php carries the shape three times, once per spelling — brace,
 * dollar, and an interpolation inside a heredoc — so a regression in any one of
 * them puts a report on its own line of Bare.php. Anchor, an ordinary parent and
 * child in the same run, is reported at the same lowered threshold: the silence
 * below is about resolution, not about an inert run.
 */
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

/**
 * An anonymous class declared where no other class-like body is open — at the
 * top level, or inside a function or closure — with a brace-balanced construct
 * in its *constructor-argument list*: a closure, a `match`, an interpolated
 * string. Each of the three is a pair of braces written before the anonymous
 * class's own body brace is ever reached, and each nets back to the depth the
 * declaration sat at.
 *
 * A parse that records the class body at the `class` keyword rather than at the
 * brace that opens it therefore closes that body on the argument list's own
 * closing brace, three tokens before the real body opens. The `use Ghost;`
 * inside the real body then reads as a namespace import, binds the trait's short
 * name to the global class of that name, and the `extends Ghost` below it is
 * counted against Bare.php's Ghost — which has no children at all.
 *
 * One file per construct, each run against Bare.php alone, so a fix that handles
 * one of the three and not the others is caught rather than carried. The
 * spelling file's own anchor pair is reported at the same lowered threshold: the
 * silence on Bare.php is about resolution, not about an inert run.
 */
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

/**
 * `class`, `trait`, `interface`, and `enum` are semi-reserved words: PHP allows
 * each as a method, constant, enum-case, or trait-alias name, and the tokenizer
 * emits the declaration keyword's own token for it — measured with
 * token_get_all(), not read off the manual. None of those four positions
 * declares a class-like body; each ends in a semicolon instead of opening one.
 *
 * A parse that records the keyword as awaiting a body is therefore left holding
 * an entry nothing consumes, and the next brace at the same parenthesis depth
 * claims it. In every fixture here that brace is the one opening the
 * `Fixture\SemiReserved\Consumer` namespace block, so the whole block reads as
 * being inside a class body — where a `use` is a trait's rather than an import.
 * `Imported` is dropped from the import map, `extends Imported` resolves to the
 * consumer's own namespace instead, and the two children land on a class that
 * nothing declares.
 *
 * One spelling per file, each analyzed on its own, so a fix that handles one
 * position of a semi-reserved name is caught here rather than carried by a
 * sibling. Each file's local pair is reported in the same run at the same
 * lowered threshold, so the imported parent's report is about resolution and
 * not about an inert run — and the two counts differ, two children against one,
 * so each report is read by count rather than by existence.
 */
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

/**
 * Braced namespace blocks let one file declare two different classes under the
 * same short name, and Collide.php does: Fixture\Namespaces\First\Same has three
 * children, Fixture\Namespaces\Second\Same has two.
 *
 * Which is why a file's declarations cannot be keyed by short name. Keyed that
 * way the second Same overwrote the first, both declarations read Second\Same's
 * count back, and First\Same's three children were unreachable — silent at a
 * threshold of three, and at two both lines claimed the same count of two.
 *
 * The counts differ, so each assertion is about a specific class rather than
 * about a report existing. Three is the pair's boundary — the first Same is over
 * it and the second is not — and two puts both over with a different number
 * each.
 */
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

/**
 * The same two blocks written on one line, which is the half of that shape the
 * declaration's line does not separate: both classes called Twin are declared on
 * line 13 of SameLine.php. Source order is what tells them apart, and it is read
 * off the same file twice — by PHP's tokenizer for the counts and by
 * PHP_CodeSniffer for the declaration being reported on.
 *
 * Three children for the first Twin and two for the second, so the pair of
 * reports on that one line is read by count and not by position alone. Ordering
 * the candidates the other way round swaps both numbers; dropping the ordinal
 * gives both reports the first Twin's three.
 */
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

/**
 * A file the sniff reads is not a file PHP has agreed to compile. token_get_all()
 * lexes rather than parses, so it hands over `use A\{A\{A\{…` without ever
 * requiring the braces to close or the result to be valid PHP — and a group-import
 * reader that recursed on each `{` recursed once per brace. Twenty thousand of
 * them is a 60KB file, and it ended the whole phpcs run with a memory exhaustion
 * fatal in well under a second: every other file in the run went unlinted because
 * one file beside them was shaped like this.
 *
 * The run is the shipped binary in a process of its own, at an explicit
 * memory_limit, because that is the failure as a consumer meets it and because
 * the suite's own process has no limit to exhaust — in-process, the recursion
 * merely takes seconds and then answers correctly. The limit is stated rather
 * than inherited so the case measures the sniff and not the runner's php.ini.
 *
 * Base's fifteen children are read back out of the report, so this is a completed
 * analysis of the whole directory at the shipped default and not a run that
 * exited early. The fixture is generated rather than committed: its size is the
 * whole point of it, and 60KB of `A\{` documents nothing sitting in the tree.
 */
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
        '--standard=' . cleanCodeRoot() . '/rules.xml',
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
    expect($status)->toBe(1);
    expect($reported)->toBe([
        'Base.php:5 The class Base has 15 children.'
            . ' Consider to rebalance this class hierarchy to keep number of children under 15.',
    ]);
});

/**
 * The ordinal that tells two same-named declarations on one line apart is read
 * out of an index built once per token stream, and this is what that buys.
 *
 * It used to be counted on demand, by walking back from each declaration over
 * every token sharing its physical line. Names are compared only after a
 * T_CLASS is found, so the walk is paid whether or not a line holds two
 * declarations of one name — and PHP puts no limit on how many declarations a
 * line may hold. The i-th costs O(i) and K of them cost O(K²) together: over
 * the 100KB fixture below, the shipped binary took 34.3s, against 0.52s once
 * the ordinals are indexed. That curve reaches a practical hang a few times
 * this size, on input a CI job, a pre-commit hook, or a lint service is handed
 * by whoever opened the pull request.
 *
 * Ten seconds separates the two by a wide margin rather than a fine one —
 * twenty times the fixed cost, under a third of the unfixed one — so the
 * assertion is about the shape of the curve and not about the speed of the
 * machine it runs on.
 *
 * Base's fifteen children are read back out of the report, so this is a
 * completed analysis of the whole directory at the shipped default and not a
 * run that exited early or skipped the packed file. The fixture is generated
 * rather than committed for the same reason the group-import one above is: its
 * size is the whole point of it.
 */
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
    $started = microtime(true);
    [$stdout, , $status] = runOutsidePackage(implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        cleanCodeRoot() . '/vendor/bin/phpcs',
        '--standard=' . cleanCodeRoot() . '/rules.xml',
        '--sniffs=' . NUMBER_OF_CHILDREN,
        '--report=json',
        '--no-cache',
        dirname($project),
    ])));
    $elapsed = (microtime(true) - $started);
    $report = json_decode($stdout, true);
    $reported = [];

    foreach (($report['files'] ?? []) as $path => $file) {
        foreach ($file['messages'] as $message) {
            $reported[] = basename((string) $path) . ':' . $message['line'] . ' ' . $message['message'];
        }
    }

    expect($status)->toBe(1);
    expect($reported)->toBe([
        'Base.php:5 The class Base has 15 children.'
            . ' Consider to rebalance this class hierarchy to keep number of children under 15.',
    ]);
    expect($elapsed)->toBeLessThan(10.0);
});

/**
 * runFiles() walks the run's file list by key(), never by value, and this is the
 * PHP_CodeSniffer behaviour that choice rests on.
 *
 * FileList::current() builds a LocalFile for the path it is on, and LocalFile's
 * constructor reads that whole file from disk. foreach asks an iterator for its
 * current value on every step whether the loop body uses it or not, so walking
 * the list with foreach read every file in the run and discarded the result — a
 * second read of every file, on top of the one scanFile() does for itself. The
 * list caches what it builds, so what each walk built is readable back off it.
 *
 * This characterises PHP_CodeSniffer rather than the sniff, and it is worth
 * saying which way that cuts. It fails if an upgrade moves the construction into
 * valid() or key(), which is exactly what would put the second read back without
 * a line of this package changing. It does not fail for the walk itself going
 * back to foreach — the test below it is what holds the walk to keys.
 */
it('leaves a run\'s file list unbuilt when it is walked by key', function (): void {
    $project = stageProjectOutsideTests(['Base.php' => "<?php\n\nclass Base\n{\n}\n"]);
    [$config, $ruleset] = buildRuleset([NUMBER_OF_CHILDREN], true);
    $config->files = [dirname($project)];

    $built = static function (PHP_CodeSniffer\Files\FileList $list): array {
        $property = new ReflectionProperty(PHP_CodeSniffer\Files\FileList::class, 'files');
        $property->setAccessible(true);

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

/**
 * The walk over the run's file list asks it for keys and never for values, and
 * this holds it there.
 *
 * The list is handed in rather than made inside the walk, so a list that
 * records being asked for a value can be given to it. That is the only way the
 * two walks differ from outside: the value a foreach pulls is a LocalFile whose
 * constructor reads the whole file, and it is then discarded — a second read of
 * every file in the run, on top of the one scanFile() does for itself — while
 * everything the walk returns stays byte-identical either way.
 *
 * The same list is walked again by value at the end, and that is the control:
 * without it, a recorder that never records would look exactly like a walk that
 * never asks.
 */
it('takes a run\'s file list by key, building no file for any of it', function (): void {
    $project = stageProjectOutsideTests(['Base.php' => "<?php\n\nclass Base\n{\n}\n"]);
    [$config, $ruleset] = buildRuleset([NUMBER_OF_CHILDREN], true);
    $config->files = [dirname($project)];

    $listed = new class ($config, $ruleset) extends PHP_CodeSniffer\Files\FileList {
        /**
         * How many times the list has been asked for the file it is on.
         */
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
    $walk->setAccessible(true);

    expect($walk->invoke($sniff, $listed))->toBe([$project]);
    expect($listed->built)->toBe(0);

    foreach ($listed as $ignored) {
        expect($ignored)->toBeInstanceOf(PHP_CodeSniffer\Files\LocalFile::class);
    }

    expect($listed->built)->toBe(1);
});

/**
 * phpcbf fixes in memory: it applies a loop's fixes to the token stream,
 * re-runs every sniff against the result, and writes to disk only once the
 * loops settle. So from the second loop on, the stream this sniff is handed
 * holds lines that the file on disk does not — and the inheritance map was read
 * from disk.
 *
 * Any fixable sniff in the same ruleset that adds or removes a line above a
 * class moves that class's declaration line. The ruleset's own BlankLines does
 * exactly that, which is what this stages: three blank lines sit above Base,
 * the fixer collapses them to one, and Base's declaration moves from line 5 to
 * line 3. Looked up at its new line in a map still holding it at its old one,
 * the class is not found and its fifteen children go unreported — phpcbf
 * finishes saying the file is clean, and `phpcs` over the file phpcbf just
 * wrote reports it immediately.
 *
 * The count is asserted, not just the violation: five of Base's children are
 * declared in Base.php itself and ten in the file beside it, so re-reading the
 * moved file has to take its first reading's five back out before adding them
 * again. Counting them twice reads as twenty and dropping them reads as ten;
 * only fifteen is the file read exactly once.
 *
 * The Fixer driven here is the one phpcbf drives, and the errors asserted are
 * the last loop's: File::process() clears them at the start of every pass.
 */
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

/**
 * The other way the stream and the disk hold different bytes, and the one an
 * editor opens: `phpcs --stdin --stdin-path=…` — or the `phpcs_input_file:`
 * marker — lints a buffer under the real file's path. Every integration that
 * lints as you type does this, and an unsaved buffer is not what is on disk.
 *
 * Here the buffer carries two lines the saved file does not, so Base sits on
 * line 5 in the buffer and line 3 on disk. The run is the directory either way,
 * so the ten children in the file beside it are counted either way: the only
 * thing that decides whether Base is found at all is which of the two sources
 * the sniff resolves it against.
 */
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

/**
 * A same-short-name class in another namespace (Fixture\Decoy\Base, three
 * children of its own) is in the same run as Fixture\Project\Base throughout.
 * Neither adds to the other — which comparing short names rather than fully
 * qualified ones would do, folding the two into one bucket of eighteen and
 * reporting that count for both.
 */
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

/**
 * The single-file case, which is parity rather than a limitation: point PHPMD
 * at Base.php alone and it counts the five children declared in it, not the
 * fifteen the directory holds. The sniff is handed the same file with no
 * project paths on the config — exactly what `phpcs Base.php` does — and
 * reports the same five.
 *
 * Silent at the shipped default of 15, reported at 5: the pair is what makes
 * this an assertion about the count rather than about the file being skipped.
 */
it('sees only the file in hand when the run has no project paths', function (): void {
    $path = NUMBER_OF_CHILDREN_BASE;
    $default = analyzeWithSniffs([NUMBER_OF_CHILDREN], $path);
    $lowered = analyzeWithSniffs([NUMBER_OF_CHILDREN], $path, static function (object $sniff): void {
        $sniff->minimum = 5;
    });

    expect(violationTuples($default))->toBe([]);
    expect(violationMessagesByLine($lowered->getErrors())[12][0])->toContain('has 5 children');
});

/**
 * Piped input has no path, so there is no codebase to resolve children against
 * and nothing the rule can honestly say. The source below would be a violation
 * at this threshold if it were read from a file.
 */
it('says nothing about piped input', function (): void {
    $source = "<?php\nclass Base {}\n"
        . implode("\n", array_map(static fn (int $i): string => "class Child{$i} extends Base {}", range(1, 15)));
    $file = analyzeStdinSource([NUMBER_OF_CHILDREN], $source);

    expect(tuplesFromMessages($file->getErrors()))->toBe([]);
});

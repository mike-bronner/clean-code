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

    expect(violationSourcesByLine($file->getErrors()))->toBe([11 => [NUMBER_OF_CHILDREN_ERROR]]);
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
 * interface extending an interface, and sixteen anonymous subclasses. None of
 * them is a direct named child, and a live PHPMD run reports on none of them.
 */
it('stays silent one child below the threshold, and on every near miss', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'passing.php');

    expect($file->getErrors())->toBe([]);
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

    expect(violationSourcesByLine($reported->getErrors()))->toBe([12 => [NUMBER_OF_CHILDREN_ERROR]]);
    expect($silent->getErrors())->toBe([]);
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

    expect(violationSourcesByLine($file->getErrors()))->toBe([12 => [NUMBER_OF_CHILDREN_ERROR]]);
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
    expect($silent->getErrors())->toBe([]);
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

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        12 => [NUMBER_OF_CHILDREN_ERROR],
        16 => [NUMBER_OF_CHILDREN_ERROR],
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

    expect(violationSourcesByLine($bare->getErrors()))->toBe([]);
    expect(violationSourcesByLine($anchor->getErrors()))->toBe([90 => [NUMBER_OF_CHILDREN_ERROR]]);
});

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

    expect(violationSourcesByLine($overThree->getErrors()))->toBe([17 => [NUMBER_OF_CHILDREN_ERROR]]);
    expect(violationMessagesByLine($overThree->getErrors())[17][0])->toContain('has 3 children');
    expect(violationSourcesByLine($overTwo->getErrors()))->toBe([
        17 => [NUMBER_OF_CHILDREN_ERROR],
        35 => [NUMBER_OF_CHILDREN_ERROR],
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

    expect(violationSourcesByLine($overThree->getErrors()))->toBe([13 => [NUMBER_OF_CHILDREN_ERROR]]);
    expect(violationMessagesByLine($overThree->getErrors())[13][0])->toContain('has 3 children');
    expect(violationSourcesByLine($overTwo->getErrors()))->toBe([
        13 => [NUMBER_OF_CHILDREN_ERROR, NUMBER_OF_CHILDREN_ERROR],
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
 * a line of this package changing. It cannot fail for runFiles() going back to
 * foreach: nothing observable outside that method separates the two walks, which
 * is why the fix carries this and not a test of its own output.
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

    expect($default->getErrors())->toBe([]);
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

    expect($file->getErrors())->toBe([]);
});

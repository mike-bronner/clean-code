<?php

/**
 * Behaviour of CleanCode.Classes.DisallowTypeIntrospection, the custom sniff
 * for the "Classes: Introspection / Type Casting" standard (issue #73).
 * Fixtures live in tests/fixtures/DisallowTypeIntrospectionSniff/.
 *
 * No PHPCS or Slevomat sniff flags type introspection by the role it plays, so
 * this is a custom sniff rather than a rules.xml wiring — the candidates and the
 * reasons they were rejected are in
 * docs/standards/classes-introspection-type-casting.md.
 *
 * There is no autofixed.php because the rule is detection-only: replacing a
 * type check with polymorphism means moving behaviour onto the object and
 * updating its call sites, which no token rewrite can do. The fixable-count
 * test below pins that, so the absent fixture stays an asserted fact.
 *
 * The sniff's whole difficulty is one question — which function body does this
 * token belong to — so most of the fixtures below are about that boundary, from
 * both sides: a check inside a body is a predicate and stays silent, a branch
 * inside the same body is that body's own branch and is reported.
 */

declare(strict_types=1);

const TYPE_INTROSPECTION_SNIFF = 'CleanCode.Classes.DisallowTypeIntrospection';

const TYPE_INTROSPECTION_INSTANCEOF = TYPE_INTROSPECTION_SNIFF . '.InstanceOf';

const TYPE_INTROSPECTION_FUNCTION = TYPE_INTROSPECTION_SNIFF . '.IntrospectionFunction';

/**
 * Every violation in failing.php, in file order, paired with the branch shape
 * it pins. Exact line/column tuples rather than a count, so a violation moving
 * between shapes cannot pass unnoticed.
 */
const TYPE_INTROSPECTION_VIOLATIONS = [
    [11, 20],   // if
    [13, 26],   // elseif
    [22, 23],   // ternary condition
    [28, 20],   // match arm condition
    [29, 20],   // match arm, first condition of a multi-condition arm
    [29, 50],   // match arm, second condition of the same arm
    [37, 25],   // switch case label
    [48, 23],   // while
    [59, 29],   // nested inside an if condition
    [68, 35],   // ternary condition, the check wrapped in a call
    [83, 24],   // if inside a closure body
    [93, 53],   // ternary inside an arrow function body
    [100, 24],  // match arm inside a closure body
    [110, 29],  // switch case label inside a closure body
    [125, 20],  // a plain if, after every callback above has closed
    [138, 78],  // if condition, after a callback closed within that same condition
    [150, 81],  // ternary condition, after a callback closed within that same condition
    [168, 27],  // ternary inside a closure body
    [175, 20],  // match arm inside an arrow function body
    [187, 60],  // match subject inside an arrow function body
    [208, 35],  // match arm result, the match written as a switch case label
    [227, 31],  // match arm result, the match written as a ternary condition
];

/**
 * Every violation in function-scope-branches.php: a branch written inside an
 * inline anonymous class's method, which the enclosing `if` condition then
 * branches on the result of.
 *
 * This is the over-suppression direction of the scope rule. A bound that
 * exempted the whole body instead of confining the search to it would report
 * none of these, and the sniff would go quiet on real violations.
 */
const TYPE_INTROSPECTION_INLINE_METHOD_VIOLATIONS = [
    [31, 28, TYPE_INTROSPECTION_INSTANCEOF],   // if inside the method body
    [52, 31, TYPE_INTROSPECTION_INSTANCEOF],   // ternary inside the method body
    [70, 28, TYPE_INTROSPECTION_INSTANCEOF],   // match arm inside the method body
    [90, 33, TYPE_INTROSPECTION_INSTANCEOF],   // switch case label inside the method body
    [111, 24, TYPE_INTROSPECTION_FUNCTION],    // while inside the method body
    [133, 21, TYPE_INTROSPECTION_FUNCTION],    // introspection function in an if inside the body
    [159, 20, TYPE_INTROSPECTION_INSTANCEOF],  // a plain if, after the inline body has closed
    [183, 20, TYPE_INTROSPECTION_INSTANCEOF],  // a concrete method, after bodyless declarations
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TYPE_INTROSPECTION_SNIFF);
});

/**
 * passing.php is the contract sweep's floor fixture, and it is discriminating
 * rather than empty: every construct the sniff registers on appears in it, in
 * the roles that are *not* a branch decision — an exception message, a log
 * line, an assertion, a `return $x instanceof Y;` predicate, a branch body, and
 * a callback passed into each kind of condition.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The rewrite the standard actually asks for: the type declared in the
 * signature and the varying behaviour moved onto the object, so nothing has to
 * ask what type anything is.
 */
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

/**
 * Every introspection *function* the sniff knows, each in a different branch
 * position, plus the two shapes that look like an exemption and are not: a
 * root-namespaced call, and a `...` that is a variadic unpack rather than the
 * first-class callable syntax.
 */
it('flags introspection functions driving a branch at their exact position', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'introspection-functions.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => TYPE_INTROSPECTION_FUNCTION,
        ],
        [
            [11, 13],  // get_class in an if
            [20, 16],  // get_debug_type in a ternary
            [25, 17],  // gettype as a switch subject
            [36, 13],  // is_a in a match arm
            [37, 13],  // is_subclass_of in a match arm
            [44, 14],  // a root-namespaced \get_class()
            [53, 13],  // an upper-cased call
            [64, 16],  // is_subclass_of in a while
            [76, 18],  // gettype in a case label
            [89, 23],  // get_debug_type as a match subject
            [104, 13], // a variadic unpack, which really does call
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

/**
 * The scope rule, silent direction. Every form of function body PHP has —
 * closure, arrow function, and a method written inline as an anonymous class —
 * paired against every branch position the sniff recognises. In each cell the
 * check decides what the body returns and the enclosing construct branches on
 * the result of calling it, so it is a predicate, not a branch decision.
 *
 * Filling the grid is the point. Deciding what bounds the search by listing
 * keywords left the third form out, and it was the form that looks least like a
 * callback while playing exactly the same role.
 */
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

/**
 * Every violation in property-hooks.php: a branch written inside a PHP 8.4
 * property hook's own body.
 *
 * The silences in that fixture matter as much as the reports and are pinned by
 * the same assertion being exact: a hook predicate stays silent even where the
 * property is declared inside an enclosing branch condition, which is the
 * false-positive class this fixture exists for. PHP_CodeSniffer gives a hook no
 * scope pointers at all, so a bound resolved from declaration tokens alone
 * measured the hook's own predicate against the condition around the property.
 */
const TYPE_INTROSPECTION_HOOK_VIOLATIONS = [
    [51, 30],   // if inside a block get hook
    [60, 29],   // ternary inside an arrow get hook
    [66, 30],   // match arm inside a block get hook
    [75, 35],   // switch case label inside a block get hook
    [91, 42],   // ternary inside a set hook's body, past its parameter list
    [145, 20],  // a plain if in a method, after every hook has closed
    [174, 38],  // if inside a closure an arrow hook's expression calls
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

/**
 * A `use function` import rebinds the bare name, so the call reaches the
 * imported function rather than the global one. The controls keep the test from
 * passing vacuously: a name this file does not import, and a root-qualified
 * call, which is the global function whatever the bare name resolves to.
 */
it('does not treat an imported name as the global introspection function', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'shadowed-by-import.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => TYPE_INTROSPECTION_FUNCTION,
        ],
        [
            [64, 13],  // an introspection function this file does not import
            [77, 14],  // a root-qualified \get_class()
        ]
    );

    expect(violationTuples($file))->toBe($expected);
});

/**
 * A function of the same name declared in the file shadows the global one. The
 * controls again: a root-qualified call, a name declared only as a *method* —
 * which an unqualified call never reaches, so it shadows nothing — and a name
 * the file does not declare at all.
 */
it('does not treat a name declared as a function in the file as the global one', function (): void {
    $file = analyzeFixture(TYPE_INTROSPECTION_SNIFF, 'shadowed-by-declaration.php');

    $expected = array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => TYPE_INTROSPECTION_FUNCTION,
        ],
        [
            [37, 14],  // a root-qualified \get_class()
            [51, 13],  // a name declared only as a method
            [69, 13],  // a name the file does not declare
        ]
    );

    expect(violationTuples($file))->toBe($expected);
});

/**
 * The ternary check resolves each token by walking forward until it meets a `?`
 * or an expression boundary, and a boolean chain is made of neither: `||` ends
 * no expression, so every check in `$a instanceof X || $b instanceof Y || …`
 * used to walk on to the statement's own end, once per check. That is quadratic
 * in the length of the chain, and nothing in rules.xml, the workflow, or PHPCS
 * itself bounds a file's length — one generated or accidentally-linted vendor
 * file was enough to inflate this one sniff superlinearly while every other
 * sniff in the same run stayed flat.
 *
 * Both endings are measured, because they are different exits from the same
 * walk: the ternary chain resolves at the `?` (and is reported, at every link),
 * the plain chain resolves at the `;` (and is silent, being a predicate the
 * caller branches on). A fix that bounded only the reported path would leave
 * the other quadratic.
 *
 * An asymptotic fix has no observable but time, so the budget sits an order of
 * magnitude above the measured cost rather than near it. Measured in this
 * harness at 2400 links per chain: 6.913s before the walk was resolved once per
 * file, 0.196s after — and 0.480s / 1.768s before at 600 / 1200, the ~4x per
 * doubling that names the growth as quadratic, against 0.054s / 0.095s after,
 * which is the 2x that names it linear. A 2.0s cap leaves a slow runner 10x
 * room while still failing the quadratic walk by 3.4x.
 *
 * The violation assertion is what stops the timing from passing vacuously: a
 * file the tokenizer gives up on is both fast and silent, and so is a sniff
 * that stopped reporting. Every link of the ternary chain is pinned, not a
 * count. The ruleset is built before the clock starts so the first parse of
 * rules.xml is not charged to the measurement.
 */
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

    buildRuleset([TYPE_INTROSPECTION_SNIFF]);

    $started = hrtime(true);
    $file = analyzeWithSniffs([TYPE_INTROSPECTION_SNIFF], $fixture);
    $elapsed = (hrtime(true) - $started) / 1e9;

    $expected = [];

    // Every link reports at the same column: the chain's first line indents by
    // 8 and spends `return `, each later line indents by 12 and spends `|| `,
    // which puts `$value` — and so `instanceof` — in the same place on both.
    for ($link = 0; $link < $links; $link++) {
        $expected[] = [
            'line' => $chainStart + $link,
            'column' => 23,
            'source' => TYPE_INTROSPECTION_INSTANCEOF,
        ];
    }

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([])
        ->and($elapsed)->toBeLessThan(2.0);
});

/**
 * Every check in a class of many sibling method bodies resolves against its own
 * body, whichever of them it is written in.
 *
 * The scope of a token used to be found by scanning the list of the file's
 * bodies, which is linear in how many the file declares: a class of K sibling
 * methods each holding one check paid that scan K times over. The lookup that
 * replaced it is a table read, so the answer no longer depends on how many
 * bodies precede or follow the one asked about. Both ends of the class are
 * pinned here because the scan's cost — and any error in the walk that replaced
 * it — falls hardest on the first-declared method, which the old order reached
 * last.
 *
 * There is no timing cap on this one, unlike the boolean-chain test above.
 * Measured on the shipped ruleset narrowed to this sniff, a class of 4000
 * sibling methods costs 1.84s with the scan and 1.60s with the lookup: the
 * quadratic term is real but too small at any file size that fits in memory to
 * separate from noise, and a cap tight enough to fail the scan would fail on a
 * slow runner too. What the assertion below pins is the answer, which is the
 * part a rewrite can get wrong.
 */
it('resolves the scope of a check in every one of many sibling bodies', function (): void {
    $methods = 50;
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'final class Dispatcher', '{'];
    $expected = [];

    for ($method = 0; $method < $methods; $method++) {
        $lines[] = "    public function decide{$method}(object \$value, array \$rows): string";
        $lines[] = '    {';

        // The predicate is silent and the branch is reported, so each body owes
        // exactly one violation: a scope resolved from a neighbour's body would
        // swap which of the two the sniff reports.
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

/**
 * The indexes this sniff builds once per token stream must not answer one
 * analysis with another analysis's pointers (#343).
 *
 * They used to be keyed by file name. Two sources analysed as STDIN report the
 * same name, and a single `Ruleset` reused across several analyses — which is
 * what buildRuleset()'s memoisation gives every call below — hands them one
 * sniff instance and one set of indexes.
 *
 * The two sources differ in exactly what the shadowed-name index records: A
 * imports `get_class` from another namespace, so its bare call reaches that
 * import and is silent; B imports a name that shadows nothing, so its call
 * reaches the global function and is reported. Under the old key B was measured
 * against A's index, inherited the import, and its violation went unreported.
 *
 * The third call is what separates a working key from no cache at all: it
 * re-analyses A and requires A's silence back, which a sniff that had simply
 * stopped caching would also give — but a sniff whose indexes leaked between
 * streams would then measure A against B's, and report A's call.
 */
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

/**
 * The indexes are built once for a token stream and read from for the rest of
 * it, rather than rebuilt on every read (#343).
 *
 * The test above proves the key never answers one analysis with another's
 * pointers. It cannot prove the other half of what a key is for, and neither
 * can any other black-box test: a sniff that rebuilt every index on every
 * single read would report exactly the same violations, only slower. These
 * counters are what tell the two apart.
 *
 * Each check below reaches the key guard three times — once resolving the bare
 * name against the file's imports, once resolving the enclosing scope, once
 * resolving the ternary — so n checks make 3n reads, of which one builds and
 * 3n-1 hit.
 */
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

/**
 * Every violation in expression-bodies.php, in file order.
 *
 * A braced body written inside an expression — `new class { … }`, a closure,
 * `match (…) { … }`, or a `${…}` owning no scope at all — is a balanced group
 * the branch scans cross whole, exactly as they cross a call's parentheses. The
 * first six entries are the checks a scan has to cross one to reach a branch
 * for; the rest sit *after* the body and were reported before this fixture
 * existed, which is what keeps the pairs discriminating rather than the whole
 * file simply going from silent to loud.
 *
 * The two silences in the fixture carry the other half of the rule and are
 * pinned by this assertion being exact: a statement block's braces are not a
 * group, they are where the expression ended, so a check in a `foreach` header
 * is not measured against the `?` of a later statement.
 */
const TYPE_INTROSPECTION_BRACED_OPERAND_VIOLATIONS = [
    [47, 15],   // match arm condition, past an anonymous class holding a match of its own
    [47, 42],   // the second condition of that same arm, past the closing brace
    [66, 15],   // switch case label, past an anonymous class
    [85, 23],   // ternary condition, crossing an anonymous class body to reach the `?`
    [90, 11],   // the check after that body
    [95, 23],   // ternary condition, crossing a closure body
    [97, 11],   // the check after that body
    [102, 23],  // ternary condition, crossing a `match` body
    [105, 11],  // the check after that body
    [114, 23],  // ternary condition, crossing a `${…}` owning no scope
    [114, 54],  // the check after those braces
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

/**
 * Source PHP_CodeSniffer cannot link is answered with silence, not with a
 * guess — the same stance the sniff keeps on a `match` with no scope opener.
 *
 * An unclosed brace is the one way a brace reaches the group rule with no
 * closer to cross to. Reading the closer anyway aborts the whole file with an
 * Internal.Exception, which is worse than the parse error PHP_CodeSniffer
 * already reports for it: every other sniff's verdict on that file is lost too.
 */
it('stays silent where a brace has no closer to cross to', function (): void {
    $source = <<<'PHP'
        <?php

        $label = $value instanceof Failure && new class {

        PHP;

    $file = analyzeStdinSource([TYPE_INTROSPECTION_SNIFF], $source);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * An import binds inside its own namespace block and nowhere else.
 *
 * The sniff used to collect `use function` imports itself, walking the whole
 * token stream and crediting every block with every import. Since #320 the
 * question belongs to CleanCode\Helpers\FunctionCalls, which resolves an import
 * against the block the call sits in — so the identical call in a block that
 * imports nothing is still PHP's own function.
 *
 * import-blocks.php spells the same call in both blocks, which is what makes
 * the assertion discriminating rather than a fixed verdict: the file-wide
 * approximation reports neither, and dropping import resolution altogether
 * reports both. Only per-block resolution reports exactly the second.
 *
 * Mutation-confirmed both ways — deleting the import reddens this, and so does
 * moving it above the first `namespace` statement.
 */
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

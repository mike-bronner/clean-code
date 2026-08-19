<?php

/**
 * Tests the custom CleanCode.ClearCode.SectionComment sniff (Clear Code:
 * Encapsulate Each Concept in a Method, #13, partial enforcement per #159).
 * Fixtures live in tests/fixtures/SectionCommentSniff/: every near-miss shape
 * in passing.php, every reported shape in failing.php, the two exclusion lists
 * in configured.php, and the PHP 8.4 hook limit in property-hooks.php. The
 * rule is detection-only, so there is no autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const SECTION_COMMENT = 'CleanCode.ClearCode.SectionComment';

const SECTION_COMMENT_WARNING = SECTION_COMMENT . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SECTION_COMMENT);
});

/**
 * Every shape the rule must stay silent on. Each group pins one of the sniff's
 * early returns, and a false positive on any of them makes an advisory rule
 * unusable in a real codebase:
 *
 * - line 13, a file-level comment followed by a statement, and line 18, a
 *   class-level comment between members — neither sits in a function body.
 * - lines 21-23 and the other docblocks, which tokenize as T_DOC_COMMENT_* and
 *   so never reach a sniff registered on T_COMMENT at all.
 * - line 28, a comment trailing a statement, and line 33, a one-line block
 *   comment sharing its line with the statement after it. A comment that
 *   shares a line annotates that line; it labels no block.
 * - lines 40-42, a block comment spanning three physical lines. PHP_CodeSniffer
 *   splits it into one T_COMMENT per line, so without the self-contained check
 *   the continuation lines read as whole comments on their own lines — the
 *   tokenizer quirk this fixture exists for.
 * - line 54, a comment with nothing but blank lines and the closing brace of
 *   its method after it, and line 62, the same at the end of a nested `if`
 *   whose method continues afterwards. Neither introduces a block.
 * - lines 70, 73, 76 and 79, the four debt markers, and lines 87, 90 and 93,
 *   the three formatter directives — all owned by sibling standards.
 * - line 101, a `phpcs:ignore` annotation. It names another sniff, so the
 *   suppression cannot account for this rule's silence: the tokenizer gives
 *   the annotation its own type rather than T_COMMENT.
 * - lines 110 and 112, comments inside an array literal, and line 121, one
 *   inside an argument list. What follows each is an element, not a statement
 *   in the enclosing scope.
 * - line 129, a comment inside an anonymous class declared in a method, and
 *   line 137, one above a `match` arm. Both still carry T_FUNCTION in their
 *   conditions, so reading the whole chain rather than the innermost scope
 *   would report both.
 * - lines 161-162, a two-line run with nothing but the closing brace after it,
 *   and lines 171-173, the same run set apart by a blank line. A blank line
 *   does not start a second label, so neither run reports at either line.
 * - lines 179-180, a comment inside an arrow function. Its body is one
 *   expression, so nothing further can follow the comment in that scope.
 * - line 187, a comment after a ternary `:`. The colon that opens a
 *   `case` body is admitted by being the opener of the comment's own scope,
 *   which this one is not — admitting `:` by token type instead would report
 *   here.
 * - line 195, a formatter directive carrying trailing text on the same
 *   comment. The directives are matched as substrings rather than as whole
 *   comments, which only this line pins: every other directive fixture is the
 *   bare spelling, which an equality match would silence just as well.
 * - lines 206, 220, 234-235 and 245 — comments after the closing brace of a
 *   closure, an anonymous class, an anonymous class inside an arrow function,
 *   and a `match`. Each brace closes an expression the statement around it
 *   continues past, so none of them ends a statement. The arrow-function one
 *   is the case PHP_CodeSniffer leaves `T_FN` out of `conditions` for: the
 *   comment arrives carrying the enclosing method as its innermost scope, and
 *   is silenced by the construct it actually sits in rather than by the arrow.
 * - line 256, a comment between a `do` block's brace and its `while`.
 * - lines 267, 280, 293 and 308 — comments before an `else`, an `elseif`, a
 *   `catch` and a `finally`. Each keyword continues the construct above the
 *   comment instead of starting a statement, and each carries the same
 *   conditions chain as the comment, so the chain comparison alone admits all
 *   four.
 * - lines 321-322 and 331, comments between bodyless interface and abstract
 *   method signatures. Those signatures open no scope at all, which the rule
 *   has to survive without reporting and without erroring.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every section label is flagged, once, at its own line:
 *
 * - line 17, straight after the opening brace; line 27, after a statement;
 *   line 39, after a nested block. The three statement boundaries a label can
 *   follow.
 * - line 47, a `#` comment, and line 55, a one-line `/` `* … *` `/` comment —
 *   the two spellings besides `//` that tokenize as T_COMMENT.
 * - line 63, the head of a two-line comment run, and line 72, the head of a
 *   run whose two lines are set apart by a blank one. Only the head reports in
 *   either shape: the run is one label for one block, and lines 64 and 74 are
 *   deliberately absent below.
 * - line 82, a label separated from its block by two blank lines. Blank lines
 *   are ignored when looking for the following statement.
 * - line 93, a label inside a closure.
 * - lines 103, 115, 125, 139, 142, 154, 159, 164, 176, 179, 219 and 224 — one
 *   label per control structure the scope walk passes through on its way to
 *   the function that owns it: `if`, `foreach`, `try`, `elseif`, `else`,
 *   `for`, `while`, `do`, `catch`, `finally`, and a `case` and a `default`
 *   body. Dropping any one of them from that walk stops its label reporting,
 *   which is what makes this list one assertion per entry rather than a
 *   sample.
 * - line 188, a label followed by an `if` rather than a simple statement, and
 *   line 200, one followed by `return`. A compound statement and a
 *   control-flow keyword are both blocks worth extracting.
 * - line 207, a label above a `case` arm. A `case` body opens on `:` rather
 *   than `{`, which is why the boundary is read off the comment's own scope.
 * - line 238, a label inside an alternative-syntax `foreach`, which opens on
 *   `:` the same way.
 * - line 250, a label inside a method of an anonymous class, and line 327, one
 *   inside a closure at file scope. Neither is nested in a named method, and
 *   both are function bodies in their own right.
 * - lines 261, 270, 279, 288, 298 and 307 — a label written directly under a
 *   debt marker, a formatter directive, a docblock, a `phpcs:ignore`
 *   annotation naming another sniff, a multi-line block comment and a comment
 *   trailing a statement. None of those six is a line this rule would report
 *   on its own, so none of them takes the label below it into its run: reading
 *   "is the line above comment-shaped?" instead drops all six labels silently,
 *   and the comment above each label is deliberately absent from the map
 *   below.
 * - line 319, a label after a bare `{ … }` block, which PHP_CodeSniffer
 *   records no scope owner for. Its brace still ends a statement, which is the
 *   ownerless case the brace test has to admit.
 */
it('flags every section label at its own line with the expected code', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            17 => [SECTION_COMMENT_WARNING],
            27 => [SECTION_COMMENT_WARNING],
            39 => [SECTION_COMMENT_WARNING],
            47 => [SECTION_COMMENT_WARNING],
            55 => [SECTION_COMMENT_WARNING],
            63 => [SECTION_COMMENT_WARNING],
            72 => [SECTION_COMMENT_WARNING],
            82 => [SECTION_COMMENT_WARNING],
            93 => [SECTION_COMMENT_WARNING],
            103 => [SECTION_COMMENT_WARNING],
            115 => [SECTION_COMMENT_WARNING],
            125 => [SECTION_COMMENT_WARNING],
            139 => [SECTION_COMMENT_WARNING],
            142 => [SECTION_COMMENT_WARNING],
            154 => [SECTION_COMMENT_WARNING],
            159 => [SECTION_COMMENT_WARNING],
            164 => [SECTION_COMMENT_WARNING],
            176 => [SECTION_COMMENT_WARNING],
            179 => [SECTION_COMMENT_WARNING],
            188 => [SECTION_COMMENT_WARNING],
            200 => [SECTION_COMMENT_WARNING],
            207 => [SECTION_COMMENT_WARNING],
            219 => [SECTION_COMMENT_WARNING],
            224 => [SECTION_COMMENT_WARNING],
            238 => [SECTION_COMMENT_WARNING],
            250 => [SECTION_COMMENT_WARNING],
            261 => [SECTION_COMMENT_WARNING],
            270 => [SECTION_COMMENT_WARNING],
            279 => [SECTION_COMMENT_WARNING],
            288 => [SECTION_COMMENT_WARNING],
            298 => [SECTION_COMMENT_WARNING],
            307 => [SECTION_COMMENT_WARNING],
            319 => [SECTION_COMMENT_WARNING],
            327 => [SECTION_COMMENT_WARNING],
        ]);
});

/**
 * Each warning is reported at the comment itself, so an editor's inline marker
 * sits under the label rather than under the block it introduces. The columns
 * are asserted for one line per indentation depth: line 327 sits in a closure
 * at file scope, line 17 in a method body, line 93 one level deeper inside a
 * closure, and line 219 two levels deeper inside a `case` body.
 */
it('reports at the comment rather than the statement it introduces', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 327, 'column' => 5, 'source' => SECTION_COMMENT_WARNING])
        ->toContain(['line' => 17, 'column' => 9, 'source' => SECTION_COMMENT_WARNING])
        ->toContain(['line' => 93, 'column' => 13, 'source' => SECTION_COMMENT_WARNING])
        ->toContain(['line' => 219, 'column' => 17, 'source' => SECTION_COMMENT_WARNING]);
});

/**
 * The message quotes the offending comment and names the standard, so a
 * developer reading the report knows both which label to remove and why. The
 * `#` spelling is asserted alongside the `//` one because the quoted text is
 * the comment's own source, trimmed but not otherwise rewritten.
 */
it('quotes the comment and names the standard in the warning message', function (): void {
    $warnings = analyzeFixture(SECTION_COMMENT, 'failing.php')->getWarnings();

    expect($warnings[17][9][0]['message'])
        ->toContain('// Validate the payload.')
        ->toContain('extract the block it introduces into a method named after it')
        ->toContain('docs/standards/clear-code-encapsulate-each-concept-in-a-method.md')
        ->and($warnings[47][9][0]['message'])->toContain('# Normalise the keys.')
        ->and($warnings[55][9][0]['message'])->toContain('/* Normalise the keys. */');
});

/**
 * Both exclusion lists are public sniff properties, as the standard's doc
 * advertises, and one fixture pins both directions for each. Under the shipped
 * defaults only the unmarked label (line 33) reports, while the debt marker
 * (line 17) and the two formatter directives (lines 25 and 41) are silent.
 * Emptying either list makes the comments it owned report; pointing either list
 * at a word the unmarked label contains silences that label instead and lets
 * the comments the list used to own through. A property that was ignored would
 * leave every run identical to the first.
 *
 * Line 41 is the same directive as line 25 with trailing text after it on the
 * one comment. It is the only fixture anywhere that separates the substring
 * match the directives are documented to use from an equality match: line 25's
 * bare spelling is silenced by either. It moves with line 25 in every run
 * below, which is what says the substring match is doing the silencing rather
 * than a second rule. The spelling itself is left to the fixture — written out
 * here it would trip CleanCode.CodeStyle.NoFormatterDirectives against this
 * file, exactly as it would in the sniff's own source.
 */
it('exposes configurable debt-marker and formatter-directive lists', function (): void {
    expect(array_keys(analyzeFixture(SECTION_COMMENT, 'configured.php')->getWarnings()))
        ->toBe([33]);

    $withoutMarkers = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->debtMarkers = [];
    });

    expect(array_keys($withoutMarkers->getWarnings()))->toBe([17, 33]);

    $withoutDirectives = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->formatterDirectives = [];
    });

    expect(array_keys($withoutDirectives->getWarnings()))->toBe([25, 33, 41]);

    $retunedMarkers = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->debtMarkers = ['Normalise'];
    });

    expect(array_keys($retunedMarkers->getWarnings()))->toBe([17]);

    $retunedDirectives = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->formatterDirectives = ['Normalise'];
    });

    expect(array_keys($retunedDirectives->getWarnings()))->toBe([25, 41]);
});

/**
 * The same two lists again, arriving the way a consuming project's ruleset
 * delivers them — through Ruleset::setSniffProperty(), which is what parsing a
 * `<property name="debtMarkers" type="array">` element calls. The callback
 * above assigns the property directly; only this path proves the lists the
 * standard's doc advertises in XML are configurable in XML rather than merely
 * writable from PHP. Each list is replaced with one the fixture does not use,
 * so the comments that list owned report and the rest stay exactly as the
 * defaults leave them — a property the XML path failed to deliver would leave
 * both runs reporting only line 33.
 */
it('takes both lists from a ruleset property element', function (): void {
    $markers = analyzeFixtureWithRulesetProperties(SECTION_COMMENT, 'configured.php', [
        'debtMarkers' => ['NOTE'],
    ]);

    expect(array_keys($markers->getWarnings()))->toBe([17, 33]);

    $directives = analyzeFixtureWithRulesetProperties(SECTION_COMMENT, 'configured.php', [
        'formatterDirectives' => ['@fmt:off'],
    ]);

    expect(array_keys($directives->getWarnings()))->toBe([25, 33, 41]);
});

/**
 * The debt markers are matched on word boundaries rather than as substrings,
 * so a comment that merely contains the letters is still a section label. Both
 * halves are asserted in one run: line 17's `Hack` is the marker in another
 * casing and is silent, while line 20's `Unshackle` contains the same four
 * letters mid-word and reports. Dropping the boundaries from the pattern
 * silences line 20 and fails this test; dropping the exclusion altogether
 * reports line 17 and fails it too.
 */
it('matches debt markers as words rather than as substrings', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'marker-substring.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            20 => [SECTION_COMMENT_WARNING],
        ]);
});

/**
 * PHP 8.4 property hooks are the one construct that bears no scope of its own:
 * PHP_CodeSniffer gives a hook body no scope opener, so its comments carry the
 * class as their innermost condition and read exactly like a comment between
 * class members. The rule stays silent on them, which is recorded here as a
 * known limit rather than left to look like coverage. The ordinary method in
 * the same fixture still reports, so a sniff that had fallen silent on the
 * whole file fails this test rather than passing it.
 */
it('stays silent inside a property hook while still reporting beside it', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'property-hooks.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            28 => [SECTION_COMMENT_WARNING],
        ]);
});

/**
 * The one entry in the sniff's function-like list that decides nothing, pinned
 * as a fact about PHP_CodeSniffer rather than left as a claim in a docblock:
 * T_FN carries a scope_opener/scope_closer pair but is never recorded in a
 * token's conditions, so a comment written inside an arrow function arrives
 * carrying the *enclosing* function as its innermost scope. That is why
 * removing T_FN from that list changes no assertion in this file, and why the
 * rule's silence on the arrow-function fixture comes from the anonymous class
 * the comment follows rather than from the arrow. A PHP_CodeSniffer release
 * that started recording T_FN fails here — which is the release that would
 * make the entry live, and the reason it is listed rather than omitted.
 */
it('is never handed an arrow function as a comment\'s enclosing scope', function (): void {
    $tokens = analyzeFixture(SECTION_COMMENT, 'passing.php')->getTokens();
    $inArrow = [];

    foreach ($tokens as $token) {
        if ($token['code'] === T_COMMENT && str_contains($token['content'], 'keeps out of this comment')) {
            $inArrow[] = $token['conditions'];
        }
    }

    expect($inArrow)->toHaveCount(1)
        ->and($inArrow[0])->not->toContain(T_FN)
        ->and($inArrow[0])->toContain(T_FUNCTION);
});

/**
 * Pins the detection-only decision: extracting a labelled block into a
 * well-named method is a redesign, not a mechanical rewrite, so no violation
 * is auto-fixable. Warnings rather than errors, because a comment can explain
 * *why* instead of labelling *what* and the token stream cannot tell the two
 * apart — a violation must not fail a consumer's build.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect($file->getWarningCount())->toBe(34)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The rule has to survive this package's own source — every sniff, helper and
 * test file, with the deliberately non-compliant fixtures excluded — without
 * erroring and without failing anybody's build.
 *
 * It is deliberately not "silent across all of it". This codebase documents
 * its own tokenizer reasoning heavily, a comment can explain *why* rather than
 * label *what*, and no token stream separates the two: the rule reports a
 * couple of hundred genuine *why*-comments here, which #159 names as the
 * documented cost of a Tier 3 heuristic rather than a defect. Rewriting them
 * is out of scope for that issue, so what is pinned instead is the gate that
 * matches an advisory rule — no error, nothing fixable, and no file that makes
 * the sniff throw. The greater-than-zero warning count is the other half: a
 * sniff that had quietly stopped registering would satisfy the first three.
 *
 * Asserted through the sniff itself rather than a `phpcs` subprocess so a
 * failure names the file; tests/Contract/ShippedPackageSmokeTest.php covers
 * the shipped-binary direction.
 */
it('runs over this package\'s own source reporting only warnings', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [];

    foreach ([$root . '/CleanCode', $root . '/tests'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $isFixture = str_contains($path, '/fixtures/');

            if ($file->isFile() === true && $file->getExtension() === 'php' && $isFixture === false) {
                $files[] = $path;
            }
        }
    }

    expect($files)->not->toBeEmpty();

    $errors = 0;
    $fixable = 0;
    $warnings = 0;

    foreach ($files as $path) {
        $file = analyzeWithSniffs([SECTION_COMMENT], $path);
        $errors += $file->getErrorCount();
        $fixable += $file->getFixableCount();
        $warnings += $file->getWarningCount();
    }

    expect($errors)->toBe(0)
        ->and($fixable)->toBe(0)
        ->and($warnings)->toBeGreaterThan(0);
});

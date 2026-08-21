<?php

/**
 * Tests the custom CleanCode.Naming.ActionMethodReturn sniff (#172), the one
 * token-visible slice of Methods: Naming (#61,
 * docs/standards/methods-naming.md): a method named for an action verb that
 * hands a value back. Fixtures live in tests/fixtures/ActionMethodReturnSniff/.
 *
 * The rest of the standard stays code review, and the boundaries are other
 * issues' — boolean accessor naming is #116, model query prefixes are #44, and
 * method-name casing is #22/#100. Nothing here reads a name for meaning; it
 * reads a name for a configured prefix and a declaration for a return.
 *
 * Warning severity, not error: framework idioms legitimately return a status
 * from an action method (Eloquent's `save(): bool`), so this points at review
 * candidates. Detection only — dropping a return type changes what every call
 * site can do with the call, so there is no autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const ACTION_METHOD_RETURN = 'CleanCode.Naming.ActionMethodReturn';

const ACTION_METHOD_RETURN_WARNING = ACTION_METHOD_RETURN . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ACTION_METHOD_RETURN);
});

/**
 * The sniff's own source passes rules.xml, the standard it belongs to. That is
 * the claim rules.xml makes about this file, and the reason the file reads the
 * way it does: match(true) guard chains instead of `if`
 * (CleanCode.Conditionals.AvoidConditionals), counted folds instead of
 * array_map()/array_filter() (CleanCode.Arrays.ConvertToCollection), the File
 * API instead of the token array (CleanCode.Arrays.ArrayAccessors), a NOWDOC
 * message (CleanCode.Strings.MultilineStrings), and the verb list written
 * several to a line rather than fourteen lines of one repeated token shape
 * (CleanCode.Pattern.AvoidDuplicateCodeBlocks). Each replacement those Arrays
 * and Strings rules ask for is a Laravel helper this package does not ship, so
 * each is written around rather than adopted.
 *
 * Asserted here because nothing else does. This exact claim was checked off
 * unrun once already on this sniff — the source reported 18 errors and 30
 * warnings while the box was ticked — and the sibling precedent is worse: the
 * same claim on ManualModelResolutionSniff.php was true when written and then
 * quietly falsified by a *later* standard landing in rules.xml, with no test to
 * notice. A comment cannot catch either failure; this can.
 *
 * Every sniff wired into rules.xml is active, not just this one, because the
 * claim is about the whole standard. Run through the *installed* phpcs rather
 * than an in-process ruleset, because "exits 0" is a claim about the binary a
 * consumer runs. Warnings are counted alongside errors deliberately: this sniff
 * reports warnings itself, so a check that read errors only would stay green
 * through exactly the drift most likely to happen. The status is asserted
 * beside the messages because phpcs exits 0 only when it reported nothing at
 * all — the two together say "phpcs looked, and found nothing," which an empty
 * message list alone does not.
 *
 * Confirmed non-vacuous by restoring the previous `if`-chain spelling of
 * matchedPrefix(), which reddens this assertion with
 * CleanCode.Conditionals.AvoidConditionals.IfStatement warnings and a status
 * of 1.
 *
 * One exception is recorded rather than silenced, and the status is 1 because
 * of it: CleanCode.ClearCode.SectionComment (#159) warns on the standalone
 * comment above conditionPointer()'s hoisted `getCondition()` call. That
 * comment explains *why* the call is hoisted rather than labelling a block,
 * which the token stream cannot tell apart from a section label — the reason
 * that rule ships as an advisory warning. Recorded here, exactly, rather than
 * suppressed or rewritten: #159's own criteria put rewriting this package's
 * explanatory comments out of scope, and an exact list still reddens on any
 * *other* drift, which is the reason this test was written.
 */
it('passes the standard it belongs to', function (): void {
    $run = installedPhpcsRun(
        cleanCodeRoot() . '/rules.xml',
        cleanCodeRoot() . '/CleanCode/Sniffs/Naming/ActionMethodReturnSniff.php'
    );

    expect(array_column($run['messages'], 'source'))->toBe(['CleanCode.ClearCode.SectionComment.Found'])
        ->and($run['status'])->toBe(1);
});

/**
 * The compliant fixture carries the constructs the sniff registers on — methods
 * with and without a declared return type, with and without a body — plus the
 * near-miss shapes it must stay silent on:
 *
 * - line 12, a *property* whose name starts with an action verb.
 * - lines 17 and 25, the compliant `void` and `never` declarations.
 * - line 35, no declared type and only a bare `return;`. Reading "the body has
 *   a return" without asking what follows it would report this.
 * - line 47, no declared type and no `return` at all.
 * - lines 56 and 64, `settleInvoice()` and `addressOf()`. A lower-case
 *   character after the verb means the verb never was a word of its own.
 * - line 75, `SetSubtitle()`. The match is case-sensitive, because a method
 *   named this way is a casing violation owned by #22/#100 — folding case here
 *   would report one declaration twice and describe it correctly neither time.
 * - line 83, `getTitle()`, which starts with no configured verb at all.
 * - lines 92, 99, 106, and 118, the four fluent spellings, exempt while
 *   $allowFluentInterface is on. Line 118 returns `$this` on two paths, so the
 *   "every value-return" rule is exercised and not just the last one.
 * - line 134, a method whose own body returns nothing while a closure inside it
 *   returns a value; line 145, the same with a named function declared in the
 *   body; line 159, the same with an anonymous class. All three declare no
 *   return type of their own, so the body really is consulted — reading every
 *   `return` between the braces would report all three.
 * - line 162, the anonymous class's own compliant `setMode(): void` — an
 *   anonymous class's methods are methods, and this one obeys the rule.
 * - line 180, an abstract declaration with no body; line 189, an interface
 *   method with neither a body nor a declared type.
 * - lines 197, 202, and 206, a plain function, a closure, and an arrow
 *   function. The standard describes an action taken on a class, which none of
 *   them is.
 * - line 240, `return (($this));`. Grouping parentheses nest, so the builder
 *   idiom is still the builder idiom however many pairs are written around it.
 *   Taking one pair off and comparing what is left would call this a
 *   value-return.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every shape that reports, at the method's *name* token — the name is half of
 * what the rule asks to be changed, and the token the reader has to find. The
 * three columns that are not 21 are the declarations whose modifiers push the
 * name further along the line: `public static function` (28),
 * `abstract public function` (30).
 *
 * The declared-type half:
 *
 * - line 12, a plain `string`.
 * - line 21, `?int`. The `?` only adds a third state to the same value.
 * - line 30, the union `int|float`.
 * - line 40, `static|false`. The fluent exemption does not reach a union: this
 *   hands back either the object or a value, and the value is the half the rule
 *   is about.
 * - line 96, a *qualified* type naming this very class. Resolving it against
 *   the class name needs the file's imports, so it is not attempted — and the
 *   declaration reports, rather than being silently exempted on a guess.
 * - line 104, a static method; line 112, a plain instance method.
 * - lines 135 and 143, an abstract method and an interface method. Neither has
 *   a body, and neither needs one: the declaration alone says a value comes
 *   back.
 * - lines 148 and 158, a trait method and an enum method.
 * - line 168, a method of an anonymous class.
 *
 * The body half, where nothing is declared:
 *
 * - line 48, a plain `return <expr>;`.
 * - line 58, `return $this;` on one path and a result on the other. That mix is
 *   the command-query blur the rule names, so the exemption does not apply.
 * - line 70, `return $this->total;` — a value built from `$this`, not `$this`.
 *   Matching the expression on token type and adjacency is what tells them
 *   apart.
 * - line 78, `set()`, where the verb is the whole name; line 86,
 *   `store_line()`, where an underscore ends the camelCase word.
 * - line 122, `return $status;` — a single token, then the semicolon, exactly
 *   the shape of `return $this;` without being it. Matching the fluent body on
 *   adjacency alone would exempt this.
 * - line 200, `return ($this->number);` — parenthesised, and still not `$this`.
 *   Taking the grouping parentheses off is what lets `return ($this);` be read
 *   as the builder idiom, and this is the half that proves taking them off does
 *   not swallow the expression with them.
 * - lines 220 and 225, `return $this();` and `return ($this)();`. Both invoke
 *   __invoke() and hand back *its* result, which is the value-return this rule
 *   is about. They are written with the same characters as the builder idiom,
 *   so a comparison that takes every parenthesis out of the expression reads
 *   both as `$this` and silently exempts them. A grouping parenthesis is the
 *   one whose match closes the expression, and neither of these has one:
 *   `$this()` is followed by the call, and `($this)()` closes its first pair
 *   before the call rather than at the end.
 *
 * The standalone `null` type, on lines 181, 186 and 191:
 *
 * - `null` is a declared type in its own right, not the nullable marker. It is
 *   neither `void` nor `never`, so a value comes back.
 * - None of the three has a body — an interface method, an abstract method, and
 *   an empty one — which is what makes reading the type the whole answer.
 *   Dropping the member from a one-member union leaves an empty type, an empty
 *   type reads as "none declared", and a body scan over no body finds no return
 *   and reports nothing at all.
 */
it('flags every action method that returns a value in the failing fixture', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 12, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 21, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 30, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 40, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 48, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 58, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 70, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 78, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 86, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 96, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 104, 'column' => 28, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 112, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 122, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 135, 'column' => 30, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 143, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 148, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 158, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 168, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 181, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 186, 'column' => 30, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 191, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 200, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 220, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 225, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

/**
 * The question a new custom sniff has to settle: does a vendor standard already
 * answer this? Four candidates come closest, and this runs each one's *whole*
 * standard over the failing fixture rather than only what rules.xml has already
 * wired in, because the master ruleset can only report on sniffs it references.
 *
 * None of them pairs a name against what the declaration hands back:
 *
 * - PSR1.Methods.CamelCapsMethodName, Squiz.NamingConventions.ValidFunctionName
 *   and PEAR.NamingConventions.ValidFunctionName read the name's *shape*.
 *   Between them they report one line, 86 — `store_line()`, for the underscore
 *   — and would keep reporting it after the return type was removed.
 * - SlevomatCodingStandard.TypeHints.ReturnTypeHint asks only that a type be
 *   *declared*, and how completely. It reports the seven declarations that omit
 *   one (48, 58, 70, 122, 200, 220, 225) and line 148's bare `array`, for an
 *   unspecified item type — and stays silent on the other sixteen, which declare
 *   a returning type perfectly well. That is the opposite half of this rule: it
 *   wants a type where there is none, this wants none where there is a type.
 *
 *   The three `: null` declarations on lines 181, 186 and 191 are where the two
 *   halves are furthest apart: each declares a type, so ReturnTypeHint is
 *   satisfied and silent, and each declares a *returning* type, which is the
 *   whole of what this sniff is reporting.
 *
 * Their nine lines overlap this sniff's twenty-four by shape, not by coverage:
 * fix every one of those nine and all twenty-four still report here. Pinned
 * rather than asserted once in a comment, because a vendor upgrade can quietly
 * change the answer.
 */
it('is answered by no nearby vendor standard', function (): void {
    $candidates = [
        'PEAR.NamingConventions.ValidFunctionName',
        'PSR1.Methods.CamelCapsMethodName',
        'SlevomatCodingStandard.TypeHints.ReturnTypeHint',
        'Squiz.NamingConventions.ValidFunctionName',
    ];
    $path = fixturePath('ActionMethodReturnSniff', 'failing.php');
    $reported = [];

    foreach (['PEAR', 'PSR12', 'SlevomatCodingStandard', 'Squiz'] as $standard) {
        foreach (allViolationSourcesByLine(analyzeWithStandard($standard, $path)) as $line => $sources) {
            foreach ($sources as $source) {
                foreach ($candidates as $candidate) {
                    if (str_starts_with($source, $candidate . '.') === true) {
                        $reported[$candidate][$line] = $line;
                    }
                }
            }
        }
    }

    ksort($reported);
    $reported = array_map('array_values', $reported);

    expect($reported)->toBe([
        'PEAR.NamingConventions.ValidFunctionName' => [86],
        'PSR1.Methods.CamelCapsMethodName' => [86],
        'SlevomatCodingStandard.TypeHints.ReturnTypeHint' => [48, 58, 70, 122, 148, 200, 220, 225],
        'Squiz.NamingConventions.ValidFunctionName' => [86],
    ]);
});

/**
 * The message names the method, so a report over a whole codebase says which
 * declaration to look at, and it names the verb that matched, so a reader can
 * tell a genuine finding from a prefix list that wants tuning.
 */
it('names the method and the matched verb in the message', function (): void {
    $warnings = analyzeFixture(ACTION_METHOD_RETURN, 'failing.php')->getWarnings();

    expect($warnings[12][21][0]['message'])
        ->toContain('setReference()')
        ->toContain('"set"');
});

/**
 * Warning severity, and offered to no fixer. Both halves matter: reported as an
 * error, a `save(): bool` inherited from Eloquent would fail every consuming
 * project's build, and offered to the fixer there is no safe rewrite — dropping
 * a return type changes what every call site can do with the call, and renaming
 * the method rewrites the call sites themselves.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'failing.php');

    expect($file->getWarningCount())->toBe(24)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

/**
 * The fluent exemption ships on, so the eight spellings of a builder —
 * `static`, `?static`, `self`, a bare enclosing-class name, that name unioned
 * with `null`, a body whose every value-return is `return $this;`, and the two
 * all-fluent unions `self|static` (line 69) and `Builder|static` (line 81) —
 * are silent out of the box. This run is the baseline the next one is measured
 * against: without it, a property that silenced the sniff outright would look
 * exactly like a working exemption.
 *
 * The two unions are the case a whole-string comparison got wrong: every member
 * of them independently means "my own object," so the union chains like any
 * other builder. Their discriminating opposite is `failing.php` line 40,
 * `static|false`, which still reports — a union is exempt only when *no* member
 * of it can be a value.
 */
it('exempts a fluent interface by default', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'fluent.php');

    expect(warningTuples($file))->toBe([]);
});

/**
 * Switching the exemption off reads those same eight declarations like any
 * other returning method. A project that has decided against fluent setters
 * gets the whole rule, and the eight lines it gets are exactly the eight the
 * exemption was holding back — the two unions included, which is what proves
 * lines 69 and 81 are silent above because the exemption reached them and not
 * because the sniff never looked at them.
 */
it('flags a fluent interface when allowFluentInterface is off', function (): void {
    $file = analyzeFixtureWithProperty(ACTION_METHOD_RETURN, 'fluent.php', 'allowFluentInterface', false);

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 25, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 32, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 39, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 46, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 58, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 69, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 81, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

/**
 * Turning the exemption off widens the rule to fluent declarations and to
 * nothing else. Every other near-miss in `passing.php` stays silent: the seven
 * fluent spellings on lines 92, 99, 106, 118, 179, 191 and 240 are the whole
 * difference.
 *
 * Lines 179, 191 and 240 — `return ($this);`, a `return $this;` with a comment
 * written before it, and `return (($this));` — are the group that proves each is
 * silent above because the exemption reached it, not because the sniff read one
 * token, found neither `$this` nor anything like it, and gave up. Reading one
 * token answers "not fluent" in *both* states, so the default run alone cannot
 * tell the two apart. Their opposites are in `failing.php`: line 200,
 * `return ($this->number);`, where the grouping parentheses come off and what is
 * inside them is still not `$this`, and lines 220 and 225, `return $this();` and
 * `return ($this)();`, where the parentheses are a call rather than a grouping
 * and stay on.
 *
 * The two that matter here declare no return type and hand back nothing — line
 * 35, whose body holds only a bare `return;`, and line 47, whose body holds no
 * `return` at all. A body with no value-return is not a *fluent* body, it is a
 * body that answers nothing, so the two are separate questions and the empty
 * case has to be settled before the fluent one is asked. Asked the other way
 * round, "every value-return is `$this`" is vacuously true of no returns at
 * all, which reads as fluent while the exemption is on and — with the exemption
 * off — as a method that returns a value, reporting both lines.
 *
 * Confirmed non-vacuous by deleting that empty-returns guard, which reddens
 * this with lines 35 and 47 added. The default-state run above cannot catch it:
 * with the exemption on, both spellings answer "not returning" and the outcome
 * is the same either way.
 */
it('widens to fluent declarations only when allowFluentInterface is off', function (): void {
    $file = analyzeFixtureWithProperty(ACTION_METHOD_RETURN, 'passing.php', 'allowFluentInterface', false);

    expect(warningTuples($file))->toBe([
        ['line' => 92, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 99, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 106, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 118, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 179, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 191, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 240, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

/**
 * A consuming ruleset reaches the same switch through a `<property>` element,
 * which hands the sniff the string "false" rather than a boolean. PHPCS folds
 * that spelling for a sniff property, and this pins it: the typed `bool`
 * declaration would raise a TypeError on a value that arrived unfolded, so a
 * project turning the exemption off in XML would take the whole ruleset parse
 * down with it.
 */
it('turns the fluent exemption off from a ruleset property', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        ACTION_METHOD_RETURN,
        'fluent.php',
        ['allowFluentInterface' => 'false']
    );

    expect(warningTuples($file))->toHaveCount(8);
});

/**
 * `publish` and `archive` are actions, and neither is a shipped default —
 * #172's list is the suggested one, not an exhaustive vocabulary. Silent out of
 * the box is the baseline for the configured run below.
 */
it('ignores verbs outside the configured prefix list', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'prefixes.php');

    expect(warningTuples($file))->toBe([]);
});

/**
 * A project tunes the list and gets its own verbs. `publishedEntries()` on line
 * 29 stays silent under the same configuration: the camelCase boundary is part
 * of the match, not part of the default list, so tuning the list cannot turn a
 * continued word into a verb.
 */
it('flags configured verbs when actionPrefixes is retuned', function (): void {
    $file = analyzeFixtureWithProperty(
        ACTION_METHOD_RETURN,
        'prefixes.php',
        'actionPrefixes',
        ['archive', 'publish']
    );

    expect(warningTuples($file))->toBe([
        ['line' => 15, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
        ['line' => 20, 'column' => 21, 'source' => ACTION_METHOD_RETURN_WARNING],
    ]);
});

/**
 * An empty verb in the configured list matches nothing, rather than matching
 * every method whose name does not start lower-case.
 *
 * Reachable from a consumer's ruleset by a single stray comma:
 * `value="set,,save"` is split on the comma by PHP_CodeSniffer and each piece
 * appended as written, so the sniff receives `['set', '', 'save']` with the
 * empty string intact. Without the guard, `str_starts_with($name, '')` answers
 * true for every name and the camelCase boundary is then asked about the name's
 * own first character — which reports `SetSubtitle()` on line 75 of
 * passing.php, a declaration #22/#100 owns, and names the matched verb as `""`.
 *
 * Driven through the XML `[]` property form rather than by assigning the array
 * directly, because the stray comma is the way the empty member arises.
 *
 * Confirmed non-vacuous by deleting the guard, which reddens this with exactly
 * that line-75 warning.
 */
it('matches nothing for an empty verb in the configured list', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        ACTION_METHOD_RETURN,
        'passing.php',
        ['actionPrefixes[]' => 'set,,save']
    );

    expect(warningTuples($file))->toBe([]);
});

/**
 * The shipped defaults, pinned where the behaviour is. rules.xml configures no
 * `<properties>` for this sniff, so what the sniff declares is what a consuming
 * project gets, and #172 names both the list and the exemption's direction.
 */
it('ships the suggested prefix list and the exemption switched on', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = new $ruleset->sniffCodes[ACTION_METHOD_RETURN]();

    expect($sniff->actionPrefixes)->toBe([
        'add',
        'apply',
        'attach',
        'clear',
        'delete',
        'detach',
        'post',
        'remove',
        'reset',
        'save',
        'send',
        'set',
        'store',
        'update',
    ])
        ->and($sniff->allowFluentInterface)->toBeTrue();
});

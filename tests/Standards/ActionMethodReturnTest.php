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
 *   *declared*, and how completely. It reports the four declarations that omit
 *   one (48, 58, 70, 122) and line 148's bare `array`, for an unspecified item
 *   type — and stays silent on the other thirteen, which declare a returning
 *   type perfectly well. That is the opposite half of this rule: it wants a
 *   type where there is none, this wants none where there is a type.
 *
 * Their six lines overlap this sniff's eighteen by shape, not by coverage: fix
 * every one of those six and all eighteen still report here. Pinned
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
        'SlevomatCodingStandard.TypeHints.ReturnTypeHint' => [48, 58, 70, 122, 148],
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

    expect($file->getWarningCount())->toBe(18)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

/**
 * The fluent exemption ships on, so the six spellings of a builder — `static`,
 * `?static`, `self`, a bare enclosing-class name, that name unioned with
 * `null`, and a body whose every value-return is `return $this;` — are silent
 * out of the box. This run is the baseline the
 * next one is measured against: without it, a property that silenced the sniff
 * outright would look exactly like a working exemption.
 */
it('exempts a fluent interface by default', function (): void {
    $file = analyzeFixture(ACTION_METHOD_RETURN, 'fluent.php');

    expect(warningTuples($file))->toBe([]);
});

/**
 * Switching the exemption off reads those same six declarations like any other
 * returning method. A project that has decided against fluent setters gets the
 * whole rule, and the six lines it gets are exactly the six the exemption was
 * holding back.
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

    expect(warningTuples($file))->toHaveCount(6);
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

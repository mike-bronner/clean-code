<?php

/**
 * CleanCode.Metrics.CouplingBetweenObjects — PHPMD's Design/CouplingBetweenObjects.
 *
 * Every claim this file makes about PHPMD was measured against a live PHPMD
 * 2.15.0 (PDepend 2.16.2) run over these same fixtures, not read off
 * phpmd.org. Two of those measurements contradict a plausible reading of the
 * rule, and both are pinned here:
 *
 *   - The threshold is *inclusive*. PHPMD's rule class reports when
 *     `$cbo >= $threshold`, so a class with exactly `maximum` dependencies is
 *     already reported, and "maximum number of acceptable dependencies" — the
 *     wording on phpmd.org and in #114's acceptance criteria — is the advice
 *     rather than the test. `boundaries.php` pins 12/13/14 against PHPMD's own
 *     measured output on that file.
 *   - Only classes are checked. PHPMD's rule is declared `implements
 *     ClassAware`, and a live run says nothing about a trait, an interface, an
 *     enum, or a plain function however many types it names. `passing.php`
 *     carries one of each, all far past the threshold.
 *
 * docs/phpmd/design-couplingbetweenobjects.md records the full mapping and the
 * measured PHPMD output for each fixture.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const COUPLING_BETWEEN_OBJECTS = 'CleanCode.Metrics.CouplingBetweenObjects';

const COUPLING_BETWEEN_OBJECTS_ERROR = 'CleanCode.Metrics.CouplingBetweenObjects.Found';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COUPLING_BETWEEN_OBJECTS);
});

/**
 * The two violating shapes, each reported once on its own declaration line: a
 * class sitting exactly on the threshold, reached through every counted source
 * at once, and a class far past it through parameter types alone.
 *
 * PHPMD 2.15.0 at its shipped default reports CouplingBetweenObjects on this
 * file at exactly lines 16 and 51, with values 13 and 18 — the same lines and
 * the same counts.
 */
it('flags a class whose dependency count reaches the threshold', function (): void {
    expect(violationTuples(analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')))->toBe([
        ['line' => 16, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 51, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * The count belongs in the message: a metric rule that only says "too many"
 * makes the reader re-count by hand. Both numbers are quoted, in PHPMD's own
 * wording — these two strings are byte-for-byte what PHPMD 2.15.0 prints for
 * this fixture, so a reader moving off `phpmd` sees no change in the report.
 */
it('names the class, its coupling value, and the threshold', function (): void {
    $errors = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')->getErrors();

    expect($errors[16][1][0]['message'])->toBe(
        'The class AtTheThreshold has a coupling between objects value of 13.'
            . ' Consider to reduce the number of dependencies under 13.'
    );
    expect($errors[51][1][0]['message'])->toBe(
        'The class FarPastTheThreshold has a coupling between objects value of 18.'
            . ' Consider to reduce the number of dependencies under 13.'
    );
});

/**
 * The inclusive boundary, in one file: twelve dependencies is silent, thirteen
 * is a violation, fourteen is a violation. A sniff that read `maximum` as a
 * strict "more than" — which is how phpmd.org and #114 both describe it —
 * would report only line 51 and still pass every other assertion here.
 *
 * PHPMD 2.15.0 at its shipped default over this same fixture reports lines 31
 * and 51 and says nothing about the twelve-dependency class on line 12.
 */
it('treats the threshold as inclusive', function (): void {
    expect(violationTuples(analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'boundaries.php')))->toBe([
        ['line' => 31, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 51, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * Every source a dependency can come from, one class each, each naming exactly
 * one type. Dropping `maximum` to 1 makes each of those classes report and
 * leaves every shape that must contribute nothing silent, so this single
 * assertion pins the whole counting rule at once:
 *
 *   line 16    ParameterTypes            a parameter type hint
 *   line 23    PropertyTypes             a property type hint
 *   line 28    ReturnTypes               a return type hint
 *   line 36    Instantiations            `new Type()`
 *   line 44    StaticCalls               `Type::method()`
 *   line 52    StaticConstants           `Type::CONSTANT`
 *   line 60    ClassConstantReferences   `Type::class`
 *   line 68    CaughtTypes               `catch (Type $e)`
 *   line 79    InstanceofTypes           `$x instanceof Type`
 *   line 89    PromotedProperties        a promoted constructor property
 *   line 96    ClosureParameters         a closure's parameter type hint
 *   line 106   ArrowFunctionParameters   an arrow function's parameter type hint
 *   line 119   DuplicateReferences       one type named six ways, counted once
 *
 * and, by their absence:
 *
 *   ScalarTypesOnly       no scalar or pseudo type is a dependency
 *   SelfReferencesOnly    nor the class itself, `self`/`static`/`parent`, or
 *                         the `extends` and `implements` clauses
 *   TraitUsers            nor a `use` of a trait, or its adaptation block
 *   AttributeHolders      nor an attribute, or a type named in its arguments
 *   DynamicReferences     nor `new $class`, `$object::m()`, `$x instanceof $y`
 *
 * PHPMD 2.15.0 over this fixture agrees on all thirteen lines and all thirteen
 * counts, and on four of the five silences. ScalarTypesOnly is the exception:
 * PHPMD counts 2 there, because PDepend models `mixed` and `object` as class
 * types. That divergence is pinned on its own in divergences.php.
 */
it('counts one dependency per source, and nothing for a near miss', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'sources.php', static function (object $sniff): void {
        $sniff->maximum = 1;
    });

    expect(array_keys(violationTuples($file)))->toHaveCount(13);
    expect(array_column(violationTuples($file), 'line'))
        ->toBe([16, 23, 28, 36, 44, 52, 60, 68, 79, 89, 96, 106, 119]);
});

/**
 * The far side of the same fixture, and what makes the assertion above mean
 * "exactly one" rather than "at least one": at a threshold of 2, every class in
 * it falls silent. Counting a source twice — reading a promoted property as
 * both a parameter and a field, say, or failing to collapse the six spellings
 * in DuplicateReferences — adds a line here.
 */
it('counts a type once however many times it is named', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'sources.php', static function (object $sniff): void {
        $sniff->maximum = 2;
    });

    expect(violationTuples($file))->toBe([]);
});

/**
 * What the compliant fixture is silent *about*, rather than merely that it is
 * silent. Both classes it holds name twelve types, one below the inclusive
 * default, and lowering the threshold to 12 makes both speak up:
 *
 *   line 26    TwelveDependencies             twelve, through every source
 *   line 140   the nested anonymous class     twelve, counted as its own scope
 *
 * and, by their absence:
 *
 *   WideTrait (15 types)          a trait is not a class and is never checked
 *   WideInterface (15 types)      nor is an interface
 *   WideEnum (15 types)           nor is an enum
 *   HostOfANarrowAnonymousClass   its anonymous class's types are not its own
 *   wideFunction (15 types)       nor is anything outside a class
 *
 * Registering T_TRAIT, T_INTERFACE, or T_ENUM, or folding a nested anonymous
 * class into its host, each adds a line here. PHPMD 2.15.0 at its shipped
 * default is silent on this fixture too.
 */
it('counts only the types the class itself names', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'passing.php', static function (object $sniff): void {
        $sniff->maximum = 12;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 140, 'column' => 22, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * Seven classes are imported by imports.php — plain, aliased, two through a
 * group, two through a comma-separated statement, and one never used — and the
 * `use function` and `use const` statements import no class at all. At a
 * threshold of 7 all three classes in the file report, so all three carry at
 * least those seven.
 */
it('counts every imported class, and neither a function nor a constant import', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'imports.php', static function (object $sniff): void {
        $sniff->maximum = 7;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 31, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 40, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
        ['line' => 57, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * One notch up is what separates the three, and what proves the short names in
 * the class bodies are actually resolved:
 *
 *   line 31   ImportsAlone          exactly seven — an empty body adds nothing
 *   line 40   ImportAndUsage        exactly seven — it names six of the seven
 *                                   again, by their short and aliased names,
 *                                   and an import and the name it enables are
 *                                   one dependency between them
 *   line 57   UnimportedShortName   eight — a short name nobody imported
 *                                   resolves against the current namespace and
 *                                   is a type of its own
 *
 * A sniff that skipped alias resolution would score ImportAndUsage at ten and
 * report it here; one that resolved every short name against the namespace
 * would score it at thirteen.
 */
it('resolves an alias and its import to a single dependency', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'imports.php', static function (object $sniff): void {
        $sniff->maximum = 8;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 57, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * The three shapes this sniff and PHPMD 2.15.0 read differently, at a threshold
 * of 1 so that any count above zero shows up. Only the anonymous class reports:
 *
 *   line 20   AnonymousHost    PHPMD folds the anonymous class's three types
 *                              into the host and scores it 3; here the host
 *                              names nothing itself and scores 0
 *   line 24   the anon class   scored on its own, at 3 — PHPMD never reports an
 *                              anonymous class at all
 *   line 37   MixedAndObject   PHPMD scores 2, because PDepend models `mixed`
 *                              and `object` as class types; neither names a
 *                              type, so both are ignored here
 *   line 50   DocblockOnly     PHPMD scores 3 by reading `@var`, `@return`, and
 *                              `@throws`; this sniff reads declarations only,
 *                              and CleanCode/ruleset.xml already requires them through
 *                              SlevomatCodingStandard.TypeHints.PropertyTypeHint
 *                              and its siblings
 *
 * Each number above is a measured PHPMD 2.15.0 run over this exact fixture.
 */
it('scores an anonymous class on its own, and ignores what names no type', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'divergences.php', static function (object $sniff): void {
        $sniff->maximum = 1;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 24, 'column' => 22, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * An anonymous class has no name to quote, so the message names its kind rather
 * than interpolating an empty string where the name would go.
 */
it('names an anonymous class by its kind', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'divergences.php', static function (object $sniff): void {
        $sniff->maximum = 1;
    });

    expect($file->getErrors()[24][22][0]['message'])->toBe(
        'The anonymous class has a coupling between objects value of 3.'
            . ' Consider to reduce the number of dependencies under 1.'
    );
});

/**
 * `maximum` is PHPMD's own property name and default. Lowering it to 3 reaches
 * the three-dependency class and still spares the two-dependency one, so the
 * threshold is genuinely read rather than hard-coded — and the inclusive
 * comparison holds at a configured value too, not only at the default.
 */
it('reports at a lowered maximum', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'configured.php', static function (object $sniff): void {
        $sniff->maximum = 3;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * The far side of the same boundary: one above the widest class in the fixture
 * silences the file. Without it, "reports at a lowered maximum" would also pass
 * against a sniff that ignored `maximum` and reported every class it saw.
 */
it('stays silent above the widest coupling in the file', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'configured.php', static function (object $sniff): void {
        $sniff->maximum = 4;
    });

    expect(violationTuples($file))->toBe([]);
});

/**
 * A ruleset always supplies a property as a string — `<property name="maximum"
 * value="3"/>` — so `maximum` has to stay untyped. Declaring it `int` turns
 * this test into a TypeError, which is the mutation that makes it
 * discriminating; the `(int)` cast where it is read normalises the value for
 * the message rather than for the comparison, which PHP already performs
 * numerically on a numeric string.
 */
it('accepts a maximum supplied as a string, the way a ruleset supplies it', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        COUPLING_BETWEEN_OBJECTS,
        'configured.php',
        ['maximum' => '3']
    );

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
});

/**
 * An unterminated class leaves PHPCS with no scope_opener on the declaration,
 * so there is no body to walk. The file is already a parse error and the sniff
 * says nothing about it — and, just as importantly, raises no PHP error of its
 * own while finding that out. The error-handler assertion is what makes this
 * discriminating: without the guard the sniff still reports nothing, but it
 * reads undefined array keys to get there.
 */
it('stays silent on an unterminated declaration without raising a PHP error', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    });

    try {
        $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'unclosed-class.php', static function (object $sniff): void {
            $sniff->maximum = 1;
        });
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([]);
    expect(violationTuples($file))->toBe([]);
});

/**
 * Detection only, matching PHPMD. Cutting a class's dependencies means moving
 * behaviour to another object: which behaviour, and to what, is not
 * machine-derivable.
 */
it('offers no fixer', function (): void {
    $file = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false, false]);
    expect($file->getFixableCount())->toBe(0);
});

/**
 * A PHP 8.4 property hook. PHP_CodeSniffer opens no scope for a hook body, so
 * every variable written inside one reports the class as its innermost
 * condition, exactly as a declared property does. That costs nothing here: a
 * variable declaring no property has no type to read.
 *
 * The class names four types — the hooked property's own type, the one it
 * instantiates in its `get` hook, a plain property, and one static call — so it
 * reports at 4 and falls silent at 5. A `set` hook's parameter type is *not*
 * among them: its list is not a function declaration, so PHPCS does not surface
 * it as a parameter. That gap has no PHPMD behaviour to match — PDepend cannot
 * parse a file containing a hook at all — and it is recorded in
 * docs/phpmd/design-couplingbetweenobjects.md rather than worked around.
 */
it('reads a hooked property without charging the hook body', function (): void {
    $reported = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'property-hooks.php', static function (
        object $sniff
    ): void {
        $sniff->maximum = 4;
    });
    $silent = analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'property-hooks.php', static function (
        object $sniff
    ): void {
        $sniff->maximum = 5;
    });

    expect(violationTuples($reported))->toBe([
        ['line' => 13, 'column' => 1, 'source' => COUPLING_BETWEEN_OBJECTS_ERROR],
    ]);
    expect(violationTuples($silent))->toBe([]);
});

/**
 * Each declared type is split on `|` and `&` so every member of a union counts
 * as its own dependency. A failed split is false, and iterating a boolean takes
 * the run down. The guard falls back to the unsplit type: a plain class name is
 * still counted and only a union goes unread, which undercounts coupling rather
 * than miscounting an unrelated type.
 *
 * The undercount is visible — the class whose count only clears the threshold
 * through its union members stops being reported — and the empty diagnostics
 * are what separate that from an unguarded read warning its way to the same
 * number.
 */
it('counts an unsplittable union type as a single member', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_split',
            static fn (): array => violationSourcesByLine(
                analyzeFixture(COUPLING_BETWEEN_OBJECTS, 'failing.php')->getErrors()
            ),
            static fn (string $pattern): bool => $pattern === '/[|&]/'
        );
    });

    expect(array_keys($expected))->toContain(16)
        ->and(array_keys($degraded))->not->toContain(16)
        ->and($diagnostics)->toBe([]);
});

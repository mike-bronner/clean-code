<?php

/**
 * Tests the custom CleanCode.Methods.NoNullArguments sniff (Methods: No Null
 * Arguments, #71). Fixtures live in tests/fixtures/NoNullArgumentsSniff/:
 * passing.php, failing.php and its committed fixer output autofixed.php, plus
 * namespaces.php for resolution across namespace blocks.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const NO_NULL_ARGUMENTS = 'CleanCode.Methods.NoNullArguments';

const NO_NULL_ARGUMENTS_POSITIONAL = NO_NULL_ARGUMENTS . '.PositionalNull';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_NULL_ARGUMENTS);
});

/**
 * passing.php is deliberately discriminating: alongside the compliant named
 * arguments, it carries the near-miss shapes the sniff must stay silent on —
 * a `null` passed to a *required* parameter, and `null` literals outside a
 * call-argument position altogether (assignments, returns, comparisons, array
 * values, default values).
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One entry per positionally-passed `null` in failing.php, at its own line.
 * This list is the sniff's whole detection contract — it is exact, so a missed
 * argument and an extra report both fail here.
 */
it('flags every positional null at its own line', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        // Attribute arguments are constructor arguments — including the second
        // attribute of a group.
        23 => [NO_NULL_ARGUMENTS_POSITIONAL],
        30 => [NO_NULL_ARGUMENTS_POSITIONAL, NO_NULL_ARGUMENTS_POSITIONAL],
        // $this-> method call, plain and nullsafe.
        69 => [NO_NULL_ARGUMENTS_POSITIONAL],
        70 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // ClassName::, self:: and static:: static calls.
        73 => [NO_NULL_ARGUMENTS_POSITIONAL],
        74 => [NO_NULL_ARGUMENTS_POSITIONAL],
        77 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // new ClassName() and new self() constructor calls.
        80 => [NO_NULL_ARGUMENTS_POSITIONAL],
        81 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // Two null arguments in one call — one violation each.
        84 => [NO_NULL_ARGUMENTS_POSITIONAL, NO_NULL_ARGUMENTS_POSITIONAL],
        // A null before a further positional argument.
        88 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // A null before an argument that cannot be named.
        93 => [NO_NULL_ARGUMENTS_POSITIONAL],
        96 => [NO_NULL_ARGUMENTS_POSITIONAL],
        100 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // Calls dispatched against the runtime class.
        132 => [NO_NULL_ARGUMENTS_POSITIONAL],
        135 => [NO_NULL_ARGUMENTS_POSITIONAL],
        138 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // ...and the declarations dispatch cannot divert.
        142 => [NO_NULL_ARGUMENTS_POSITIONAL],
        146 => [NO_NULL_ARGUMENTS_POSITIONAL],
        149 => [NO_NULL_ARGUMENTS_POSITIONAL],
        150 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // `self::` and `new self()` in the same extendable class.
        155 => [NO_NULL_ARGUMENTS_POSITIONAL],
        156 => [NO_NULL_ARGUMENTS_POSITIONAL],
        196 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // Trait methods, which the using class may replace — reached through
        // `$this->` and through `self::`/`new self()` alike.
        213 => [NO_NULL_ARGUMENTS_POSITIONAL],
        214 => [NO_NULL_ARGUMENTS_POSITIONAL],
        215 => [NO_NULL_ARGUMENTS_POSITIONAL],
        222 => [NO_NULL_ARGUMENTS_POSITIONAL],
        223 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // `self` inside an anonymous class nested in a trait names that
        // anonymous class, not the trait.
        251 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // Enum and anonymous class — neither can be extended.
        270 => [NO_NULL_ARGUMENTS_POSITIONAL],
        287 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // Standalone function call.
        302 => [NO_NULL_ARGUMENTS_POSITIONAL],
        // A null followed by an argument the call already names.
        311 => [NO_NULL_ARGUMENTS_POSITIONAL],
    ]);
});

/**
 * The half of the severity contract the source assertion cannot see: it reads
 * getErrors() only, so it would hold just as well if the sniff *also* raised a
 * warning per argument.
 */
it('raises no warnings alongside the errors', function (): void {
    expect(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getWarnings())->toBe([]);
});

/**
 * The fixer writes the resolved declaration's parameter name into the source,
 * so it may only run when this file proves that declaration is the one the call
 * reaches. Every violation is reported either way.
 *
 * Keyed by line rather than a flat list so each entry reads against the fixture
 * shape it pins; the counts below are the totals the same split adds up to.
 */
it('offers a fixer only where the rewrite is provably safe', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php');

    $fixableByLine = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $fixableByLine[$line][] = $violation['fixable'];
            }
        }
    }

    ksort($fixableByLine);

    expect($fixableByLine)->toBe([
        // An attribute names the class it instantiates outright.
        23 => [true],
        30 => [true, true],
        // $this-> inside a final class: no subclass can exist to divert the
        // dispatch.
        69 => [true],
        70 => [true],
        // Early-bound: ClassName::, self::, new ClassName, new self — and
        // static:: contained by the same final class.
        73 => [true],
        74 => [true],
        77 => [true],
        80 => [true],
        81 => [true],
        84 => [true, true],
        88 => [true],
        // Unfixable for an unrelated reason: a later argument in the call
        // cannot be named.
        93 => [false],
        96 => [false],
        100 => [false],
        // Late-bound in an extendable class — $this->, static:: and
        // new static(). An override may rename the parameter, so the violation
        // is reported unfixed.
        132 => [false],
        135 => [false],
        138 => [false],
        // A private method is resolved in the scope that declares it, so
        // `$this->` reaches this one...
        142 => [true],
        // ...but `static::` binds to the subclass before it checks visibility,
        // so `private` does not protect it there.
        146 => [false],
        // A final method — and a final constructor behind `new static()` —
        // cannot be overridden at all.
        149 => [true],
        150 => [true],
        // `self` is not late-bound: it names the class it is written in, so it
        // reaches these declarations even though a subclass may override them.
        155 => [true],
        156 => [true],
        196 => [true],
        // A trait's methods are copied into the using class, which may replace
        // any of them: public, private and final alike.
        213 => [false],
        214 => [false],
        215 => [false],
        // `self` inside a trait names the *using* class, so it is no more
        // provable than `$this->` is — rewriting either would write the trait's
        // parameter name into a call the using class's own declaration answers.
        222 => [false],
        223 => [false],
        // The enclosing trait does not make this unfixable: `self` names the
        // anonymous class the call is written in, and nothing can extend that.
        251 => [true],
        // An enum cannot be extended and an anonymous class has no name to
        // extend, so neither can be subclassed.
        270 => [true],
        287 => [true],
        // A namespace-level function is early-bound.
        302 => [true],
        // An argument the call already names is skipped rather than named a
        // second time, which leaves the flagged null nameable on its own.
        311 => [true],
    ]);

    expect($file->getErrorCount())->toBe(36)
        ->and($file->getFixableCount())->toBe(24);
});

/**
 * The declined violations still have to say *why*, and the two reasons are
 * different: an argument later in the call that cannot be given a name, versus
 * a call whose target is chosen at runtime. A single "cannot be fixed" string
 * would let either explanation stand in for the other.
 */
it('explains a violation declined for an unnameable later argument', function (int $line): void {
    $messages = violationMessagesByLine(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getErrors());

    expect($messages)->toHaveKey($line)
        ->and($messages[$line][0])->toContain('cannot be fixed automatically')
        ->and($messages[$line][0])->toContain('cannot be named');
})->with([93, 96, 100]);

it('explains a violation declined for runtime dispatch', function (int $line): void {
    $messages = violationMessagesByLine(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getErrors());

    expect($messages)->toHaveKey($line)
        ->and($messages[$line][0])->toContain('cannot be fixed automatically')
        ->and($messages[$line][0])->toContain('dispatched against the runtime class');
})->with([132, 135, 138, 146, 213, 214, 215, 222, 223]);

/**
 * A fixable violation names the replacement outright, so the diagnostic is
 * actionable without running the fixer.
 */
it('names the parameter to use in a fixable violation', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getErrors());

    expect($messages[302][0])->toContain('$retries')
        ->and($messages[302][0])->toContain('retries: null');
});

/**
 * A short name is unique only within its namespace block, so a declaration in
 * another block belongs to a different symbol and must not be borrowed. Only
 * the two calls in the first block resolve to an optional parameter; the
 * identical-looking calls in the second reach declarations whose parameter is
 * required.
 */
it('stops resolution at the namespace boundary', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'namespaces.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        27 => [NO_NULL_ARGUMENTS_POSITIONAL],
        28 => [NO_NULL_ARGUMENTS_POSITIONAL],
    ]);
});

/**
 * Re-running the sniff over its own committed output must surface nothing new:
 * every violation the fixer touched is gone, and the reports left are exactly
 * the ones it deliberately declined — the same twelve lines, at the same line
 * numbers, since a named-argument rewrite never changes the line count.
 *
 * tests/Contract/SniffContractTest.php already pins autofixed.php as the byte
 * output of failing.php and as idempotent under a second pass. What it cannot
 * say is that the leftovers are the *declined* set rather than a fixer that
 * quietly stopped fixing, which is what this asserts.
 */
it('leaves only the declined violations in its fixed output', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'autofixed.php');

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([93, 96, 100, 132, 135, 138, 146, 213, 214, 215, 222, 223])
        ->and($file->getFixableCount())->toBe(0);
});

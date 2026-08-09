<?php

/**
 * Tests the custom CleanCode.Metrics.TooManyFields sniff, which replicates
 * PHPMD's CodeSize/TooManyFields rule (issue #98). Fixtures live in
 * tests/fixtures/TooManyFieldsSniff/.
 *
 * Every expectation below was checked against a live PHPMD 2.15.0 run over the
 * same fixture, so "matches PHPMD" is a measurement rather than a claim:
 *
 * - failing.php — PHPMD reports the same five classes, on the same five lines,
 *   with the same count of 16.
 * - passing.php — PHPMD reports no TooManyFields violation either.
 * - divergences.php, php84-modifiers.php, property-hooks.php — the three shapes
 *   where the two tools part company, each pinned by its own test below and
 *   described in docs/phpmd/codesize-toomanyfields.md.
 *
 * Beyond the contract's passing.php and failing.php the fixture directory
 * carries four extra files, each named for what it exercises:
 *
 * - divergences.php     — promoted properties and anonymous classes.
 * - php84-modifiers.php — `final` and asymmetric-visibility properties.
 * - property-hooks.php  — hook bodies, which must not be counted as fields.
 * - unclosed-class.php  — a class body left open mid-edit.
 *
 * There is no autofixed.php: extracting a new object and re-pointing every use
 * of the moved fields is a design decision, and the detection-only test below
 * proves the sniff offers no fix rather than asserting the absence of a file.
 */

declare(strict_types=1);

const TOO_MANY_FIELDS = 'CleanCode.Metrics.TooManyFields';

const TOO_MANY_FIELDS_CODE = TOO_MANY_FIELDS . '.MaxExceeded';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_FIELDS);
});

/**
 * passing.php is the silent half of the boundary and every near-miss at once: a
 * class with exactly 15 fields, 16 class constants, 16 enum cases, 16 interface
 * constants, a trait holding 16 fields and the class that uses it, 18 locals
 * and closure variables, 16 unpromoted constructor parameters, a parent and
 * child declaring 10 each, a nested anonymous class, and a constructor holding
 * 14 promoted parameters followed by 4 plain ones. Each is a decision the
 * counter has to make, so the file's silence is a verdict rather than an
 * absence.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The five failing classes, at the exact lines and columns PHPMD 2.15.0 reports
 * them: 16 plain properties, 16 static ones, 16 spread over 8 multi-property
 * declarations, 16 alongside constants and method locals, and 16 declared with
 * `var`, a bare `static`, a bare `readonly`, and an attribute in the way.
 */
it('flags every class over the threshold at its declaration', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
        ['line' => 34, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
        ['line' => 56, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
        ['line' => 70, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
        ['line' => 108, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The count itself, not just the fact of a violation. Every failing class holds
 * exactly 16 fields; a counter that drifted by one — swallowing the second half
 * of a multi-property declaration, or counting the five constants in Invoice —
 * would still report all five classes and pass the test above.
 */
it('counts the fields it reports', function (): void {
    expect(violationMessages(analyzeFixture(TOO_MANY_FIELDS, 'failing.php')))->each(
        fn ($message) => $message->toContain('has 16 fields')
    );
});

/**
 * The threshold is exclusive, as PHPMD's is: a class sitting exactly on it is
 * silent, and one field more is reported. passing.php's first class holds
 * exactly 15 fields, so lowering the threshold to 14 has to flag it — which
 * also proves the property is read from the ruleset rather than hardcoded.
 */
it('reports a class only once it is above the threshold', function (): void {
    expect(violationTuples(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'passing.php', 'maxFields', 15)))->toBe([]);

    expect(violationTuples(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'passing.php', 'maxFields', 14)))->toBe([
        ['line' => 15, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
    ]);
});

/**
 * Raising the threshold silences the failing fixture, so the property drives
 * the count in both directions rather than only tightening it.
 */
it('accepts a raised threshold', function (): void {
    expect(violationTuples(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'failing.php', 'maxFields', 16)))->toBe([]);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The two divergences from PHPMD 2.15.0, both deliberate:
 *
 * - PromotedPerson (line 20) — 16 promoted constructor properties. PHPMD reads
 *   the class as having no fields at all, because PDepend does not model
 *   promotion. This ruleset requires promotion, so inheriting that blind spot
 *   would make the rule useless here.
 * - The two anonymous classes (lines 50 and 74) — PHPMD charges the nested
 *   one's fields to the enclosing class instead, at line 46, and never examines
 *   the top-level one.
 *
 * Enclosing itself declares no fields, so its absence from this list is the
 * other half of the second divergence.
 */
it('diverges from PHPMD on promoted properties and anonymous classes', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 20, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
        ['line' => 50, 'column' => 20, 'source' => TOO_MANY_FIELDS_CODE],
        ['line' => 74, 'column' => 17, 'source' => TOO_MANY_FIELDS_CODE],
    ])->and(violationMessages($file))->each(
        fn ($message) => $message->toContain('has 16 fields')
    );
});

/**
 * The anonymous classes are reported by that name, since there is none to give.
 */
it('names an anonymous class in the message', function (): void {
    $messages = violationMessages(analyzeFixture(TOO_MANY_FIELDS, 'divergences.php'));

    expect($messages[0])->toContain('The class PromotedPerson has')
        ->and($messages[1])->toContain('The class {anonymous} has')
        ->and($messages[2])->toContain('The class {anonymous} has');
});

/**
 * PHP 8.4's `final` properties and asymmetric visibility, 8 of each. PHPMD
 * 2.15.0 reports nothing whatever for this file — PDepend cannot parse either
 * keyword and abandons the whole file — which is why these shapes have a
 * fixture of their own instead of sitting in failing.php, where they would
 * silence PHPMD's verdict on the classes around them.
 */
it('counts PHP 8.4 property modifiers PHPMD cannot parse', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'php84-modifiers.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
    ])->and(violationMessages($file)[0])->toContain('has 16 fields');
});

/**
 * A property hook's body is not a scope in PHP_CodeSniffer, so its `$this`, its
 * locals, and its setter parameter all reach the counter looking like
 * class-level variables. Temperature declares 3 fields and writes 17 such
 * variables; counting any of them would put it over the default threshold, so
 * the silence below is the guard and the count is pinned exactly.
 */
it('does not count the variables inside a property hook', function (): void {
    expect(violationTuples(analyzeFixture(TOO_MANY_FIELDS, 'property-hooks.php')))->toBe([]);

    expect(violationMessages(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'property-hooks.php', 'maxFields', 2))[0])
        ->toContain('has 3 fields');
});

/**
 * An unterminated class body is live coding, not a design smell — the fields
 * still being typed are not a count worth judging. The fixture holds 20 of
 * them, so a sniff that read a half-written class at all would report it.
 *
 * PHP_CodeSniffer gives such a class neither a scope opener nor a scope closer,
 * so dropping the sniff's guard raises an "Undefined array key" warning rather
 * than a violation. phpunit.xml.dist carries failOnWarning="true" so that this
 * test still turns the suite red when the guard goes.
 */
it('says nothing about an unterminated class body', function (): void {
    expect(violationTuples(analyzeFixture(TOO_MANY_FIELDS, 'unclosed-class.php')))->toBe([]);
});

/**
 * The whole ruleset, not just the isolated sniff: rules.xml pulls the sniff in
 * through its CleanCode standard reference, and this is what proves a consuming
 * project running `phpcs --standard=rules.xml` gets the rule — which is the
 * point of replacing PHPMD for it.
 */
it('reports through the master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('TooManyFieldsSniff', 'failing.php'));

    $lines = array_keys(array_filter(
        violationSourcesByLine($file->getErrors()),
        static fn (array $sources): bool => in_array(TOO_MANY_FIELDS_CODE, $sources, true)
    ));

    expect($lines)->toBe([13, 34, 56, 70, 108]);
});

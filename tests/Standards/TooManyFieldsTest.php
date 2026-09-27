<?php

declare(strict_types=1);

const TOO_MANY_FIELDS = 'CleanCode.Metrics.TooManyFields';

const TOO_MANY_FIELDS_CODE = TOO_MANY_FIELDS . '.MaxExceeded';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_FIELDS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

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

it('counts the fields it reports', function (): void {
    expect(violationMessages(analyzeFixture(TOO_MANY_FIELDS, 'failing.php')))->each(
        fn ($message) => $message->toContain('has 16 fields')
    );
});

it('reports a class only once it is above the threshold', function (): void {
    expect(violationTuples(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'passing.php', 'maxFields', 15)))->toBe([]);

    expect(violationTuples(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'passing.php', 'maxFields', 14)))->toBe([
        ['line' => 15, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
    ]);
});

it('accepts a raised threshold', function (): void {
    expect(violationTuples(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'failing.php', 'maxFields', 16)))->toBe([]);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getFixableCount())->toBe(0);
});

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

it('names an anonymous class in the message', function (): void {
    $messages = violationMessages(analyzeFixture(TOO_MANY_FIELDS, 'divergences.php'));

    expect($messages[0])->toContain('The class PromotedPerson has')
        ->and($messages[1])->toContain('The class {anonymous} has')
        ->and($messages[2])->toContain('The class {anonymous} has');
});

it('counts PHP 8.4 property modifiers PHPMD cannot parse', function (): void {
    $file = analyzeFixture(TOO_MANY_FIELDS, 'php84-modifiers.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => TOO_MANY_FIELDS_CODE],
    ])->and(violationMessages($file)[0])->toContain('has 16 fields');
});

it('does not count the variables inside a property hook', function (): void {
    expect(violationTuples(analyzeFixture(TOO_MANY_FIELDS, 'property-hooks.php')))->toBe([]);

    expect(violationMessages(analyzeFixtureWithProperty(TOO_MANY_FIELDS, 'property-hooks.php', 'maxFields', 2))[0])
        ->toContain('has 3 fields');
});

it('says nothing about an unterminated class body', function (): void {
    expect(violationTuples(analyzeFixture(TOO_MANY_FIELDS, 'unclosed-class.php')))->toBe([]);
});

it('reports through the master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('TooManyFieldsSniff', 'failing.php'));

    $lines = array_keys(array_filter(
        violationSourcesByLine($file->getErrors()),
        static fn (array $sources): bool => in_array(TOO_MANY_FIELDS_CODE, $sources, true)
    ));

    expect($lines)->toBe([13, 34, 56, 70, 108]);
});

<?php

declare(strict_types=1);

const MODEL_NAMING_CONVENTIONS = 'CleanCode.Naming.ModelNamingConventions';

const MODEL_NAMING_BOOLEAN_PROPERTY = MODEL_NAMING_CONVENTIONS . '.BooleanPropertyPrefix';

const MODEL_NAMING_BOOLEAN_METHOD = MODEL_NAMING_CONVENTIONS . '.BooleanMethodPrefix';

const MODEL_NAMING_FIND_PREFIX = MODEL_NAMING_CONVENTIONS . '.FindMethodPrefix';

const MODEL_NAMING_FIND_MODEL_NAME = MODEL_NAMING_CONVENTIONS . '.FindModelName';

const MODEL_NAMING_GET_PREFIX = MODEL_NAMING_CONVENTIONS . '.GetMethodPrefix';

const MODEL_NAMING_LEGACY_ACCESSOR = MODEL_NAMING_CONVENTIONS . '.LegacyAttributeAccessor';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MODEL_NAMING_CONVENTIONS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column under its own code', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 15, 'column' => 20, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 19, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 21, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        ['line' => 27, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        ['line' => 32, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        ['line' => 37, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        ['line' => 42, 'column' => 12, 'source' => MODEL_NAMING_FIND_MODEL_NAME],
        ['line' => 47, 'column' => 12, 'source' => MODEL_NAMING_GET_PREFIX],
        ['line' => 52, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
        ['line' => 57, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
        ['line' => 64, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        ['line' => 71, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        ['line' => 80, 'column' => 22, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 81, 'column' => 24, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
    ]);

    expect($file->getWarnings())->toBe([]);
});

it('recognises a model by its Eloquent base class', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'model-by-base-class.php');

    expect(violationTuples($file))->toBe([
        ['line' => 20, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 22, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        ['line' => 34, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 36, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        ['line' => 50, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        ['line' => 62, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
    ]);
});

it('leaves a non-model class alone', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'not-a-model.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('treats an unimported base class as the project\'s own', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'unimported-base.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('resolves aliased types before it interprets them', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'aliased-imports.php');

    expect(violationTuples($file))->toBe([
        ['line' => 24, 'column' => 12, 'source' => MODEL_NAMING_GET_PREFIX],
        ['line' => 31, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        ['line' => 39, 'column' => 12, 'source' => MODEL_NAMING_FIND_MODEL_NAME],
    ]);
});

it('does not report correct names behind aliased types', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'aliased-imports-passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('keeps function and const imports out of the class import map', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'group-use-mixed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('identifies case-mismatched symbols', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'case-mismatched-symbols.php');

    expect(violationTuples($file))->toBe([
        ['line' => 24, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 32, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        ['line' => 43, 'column' => 12, 'source' => MODEL_NAMING_FIND_MODEL_NAME],
        ['line' => 57, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
        ['line' => 62, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
    ]);

    expect($file->getWarnings())->toBe([]);
});

it('does not invent violations from case-mismatched symbols', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'case-mismatched-symbols-passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('skips ambiguous and exempt declarations', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'edge-cases.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('does not read abstract-method parameters as properties', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'abstract-model.php');

    expect(violationTuples($file))->toBe([
        ['line' => 20, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
    ]);
});

it('reports every violation as unfixable', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'failing.php');

    expect($file->getErrorCount())->toBe(15)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, 15, false));
});

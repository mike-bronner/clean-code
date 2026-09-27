<?php

declare(strict_types=1);

const MEMBER_ORDERING = 'CleanCode.Models.MemberOrdering';

const MEMBER_ORDERING_TRAIT_ORDER = MEMBER_ORDERING . '.TraitOrder';

const MEMBER_ORDERING_MULTIPLE_TRAITS = MEMBER_ORDERING . '.MultipleTraitsPerLine';

const MEMBER_ORDERING_PROPERTY_ORDER = MEMBER_ORDERING . '.PropertyOrder';

const MEMBER_ORDERING_PROPERTY_GROUP = MEMBER_ORDERING . '.PropertyGroupOrder';

const MEMBER_ORDERING_RELATIONSHIP = MEMBER_ORDERING . '.RelationshipMethodOrder';

const MEMBER_ORDERING_ACCESSOR = MEMBER_ORDERING . '.AccessorMethodOrder';

const MEMBER_ORDERING_METHOD = MEMBER_ORDERING . '.MethodOrder';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MEMBER_ORDERING);
});

it('reports one violation of each of the five ordering rules', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 8, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
            ['line' => 9, 'column' => 5, 'source' => MEMBER_ORDERING_MULTIPLE_TRAITS],
            ['line' => 13, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 17, 'column' => 22, 'source' => MEMBER_ORDERING_PROPERTY_GROUP],
            ['line' => 24, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
            ['line' => 33, 'column' => 21, 'source' => MEMBER_ORDERING_ACCESSOR],
            ['line' => 42, 'column' => 21, 'source' => MEMBER_ORDERING_METHOD],
        ]);
});

it('names both members in every ordering message', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'failing.php');
    $messages = violationMessagesByLine($file->getErrors());

    expect($messages[8][0])->toContain('Trait Alpha is out of alphabetical order; it belongs before Zebra')
        ->and($messages[9][0])->toContain('this one declares 2')
        ->and($messages[13][0])->toContain('Property $alpha is out of alphabetical order; it belongs before $zulu')
        ->and($messages[17][0])->toContain('Property $table is protected and follows a private property')
        ->and($messages[24][0])->toContain('Relationship method author() is out of alphabetical order')
        ->and($messages[33][0])->toContain('Accessor getTitle() is out of alphabetical order')
        ->and($messages[42][0])->toContain('Method archive() is out of alphabetical order');
});

it('is silent on compliant models and on every near-miss shape', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('checks an anonymous class against all five rules', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'anonymous-class.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 21, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
            ['line' => 22, 'column' => 5, 'source' => MEMBER_ORDERING_MULTIPLE_TRAITS],
            ['line' => 26, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 30, 'column' => 22, 'source' => MEMBER_ORDERING_PROPERTY_GROUP],
            ['line' => 37, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
            ['line' => 46, 'column' => 21, 'source' => MEMBER_ORDERING_ACCESSOR],
            ['line' => 55, 'column' => 21, 'source' => MEMBER_ORDERING_METHOD],
            ['line' => 64, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
        ]);
});

it('reads a hooked property without reading its hook body', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'property-hooks.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 39, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 50, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

it('reads trait names past separators, qualifiers, and a conflict block', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'conflict-block.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 19, 'column' => 5, 'source' => MEMBER_ORDERING_MULTIPLE_TRAITS],
        ])
        ->and(violationMessagesByLine($file->getErrors())[19][0])->toContain('this one declares 3');
});

it('checks every property of a multi-property or attributed declaration', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'multi-property.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 20, 'column' => 20, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 30, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

it('reports each displaced member once and ignores letter case', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'cascade-and-case.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 21, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

it('classifies each method by its declared return type and its name', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'classification.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reads a property declared with a non-visibility modifier', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'property-modifiers.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 26, 'column' => 18, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 33, 'column' => 21, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 40, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 47, 'column' => 9, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

it('reads the short name of a namespace-qualified parent', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'qualified-parent.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 29, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 36, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

it('passes over a method the tokenizer never named', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'truncated-method.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 24, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

it('passes over a class the tokenizer never opened', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('takes its model parents and relation return types from the ruleset', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'configured.php', static function (object $sniff): void {
        $sniff->modelParentClasses = ['Entity'];
        $sniff->relationReturnTypes = ['Association'];
    });

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 22, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
        ]);
});

it('gates on the shipped parents and relation types when nothing is configured', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'configured.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 39, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

it('reports all four alphabetical rules on the evaluation fixture', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'alphabetical-only.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 18, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
            ['line' => 22, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 29, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
            ['line' => 38, 'column' => 21, 'source' => MEMBER_ORDERING_ACCESSOR],
            ['line' => 47, 'column' => 21, 'source' => MEMBER_ORDERING_METHOD],
        ]);
});

it('settles which of the pinned Slevomat sniffs cover which rules', function (
    string $fixture,
    array $expected
): void {
    $file = analyzeWithStandard(
        'SlevomatCodingStandard',
        fixturePath('MemberOrderingSniff', $fixture)
    );

    $matched = array_values(array_unique(array_filter(
        array_merge(...array_values(allViolationSourcesByLine($file))),
        static fn (string $violation): bool => preg_match(
            '/^SlevomatCodingStandard\\.Classes\\.(ClassStructure|PropertyDeclaration|TraitUseDeclaration)\\./',
            $violation
        ) === 1
    )));

    sort($matched);

    expect($matched)->toBe($expected);
})->with([
    'nothing shipped covers the alphabetical rules' => ['alphabetical-only.php', []],
    'the grouping and one-per-line halves are covered' => ['failing.php', [
        'SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder',
        'SlevomatCodingStandard.Classes.TraitUseDeclaration.MultipleTraitsPerDeclaration',
    ]],
]);

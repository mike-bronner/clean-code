<?php

/**
 * Tests the custom CleanCode.Classes.RequireProperties sniff, which carries the
 * "Properties: Are Required" standard (issue #55). Fixtures live in
 * tests/fixtures/RequirePropertiesSniff/.
 *
 * No existing PHPCS or Slevomat sniff reports a class for holding no state, so
 * this standard needed a custom sniff; the search is recorded in
 * docs/standards/properties-are-required.md and pinned by the vendor-standard
 * test at the bottom of this file, so "nothing existed" stays a measurement
 * rather than a claim a Slevomat upgrade could quietly falsify.
 *
 * The sniff reads state as declared *or* inherited *or* composed — a property,
 * an `extends` clause, or a trait `use`. That is the semantics the repository
 * owner chose on the PR (option 2 of three), over the narrower reading in which
 * only a class's own declaration counts: `class NotFoundException extends
 * HttpException {}` plainly has state, and flagging it would contradict the
 * standard's own rationale. The `extends`/`use` halves are therefore a
 * deliberate heuristic, and the tests below pin both what it accepts and what
 * it still refuses.
 *
 * There is no autofixed.php: which property models a concept is a design
 * decision, so the sniff offers no fix, and the detection-only test below
 * proves that rather than asserting the absence of a file.
 */

declare(strict_types=1);

const REQUIRE_PROPERTIES = 'CleanCode.Classes.RequireProperties';

const REQUIRE_PROPERTIES_CODE = REQUIRE_PROPERTIES . '.MissingProperty';

/**
 * The compliant fixture's bytes, and a way to run an edited copy of them. The
 * mutation tests below all work by breaking one clause in the compliant file
 * and watching the sniff start reporting, which needs a real path — every
 * analyze* helper resolves a file — so the edited source is staged outside the
 * repository rather than written back over the fixture.
 */
$compliantSource = static fn (): string => file_get_contents(
    fixturePath('RequirePropertiesSniff', 'passing.php')
);

$analyzeSource = static fn (string $source) => analyzeWithSniffs(
    [REQUIRE_PROPERTIES],
    stageSourceOutsideTests($source, 'edited.php')
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_PROPERTIES);
});

/**
 * passing.php is every route to state at once, plus the shapes the sniff must
 * not speak about: an instance property, a static one, promotion-only state, a
 * subclass declaring nothing, an empty exception subclass, a trait-composed
 * class, a property hook, an interface, two traits and an enum. Each is a
 * decision the sniff has to make, so the file's silence is a verdict rather
 * than an absence.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REQUIRE_PROPERTIES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The nine stateless classes, at the exact lines and columns they are declared
 * on. Column 10 on line 60 is the `class` keyword of `abstract class
 * Transformer` — the report lands on the keyword, not on the modifier.
 *
 * The interface at line 11 is absent from this list, which is the other half of
 * the exclusion asserted below.
 */
it('flags every stateless class at its declaration', function (): void {
    $file = analyzeFixture(REQUIRE_PROPERTIES, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 17, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 26, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 32, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 42, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 51, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 60, 'column' => 10, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 66, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 82, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
        ['line' => 95, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The message names the class it reports, so a developer reading a run over a
 * multi-class file can tell which declaration is meant. `{anonymous}` never
 * appears: the sniff registers on T_CLASS, which PHP_CodeSniffer does not emit
 * for an anonymous class.
 */
it('names the class it reports', function (): void {
    $messages = violationMessages(analyzeFixture(REQUIRE_PROPERTIES, 'failing.php'));

    expect($messages[0])->toContain('Class Formatter encapsulates no state')
        ->and($messages[5])->toContain('Class Transformer encapsulates no state')
        ->and($messages[8])->toContain('Class Builder encapsulates no state');
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(REQUIRE_PROPERTIES, 'failing.php');

    expect($file->getErrorCount())->toBe(9)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The exclusions are about the *declaration kind*, not about those shapes
 * happening to hold a property. Silence on the compliant fixture already covers
 * the three, but silence is also what a sniff that never ran would produce — so
 * each keyword is swapped for `class` here and has to start reporting. The
 * `Stringable` trait is the discriminating one: it holds only a method, so as a
 * class it is a violation and as a trait it must not be.
 */
it('says nothing about interfaces, traits or enums', function (
    string $declaration,
    string $asClass,
    int $line
) use (
    $compliantSource,
    $analyzeSource
): void {
    $source = $compliantSource();

    expect(violationTuples($analyzeSource($source)))->toBe([]);

    $rewritten = str_replace($declaration, $asClass, $source);

    expect(violationTuples($analyzeSource($rewritten)))->toBe([
        ['line' => $line, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
    ]);
})->with([
    'an interface' => ['interface Payable', 'class Payable', 14],
    'a trait' => ['trait Stringable', 'class Stringable', 24],
    'an enum' => ['enum Currency: string', 'class Currency', 32],
]);

/**
 * Inherited and composed state, the semantics the repository owner chose on the
 * PR. Both are what separate this sniff from the "own declaration only"
 * reading, and both are exercised by a class that declares nothing whatever of
 * its own: Refund and PaymentFailed only `extends`, Post only `use`s a trait.
 *
 * Asserted by inverting the fixture rather than by reading the compliant file's
 * silence, which the test above already covers: stripping the `extends Money`
 * or the `use HasTimestamps;` turns that class into the failing shape, so this
 * only stays green while the sniff really is reading those clauses.
 */
it('accepts state a class inherits or composes', function (
    string $clause,
    int $line
) use (
    $compliantSource,
    $analyzeSource
): void {
    $stripped = str_replace($clause, '', $compliantSource());

    expect(violationTuples($analyzeSource($stripped)))->toBe([
        ['line' => $line, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
    ]);
})->with([
    'an extends clause' => [' extends Money', 76],
    'a trait use' => ["    use HasTimestamps;\n\n", 87],
]);

/**
 * An unterminated class body is live coding, not a design smell — a class still
 * being typed has not decided what it holds yet. The fixture is the failing
 * shape on its face (a method, no state), so a sniff that read a half-written
 * class at all would report it.
 *
 * PHP_CodeSniffer gives such a class neither a scope opener nor a scope closer,
 * so dropping the sniff's guard raises an "Undefined array key" warning rather
 * than a violation. phpunit.xml.dist carries failOnWarning="true" so that this
 * test still turns the suite red when the guard goes.
 */
it('says nothing about an unterminated class body', function (): void {
    expect(violationTuples(analyzeFixture(REQUIRE_PROPERTIES, 'unclosed-class.php')))->toBe([]);
});

/**
 * The whole ruleset, not just the isolated sniff: rules.xml pulls the sniff in
 * through its CleanCode standard reference, and this is what proves a consuming
 * project running `phpcs --standard=rules.xml` gets the rule.
 */
it('reports through the master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('RequirePropertiesSniff', 'failing.php'));

    $lines = array_keys(array_filter(
        violationSourcesByLine($file->getErrors()),
        static fn (array $sources): bool => in_array(REQUIRE_PROPERTIES_CODE, $sources, true)
    ));

    expect($lines)->toBe([17, 26, 32, 42, 51, 60, 66, 82, 95]);
});

/**
 * AC #1: the existing-sniff search, run rather than asserted. Slevomat's class
 * sniffs address how properties are *declared* (RequireConstructorProperty-
 * Promotion), their visibility (ForbiddenPublicProperty) and their ordering
 * (ClassStructure); none of them, and nothing in PSR12, Squiz or Generic,
 * reports a class for declaring none.
 *
 * Asked of stateless-stub.php rather than failing.php, and of each vendor
 * standard whole rather than of rules.xml. The stub holds one empty class and
 * nothing else, so everything a standard says about it is listed below and can
 * be read one source at a time; failing.php would instead bury the answer under
 * the vendor standards' opinions about its constants, its parameters and its
 * anonymous class.
 *
 * Every source pinned here is about the class's *presentation* — a strict-types
 * format, a missing `final`, a file name, brace and blank-line placement, a
 * missing docblock, a closing tag, a trailing newline. Not one of them is about
 * the class holding no data, and PSR12 has nothing to say at all. If an upgrade
 * adds a rule that does cover this case, it appears in one of these lists and
 * the test says so instead of leaving the custom sniff unexamined.
 */
it('finds no existing sniff that reports a stateless class', function (string $standard, array $expected): void {
    $file = analyzeWithStandard($standard, fixturePath('RequirePropertiesSniff', 'stateless-stub.php'));

    expect(allViolationSourcesByLine($file))->toBe($expected);
})->with([
    'Slevomat' => [
        'SlevomatCodingStandard',
        [
            3 => ['SlevomatCodingStandard.TypeHints.DeclareStrictTypes.IncorrectStrictTypesFormat'],
            15 => [
                'SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal',
                'SlevomatCodingStandard.Files.TypeNameMatchesFileName.NoMatchBetweenTypeNameAndFileName',
            ],
            16 => ['SlevomatCodingStandard.Classes.EmptyLinesAroundClassBraces.NoEmptyLineAfterOpeningBrace'],
            17 => ['SlevomatCodingStandard.Classes.EmptyLinesAroundClassBraces.NoEmptyLineBeforeClosingBrace'],
        ],
    ],
    'PSR12' => ['PSR12', []],
    'Squiz' => [
        'Squiz',
        [
            1 => ['Squiz.Commenting.FileComment.Missing', 'Squiz.Files.FileExtension.ClassFound'],
            15 => ['Squiz.Classes.ClassFileName.NoMatch'],
            17 => ['Squiz.Commenting.ClosingDeclarationComment.Missing'],
        ],
    ],
    'Generic' => [
        'Generic',
        [
            1 => ['Generic.PHP.ClosingPHPTag.NotFound'],
            16 => ['Generic.Classes.OpeningBraceSameLine.BraceOnNewLine'],
            17 => ['Generic.Files.EndFileNoNewline.Found'],
        ],
    ],
]);

/**
 * The other half of the search: the custom sniff *does* report that stub, so
 * the silence above is a difference between the standards rather than a file
 * nothing can see.
 */
it('reports the stateless stub the vendor standards ignore', function (): void {
    expect(violationTuples(analyzeFixture(REQUIRE_PROPERTIES, 'stateless-stub.php')))->toBe([
        ['line' => 15, 'column' => 1, 'source' => REQUIRE_PROPERTIES_CODE],
    ]);
});

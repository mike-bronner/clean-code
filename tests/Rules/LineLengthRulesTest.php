<?php

/**
 * Tests the Generic.Files.LineLength configuration in the master rules.xml:
 * warning above 100 characters, error above 120, reporting-only.
 *
 * Unlike the per-sniff tests, these run the *whole* master ruleset over the
 * fixture and then scope the assertions to the line-length sources. The
 * fixtures are ordinary procedural files, so unrelated PSR rules they happen to
 * trip must not mask what is being asserted here.
 *
 * Line numbers refer to tests/fixtures/LineLengthSniff/failing.php, whose probe
 * lines are exactly 100 (line 3), 101 (line 5), 120 (line 7), and 121 (line 9)
 * characters long.
 */

declare(strict_types=1);

const LINE_LENGTH_WARNING = 'Generic.Files.LineLength.TooLong';

const LINE_LENGTH_ERROR = 'Generic.Files.LineLength.MaxExceeded';

$lineLengthFixture = static fn (string $fixture) => analyzeWithMasterRuleset(
    fixturePath('LineLengthSniff', $fixture)
);

it('raises no line-length violations on the compliant fixture', function () use ($lineLengthFixture): void {
    $file = $lineLengthFixture('passing.php');

    $sources = array_merge(
        ...array_values(violationSourcesByLine($file->getWarnings())),
        ...array_values(violationSourcesByLine($file->getErrors())),
    );

    expect($sources)->not->toContain(LINE_LENGTH_WARNING, 'Compliant fixture must raise no line-length warning.')
        ->and($sources)->not->toContain(LINE_LENGTH_ERROR, 'Compliant fixture must raise no line-length error.');
});

it('does not flag a line of exactly one hundred characters', function () use ($lineLengthFixture): void {
    $file = $lineLengthFixture('failing.php');

    expect($file->getWarnings())->not->toHaveKey(3)
        ->and($file->getErrors())->not->toHaveKey(3);
});

it('warns on a line of one hundred and one characters', function () use ($lineLengthFixture): void {
    $file = $lineLengthFixture('failing.php');

    expect(violationSourcesByLine($file->getWarnings())[5])->toBe([LINE_LENGTH_WARNING])
        ->and($file->getErrors())->not->toHaveKey(5);
});

it('warns rather than errors at exactly one hundred and twenty characters', function () use ($lineLengthFixture): void {
    $file = $lineLengthFixture('failing.php');

    expect(violationSourcesByLine($file->getWarnings())[7])->toBe([LINE_LENGTH_WARNING])
        ->and($file->getErrors())->not->toHaveKey(7);
});

it('errors on a line of one hundred and twenty-one characters', function () use ($lineLengthFixture): void {
    $file = $lineLengthFixture('failing.php');

    expect(violationSourcesByLine($file->getErrors())[9])->toBe([LINE_LENGTH_ERROR])
        ->and($file->getWarnings())->not->toHaveKey(9);
});

it('reports violations at the expected lines only', function () use ($lineLengthFixture): void {
    $file = $lineLengthFixture('failing.php');

    expect(violationSourcesByLine($file->getWarnings()))
        ->toBe([5 => [LINE_LENGTH_WARNING], 7 => [LINE_LENGTH_WARNING]])
        ->and(violationSourcesByLine($file->getErrors()))->toBe([9 => [LINE_LENGTH_ERROR]]);
});

it('is reporting-only', function () use ($lineLengthFixture): void {
    expect($lineLengthFixture('failing.php')->getFixableCount())->toBe(0);
});

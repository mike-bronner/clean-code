<?php

declare(strict_types=1);

const DEPTH_OF_INHERITANCE = 'CleanCode.Metrics.DepthOfInheritance';

const DEPTH_OF_INHERITANCE_ERROR = 'CleanCode.Metrics.DepthOfInheritance.TooDeep';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DEPTH_OF_INHERITANCE);
});

it('ships PHPMD\'s default minimum of 6', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[DEPTH_OF_INHERITANCE]];

    expect($sniff->minimum)->toBe(6);
});

it('says nothing about a chain below the threshold, or about a non-class', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'passing.php')))->toBe([]);
});

it('flags a class whose parent count reaches the threshold', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'failing.php')))->toBe([
        ['line' => 51, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 56, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 81, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 85, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 85, 'column' => 35, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

it('names the class, its parent count, and the threshold', function (): void {
    $errors = analyzeFixture(DEPTH_OF_INHERITANCE, 'failing.php')->getErrors();

    expect($errors[51][1][0]['message'])->toBe(
            'The class Level6 has 6 parents. Current threshold is 6.'
                . ' Reduce the depth of this class hierarchy.'
        );
    expect($errors[81][1][0]['message'])->toBe(
            'The class Grafted has 6 parents. Current threshold is 6.'
                . ' Reduce the depth of this class hierarchy.'
        );
});

it('treats the threshold as inclusive', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'boundaries.php')))->toBe([
        ['line' => 40, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 44, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

it('takes its threshold from the minimum property', function (int $minimum, array $lines): void {
    $file = analyzeFixtureWithProperty(DEPTH_OF_INHERITANCE, 'boundaries.php', 'minimum', $minimum);

    expect(array_keys(violationSourcesByLine($file->getErrors())))->toBe($lines);
})->with([
    'raised to 7' => [7, [44]],
    'shipped 6' => [6, [40, 44]],
    'lowered to 5' => [5, [36, 40, 44]],
]);

it('abandons the count on an inheritance cycle', function (): void {
    $file = analyzeFixtureWithProperty(DEPTH_OF_INHERITANCE, 'cycle.php', 'minimum', 1);

    expect(violationTuples($file))->toBe([]);
});

it('resolves a parent chain across the files being analysed', function (): void {
    $directory = fixturePath(sniffFixtureDirectory(DEPTH_OF_INHERITANCE), 'project');
    $files = analyzeFileset([DEPTH_OF_INHERITANCE], $directory);

    expect(array_keys($files))->toBe([
        'base.php',
        'braced.php',
        'interpolated.php',
        'leaf.php',
        'mid.php',
    ]);
    expect(violationTuples($files['base.php']))->toBe([]);
    expect(violationTuples($files['mid.php']))->toBe([]);
    expect(violationTuples($files['leaf.php']))->toBe([
        ['line' => 23, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
    expect(violationTuples($files['braced.php']))->toBe([
        ['line' => 14, 'column' => 5, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

it('counts a ${expr} interpolation opened across a namespace boundary', function (): void {
    $directory = fixturePath(sniffFixtureDirectory(DEPTH_OF_INHERITANCE), 'project');
    $files = analyzeFileset([DEPTH_OF_INHERITANCE], $directory);

    expect(violationTuples($files['interpolated.php']))->toBe([
        ['line' => 34, 'column' => 5, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
    expect($files['interpolated.php']->getErrors()[34][5][0]['message'])->toBe(
            'The class Deepest has 8 parents. Current threshold is 6.'
                . ' Reduce the depth of this class hierarchy.'
        );
});

it('counts a {$expr} interpolation inside a class body', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'interpolation.php')))->toBe([
        ['line' => 66, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

it('counts every resolved ancestor across files', function (): void {
    $directory = fixturePath(sniffFixtureDirectory(DEPTH_OF_INHERITANCE), 'project');
    $files = analyzeFileset([DEPTH_OF_INHERITANCE], $directory);

    expect($files['leaf.php']->getErrors()[23][1][0]['message'])->toBe(
            'The class Leaf3 has 6 parents. Current threshold is 6.'
                . ' Reduce the depth of this class hierarchy.'
        );
    expect($files['braced.php']->getErrors()[14][5][0]['message'])->toBe(
            'The class Braced1 has 7 parents. Current threshold is 6.'
                . ' Reduce the depth of this class hierarchy.'
        );
});

it('stops at the edge of the analysed set', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'project/leaf.php')))->toBe([]);
});

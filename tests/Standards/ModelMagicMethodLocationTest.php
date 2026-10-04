<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

pest()->group('arch');

const MODEL_MAGIC_METHOD = 'CleanCode.Models.ModelMagicMethodLocation';

const MODEL_MAGIC_ATTRIBUTE = MODEL_MAGIC_METHOD . '.AttributeMethod';

const MODEL_MAGIC_SCOPE = MODEL_MAGIC_METHOD . '.ScopeMethod';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MODEL_MAGIC_METHOD);
});

it('flags every model-magic method declared in a class body', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'failing.php');
    $warnings = $file->getWarnings();
    ksort($warnings);

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            11 => [MODEL_MAGIC_ATTRIBUTE],
            16 => [MODEL_MAGIC_ATTRIBUTE],
            21 => [MODEL_MAGIC_ATTRIBUTE],
            26 => [MODEL_MAGIC_ATTRIBUTE],
            31 => [MODEL_MAGIC_ATTRIBUTE],
            36 => [MODEL_MAGIC_ATTRIBUTE],
            41 => [MODEL_MAGIC_ATTRIBUTE],
            46 => [MODEL_MAGIC_ATTRIBUTE],
            51 => [MODEL_MAGIC_SCOPE],
            57 => [MODEL_MAGIC_SCOPE],
            64 => [MODEL_MAGIC_SCOPE],
            70 => [MODEL_MAGIC_SCOPE],
            78 => [MODEL_MAGIC_ATTRIBUTE],
            80 => [MODEL_MAGIC_SCOPE],
        ]);
});

it('reports each method on its function keyword', function (): void {
    $warnings = analyzeFixture(MODEL_MAGIC_METHOD, 'failing.php')->getWarnings();
    ksort($warnings);

    expect(array_map('array_keys', $warnings))->toBe([
        11 => [12],
        16 => [12],
        21 => [12],
        26 => [12],
        31 => [12],
        36 => [12],
        41 => [12],
        46 => [12],
        51 => [12],
        57 => [12],
        64 => [15],
        70 => [19],
        78 => [21],
        80 => [21],
    ]);
});

it('names the method and its destination trait', function (): void {
    $warnings = analyzeFixture(MODEL_MAGIC_METHOD, 'failing.php')->getWarnings();

    expect($warnings[11][12][0]['message'])
        ->toBe(
            'Model method getTitleAttribute() is declared in the class body; extract it to '
                . "the model's Attributes trait (e.g. App\Concerns\Attributes\Book) so the model "
                . 'stays lean (see resources/boost/guidelines/models-structure-attributes-queries-traits.md)'
        )
        ->and($warnings[51][12][0]['message'])
        ->toBe(
            'Model method scopePublished() is declared in the class body; extract it to '
                . "the model's Queries trait (e.g. App\Concerns\Queries\Book) so the model "
                . 'stays lean (see resources/boost/guidelines/models-structure-attributes-queries-traits.md)'
        );
});

it('leaves compliant and near-miss declarations alone', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('ignores a return type that does not resolve to the cast', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'unimported-attribute.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('passes over a class the tokenizer never closed', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'unclosed-class.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('does not borrow a later declaration name for an unfinished one', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'truncated-declaration.php');
    $warnings = $file->getWarnings();

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([23 => [MODEL_MAGIC_ATTRIBUTE]]);
});

it('judges each file in a run on its own imports', function (): void {
    [$config, $ruleset] = buildRuleset([MODEL_MAGIC_METHOD], true);
    $ruleset->sniffs = array_intersect_key(
            $ruleset->sniffs,
            array_flip($ruleset->sniffCodes === [] ? [] : [$ruleset->sniffCodes[MODEL_MAGIC_METHOD]])
        );

    $directory = __DIR__ . '/../fixtures/ModelMagicMethodLocationSniff/';
    $counts = [];

    foreach (['failing.php', 'unimported-attribute.php'] as $fixture) {
        $file = new LocalFile($directory . $fixture, $ruleset, $config);
        $file->process();
        $counts[$fixture] = $file->getWarningCount();
    }

    expect($counts)->toBe(['failing.php' => 14, 'unimported-attribute.php' => 0]);
});

it('reads a group import past its function and constant members', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'group-import.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))
        ->toBe([26 => [MODEL_MAGIC_ATTRIBUTE]]);
});

it('passes the standard it belongs to', function (): void {
    $report = installedPhpcsReport(
            'CleanCode',
            cleanCodeRoot() . '/CleanCode/Sniffs/Models/ModelMagicMethodLocationSniff.php'
        );

    expect(array_column($report, 'source'))->toBe([]);
});

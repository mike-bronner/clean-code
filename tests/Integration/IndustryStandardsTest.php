<?php

declare(strict_types=1);

const UNDEFINED = 'VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable';

const SHORT_NAME = 'CleanCode.Naming.ShortVariable.TooShort';

const DUPLICATE_BLOCK = 'CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found';

const INLINE_FQN = 'SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName';

const INLINE_FQN_NO_NAMESPACE = INLINE_FQN . 'WithoutNamespace';

$integrationFixture = static fn (string $fixture) => analyzeWithMasterRuleset(
        __DIR__ . '/fixtures/' . $fixture
    );

$shortOpenTagIsOn = (bool) ini_get('short_open_tag');

$shortOpenTagSource = $shortOpenTagIsOn === true
    ? 'Generic.PHP.DisallowShortOpenTag.Found'
    : 'Generic.PHP.DisallowShortOpenTag.PossibleFound';

it('reports the expected violations', function (
    string $fixture,
    array $expectedErrors,
    array $expectedWarnings
) use ($integrationFixture): void {
    $file = $integrationFixture($fixture);

    expect(violationCountsByLine($file->getErrors()))->toBe($expectedErrors, 'Errors in ' . $fixture)
        ->and(violationCountsByLine($file->getWarnings()))->toBe($expectedWarnings, 'Warnings in ' . $fixture);
})->with([
    'compliant class produces no errors' => ['compliant.php', [], [21 => 1, 25 => 2]],
    'compliant abstract class produces no errors' => ['compliant-abstract.php', [], [13 => 1]],
    'side effects mixed with declarations' => ['side-effects.php', [], [1 => 1]],
    'inline HTML mixed with a class declaration' => ['mixed-html.php', [2 => 1], [1 => 1]],
    'short open tag' => [
        'short-open-tag.php',
        $shortOpenTagIsOn === true ? [1 => 1] : [],
        $shortOpenTagIsOn === true ? [] : [1 => 1],
    ],
    'alternative PHP tags' => ['alternative-php-tags.php', [2 => 1], [1 => 1]],
    'trailing closing tag in a pure-PHP file' => ['closing-tag.php', [9 => 1], []],
    'code sharing the opening tag line' => ['open-tag-not-alone.php', [1 => 2], []],
    'more than one class per file' => ['multiple-classes.php', [9 => 1], []],
    'class outside a namespace' => ['no-namespace.php', [3 => 1], []],
    'missing member visibility' => ['visibility.php', [9 => 3, 11 => 2], [7 => 1]],
    'line exceeding the 120-character hard limit' => ['line-length.php', [7 => 1], []],
    'incorrect and tab indentation' => ['indentation.php', [9 => 1, 10 => 1], [10 => 1]],
    'braces not on their required lines' => ['braces.php', [5 => 1, 6 => 1], []],
    'malformed control structures' => ['control-structures.php', [9 => 3, 11 => 1, 12 => 1], [9 => 1]],
]);

it('pins the PSR opening-tag sniffs', function (string $fixture, array $expected) use ($integrationFixture): void {
    $file = $integrationFixture($fixture);

    expect(allViolationSourcesByLine($file))->toBe($expected, 'Violations in ' . $fixture);
})->with([
    'short open tag' => [
        'short-open-tag.php',
        [1 => [$shortOpenTagSource]],
    ],
    'alternative PHP tags' => [
        'alternative-php-tags.php',
        [
            1 => ['Generic.PHP.DisallowAlternativePHPTags.MaybeASPOpenTagFound'],
            2 => ['Generic.PHP.DisallowAlternativePHPTags.ScriptOpenTagFound'],
        ],
    ],
    'trailing closing tag in a pure-PHP file' => [
        'closing-tag.php',
        [9 => ['PSR2.Files.ClosingTag.NotAllowed']],
    ],
    'code sharing the opening tag line' => [
        'open-tag-not-alone.php',
        [
            1 => [
                'PSR12.Files.FileHeader.SpacingAfterTagBlock',
                'PSR12.Files.OpenTag.NotAlone',
            ],
        ],
    ],
]);

it('produces the expected fixer output', function (string $fixture) use ($integrationFixture): void {
    $file = $integrationFixture($fixture . '.php');

    expect(autofixedContents($file))->toBe(
        file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.fixed.php'),
        'Fixer output for ' . $fixture
    );
})->with([
    'indentation is auto-fixable' => ['indentation'],
    'brace placement is auto-fixable' => ['braces'],
    'control structures are auto-fixable' => ['control-structures'],
]);

it('keeps custom-standard-shaped code PSR12-clean', function (string $path, array $expected): void {
    $file = analyzeWithMasterRuleset($path);

    expect(allViolationSourcesByLine($file))->toBe($expected, 'Violations in ' . basename($path));
})->with([
    'one-thought-per-line chain style is PSR12-clean' => [
        fixturePath('OneThoughtPerLineSniff', 'autofixed.php'),
        [
            3 => [UNDEFINED],
            4 => [UNDEFINED],
            9 => [UNDEFINED, UNDEFINED],
            10 => [UNDEFINED],
            12 => [UNDEFINED, UNDEFINED],
            20 => [UNDEFINED],
            23 => [UNDEFINED],
            25 => [UNDEFINED],
            30 => [SHORT_NAME, UNDEFINED],
            38 => [UNDEFINED],
            41 => [UNDEFINED],
            45 => [UNDEFINED, UNDEFINED],
            47 => [UNDEFINED],
            49 => [UNDEFINED, UNDEFINED],
            52 => [UNDEFINED],
        ],
    ],
    'throwable-only catches are PSR12-clean' => [
        fixturePath('ReferenceThrowableOnlySniff', 'autofixed.php'),
        [
            1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
            6 => [INLINE_FQN_NO_NAMESPACE],
            7 => [DUPLICATE_BLOCK],
            13 => [INLINE_FQN_NO_NAMESPACE],
            14 => [DUPLICATE_BLOCK],
            20 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            21 => [DUPLICATE_BLOCK],
            27 => [INLINE_FQN_NO_NAMESPACE],
            34 => [INLINE_FQN],
            35 => [DUPLICATE_BLOCK],
            41 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            49 => [INLINE_FQN_NO_NAMESPACE],
            51 => [INLINE_FQN_NO_NAMESPACE],
            56 => [INLINE_FQN_NO_NAMESPACE],
            62 => [INLINE_FQN_NO_NAMESPACE],
            65 => [INLINE_FQN_NO_NAMESPACE],
            73 => [INLINE_FQN_NO_NAMESPACE],
            79 => ['PSR1.Classes.ClassDeclaration.MissingNamespace', INLINE_FQN_NO_NAMESPACE],
            84 => [INLINE_FQN_NO_NAMESPACE],
        ],
    ],
    'non-capturing catches are PSR12-clean' => [
        fixturePath('RequireNonCapturingCatchSniff', 'autofixed.php'),
        [
            1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
            4 => [DUPLICATE_BLOCK],
            6 => [INLINE_FQN_NO_NAMESPACE],
            13 => [INLINE_FQN_NO_NAMESPACE],
            18 => [DUPLICATE_BLOCK],
            20 => [INLINE_FQN_NO_NAMESPACE],
            27 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            34 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            42 => [INLINE_FQN_NO_NAMESPACE],
            45 => [INLINE_FQN_NO_NAMESPACE],
            51 => [DUPLICATE_BLOCK],
            53 => [INLINE_FQN_NO_NAMESPACE],
            61 => [INLINE_FQN_NO_NAMESPACE],
            69 => [INLINE_FQN_NO_NAMESPACE],
            78 => [INLINE_FQN_NO_NAMESPACE],
            88 => [INLINE_FQN_NO_NAMESPACE],
            97 => [INLINE_FQN_NO_NAMESPACE],
        ],
    ],
]);

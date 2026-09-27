<?php

declare(strict_types=1);

const SECTION_COMMENT = 'CleanCode.ClearCode.SectionComment';

const SECTION_COMMENT_WARNING = SECTION_COMMENT . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SECTION_COMMENT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every section label at its own line with the expected code', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            17 => [SECTION_COMMENT_WARNING],
            27 => [SECTION_COMMENT_WARNING],
            39 => [SECTION_COMMENT_WARNING],
            47 => [SECTION_COMMENT_WARNING],
            55 => [SECTION_COMMENT_WARNING],
            63 => [SECTION_COMMENT_WARNING],
            72 => [SECTION_COMMENT_WARNING],
            82 => [SECTION_COMMENT_WARNING],
            93 => [SECTION_COMMENT_WARNING],
            103 => [SECTION_COMMENT_WARNING],
            115 => [SECTION_COMMENT_WARNING],
            125 => [SECTION_COMMENT_WARNING],
            139 => [SECTION_COMMENT_WARNING],
            142 => [SECTION_COMMENT_WARNING],
            154 => [SECTION_COMMENT_WARNING],
            159 => [SECTION_COMMENT_WARNING],
            164 => [SECTION_COMMENT_WARNING],
            176 => [SECTION_COMMENT_WARNING],
            179 => [SECTION_COMMENT_WARNING],
            188 => [SECTION_COMMENT_WARNING],
            200 => [SECTION_COMMENT_WARNING],
            207 => [SECTION_COMMENT_WARNING],
            219 => [SECTION_COMMENT_WARNING],
            224 => [SECTION_COMMENT_WARNING],
            238 => [SECTION_COMMENT_WARNING],
            250 => [SECTION_COMMENT_WARNING],
            261 => [SECTION_COMMENT_WARNING],
            270 => [SECTION_COMMENT_WARNING],
            279 => [SECTION_COMMENT_WARNING],
            288 => [SECTION_COMMENT_WARNING],
            298 => [SECTION_COMMENT_WARNING],
            307 => [SECTION_COMMENT_WARNING],
            317 => [SECTION_COMMENT_WARNING],
            329 => [SECTION_COMMENT_WARNING],
            341 => [SECTION_COMMENT_WARNING],
            353 => [SECTION_COMMENT_WARNING],
            365 => [SECTION_COMMENT_WARNING],
            376 => [SECTION_COMMENT_WARNING],
            378 => [SECTION_COMMENT_WARNING],
            390 => [SECTION_COMMENT_WARNING],
            398 => [SECTION_COMMENT_WARNING],
        ]);
});

it('reports at the comment rather than the statement it introduces', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 398, 'column' => 5, 'source' => SECTION_COMMENT_WARNING])
        ->toContain(['line' => 17, 'column' => 9, 'source' => SECTION_COMMENT_WARNING])
        ->toContain(['line' => 93, 'column' => 13, 'source' => SECTION_COMMENT_WARNING])
        ->toContain(['line' => 219, 'column' => 17, 'source' => SECTION_COMMENT_WARNING]);
});

it('quotes the comment and names the standard in the warning message', function (): void {
    $warnings = analyzeFixture(SECTION_COMMENT, 'failing.php')->getWarnings();

    expect($warnings[17][9][0]['message'])
        ->toContain('// Validate the payload.')
        ->toContain('extract the block it introduces into a method named after it')
        ->toContain('docs/standards/clear-code-encapsulate-each-concept-in-a-method.md')
        ->and($warnings[47][9][0]['message'])->toContain('# Normalise the keys.')
        ->and($warnings[55][9][0]['message'])->toContain('/* Normalise the keys. */');
});

it('exposes configurable debt-marker and formatter-directive lists', function (): void {
    expect(array_keys(analyzeFixture(SECTION_COMMENT, 'configured.php')->getWarnings()))
        ->toBe([33]);

    $withoutMarkers = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->debtMarkers = [];
    });

    expect(array_keys($withoutMarkers->getWarnings()))->toBe([17, 33]);

    $withoutDirectives = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->formatterDirectives = [];
    });

    expect(array_keys($withoutDirectives->getWarnings()))->toBe([25, 33, 41]);

    $retunedMarkers = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->debtMarkers = ['Normalise'];
    });

    expect(array_keys($retunedMarkers->getWarnings()))->toBe([17]);

    $retunedDirectives = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->formatterDirectives = ['Normalise'];
    });

    expect(array_keys($retunedDirectives->getWarnings()))->toBe([25, 41]);
});

it('takes both lists from a ruleset property element', function (): void {
    $markers = analyzeFixtureWithRulesetProperties(SECTION_COMMENT, 'configured.php', [
        'debtMarkers' => ['NOTE'],
    ]);

    expect(array_keys($markers->getWarnings()))->toBe([17, 33]);

    $directives = analyzeFixtureWithRulesetProperties(SECTION_COMMENT, 'configured.php', [
        'formatterDirectives' => ['@fmt:off'],
    ]);

    expect(array_keys($directives->getWarnings()))->toBe([25, 33, 41]);
});

it('matches debt markers as words rather than as substrings', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'marker-substring.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            20 => [SECTION_COMMENT_WARNING],
        ]);
});

it('stays silent inside a property hook while still reporting beside it', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'property-hooks.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            28 => [SECTION_COMMENT_WARNING],
        ]);
});

it('is never handed an arrow function as a comment\'s enclosing scope', function (): void {
    $tokens = analyzeFixture(SECTION_COMMENT, 'passing.php')->getTokens();
    $inArrow = [];

    foreach ($tokens as $token) {
        if ($token['code'] === T_COMMENT && str_contains($token['content'], 'keeps out of this comment')) {
            $inArrow[] = $token['conditions'];
        }
    }

    expect($inArrow)->toHaveCount(1)
        ->and($inArrow[0])->not->toContain(T_FN)
        ->and($inArrow[0])->toContain(T_FUNCTION);
});

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect($file->getWarningCount())->toBe(41)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('finds no section comment in this package\'s own source', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [];

    foreach ([$root . '/CleanCode', $root . '/tests'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $isFixture = str_contains($path, '/fixtures/');

            if ($file->isFile() === true && $file->getExtension() === 'php' && $isFixture === false) {
                $files[] = $path;
            }
        }
    }

    expect($files)->not->toBeEmpty();

    $errors = 0;
    $fixable = 0;
    $warnings = 0;

    foreach ($files as $path) {
        $file = analyzeWithSniffs([SECTION_COMMENT], $path);
        $errors += $file->getErrorCount();
        $fixable += $file->getFixableCount();
        $warnings += $file->getWarningCount();
    }

    expect($errors)->toBe(0)
        ->and($fixable)->toBe(0)
        ->and($warnings)->toBe(0);
});

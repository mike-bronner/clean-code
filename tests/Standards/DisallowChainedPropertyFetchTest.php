<?php

declare(strict_types=1);

const CHAINED = 'CleanCode.Models.DisallowChainedPropertyFetch';

const CHAINED_ERROR = CHAINED . '.Found';

const CHAINED_FAILING_LINES = [
    3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 17, 19, 20, 21, 22, 23, 25, 30,
    36, 41,
];

const CHAINED_PRECEDER_LINES = [
    11, 16, 19, 20, 22, 24, 28, 29, 30, 31, 32, 33, 34, 36, 38, 42, 43, 44, 45,
    46, 47, 48, 49, 52, 56, 61, 66, 71, 76, 82, 83, 84, 85, 91, 92, 93, 94, 95,
    98,
];

const CHAINED_REFUSED_PRECEDERS = [
    'T_CLOSE_PARENTHESIS', 'T_CLOSE_SQUARE_BRACKET', 'T_CLOSE_CURLY_BRACKET',
    'T_CLOSE_SHORT_ARRAY', 'T_CLOSE_USE_GROUP', 'T_ATTRIBUTE_END',
    'T_CLOSE_TAG',

    'T_ARRAY', 'T_ISSET', 'T_EMPTY', 'T_EVAL', 'T_EXIT', 'T_LIST', 'T_UNSET',
    'T_MATCH', 'T_IF', 'T_ELSEIF', 'T_WHILE', 'T_FOR', 'T_FOREACH', 'T_SWITCH',
    'T_CATCH', 'T_DECLARE', 'T_FUNCTION', 'T_FN', 'T_CLOSURE', 'T_CLASS',
    'T_ANON_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM', 'T_USE',
    'T_HALT_COMPILER', 'T_TRY', 'T_FINALLY',

    'T_NEW', 'T_INSTANCEOF', 'T_BREAK', 'T_CONTINUE', 'T_STATIC',
    'T_NAMESPACE', 'T_GOTO', 'T_GOTO_LABEL', 'T_GLOBAL', 'T_DEFAULT',
    'T_MATCH_DEFAULT',
    'T_ENUM_CASE', 'T_AS', 'T_INSTEADOF', 'T_EXTENDS', 'T_IMPLEMENTS',
    'T_CONST', 'T_ENDDECLARE', 'T_ENDFOR', 'T_ENDFOREACH', 'T_ENDIF',
    'T_ENDSWITCH', 'T_ENDWHILE',

    'T_ABSTRACT', 'T_FINAL', 'T_VAR', 'T_PUBLIC', 'T_PRIVATE', 'T_PROTECTED',
    'T_READONLY', 'T_PUBLIC_SET', 'T_PRIVATE_SET', 'T_PROTECTED_SET',
    'T_CALLABLE', 'T_PARAM_NAME', 'T_NULLABLE', 'T_TYPE_UNION',
    'T_TYPE_INTERSECTION', 'T_TYPE_OPEN_PARENTHESIS',
    'T_TYPE_CLOSE_PARENTHESIS',

    'T_STRING', 'T_VARIABLE', 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED',
    'T_NAME_RELATIVE', 'T_LNUMBER', 'T_DNUMBER', 'T_CONSTANT_ENCAPSED_STRING',
    'T_DOUBLE_QUOTED_STRING', 'T_HEREDOC', 'T_NOWDOC', 'T_TRUE', 'T_FALSE',
    'T_NULL', 'T_SELF', 'T_PARENT', 'T_CLASS_C', 'T_DIR', 'T_FILE',
    'T_FUNC_C', 'T_LINE', 'T_METHOD_C', 'T_NS_C', 'T_TRAIT_C', 'T_PROPERTY_C',
    'T_BACKTICK',

    'T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON',
    'T_PAAMAYIM_NEKUDOTAYIM', 'T_NS_SEPARATOR', 'T_INC', 'T_DEC', 'T_DOLLAR',
    'T_ATTRIBUTE', 'T_OPEN_USE_GROUP',
    'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG',
    'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG',

    'T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT', 'T_DOC_COMMENT_OPEN_TAG',
    'T_DOC_COMMENT_CLOSE_TAG', 'T_DOC_COMMENT_STAR', 'T_DOC_COMMENT_STRING',
    'T_DOC_COMMENT_TAG', 'T_DOC_COMMENT_WHITESPACE', 'T_PHPCS_DISABLE',
    'T_PHPCS_ENABLE', 'T_PHPCS_IGNORE', 'T_PHPCS_IGNORE_FILE', 'T_PHPCS_SET',
    'T_INLINE_HTML', 'T_NONE', 'T_BAD_CHARACTER',

    'T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES', 'T_STRING_VARNAME',
    'T_NUM_STRING', 'T_ENCAPSED_AND_WHITESPACE', 'T_START_HEREDOC',
    'T_END_HEREDOC', 'T_START_NOWDOC', 'T_END_NOWDOC',
];

const CHAINED_TOKENS_ADDED_IN = [
    'T_PROPERTY_C' => 80400,
    'T_PIPE' => 80500,
];

$stagedRun = static fn (string $fixture) => analyzeWithSniffs(
    [CHAINED],
    stageFixtureOutsideTests(fixturePath('DisallowChainedPropertyFetchSniff', $fixture))
);

$namedPreceders = static fn (): array => array_merge(...array_map(
    static fn (string $constant): array => tokenNamesInConstant(
        cleanCodeRoot() . '/CleanCode/Sniffs/Models/DisallowChainedPropertyFetchSniff.php',
        $constant,
        [CHAINED]
    ),
    ['GROUP_PRECEDERS', 'GROUP_PRECEDER_TYPES']
));

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CHAINED);
});

it('produces no violations on the compliant fixture', function () use ($stagedRun): void {
    $file = $stagedRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]));
});

it('names the first completing pair and the accessor remedy', function () use ($stagedRun): void {
    $messages = violationMessagesByLine($stagedRun('failing.php')->getErrors());

    expect($messages[3][0])->toContain('author->name')
        ->and($messages[4][0])->toContain('author?->name')
        ->and($messages[5][0])->toContain('author?->name')
        ->and($messages[9][0])->toContain('c->d')
        ->and($messages[7][0])->toContain('author->address')
        ->and($messages[7][0])->not->toContain('address->city')
        ->and($messages[3][0])->toContain('getAuthorNameAttribute()')
        ->and($messages[3][0])->toContain('accessor attribute on the first model');
});

it('reports a chain behind every admitted grouping-parenthesis preceder', function () use ($stagedRun): void {
    $file = $stagedRun('group-preceders.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))
        ->toBe(array_fill_keys(CHAINED_PRECEDER_LINES, [CHAINED_ERROR]));
});

it('reports a chain behind the grouping-parenthesis preceders PHP 8.5 adds', function () use ($stagedRun): void {
    $file = $stagedRun('group-preceders-php85.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))
        ->toBe([14 => [CHAINED_ERROR], 18 => [CHAINED_ERROR]]);
})->skip(PHP_VERSION_ID < 80500, 'T_PIPE needs PHP 8.5');

it('classifies every token in PHP_CodeSniffer\'s catalogue', function () use ($namedPreceders): void {
    $catalogue = [];

    foreach (['tokenizer', 'user'] as $group) {
        foreach (array_keys(get_defined_constants(true)[$group] ?? []) as $name) {
            if (str_starts_with($name, 'T_') === true) {
                $catalogue[$name] = constant($name);
            }
        }
    }

    $unions = \PHP_CodeSniffer\Util\Tokens::$assignmentTokens
        + \PHP_CodeSniffer\Util\Tokens::$operators
        + \PHP_CodeSniffer\Util\Tokens::$comparisonTokens
        + \PHP_CodeSniffer\Util\Tokens::$booleanOperators
        + \PHP_CodeSniffer\Util\Tokens::$castTokens;

    $admitted = array_merge(
        $namedPreceders(),
        array_keys(array_filter($catalogue, static fn ($code): bool => isset($unions[$code]) === true))
    );

    $premature = array_keys(array_filter(
        CHAINED_TOKENS_ADDED_IN,
        static fn (int $addedIn): bool => PHP_VERSION_ID < $addedIn
    ));

    $classified = array_diff(array_merge($admitted, CHAINED_REFUSED_PRECEDERS), $premature);

    sort($classified);
    $expected = array_keys($catalogue);
    sort($expected);

    expect(array_values(array_diff(array_keys($catalogue), $classified)))
        ->toBe([], 'every token PHP_CodeSniffer defines is admitted or refused')
        ->and(array_values(array_diff($classified, array_keys($catalogue))))
        ->toBe([], 'neither list names a token PHP_CodeSniffer does not define')
        ->and(array_values(array_intersect($admitted, CHAINED_REFUSED_PRECEDERS)))
        ->toBe([], 'no token is both admitted and refused')
        ->and($classified)->toBe($expected);
});

it('gates a token name only for the PHP versions that predate it', function () use ($namedPreceders): void {
    expect(CHAINED_TOKENS_ADDED_IN)->not->toBe([]);

    $classified = array_merge($namedPreceders(), CHAINED_REFUSED_PRECEDERS);

    foreach (CHAINED_TOKENS_ADDED_IN as $name => $addedIn) {
        expect(defined($name))
            ->toBe(PHP_VERSION_ID >= $addedIn, $name . ' is defined from PHP ' . $addedIn . ' onward')
            ->and($classified)->toContain($name);
    }
});

it('errors through the whole master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(
        stageFixtureOutsideTests(fixturePath('DisallowChainedPropertyFetchSniff', 'failing.php'))
    );

    $onlyChained = static fn (array $messages): array => array_filter(
        array_map(
            static fn (array $sources): array => array_values(
                array_filter($sources, static fn (string $source): bool => $source === CHAINED_ERROR)
            ),
            violationSourcesByLine($messages)
        ),
        static fn (array $sources): bool => $sources !== []
    );

    expect($onlyChained($file->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]))
        ->and($onlyChained($file->getWarnings()))->toBe([]);
});

it('is scoped out of test paths', function () use ($stagedRun): void {
    $inRepo = analyzeWithSniffs(
        [CHAINED],
        fixturePath('DisallowChainedPropertyFetchSniff', 'failing.php')
    );

    expect($inRepo->getErrors())->toBe([])
        ->and($stagedRun('failing.php')->getErrors())->toHaveCount(count(CHAINED_FAILING_LINES));
});

it('handles a truncated chain without falling over', function () use ($stagedRun): void {
    $file = $stagedRun('unterminated.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))->toBe([3 => [CHAINED_ERROR]]);
});

it('refuses malformed source rather than guessing at it', function () use ($stagedRun): void {
    $file = $stagedRun('malformed.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))->toBe([12 => [CHAINED_ERROR]]);
});

it('is silenced by the published inline suppression', function () use ($stagedRun): void {
    $file = $stagedRun('suppressed.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([14 => [CHAINED_ERROR]]);
});

it('reports detection-only errors', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getErrorCount())->toBe(count(CHAINED_FAILING_LINES))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('decides a chain in time linear in its length', function (string $shape, int $size, int $steps): void {
    $source = "<?php\n\n\$a";

    for ($hop = 0; $hop < $size; $hop++) {
        $source .= $shape === 'interleaved' && $hop % 3 === 2 ? '->m()' : "->p{$hop}";
    }

    $path = stageGeneratedFixture("chained-{$shape}.php", $source . ";\n");

    $file = analyzeWithSniffs([CHAINED], $path);

    expect($file->getErrorCount())->toBeGreaterThan(0, 'the chain is still reported')
        ->and(sniffInstance(CHAINED)->walkSteps())->toBe(
            $steps,
            "{$shape} at n={$size}: the record answers every re-entry into a receiver already walked"
        );
})->with([
    'n consecutive hops' => ['plain', 16000, 1],
    'n hops in call-separated segments' => ['interleaved', 16000, 21329],
]);

it('keeps no record across files', function () use ($stagedRun): void {
    $first = $stagedRun('failing.php');
    $firstSteps = sniffInstance(CHAINED)->walkSteps();
    $between = $stagedRun('passing.php');
    $betweenSteps = sniffInstance(CHAINED)->walkSteps();
    $second = $stagedRun('failing.php');
    $secondSteps = sniffInstance(CHAINED)->walkSteps();

    expect(violationSourcesByLine($first->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]))
        ->and($between->getErrors())->toBe([])
        ->and(violationSourcesByLine($second->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]))
        ->and($betweenSteps)->toBeGreaterThan(
            0,
            'the middle file walks, so a count left standing would carry into the third run'
        )
        ->and($secondSteps)->toBe(
            $firstSteps,
            'the walk-step count is cleared with the record, so the same file steps the same'
        );
});

it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('DisallowChainedPropertyFetchSniff', 'failing.php');

    $staged = installedSniffRun(CHAINED, stageFixtureOutsideTests($failing));
    $inRepo = installedSniffRun(CHAINED, $failing);
    $passing = installedSniffRun(
        CHAINED,
        stageFixtureOutsideTests(fixturePath('DisallowChainedPropertyFetchSniff', 'passing.php'))
    );

    expect(array_column($staged['messages'], 'line'))->toBe(CHAINED_FAILING_LINES)
        ->and(array_unique(array_column($staged['messages'], 'source')))->toBe([CHAINED_ERROR])
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['ERROR'])
        ->and($staged['status'])->toBe(2)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});

it('keeps its root record from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        $flag = self::$book->author->name;

        PHP;

    $sourceB = <<<'PHP'
        <?php

        $flag = !!$book->author->name;

        PHP;

    $first = analyzeStdinSource([CHAINED], $sourceA);
    $second = analyzeStdinSource([CHAINED], $sourceB);
    $third = analyzeStdinSource([CHAINED], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 3, 'column' => 26, 'source' => CHAINED_ERROR],
        ])
        ->and(violationMessagesByLine($second->getErrors()))->toBe([
            3 => [
                'Chained property fetch author->name; expose the value as an accessor '
                    . 'attribute on the first model instead (e.g. getAuthorNameAttribute() so '
                    . 'callers read $book->authorName rather than $book->author->name) '
                    . '(see resources/boost/guidelines/models-relationship-properties.md)',
            ],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([]);
});

it('keeps its walked-root record for the whole stream, not one read', function (): void {
    $sniff = sniffInstance(CHAINED);

    foreach ([2, 4, 8] as $size) {
        $fetches = '';

        for ($index = 0; $index < $size; $index++) {
            $fetches .= "        \$one{$index} = \$this->alpha{$index}->beta;\n";
        }

        $source = "<?php\n\nclass Consumer\n{\n    public function read(): void\n    {\n"
            . $fetches . "    }\n}\n";

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([CHAINED], $source);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe($size, "n={$size}: every fetch is still reported")
            ->and($counted['roots.builds'])->toBe(
                1,
                "n={$size}: the record is emptied once for the stream, not once per read"
            )
            ->and($counted['roots.hits'])->toBe(
                $size - 1,
                "n={$size}: every read after the first finds the record already standing"
            );
    }
});

<?php

/**
 * Tests the custom CleanCode.CodeStyle.NoFormatterDirectives sniff, the partial
 * enforcement of Code Style: Linters (config & no auto-formatter) (#51, sniff
 * #143) — docs/standards/code-style-linters-config--no-auto-formatter.md.
 *
 * The standard itself is Tier 3 and stays with code review. The sniff covers
 * the one slice that reaches the tokens: an auto-formatter on/off or ignore
 * marker committed in a comment. The severity and detection-only tests below
 * pin both ends of that report, because a regression to warnings or to a
 * fixable report would leave every line assertion here passing.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const NO_FORMATTER_DIRECTIVES = 'CleanCode.CodeStyle.NoFormatterDirectives';

/**
 * Every error the failing fixture must produce, as line/column. The column is
 * the comment token's own start rather than the marker's offset inside it,
 * which is what lines 39 and 41 pin: a trailing comment reports at the comment
 * (column 9), and a comment carrying two markers reports once (line 41).
 */
const NO_FORMATTER_DIRECTIVES_TUPLES = [
    ['line' => 3, 'column' => 1],
    ['line' => 5, 'column' => 1],
    ['line' => 7, 'column' => 1],
    ['line' => 10, 'column' => 1],
    ['line' => 14, 'column' => 1],
    ['line' => 19, 'column' => 5],
    ['line' => 23, 'column' => 4],
    ['line' => 24, 'column' => 4],
    ['line' => 28, 'column' => 5],
    ['line' => 32, 'column' => 11],
    ['line' => 39, 'column' => 9],
    ['line' => 41, 'column' => 1],
];

/**
 * Every message the failing fixture must produce, in report order. Asserted
 * verbatim because the marker the message names is the sniff's whole answer:
 * a sniff that flagged the right comments while naming the wrong marker would
 * pass the tuple test above.
 *
 * Line 39's shouted `@FORMATTER:OFF` is reported as the configured spelling,
 * which is what makes the match case-insensitive rather than a second entry in
 * the list. Line 41 names the first configured marker of the two it carries.
 */
const NO_FORMATTER_DIRECTIVES_MESSAGES = [
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:on must not be committed; correct style by hand instead',
    'Auto-formatter directive prettier-ignore must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:on must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:on must not be committed; correct style by hand instead',
    'Auto-formatter directive prettier-ignore must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive prettier-ignore must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
    'Auto-formatter directive @formatter:off must not be committed; correct style by hand instead',
];

/**
 * Runs a fixture through a ruleset built from an actual ruleset XML file in the
 * sniff's own fixture directory, so PHPCS's own <property>/<element> reader is
 * what configures the sniff — the route a consuming project's ruleset takes.
 *
 * Builds its own Config rather than going through buildRuleset(), which always
 * parses rules.xml. The sniff is referenced by file path from the XML, so no
 * installed_paths entry is needed; the fresh ConfigDouble resets PHPCS's static
 * config state, exactly as tests/Standards/ConvertToCollectionTest.php does, so
 * nothing here leaks into a memoised ruleset later in the run.
 *
 * @var callable(string, string): LocalFile
 */
$analyzeThroughRulesetFile = static function (string $ruleset, string $fixture): LocalFile {
    $directory = sniffFixtureDirectory(NO_FORMATTER_DIRECTIVES);

    $config = new ConfigDouble(['--standard=' . fixturePath($directory, $ruleset)]);
    $config->cache = false;

    $file = new LocalFile(fixturePath($directory, $fixture), new Ruleset($config), $config);
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_FORMATTER_DIRECTIVES);
});

/**
 * passing.php pairs every comment shape the sniff registers on — line, hash,
 * one-line block, multi-line block, and a doc comment carrying both a tag and
 * prose — with the near-miss shapes it must stay silent on: the word formatter
 * and the word off apart, the marker's name without its state, both shipped
 * markers misspelled, and each of them quoted in a single-quoted string, a
 * double-quoted string and a nowdoc body. Each of those is one of the sniff's
 * own decisions, so the fixture's silence is a verdict rather than the absence
 * of anything to look at.
 *
 * Its last comment is a marker no shipped entry carries. It is silent here and
 * flagged under custom-directives.xml below, which is what keeps the
 * configured-list test from passing on a fixture that was never quiet.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * failing.php carries each shipped marker in each comment shape that can hold
 * it: the two line spellings, a one-line block, an inner line of a multi-line
 * block, a doc comment's tag token, a doc comment's string token, a marker
 * written mid-sentence, one written after another tag, and a trailing comment
 * shouting its marker.
 */
it('flags every directive comment at its own line and column', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    $expected = [];

    foreach (NO_FORMATTER_DIRECTIVES_TUPLES as $tuple) {
        $expected[] = $tuple + ['source' => NO_FORMATTER_DIRECTIVES . '.Found'];
    }

    expect(violationTuples($file))->toBe($expected);
});

it('names the directive each comment carries', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect(violationMessages($file))->toBe(NO_FORMATTER_DIRECTIVES_MESSAGES);
});

/**
 * One report per comment token, not per marker. failing.php's last comment
 * carries both a formatter marker and an ignore marker, and a sniff that
 * reported each would leave the tuple list above one entry short — this says
 * so directly, so the reason for the count is on the record rather than
 * inferred from a list of twelve.
 */
it('reports a comment carrying two directives once', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))->toHaveKey(41)
        ->and(violationCountsByLine($file->getErrors())[41])->toBe(1);
});

/**
 * The standard forbids the tool outright, so every report is an error. Asserted
 * separately from the tuple test, which reads the error list and would pass
 * unchanged had the sniff raised warnings and no errors at all.
 */
it('reports errors rather than warnings', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect($file->getErrorCount())->toBe(count(NO_FORMATTER_DIRECTIVES_TUPLES))
        ->and($file->getWarnings())->toBe([])
        ->and($file->getWarningCount())->toBe(0);
});

/**
 * Detection only, and there is no autofixed.php fixture. Deleting the marker
 * removes the evidence rather than the formatter, and leaves the region it
 * guarded formatted, so no mechanical rewrite settles the violation.
 */
it('reports detection-only violations', function (): void {
    $file = analyzeFixture(NO_FORMATTER_DIRECTIVES, 'failing.php');

    expect($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(NO_FORMATTER_DIRECTIVES_TUPLES), false));
});

/**
 * A configured list replaces the shipped one rather than extending it. Run
 * against failing.php — a file that is otherwise nothing but violations — so
 * the silence can only come from the three shipped markers leaving the list.
 */
it('flags nothing once the shipped directives leave the configured list', function (): void {
    $file = analyzeFixtureWithProperty(
        NO_FORMATTER_DIRECTIVES,
        'failing.php',
        'directives',
        ['@fmt:off']
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The other half of the same property, driven through a real ruleset XML file
 * rather than Ruleset::setSniffProperty() — a ruleset's own <property> tag is
 * parsed inside Ruleset::processRule() and handed over pre-parsed, so only a
 * ruleset file exercises the path a consuming project actually takes.
 *
 * Both <element> spellings are covered. The keyless one is shouted, which pins
 * the case-insensitive match on a *configured* marker rather than only on a
 * shipped one; the keyed one proves the key is ignored, and its value is a
 * near-miss spelling passing.php carries that the shipped list leaves alone.
 */
it('takes both element-node property spellings from a real ruleset file', function () use (
    $analyzeThroughRulesetFile
): void {
    $file = $analyzeThroughRulesetFile('custom-directives.xml', 'passing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 39, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 57, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
    ])->and(violationMessages($file))->toBe([
        'Auto-formatter directive formatter:off must not be committed; correct style by hand instead',
        'Auto-formatter directive @FMT:OFF must not be committed; correct style by hand instead',
    ]);
});

/**
 * A configured directive is trimmed before it is matched, so the entry below
 * still flags each of failing.php's six `@formatter:off` comments — the two
 * line spellings among them, so the assertion cannot be satisfied by one
 * comment shape alone. Untrimmed, the padded needle matches nothing at all.
 */
it('trims a configured directive before matching it', function (): void {
    $file = analyzeFixtureWithProperty(
        NO_FORMATTER_DIRECTIVES,
        'failing.php',
        'directives',
        ['  @formatter:off  ']
    );

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 10, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 19, 'column' => 5, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 28, 'column' => 5, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 39, 'column' => 9, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
        ['line' => 41, 'column' => 1, 'source' => NO_FORMATTER_DIRECTIVES . '.Found'],
    ]);
});

/**
 * A configured entry that is empty once trimmed configures nothing. PHP finds
 * an empty needle at offset 0 of every string, so without the skip the sniff
 * would report every comment in failing.php — and failing.php is chosen here
 * for exactly that reason: it is dense with comments the shipped list already
 * flags, so a silent run can only be the guard doing its job.
 */
it('ignores a configured directive that is empty once trimmed', function (): void {
    $file = analyzeFixtureWithProperty(
        NO_FORMATTER_DIRECTIVES,
        'failing.php',
        'directives',
        ['', '   ']
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

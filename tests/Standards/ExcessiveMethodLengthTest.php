<?php

/**
 * Tests the custom CleanCode.Functions.ExcessiveMethodLength sniff (PHPMD
 * CodeSize ExcessiveMethodLength, #91). Fixtures live in
 * tests/fixtures/ExcessiveMethodLengthSniff/, and the mapping is documented in
 * docs/phpmd/codesize-excessivemethodlength.md.
 *
 * Every number asserted below was read off a live PHPMD 2.15.0 run over these
 * same three fixture files, at the same thresholds, rather than derived from
 * the sniff. Running that rule at `minimum=1` makes PHPMD print its measured
 * count for every declaration it can see, which is what pins the metric itself
 * and not merely the pass/fail verdict:
 *
 *   loc  (ignore-whitespace false)  failing 100/100/100/100 at lines 14, 115,
 *                                   216, 318; passing 99/6/6/6/6/1/1 at lines
 *                                   29, 129, 136, 143, 150, 160, 165;
 *                                   configured 15/4/9/14/7 at lines 13, 29, 34,
 *                                   45, 70
 *   eloc (ignore-whitespace true)   failing 99/97/27/99; passing 98/5/5/5/5,
 *                                   the two bodiless declarations dropping to
 *                                   zero; configured 10/3/7/13/4
 *
 * The sniff reproduces all of it exactly — same lines, same counts, same
 * silences — which is the whole point: `phpmd` no longer has to run for this
 * rule.
 *
 * The rule is detection-only. Shortening a method means extracting helpers and
 * naming them, so there is no autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const EXCESSIVE_METHOD_LENGTH = 'CleanCode.Functions.ExcessiveMethodLength';

const EXCESSIVE_METHOD_LENGTH_ERROR = EXCESSIVE_METHOD_LENGTH . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_METHOD_LENGTH);
});

/**
 * The compliant fixture carries the near-miss shapes the sniff must stay silent
 * on at PHPMD's default threshold of 100, each of which a plausible
 * implementation gets wrong:
 *
 * - line 29, a method of exactly 99 lines — one below the inclusive threshold.
 *   It is preceded by a seven-line docblock and two attributes, so an
 *   implementation that measured from the docblock (106 lines) or from the
 *   first attribute (101) would report it. PHPMD measures from `public`.
 * - lines 129 to 150, four six-line methods inside a class spanning more than
 *   150 lines. The metric is per declaration, not per class.
 * - lines 160 and 165, an abstract method and an interface method. Neither has
 *   a body, and an implementation that hunted forward for a closing brace
 *   would measure one of them as the rest of the file.
 * - line 168, a file-scope closure of 112 lines, and line 281, an arrow
 *   function. PDepend models neither as a declaration, so PHPMD cannot report
 *   them however long they get; registering T_CLOSURE or T_FN here would.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every declaration PHPMD reports on the failing fixture, at its own defaults,
 * at the line and column PHPMD anchors to — the first modifier keyword, or the
 * `function` keyword when there is none.
 *
 * - line 14, a method of exactly 100 lines. This is the boundary that matters:
 *   PHPMD's comparison is `$loc < $threshold` and returns early, so equality is
 *   already a violation. Together with line 29 of the compliant fixture (99
 *   lines, silent) it pins both sides of it.
 * - line 115, a declaration whose `public` and `static` sit on their own lines
 *   ahead of `function`. It measures 100 counted from `public` and 98 counted
 *   from `function`, so it is reported here only because the modifiers are
 *   inside the span — and it is reported *at* `public`, column 5, not at the
 *   `function` keyword on line 117.
 * - line 216, a method padded to 100 lines with blank and comment lines. It
 *   fails under the default metric and, at 27 executable lines, passes easily
 *   under the other one; the ignoreWhitespace test below turns exactly this
 *   declaration off.
 * - line 318, a plain function at file scope, column 1. Despite the rule's
 *   name PHPMD reports functions too — PHPMD\Rule\Design\LongMethod implements
 *   FunctionAware as well as MethodAware.
 */
it('flags every declaration at or over the default threshold', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 115, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 216, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 318, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The measured count and the threshold both reach the diagnostic, because the
 * number is what makes the report actionable — and because asserting only that
 * *something* was reported would pass against a sniff that had miscounted every
 * declaration in the file. All four measure exactly 100, as PHPMD measures
 * them.
 *
 * The noun is asserted too: PHPMD says "method" inside a class-like scope and
 * "function" outside one, and line 318 is the only declaration here that sits
 * outside.
 */
it('reports the measured line count and threshold', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'failing.php');

    $messages = array_map(
        static fn (array $columns): string => reset($columns)[0]['message'],
        $file->getErrors()
    );

    expect(array_values($messages))->toBe([
        'The method exactlyAtTheThreshold() has 100 lines of code, and the threshold is 100; '
            . 'a declaration this long is doing several jobs, so extract each one into its own '
            . 'method (see docs/phpmd/codesize-excessivemethodlength.md)',
        'The method modifiersOnTheirOwnLines() has 100 lines of code, and the threshold is 100; '
            . 'a declaration this long is doing several jobs, so extract each one into its own '
            . 'method (see docs/phpmd/codesize-excessivemethodlength.md)',
        'The method padOutWithBlanksAndComments() has 100 lines of code, and the threshold is 100; '
            . 'a declaration this long is doing several jobs, so extract each one into its own '
            . 'method (see docs/phpmd/codesize-excessivemethodlength.md)',
        'The function standaloneFunctionThatRunsLong() has 100 lines of code, and the threshold '
            . 'is 100; a declaration this long is doing several jobs, so extract each one into '
            . 'its own method (see docs/phpmd/codesize-excessivemethodlength.md)',
    ]);
});

/**
 * The rule is report-only, matching PHPMD, which offers no fix for it either.
 */
it('offers no fix for any violation', function (): void {
    $file = analyzeFixture(EXCESSIVE_METHOD_LENGTH, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false, false, false, false])
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * Lowering `minimum` reports the declarations between the new threshold and the
 * old one, and no others. At 14, PHPMD reports holdsAClosure() (15 lines) and
 * configuredStandalone() (14, the boundary again) and stays silent on short()
 * (4) and withMultilineString() (9).
 *
 * holdsAClosure() is the interesting one: it is a fifteen-line method only
 * because it wraps a nested closure, whose lines PDepend folds into the
 * enclosing method rather than measuring separately. That is the other half of
 * the compliant fixture's file-scope closure — nested, a closure counts; alone,
 * it is invisible.
 */
it('honours a lowered minimum', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'configured.php',
        static function (object $sniff): void {
            $sniff->minimum = 14;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

/**
 * One line higher, and the boundary declaration drops out while the fifteen-line
 * one stays. Asserted separately from the test above so that an off-by-one in
 * the comparison cannot hide: at `minimum = 15` a `>` implementation reports
 * nothing at all here, and a `>=` one reports line 13.
 */
it('treats a declaration of exactly minimum lines as too long', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'configured.php',
        static function (object $sniff): void {
            $sniff->minimum = 15;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

/**
 * A comment written between two modifiers does not stop the span at the second
 * one. anchorsAtTheFirstModifier() puts `public` on line 70, a comment on 71 and
 * `static function` on 72, so the declaration measures seven lines counted from
 * `public` and five counted from `static`.
 *
 * The threshold is placed at six to sit between those two numbers, which makes
 * the test two-sided: a walk that stopped at the comment would score the
 * declaration five and drop line 70 from the report entirely — and if it did
 * report, it would report line 72. Both the line and the verdict move, and the
 * looser thresholds used elsewhere in this file cannot see either, because a
 * two-line miscount does not change the verdict at 14 or 15.
 *
 * Live PHPMD 2.15.0 at `minimum = 1` measures the same seven lines and anchors
 * the report on line 70, so this is parity and not a choice of our own.
 */
it('starts the span at the first modifier when a comment sits between two', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'configured.php',
        static function (object $sniff): void {
            $sniff->minimum = 6;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 34, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 70, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);

    expect($file->getErrors()[70][5][0]['message'])->toContain('has 7 lines of code');
});

/**
 * `ignoreWhitespace` swaps PDepend's `loc` for its `eloc`, which counts only
 * the lines between the braces that carry a non-comment token.
 *
 * The default fixture is the sharpest test of it: padOutWithBlanksAndComments()
 * measures 100 lines and 27 executable ones, so turning the property on drops
 * that one declaration and leaves the other three — which measure 99, 97, and
 * 99 executable lines — reported. A property that did nothing would leave all
 * four; one that dropped comments only, or blanks only, would not land on 27.
 */
it('counts only executable lines when ignoreWhitespace is set', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'failing.php',
        static function (object $sniff): void {
            $sniff->ignoreWhitespace = true;
            $sniff->minimum = 99;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 318, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

/**
 * The executable count excludes comment lines and the signature, and the
 * threshold here is placed on exactly that boundary.
 *
 * holdsAClosure() spans lines 13 to 27 and scores ten executable lines. Three
 * separate mistakes would each score it eleven and report it at `minimum = 11`:
 * counting the comment on line 18, counting the blank lines on 17, 22 and 25,
 * or starting the count at the declaration on line 13 rather than at the brace
 * on line 14. It stays silent, while configuredStandalone() — thirteen
 * executable lines, and no comment or blank line anywhere in it to be confused
 * by — is reported.
 *
 * The looser thresholds used by the other executable-line tests here cannot see
 * any of the three, because a miscount of one does not change the verdict at
 * them. Live PHPMD 2.15.0 at the same setting reports line 45 alone.
 */
it('excludes comments and the signature from the executable count', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'configured.php',
        static function (object $sniff): void {
            $sniff->ignoreWhitespace = true;
            $sniff->minimum = 11;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

/**
 * A construct spanning several lines contributes all of them, so the four-line
 * string literal in withMultilineString() carries four of its seven executable
 * lines. PHPMD measures the same seven.
 *
 * This is cheap to get right — PHPCS splits a multi-line string into one token
 * per line, so counting each token's own line already covers it — and cheap to
 * get wrong in the other direction, by measuring the literal's span from its
 * own content. At `minimum = 7`, a body read as one line per *statement* scores
 * withMultilineString() four and drops line 34 from the report.
 */
it('counts every line a multi-line construct spans', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'configured.php',
        static function (object $sniff): void {
            $sniff->ignoreWhitespace = true;
            $sniff->minimum = 7;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 34, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

/**
 * A bodiless declaration scores zero executable lines, as PDepend scores it,
 * so no threshold above zero reports one. The compliant fixture's abstract and
 * interface methods are the subjects; `minimum = 1` is the lowest threshold the
 * sniff honours, and even there they stay silent while the four short methods
 * around them do not.
 *
 * Worth its own test because the two are the only declarations in the suite
 * with no scope_opener, and the guard that returns zero for them is otherwise
 * unreachable — an implementation that fell through to the `loc` branch instead
 * would report both.
 */
it('scores a bodiless declaration zero executable lines', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'passing.php',
        static function (object $sniff): void {
            $sniff->ignoreWhitespace = true;
            $sniff->minimum = 1;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 29, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 129, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 136, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 143, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 150, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

/**
 * Under the default metric those same two declarations measure one line each,
 * so they are reachable — and reported — at `minimum = 1`. This is the other
 * half of the test above: it proves the silence there comes from the `eloc`
 * score and not from the sniff skipping bodiless declarations altogether.
 *
 * The semicolon each one ends with is the very next token after its parameter
 * list, so this says nothing about how far the search for it may run; the
 * malformed fixture below is what pins that.
 */
it('measures a bodiless declaration as one line under the default metric', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 1;
        }
    );

    $bodiless = array_filter(
        violationTuples($file),
        static fn (array $tuple): bool => in_array($tuple['line'], [160, 165], true)
    );

    expect(array_values($bodiless))->toBe([
        ['line' => 160, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 165, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);

    $messages = $file->getErrors();

    expect($messages[160][5][0]['message'])->toContain('has 1 lines of code')
        ->and($messages[165][5][0]['message'])->toContain('has 1 lines of code');
});

/**
 * A declaration cut short mid-edit — bodiless, but without the semicolon that
 * would end it — is scored one line and never reported.
 *
 * This is what bounds the search in signatureTerminator(). The fragment on line
 * 20 of the malformed fixture has no terminator of its own, and the next
 * semicolon in the file belongs to a statement inside the following method, on
 * line 24; a search that accepted it would score the fragment five lines and,
 * at `minimum = 5`, report it. Refusing to cross the opening brace keeps a typo
 * from manufacturing a violation.
 *
 * The threshold is set to 5 rather than left at the default precisely so the
 * miscount would be visible: the real method below it measures eight lines and
 * is reported, which is what shows the fixture is being processed at all rather
 * than silently skipped as unparseable.
 *
 * PHPMD is not the reference here. PDepend cannot parse the file, so there is
 * no live run to compare against — this pins a fail-closed choice of our own,
 * not parity.
 */
it('scores a declaration with no body and no terminator as one line', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'malformed.php',
        static function (object $sniff): void {
            $sniff->minimum = 5;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 22, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

/**
 * A `minimum` PHPCS could not have read as a sane threshold falls back to
 * PHPMD's default of 100 rather than to zero.
 *
 * PHPCS hands `<property>` values over as strings and casts nothing but the
 * literals `true` and `false` (Ruleset::setSniffProperty()), so a typo reaches
 * the sniff verbatim. `(int) 'abc'` is 0, and a threshold of 0 reports every
 * declaration in the codebase — a linting standard that fails everything is a
 * standard that gets switched off, so the fallback goes the other way. A
 * negative value is refused for the same reason.
 */
it('falls back to the default threshold on an unusable minimum', function (string $minimum): void {
    $file = analyzeFixture(
        EXCESSIVE_METHOD_LENGTH,
        'configured.php',
        static function (object $sniff) use ($minimum): void {
            $sniff->minimum = $minimum;
        }
    );

    expect($file->getErrors())->toBe([]);
})->with([
    'not a number' => ['abc'],
    'empty' => [''],
    'zero' => ['0'],
    'negative' => ['-5'],
]);

/**
 * The properties have to survive the trip through a real ruleset file, not just
 * a direct assignment from a test.
 *
 * PHPCS converts `true` and `false` and leaves everything else a string, so
 * `minimum` arrives as `'14'` — which is why the property is declared untyped
 * and cast in normalizedMinimum(). Setting it directly, as every test above
 * does, assigns an `int` and would never catch a typed-property regression
 * here. The expected reports are the same two as the lowered-minimum test, so
 * the two can be compared directly.
 */
it('accepts both properties from a ruleset file', function (): void {
    $standard = sys_get_temp_dir() . '/' . uniqid('cleancode-ruleset-', true) . '.xml';
    $sniffPath = cleanCodeRoot() . '/CleanCode/Sniffs/Functions/ExcessiveMethodLengthSniff.php';

    file_put_contents($standard, <<<XML
        <?xml version="1.0"?>
        <ruleset name="ExcessiveMethodLengthFromXml">
            <rule ref="{$sniffPath}">
                <properties>
                    <property name="minimum" value="14"/>
                    <property name="ignoreWhitespace" value="false"/>
                </properties>
            </rule>
        </ruleset>
        XML);

    $config = new ConfigDouble(['--standard=' . $standard]);
    $config->cache = false;
    Config::setConfigData('installed_paths', '', true);

    $file = new LocalFile(
        fixturePath('ExcessiveMethodLengthSniff', 'configured.php'),
        new Ruleset($config),
        $config
    );
    $file->process();

    unlink($standard);

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 5, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
        ['line' => 45, 'column' => 1, 'source' => EXCESSIVE_METHOD_LENGTH_ERROR],
    ]);
});

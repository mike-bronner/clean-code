<?php

/**
 * Tests the custom CleanCode.Naming.ShortVariable sniff, which replicates
 * PHPMD's Naming/ShortVariable (#106) — docs/phpmd/naming-shortvariable.md.
 *
 * Fixtures live in tests/fixtures/ShortVariableSniff/. The sniff is
 * detection-only, so the contract's third fixture (autofixed.php) does not
 * apply; passing.php and failing.php are joined by the fixtures that pin one
 * decision each — divergences.php, static-access.php, contexts.php,
 * trait-method.php, reopened-tags.php, multiline-string.php and
 * malformed-declaration.php.
 *
 * Every line asserted against failing.php was cross-checked against a live
 * phpmd 2.15 run of rulesets/naming.xml/ShortVariable at its defaults, from a
 * cold pdepend cache: phpmd reports the same twenty-five lines, with the same
 * names, and reports nothing at all on passing.php.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

use PHP_CodeSniffer\Ruleset;

const SHORT_VARIABLE = 'CleanCode.Naming.ShortVariable';

const SHORT_VARIABLE_TOO_SHORT = SHORT_VARIABLE . '.TooShort';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SHORT_VARIABLE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The exact line and column of every report, one per offending name per
 * scope.
 *
 * The shapes, in order: a global function's parameter (22) and local (24), a
 * trait field (31), an interface method's parameter (36), an enum method's
 * parameter (43), three properties — untyped, static and typed (51, 53, 55),
 * a promoted constructor parameter (57), the class's own implementation of
 * the interface method (61), two parameters including one with a default
 * (66), a local whose second occurrence three lines later is not reported
 * again (68), two variables destructured from an array (78), two destructured
 * out of a foreach value (82), a by-reference foreach value (89), a name that
 * only ever appears interpolated into a double-quoted string (101) and one
 * that only appears in a heredoc (102), a static and a global declaration
 * (108, 109), and a closure's parameter and local plus an arrow function's
 * parameter (119, 120, 124).
 *
 * Two of those carry a second claim. `$ip` is reported at 36 and again at 61:
 * the same name in two scopes, which per-file deduplication would report once.
 * `$rr` is reported at 68 and not at 71: the same name twice in one scope,
 * which no deduplication at all would report twice.
 *
 * The column is the variable token, which is what makes the destructuring
 * cases (78, 82) and the by-reference case (89) worth asserting separately: an
 * implementation that reported at the statement, or that mistook the `&` for
 * the variable, would still report the right lines.
 */
it('flags every short name at the variable token', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 22, 'column' => 28, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 24, 'column' => 5, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 31, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 36, 'column' => 32, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 43, 'column' => 32, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 51, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 53, 'column' => 19, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 55, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 57, 'column' => 45, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 61, 'column' => 32, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 66, 'column' => 39, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 66, 'column' => 44, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 68, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 78, 'column' => 10, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 78, 'column' => 15, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 82, 'column' => 36, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 82, 'column' => 41, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 89, 'column' => 29, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 101, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 102, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 108, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 109, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 119, 'column' => 27, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 120, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 124, 'column' => 26, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * Renaming a variable means rewriting every read and write of it, and for a
 * property or parameter every caller too, so there is no safe mechanical
 * rewrite — matching PHPMD, which does not fix this rule either.
 */
it('reports without offering a fix', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'failing.php');

    expect($file->getErrorCount())->toBe(25)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

/**
 * The message quotes the offending name, sigil included, and the threshold
 * that rejected it — PHPMD's own message text, so a report reads the same in
 * either tool.
 */
it('names the variable and the threshold in the message', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'failing.php');

    $messages = $file->getErrors()[68][9];

    expect($messages[0]['message'])
        ->toBe('Avoid variables with short names like $rr. Configured minimum length is 3.');
});

/**
 * PHPMD's comparison is `strlen($name) >= $threshold` on the name without its
 * sigil, so the boundary sits *at* the minimum: a three-character name passes
 * and a two-character one fails. Driving the same fixture at two thresholds
 * pins both halves — the line asserted at each threshold is the same `$abc`
 * property, so only the comparison can be what changes the outcome.
 */
it('passes a name exactly at the minimum and fails one character shorter', function (): void {
    $atMinimum = analyzeFixture(
        SHORT_VARIABLE,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 3;
        }
    );

    $oneAbove = analyzeFixture(
        SHORT_VARIABLE,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect($atMinimum->getErrors())->toBe([])
        ->and(array_keys($oneAbove->getErrors()))->toContain(25);
});

/**
 * Byte length, not character length. `$añ` is two characters but three bytes,
 * and PHPMD measures it with strlen(), so both tools accept it at the default
 * minimum of three. Raising the minimum to four proves the declaration is
 * reached at all — without that half, a sniff that simply skipped non-ASCII
 * names would pass the first assertion too.
 */
it('measures the name in bytes, as PHPMD does', function (): void {
    $atDefault = analyzeFixture(SHORT_VARIABLE, 'passing.php');

    $raised = analyzeFixture(
        SHORT_VARIABLE,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect(array_keys($atDefault->getErrors()))->not->toContain(29)
        ->and(array_keys($raised->getErrors()))->toContain(29);
});

/**
 * The exceptions list exempts a name whatever its length, and is written
 * without the `$` sigil because PHPMD strips the sigil before comparing.
 * `$ip` is declared twice in failing.php, in two different scopes (36 and
 * 61); both go quiet, which an exemption keyed to the occurrence rather than
 * the name would not do. Line 66 stays reported, so the list exempts what it
 * names rather than switching the rule off.
 */
it('never reports a name in the exceptions list', function (): void {
    $file = analyzeFixture(
        SHORT_VARIABLE,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ip';
        }
    );

    expect(array_keys($file->getErrors()))
        ->not->toContain(36)
        ->not->toContain(61)
        ->toContain(66);
});

/**
 * The sigil is not part of the name PHPMD compares — it calls
 * substr($image, 1) first — so an entry written `$ip`, the way it appears in
 * the source, exempts nothing. Pinned because writing the sigil is the
 * obvious mistake, and a sniff that compared the raw token would silently
 * accept it here and reject the sigil-less spelling the tests above rely on.
 */
it('ignores an exceptions entry written with its sigil', function (): void {
    $file = analyzeFixture(
        SHORT_VARIABLE,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = '$ip';
        }
    );

    expect(array_keys($file->getErrors()))->toContain(36, 61);
});

/**
 * PHPMD explodes the raw property on commas and compares the parts without
 * trimming them, so "ip, tf" exempts `ip` and ` tf` — never `tf`. This sniff
 * reproduces that rather than being kinder, because trimming would exempt
 * names phpmd still reports, and a ruleset that exists to replace phpmd must
 * not fall silent where phpmd speaks.
 *
 * Line 31 is the `$tf` trait field: still reported despite appearing in the
 * property.
 */
it('does not trim the exceptions list, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_VARIABLE,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ip, tf';
        }
    );

    expect(array_keys($file->getErrors()))
        ->toContain(31)
        ->not->toContain(36);
});

/**
 * The exceptions comparison is case-sensitive, matching PHPMD. PHP variable
 * names are case-sensitive too, so `IP` and `ip` are genuinely different
 * variables — but the point of the assertion is the comparison, not the
 * language: a case-insensitive one would exempt line 36 here.
 */
it('matches exceptions case-sensitively, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_VARIABLE,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'IP';
        }
    );

    expect(array_keys($file->getErrors()))->toContain(36, 61);
});

/**
 * PHPCS hands ruleset properties over as raw strings and converts an empty
 * value to null before assigning it. This is what makes the null branch in
 * the sniff reachable from a plain <property name="minimum" value=""/> —
 * asserted here against PHPCS itself, so the fallback below is pinned to real
 * behaviour rather than to an assumption about it.
 */
it('receives null from PHPCS for an empty ruleset property', function (): void {
    [, $ruleset] = buildRuleset([SHORT_VARIABLE], true);
    $sniffClass = $ruleset->sniffCodes[SHORT_VARIABLE];

    $ruleset->setSniffProperty($sniffClass, 'minimum', ['scope' => 'sniff', 'value' => '']);

    expect($ruleset->sniffs[$sniffClass]->minimum)->toBeNull();
})->skip(
    method_exists(Ruleset::class, 'setSniffProperty') === false,
    'This PHPCS release does not expose setSniffProperty().'
);

/**
 * An unusable `minimum` falls back to PHPMD's default rather than being cast.
 * This is the fail-closed half of the property handling: (int) null and
 * (int) 'abc' are both 0, and a threshold of 0 passes every name, so a cast
 * would silently switch the rule off on a typo'd ruleset instead of carrying
 * on enforcing. Every value here therefore has to keep reporting exactly what
 * the default reports.
 *
 * `null` is what PHPCS assigns for value="" (pinned above); the rest are the
 * shapes a hand-edited ruleset produces.
 */
it('falls back to the default minimum when the configured one is unusable', function (mixed $configured): void {
    $file = analyzeFixture(
        SHORT_VARIABLE,
        'failing.php',
        static function (object $sniff) use ($configured): void {
            $sniff->minimum = $configured;
        }
    );

    expect($file->getErrorCount())->toBe(25);
})->with([
    'null (PHPCS empty property)' => [null],
    'empty string' => [''],
    'non-numeric' => ['abc'],
    'zero' => ['0'],
    'negative' => ['-1'],
    'float-ish' => ['2.5'],
]);

/**
 * A usable string threshold is honoured, which is the ordinary ruleset path —
 * PHPCS never assigns an int. Without this, the fallback test above would
 * hold just as well against a sniff that ignored the property entirely.
 */
it('honours a numeric string threshold from a ruleset', function (): void {
    $file = analyzeFixture(
        SHORT_VARIABLE,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = '4';
        }
    );

    expect(array_keys($file->getErrors()))->toContain(25, 29);
});

/**
 * The three contexts PHPMD allows a short name in are keyed to where the name
 * first occurs, not to the name itself. passing.php is silent on `$i`, `$e`
 * and `$v` because each first occurs in a `for` init, a `catch` binding or a
 * `foreach` value; contexts.php writes each one as an ordinary local
 * beforehand, and each is reported there.
 *
 * The pair is what discriminates. The silence alone would hold against a
 * sniff that exempted those spellings outright, and the reports alone would
 * hold against one that had no exemptions at all.
 */
it('exempts a short name only where PHPMD allows the context', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'contexts.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 37, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 50, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * A static property access is not an occurrence at all, rather than an
 * occurrence that is exempt. Line 27 is the property's declaration and line
 * 32 an ordinary local of the same name, declared *after* two `self::$sa`
 * accesses: an implementation that treated the access as an exempt occurrence
 * would have spent the name's one report on it and left line 32 silent.
 */
it('does not count a static property access as an occurrence', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'static-access.php');

    expect(violationTuples($file))->toBe([
        ['line' => 27, 'column' => 19, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 32, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * `$this` is never an occurrence, however it is written — bare, as a member
 * chain's receiver, or interpolated into a string with either syntax.
 *
 * The threshold has to be raised to pin this at all: `this` is four
 * characters, so at the default minimum of 3 it clears the length gate on its
 * own and the exclusion never runs. At 5 — which the `minimum` property
 * explicitly supports — every `$this` in the fixture would be reported
 * without it.
 *
 * The exact list is what discriminates, in two directions. Each of the five
 * controls is short and must still be reported, so a sniff that had given up
 * on the file would fail; and the controls are split across the two places
 * the receiver is dropped — `$ma` and `$so` are ordinary variable tokens,
 * `$si`, `$bi` and `$hi` appear nowhere but inside a string — so dropping
 * `$this` from only one of the two paths leaves the other's `$this` in the
 * list and fails too.
 *
 * phpmd 2.15 at the same threshold reports the same five controls and adds
 * `$this` at 47, 54 and 65 (see the fixture's docblock for why those three and
 * not the other two). This is the one place the sniff is deliberately quieter:
 * PHP forbids assigning `$this`, so the report names nothing a rename could
 * fix.
 */
it('never reports the implicit receiver, however it is written', function (): void {
    $file = analyzeFixture(
        SHORT_VARIABLE,
        'implicit-receiver.php',
        static function (object $sniff): void {
            $sniff->minimum = 5;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 40, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 47, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 54, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 59, 'column' => 16, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 65, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * A trait method's short parameter is reported once. phpmd reports it twice —
 * pdepend hands it over under the trait node and again under the method node,
 * and PHPMD's per-name map is reset between the two. The violation is
 * reported either way; only the duplicate is dropped.
 */
it('reports a trait member once, where PHPMD reports it twice', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'trait-method.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 25, 'column' => 29, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * Everything outside a class-like and outside a named function shares one
 * scope, however many PHP blocks the file has. `$fv` is written in the first
 * block and read in the second, and is reported once; a sniff that gave every
 * open tag its own scope would report it twice, and one that gave the file
 * scope to the *last* tag would miss line 14 and report line 22 instead.
 */
it('gives a file one procedural scope across reopened tags', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'reopened-tags.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 22, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * PHPCS tokenises a multi-line double-quoted string one line at a time, so an
 * interpolated name is reported on the line it is written on — line 21 here,
 * which is the line phpmd reports too. A sniff that reported at the opening
 * quote would still report the right name and the right count, which is why
 * the line is asserted rather than the presence.
 */
it('reports an interpolated name on its own line', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'multiline-string.php');

    expect(violationTuples($file))->toBe([
        ['line' => 21, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * A method declared without a parameter list gives PHPCS no
 * `parenthesis_opener` to bound it with, so there is nothing telling the
 * sniff which tokens the declaration owns. It refuses to guess and says
 * nothing about that declaration — including about the `$bv` local inside it.
 *
 * The assertion needs both halves. `ok($op)` on line 26 is well-formed and
 * its parameter is short, so it must still be reported — without it, a sniff
 * that had simply given up on the whole file would satisfy the silence on the
 * malformed declaration just as well.
 */
it('refuses to read a declaration it cannot bound', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'malformed-declaration.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 24, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * The four shapes this sniff reports and phpmd does not: procedural code
 * (23), a name whose first occurrence sits inside a `->` or `::` chain (35,
 * 36, 38), a name declared in a catch block's body (50), and the field,
 * parameter and local of an anonymous class's method (65, 67, 69).
 *
 * Verified against a live phpmd 2.15 run from a cold cache: it reports
 * nothing on this fixture, at any threshold, while every name below is a real
 * violation of the rule as PHPMD states it.
 */
it('reports shapes that PHPMD cannot see or exempts wholesale', function (): void {
    $file = analyzeFixture(SHORT_VARIABLE, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 1, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 35, 'column' => 30, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 36, 'column' => 25, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 38, 'column' => 44, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 50, 'column' => 13, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 65, 'column' => 12, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 67, 'column' => 26, 'source' => SHORT_VARIABLE_TOO_SHORT],
        ['line' => 69, 'column' => 9, 'source' => SHORT_VARIABLE_TOO_SHORT],
    ]);
});

/**
 * The whole point of the sniff: running the master ruleset the package ships
 * covers the rule, so `phpmd` no longer has to run for it. The assertion is
 * scoped to this sniff's own source so that unrelated additions to rules.xml
 * cannot break it — the fixture trips other standards too.
 */
it('reports through the master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('ShortVariableSniff', 'failing.php'));

    $lines = array_keys(array_filter(
        allViolationSourcesByLine($file),
        static fn (array $sources): bool => in_array(SHORT_VARIABLE_TOO_SHORT, $sources, true)
    ));

    expect($lines)->toBe([
        22, 24, 31, 36, 43, 51, 53, 55, 57, 61, 66,
        68, 78, 82, 89, 101, 102, 108, 109, 119, 120, 124,
    ]);
});

/**
 * interpolatedNames() reads the variable names out of a string literal so a
 * short one interpolated there is reported like any other. A failed read that
 * is not guarded returns `$matches[1]` off an untouched $matches — null here —
 * from a method that declared it returns an array.
 *
 * The empty list is the guard's exit: a name that was not read cannot be
 * checked, so the short names inside that string go unreported. The fixture's
 * interpolated reports disappearing is that exit made visible.
 */
it('reports no interpolated name when the string cannot be read', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(SHORT_VARIABLE, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_match_all',
            static fn (): array => violationSourcesByLine(analyzeFixture(SHORT_VARIABLE, 'failing.php')->getErrors()),
            static fn (string $pattern): bool => str_contains($pattern, '\$\{?([a-zA-Z_')
        );
    });

    expect(array_keys($expected))->toContain(101)
        ->and(array_keys($degraded))->not->toContain(101)
        ->and($diagnostics)->toBe([]);
});

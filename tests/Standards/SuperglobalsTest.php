<?php

/**
 * Tests the custom CleanCode.Controversial.Superglobals sniff, which replicates
 * PHPMD's Controversial/Superglobals rule
 * (docs/phpmd/controversial-superglobals.md).
 *
 * Every line asserted below was measured against a live PHPMD 2.15.0 run over
 * the same fixtures, not read off PHPMD's documentation. On failing.php,
 * `phpmd <fixture> text controversial` reports the same twenty-six accesses
 * under the same twenty-six names; on passing.php its Superglobals rule stays
 * silent. divergences.php holds every shape where the two tools disagree — in
 * both directions — so the parity claim covers the whole of both floor
 * fixtures rather than most of them.
 *
 * The rule is detection-only, matching PHPMD: swapping a superglobal read for
 * an injected request abstraction depends on which abstraction the project uses
 * and on what the value is for. There is no autofixed fixture, and the
 * detection-only test pins that.
 */

declare(strict_types=1);

const SUPERGLOBALS = 'CleanCode.Controversial.Superglobals';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SUPERGLOBALS);
});

/**
 * passing.php is the sniff's whole silence contract, and every block in it is a
 * separate verdict rather than merely the absence of a superglobal:
 *
 * - Two member declarations named `$_GET` and `$_POST`. They declare a
 *   property; nothing reads the superglobal. This is one of the two reasons
 *   SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable could not be
 *   wired in instead — it reports both, and PHPMD reports neither.
 * - `$request->_GET` and `$request?->_SERVER`. PHPCS demotes the name after an
 *   object operator to an identifier, so the sniff never sees a variable. This
 *   fixture is what pins that tokenizer behaviour, since the sniff carries no
 *   guard for it.
 * - `$_get` and `$_post`. PHP variable names are case sensitive and so is the
 *   rule, which is why the sniff compares content without normalising case.
 * - `$_GETTER` and `$_ENVIRONMENT`, bare and interpolated. A name that merely
 *   starts with a superglobal's spelling must not match — the bare form is
 *   covered by an exact comparison, the interpolated form by the pattern's
 *   trailing word boundary, and only the interpolated pair can go wrong
 *   silently, so both spellings are here.
 * - A single-quoted string, an escaped `\$_POST`, and a nowdoc. None of the
 *   three interpolates, so none reads a superglobal.
 * - A heredoc that interpolates an ordinary variable, so the heredoc branch is
 *   exercised on this fixture and has to come back clean rather than being
 *   silent for want of anything to walk.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The exact position of every violation in failing.php.
 *
 * Asserting the column as well as the line matters: a bare access is reported
 * on the variable token itself, so a rewrite that reported the enclosing
 * function instead — which is where PHPMD reports — would keep plausible line
 * numbers and lose every position inside a multi-statement body.
 *
 * Lines 42-48 are the interpolated accesses. Line 44 carries two, because one
 * string holds two superglobals; both are reported at the string's own column,
 * since PHPCS hands the sniff the string as a single token. Lines 47 and 48 are
 * the two heredoc lines, and their separate line numbers are the point: PHPCS
 * emits one T_HEREDOC token per line, which is what keeps a multi-line heredoc
 * from collapsing onto the line the `<<<` sits on.
 *
 * Line 67 is the backtick operator, which interpolates exactly as a
 * double-quoted string does but which PHPCS hands over as a plain variable
 * rather than as part of a string token. It is here because it looks like a
 * string case and is not one: it reaches the sniff by the bare-variable path,
 * so no amount of coverage on the two string tokens would exercise it.
 */
it('flags every superglobal access at its own position', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    $positions = array_map(
        static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
        violationTuples($file)
    );

    expect($positions)->toBe([
        [13, 9],
        [14, 9],
        [15, 9],
        [16, 9],
        [17, 9],
        [18, 9],
        [19, 9],
        [20, 9],
        [21, 9],
        [28, 9],
        [29, 9],
        [30, 9],
        [31, 9],
        [32, 9],
        [33, 9],
        [34, 9],
        [42, 17],
        [43, 19],
        [44, 27],
        [44, 27],
        [45, 30],
        [47, 1],
        [48, 1],
        [56, 30],
        [57, 33],
        [67, 20],
    ]);
});

/**
 * The sixteen names PHPMD 2.15.0 carries in its own $superglobals list, each
 * named in the message rather than merely counted. The nine PHP 8 superglobals
 * are the obvious half; the seven PHP 4 long-form aliases are the half that
 * SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable omits entirely,
 * and omitting them is the other reason it could not be wired in instead of
 * this sniff. Without this assertion a sniff that had quietly dropped the
 * aliases would still satisfy the position test above, since failing.php's
 * alias block would simply report nothing and the remaining lines would shift
 * out of the expectation together.
 */
it('flags every name PHPMD carries, aliases included', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    $named = [];

    foreach ($file->getErrors() as $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $named[] = explode(' ', $violation['message'])[1];
            }
        }
    }

    $named = array_values(array_unique($named));
    sort($named);

    expect($named)->toBe([
        '$GLOBALS',
        '$HTTP_COOKIE_VARS',
        '$HTTP_ENV_VARS',
        '$HTTP_GET_VARS',
        '$HTTP_POST_FILES',
        '$HTTP_POST_VARS',
        '$HTTP_SERVER_VARS',
        '$HTTP_SESSION_VARS',
        '$_COOKIE',
        '$_ENV',
        '$_FILES',
        '$_GET',
        '$_POST',
        '$_REQUEST',
        '$_SERVER',
        '$_SESSION',
    ]);
});

/**
 * The escape guard, isolated. failing.php line 45 is
 * `"live $_COOKIE, literal \$_SESSION"` — one live interpolation beside one
 * cancelled by a backslash. The string reaches the sniff as a single
 * T_DOUBLE_QUOTED_STRING only because the live half is there, so this is the
 * one shape where dropping the guard would not simply be caught by the count:
 * a pattern without it reports `$_SESSION` here as well, and PHPMD does not.
 */
it('ignores an escaped superglobal beside a live one', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');
    $messages = array_column($file->getErrors()[45][30], 'message');

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toStartWith('Superglobal $_COOKIE ');
});

/**
 * Every violation carries the one message code, so a consuming ruleset can
 * silence the rule with a single `<exclude>`.
 */
it('reports every violation under one message code', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    $sources = array_unique(array_column(violationTuples($file), 'source'));

    expect($sources)->toBe([SUPERGLOBALS . '.Found']);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The shapes where this sniff is deliberately stricter than PHPMD, kept apart
 * from failing.php so that fixture can claim exact parity.
 *
 * PHPMD 2.15.0's Superglobals rule reports nothing on any of these five lines:
 *
 * - Lines 18, 19, 21 and 22 are file scope. PHPMD's rule is MethodAware and
 *   FunctionAware, so it inspects function and method bodies only. Line 19 is
 *   a long-form alias and line 21 sits in an `if` condition rather than an
 *   assignment, so the block is not four spellings of one shape.
 * - Line 34 is `"id is ${_POST} here"`. PDepend does not model the `${name}`
 *   interpolation form, so PHPMD misses it; PHP reads the superglobal all the
 *   same. It is the only fixture anywhere that exercises the pattern's
 *   optional brace, so removing the brace leaves this expectation short.
 *
 * All five are true superglobal reads, so they are kept rather than suppressed
 * for parity — the posture rules.xml already takes for the extra
 * VariableAnalysis and DisallowExitExpression reports.
 */
it('reports the shapes PHPMD misses', function (): void {
    $file = analyzeFixture(SUPERGLOBALS, 'divergences.php');

    $positions = array_map(
        static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
        violationTuples($file)
    );

    expect($positions)->toBe([
        [18, 19],
        [19, 20],
        [21, 11],
        [22, 25],
        [34, 12],
    ]);
});

/**
 * The one shape where this sniff is deliberately narrower than PHPMD, and the
 * other half of divergences.php.
 *
 * The fixture line is `[self::$_POST, static::$_POST, StaticHolder::$_POST]`.
 * PHPMD reports all three, because PDepend matches a variable's image without
 * looking left; `::` binds the name to a static property of the named class, so
 * no superglobal is reached and all three reports are false positives. The
 * rule's acceptance criteria require this sniff not to false-positive on a
 * non-superglobal variable, so the false positives are not replicated. All
 * three spellings of the class reference are asserted because the sniff's guard
 * looks at the `::` rather than at what precedes it.
 *
 * The line number is located in the fixture rather than written out, because a
 * hard-coded number silently stops discriminating the moment an edit above it
 * shifts the fixture — the assertion would then hold on a line that never had a
 * violation either way, and the guard could be deleted with this test still
 * green.
 *
 * Asserting the whole file is empty would pass against a sniff that had fallen
 * silent, which is why the stricter test above shares this fixture: between
 * them the two assertions pin that the sniff is silent *here specifically*
 * while still reporting five other lines of the same file.
 */
it('stays silent on a static property access', function (): void {
    $fixture = file(fixturePath(sniffFixtureDirectory(SUPERGLOBALS), 'divergences.php'));
    $staticAccessLines = array_keys(array_filter(
        $fixture,
        static fn (string $line): bool => str_contains($line, 'StaticHolder::$_POST')
    ));

    expect($staticAccessLines)->toHaveCount(1);

    $staticAccessLine = ($staticAccessLines[0] + 1);
    $file = analyzeFixture(SUPERGLOBALS, 'divergences.php');

    expect(array_column(violationTuples($file), 'line'))->not->toContain($staticAccessLine);
});

<?php

/**
 * Tests the custom CleanCode.Naming.ShortClassName sniff (PHPMD Naming:
 * ShortClassName, #103). Fixtures live in
 * tests/fixtures/ShortClassNameSniff/.
 *
 * The sniff replicates PHPMD's rule: a class-like declaration whose
 * unqualified name is shorter than `minimum` bytes, and is not on the
 * `exceptions` list, is reported on its declaration keyword.
 *
 * The rule is detection-only. Renaming a type rewrites every reference to it
 * and, under PSR-4, the file it lives in, so there is no autofixed fixture —
 * PHPMD offers no fix either.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const SHORT_CLASS_NAME = 'CleanCode.Naming.ShortClassName';

const SHORT_CLASS_NAME_ERROR = SHORT_CLASS_NAME . '.TooShort';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SHORT_CLASS_NAME);
});

/**
 * The compliant fixture pins the threshold's passing side and every near-miss
 * shape the sniff must stay silent on:
 *
 * - lines 7, 11, 15, and 19 declare an interface, trait, enum, and class whose
 *   names are exactly three bytes — the boundary. `minimum` is a *reporting*
 *   threshold, so a name of exactly that length passes, matching PHPMD's
 *   `strlen($name) >= $threshold` early return. Their two-byte counterparts in
 *   failing.php are the other half of the boundary pair.
 * - line 3, `namespace Ab;` — the namespace is two bytes while the class it
 *   holds is not. The name measured is the unqualified one, so a short
 *   namespace is never a violation.
 * - line 5, `use App\Fo;` — an import of a two-byte class name. Only a
 *   declaration is measured, not a reference to one declared elsewhere.
 * - line 25, `new class () {}` — an anonymous class. PHPCS gives it its own
 *   T_ANON_CLASS token, which the sniff does not register.
 * - line 28, `Fo::of($ab)`, and line 30, `Fo::class` — a two-byte class used,
 *   not declared. Both spell the name as a T_STRING, never as a declaration.
 * - line 23, `public function fo()` — a two-byte *method* name. The rule
 *   speaks about types; short method and variable names are PHPMD's separate
 *   ShortMethodName and ShortVariable rules.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every class-like keyword PHPMD's rule speaks about, each declared with a
 * name below the shipped threshold of three, and each reported once on its own
 * declaration keyword:
 *
 * - line 3, `class Fo`
 * - line 7, `interface Ab`
 * - line 11, `trait Tr`
 * - line 15, `enum En`
 * - line 19, `class X` — one byte, well under the threshold rather than one
 *   below it.
 *
 * PHPMD's rule declares ClassAware, InterfaceAware, TraitAware, and EnumAware,
 * so all four are its subject even though the rule name and the phpmd.org
 * description mention only classes and interfaces. Dropping any one of them
 * from the sniff's register() would make this ruleset looser than the tool it
 * replaces, and turns this test red.
 *
 * The column is 1 for each because the report is attached to the declaration
 * keyword, and each declaration starts its line.
 */
it('flags every class-like declaration under the threshold', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 3, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 7, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 11, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 15, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
            ['line' => 19, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
        ]);
});

/**
 * The message names the offending type and the threshold it fell under, so a
 * report over a whole codebase says which name to change and what to change it
 * to. The wording is PHPMD's own message for this rule, so a project moving
 * off `phpmd` reads the same sentence.
 */
it('names the class and the configured threshold in the message', function (): void {
    $errors = analyzeFixture(SHORT_CLASS_NAME, 'failing.php')->getErrors();

    expect($errors[3][1][0]['message'])
        ->toBe('Avoid classes with short names like Fo. Configured minimum length is 3.');
});

/**
 * Pins the detection-only decision: renaming a type is a refactor across every
 * reference to it, so no violation carries a fixer hook.
 */
it('reports detection-only errors', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'failing.php');

    expect($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false, false, false]);
});

/**
 * The threshold is a public sniff property, so a project that wants longer
 * names can raise it. One fixture pins both directions: its five classes are
 * three and four bytes long, so all five pass under the shipped default of
 * three, and the four three-byte ones are reported once the threshold is
 * raised to four. A property that was ignored would leave both runs identical
 * and fail the second assertion.
 *
 * `Http` on line 15 stays silent in both runs — at four bytes it is at the
 * raised boundary, which passes for the same reason three bytes passes under
 * the default.
 */
it('exposes a configurable minimum', function (): void {
    expect(analyzeFixture(SHORT_CLASS_NAME, 'exceptions.php')->getErrors())->toBe([]);

    $raised = analyzeFixture(
        SHORT_CLASS_NAME,
        'exceptions.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect(violationSourcesByLine($raised->getErrors()))->toBe([
        3 => [SHORT_CLASS_NAME_ERROR],
        7 => [SHORT_CLASS_NAME_ERROR],
        11 => [SHORT_CLASS_NAME_ERROR],
        19 => [SHORT_CLASS_NAME_ERROR],
    ]);
});

/**
 * The exceptions list exempts a name however short it is. This run differs
 * from the raised-threshold run above by the `exceptions` property alone, so
 * the three names it lists — `Log` (line 3), `URL` (line 7), `FTP` (line 11) —
 * go silent while everything else holds.
 *
 * `Ftp` on line 19 is the discriminator, and it is still reported: matching is
 * case-sensitive, as PHPMD's `array_flip()` + `isset()` lookup is, so a
 * differently-cased spelling of a listed name is not on the list.
 *
 * The list is written with stray spaces around its entries on purpose. PHPMD
 * reads its own list through Strings::splitToList(), which trims each entry;
 * an implementation that split on the comma alone would compare `' URL '` and
 * report line 7. The empty-entry half of splitToList() is matched too, but has
 * no fixture: a declaration's name is never the empty string, so dropping
 * empty entries cannot change what is reported either way.
 *
 * The threshold is raised for this run because PHPMD's example exceptions are
 * all exactly three bytes and so already pass under the shipped minimum of
 * three — the list only starts to matter once a project asks for longer names.
 */
it('honors the exceptions list, case-sensitively', function (): void {
    $file = analyzeFixture(
        SHORT_CLASS_NAME,
        'exceptions.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
            $sniff->exceptions = 'Log, URL , FTP';
        }
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        19 => [SHORT_CLASS_NAME_ERROR],
    ]);
});

/**
 * The name is measured in *bytes*, not characters — the sniff's one load-bearing
 * design decision, and PHPMD's own, since PHPMD compares with `strlen()` too.
 * The two counts disagree on any non-ASCII identifier, which is legal PHP
 * (`[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*`), so this fixture straddles the
 * shipped `minimum` of 3 in both directions at once:
 *
 * | Line | Name  | Bytes | Characters | Byte verdict | Character verdict |
 * | ---- | ----- | ----- | ---------- | ------------ | ----------------- |
 * | 3    | `Aé`  | 3     | 2          | passes       | would be reported |
 * | 7    | `類`  | 3     | 1          | passes       | would be reported |
 * | 11   | `Δ`   | 2     | 1          | reported     | would be reported |
 * | 15   | `Ünï` | 5     | 3          | passes       | passes            |
 *
 * Only line 11 is reported, which is what pins the decision: swapping
 * `strlen()` for `mb_strlen()` in the sniff adds lines 3 and 7 and turns this
 * test red. Lines 3 and 7 are the discriminators — a name at exactly the
 * threshold in bytes but under it in characters. Line 11 is the other half of
 * the pair: it proves a multibyte name is measured rather than skipped, so the
 * silence on lines 3 and 7 is a verdict and not an exemption. Line 15 clears
 * both counts and holds the passing side.
 *
 * Byte-counting can only ever report a subset of what character-counting
 * reports, because a UTF-8 name is never fewer bytes than characters — so the
 * reverse discriminator (reported by bytes, silent by characters) does not
 * exist and no fixture can cover it.
 *
 * A live PHPMD 2.15.0 run over this same fixture reports line 11 and nothing
 * else, with the same message text, so the byte semantics are parity with the
 * tool this sniff replaces rather than a local choice.
 *
 * The message assertion is the second half: the offending name is rendered
 * through the byte path intact, not truncated mid-sequence into mojibake.
 */
it('measures a name in bytes, not characters', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'multibyte.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 11, 'column' => 1, 'source' => SHORT_CLASS_NAME_ERROR],
        ])
        ->and($file->getErrors()[11][1][0]['message'])
        ->toBe('Avoid classes with short names like Δ. Configured minimum length is 3.');
});

/**
 * A `class` keyword with no name after it — what PHPCS hands a sniff for a
 * file caught mid-edit. There is no name to measure, so the sniff passes over
 * the file rather than reporting a nameless violation: without the guard,
 * `strlen(null)` evaluates to 0, falls under every threshold, and this fixture
 * earns an error whose message names nothing at all.
 */
it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(SHORT_CLASS_NAME, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

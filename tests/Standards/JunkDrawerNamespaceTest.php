<?php

/**
 * Tests the custom CleanCode.ClearCode.JunkDrawerNamespace sniff (Clear Code:
 * Encapsulate Related Classes in a Domain, #16, partial enforcement per #190).
 * Fixtures live in tests/fixtures/JunkDrawerNamespaceSniff/: the domain,
 * framework-layer and near-miss namespaces in passing.php, every discouraged
 * default in failing.php, the property-configurability pair in configured.php,
 * and the end-of-file truncation in unterminated-namespace.php. The rule is
 * detection-only, so there is no autofixed fixture.
 *
 * Every fixture uses braced namespace blocks. The sniff's whole subject is the
 * namespace declaration, and one file can only carry more than one of those in
 * the braced spelling.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const JUNK_DRAWER_NAMESPACE = 'CleanCode.ClearCode.JunkDrawerNamespace';

const JUNK_DRAWER_NAMESPACE_WARNING = JUNK_DRAWER_NAMESPACE . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(JUNK_DRAWER_NAMESPACE);
});

/**
 * The compliant namespaces and every near-miss shape stay silent. Each group
 * pins one of the sniff's exits, and a false positive on any of them makes the
 * rule unusable in a real application:
 *
 * - line 5, `App\Billing` — a namespace naming a real-world functional block,
 *   which is what the standard asks for.
 * - line 6, `use App\Helpers\Formatter;` — an *import* of a junk-drawer
 *   namespace. The standard is about where a class is filed, not about what it
 *   references, and the sniff registers on T_NAMESPACE alone. Without that the
 *   fix would be to stop consuming the bucket rather than to dissolve it.
 * - line 8, `class Helpers` — a discouraged name as a *class* name. The sniff
 *   reads namespace segments only; a class may legitimately be called Helpers.
 * - line 12, `namespace\Helpers\present(...)` — the relative-name operator,
 *   which tokenises as T_NAMESPACE too. It declares nothing, and reading it as
 *   a declaration would make the sniff report on function calls. The `Helpers`
 *   qualifier is what makes this discriminating: with the operator read as a
 *   declaration, the name behind it is a discouraged segment and the line
 *   reports.
 * - lines 17, 23 and 26, `App\Http\Controllers`, `App\Models` and
 *   `App\Providers` — framework-mandated technical layers. Flagging them would
 *   fight the framework rather than enforce the standard, so they are
 *   deliberately absent from the shipped list.
 * - lines 29, 32 and 35, `App\HelperRegistry`, `App\Uncommon` and
 *   `App\Generals` — segments that contain, extend or paraphrase a discouraged
 *   name. The comparison is whole-segment; degrading it into a substring match
 *   flags all three.
 * - line 38, `namespace { }` — the global namespace block, which names no
 *   segments at all.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every discouraged namespace is flagged at its declaration.
 *
 * - lines 5, 8, 11, 14, 17 and 20 — the six shipped defaults: `Helpers`,
 *   `Utils`, `Utilities`, `Misc`, `Common` and `General`. Line 11
 *   (`App\Support\Utilities`) also pins that the offending segment need not be
 *   the second one: the whole name is scanned, not a fixed position.
 * - lines 23 and 26, `App\helpers` and `App\UTILS` — the comparison is
 *   case-insensitive, in both directions from the shipped spelling.
 * - line 29, `Helpers\Billing` — the offending segment as the *root* of the
 *   name. A scan that skipped the first segment as "the vendor" misses it.
 * - line 32, `App\Helpers\Utils` — two discouraged segments in one name report
 *   twice, so the developer is told about both rather than the first only.
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            5 => [JUNK_DRAWER_NAMESPACE_WARNING],
            8 => [JUNK_DRAWER_NAMESPACE_WARNING],
            11 => [JUNK_DRAWER_NAMESPACE_WARNING],
            14 => [JUNK_DRAWER_NAMESPACE_WARNING],
            17 => [JUNK_DRAWER_NAMESPACE_WARNING],
            20 => [JUNK_DRAWER_NAMESPACE_WARNING],
            23 => [JUNK_DRAWER_NAMESPACE_WARNING],
            26 => [JUNK_DRAWER_NAMESPACE_WARNING],
            29 => [JUNK_DRAWER_NAMESPACE_WARNING],
            32 => [JUNK_DRAWER_NAMESPACE_WARNING, JUNK_DRAWER_NAMESPACE_WARNING],
        ]);
});

/**
 * The report severity is the standard's own judgement, not an incidental
 * detail: a small library may legitimately keep one `Helpers` bucket, so the
 * sniff points at a domain-grouping candidate rather than mandating a move.
 * Asserted as a property of the sniff's whole output — every message it raised
 * on the failing fixture arrived through addWarning(), and none through
 * addError() — so a single detection switched to error severity fails here.
 */
it('reports at warning severity, never error', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(11)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * Each warning is reported at the namespace name rather than at the `namespace`
 * keyword, so an editor's inline marker sits under the construct that has to
 * change. Line 32 carries both of its warnings at that one column: the name is
 * a single construct and the fix rewrites all of it, so the two are told apart
 * by the segment their message names, not by position.
 */
it('reports at the namespace name rather than the keyword', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 5, 'column' => 11, 'source' => JUNK_DRAWER_NAMESPACE_WARNING])
        ->toContain(['line' => 29, 'column' => 11, 'source' => JUNK_DRAWER_NAMESPACE_WARNING])
        ->and(array_keys($file->getWarnings()[32]))->toBe([11]);
});

/**
 * The message names both the whole namespace and the one segment that offends
 * it, so a developer reading the report knows which name to change and which
 * part of it is wrong, and points at the standard it enforces. The
 * `App\helpers` and `App\UTILS` messages are asserted
 * because the comparison is case-insensitive while the message is not: the
 * source spelling has to survive into the output rather than the lowercased
 * copy the check works from. Line 32 pins that two segments in one name produce
 * two *different* messages rather than the same one twice.
 */
it('names the namespace and the offending segment in the message', function (): void {
    $warnings = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'failing.php')->getWarnings();

    expect($warnings[5][11][0]['message'])
        ->toContain('Namespace App\\Helpers')
        ->toContain('the "Helpers" segment')
        ->toContain('Group related classes into a domain namespace')
        ->toContain('docs/standards/clear-code-encapsulate-related-classes-in-a-domain.md')
        ->and($warnings[23][11][0]['message'])->toContain('the "helpers" segment')
        ->and($warnings[26][11][0]['message'])->toContain('the "UTILS" segment')
        ->and($warnings[32][11][0]['message'])->toContain('the "Helpers" segment')
        ->and($warnings[32][11][1]['message'])->toContain('the "Utils" segment')
        ->and($warnings[32][11][1]['message'])->toContain('Namespace App\\Helpers\\Utils');
});

/**
 * The discouraged-segment list is a public sniff property, as the standard's
 * doc advertises, and a consuming project can replace it, extend it, or empty
 * it. One fixture pins every direction: under the shipped defaults
 * `App\Helpers` (line 5) warns while `App\Widgets` (line 8) does not, and each
 * retuning moves that verdict. A property that was ignored would leave all four
 * runs identical.
 */
it('exposes a configurable discouraged-segment list', function (): void {
    $shipped = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'configured.php');

    expect(array_keys($shipped->getWarnings()))->toBe([5]);

    $replaced = analyzeFixture(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        static function (object $sniff): void {
            $sniff->discouragedSegments = ['Widgets'];
        }
    );

    expect(array_keys($replaced->getWarnings()))->toBe([8]);

    $extended = analyzeFixture(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        static function (object $sniff): void {
            $sniff->discouragedSegments = ['Helpers', 'Widgets'];
        }
    );

    expect(array_keys($extended->getWarnings()))->toBe([5, 8]);

    $emptied = analyzeFixture(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        static function (object $sniff): void {
            $sniff->discouragedSegments = [];
        }
    );

    expect($emptied->getWarnings())->toBe([]);
});

/**
 * The same override, arriving the way a consuming project's ruleset.xml
 * actually delivers it — through Ruleset::setSniffProperty(), which is what
 * parsing a `<property name="discouragedSegments" type="array">` element calls.
 * Distinct from the callback above, which assigns the property directly: only
 * this path proves the sniff is configurable in XML rather than merely
 * writable from PHP.
 */
it('accepts the list from a consuming ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        JUNK_DRAWER_NAMESPACE,
        'configured.php',
        ['discouragedSegments' => ['Helpers', 'Widgets']]
    );

    expect(array_keys($file->getWarnings()))->toBe([5, 8]);
});

/**
 * The everyday spelling: one semicolon-terminated declaration per file, which
 * is what an application actually writes and what every other fixture here
 * gives up to carry more than one namespace at a time. The two spellings end
 * the name on different tokens, so neither stands in for the other — reading
 * the name to the `{` only leaves this file reporting nothing.
 */
it('flags a semicolon-terminated declaration', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'unbraced.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 5, 'column' => 11, 'source' => JUNK_DRAWER_NAMESPACE_WARNING],
        ])
        ->and($file->getWarnings()[5][11][0]['message'])->toContain('Namespace App\\Helpers');
});

/**
 * A file whose last token is the `namespace` keyword itself — a truncated or
 * mid-edit file, which PHPCS still tokenises and hands to every sniff. There is
 * no name after it to read, and the sniff has to say nothing rather than index
 * past the end of the token stack.
 */
it('stays silent on a namespace declaration with no name after it', function (): void {
    $file = analyzeFixture(JUNK_DRAWER_NAMESPACE, 'unterminated-namespace.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

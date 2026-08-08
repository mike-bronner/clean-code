<?php

/**
 * Tests the custom CleanCode.Controllers.ManualModelResolution sniff
 * (Controllers: Route Model Binding, #50). Fixtures live in
 * tests/fixtures/ManualModelResolutionSniff/: the compliant action and every
 * near-miss shape in passing.php, the flagged resolutions in failing.php, the
 * nested scopes in nested-scopes.php — the two accepted false positives and
 * the nested named functions that stay silent — and a mid-edit file in
 * unterminated.php. The rule is detection-only, so there is no autofixed
 * fixture — replacing a manual lookup with a bound parameter also means
 * changing the route definition in another file, which no fixer can do.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml. No path scoping is needed for this
 * sniff — it only ever speaks inside a *Controller class — so the fixtures
 * are processed where they live.
 */

declare(strict_types=1);

const MANUAL_RESOLUTION = 'CleanCode.Controllers.ManualModelResolution';

const MANUAL_RESOLUTION_WARNING = MANUAL_RESOLUTION . '.Found';

// Flattens a fixture's warnings to line => column => source, so the exact
// report position is asserted rather than only the line. The shared
// violationTuples() helper reads the error list, which is empty for a
// warning-only sniff. A closure rather than a function so this file declares
// no symbols alongside its side effects, which PSR-12 forbids.
$warningsByPosition = static function (string $fixture): array {
    $map = [];

    foreach (analyzeFixture(MANUAL_RESOLUTION, $fixture)->getWarnings() as $line => $columns) {
        foreach ($columns as $column => $messages) {
            foreach ($messages as $message) {
                $map[$line][$column] = $message['source'];
            }
        }
    }

    return $map;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MANUAL_RESOLUTION);
});

/**
 * The compliant action and every near-miss stay silent — a false positive on
 * any of them makes the rule unusable. Most groups pin one of the sniff's
 * early returns, confirmed by deleting that check and watching this fixture
 * start reporting; the ones that instead fall out of a *later* check say so
 * rather than implying coverage they do not carry:
 *
 * - line 7, `show(User $user)` — the shape the standard mandates. Nothing to
 *   resolve, so nothing to report.
 * - line 14, `User::find(1)` — a literal is not a parameter.
 * - lines 19 and 24, `User::find($this->userId)` and
 *   `User::find($request->id)` — a property read opens with a variable, so
 *   only the "the token after the variable ends the argument" check separates
 *   them from a bare parameter. Line 24 is what pins that check: `$request`
 *   *is* one of the method's parameters, so without it the property read
 *   would be flagged as the parameter itself. Line 19 covers the `$this`
 *   spelling, which the parameter-name check would also reject.
 * - line 31, `User::find($key)` — a local variable computed in the body. It
 *   is not one of the method's parameters, so it cannot have come from a
 *   route segment.
 * - lines 36 and 41, `User::where(...)->first()` and `User::query()->find()`
 *   — a query-builder chain and an instance-side finder. `where` and `query`
 *   are not resolution methods, and the `->find()` in line 41 carries an
 *   object operator rather than `::`, which the sniff never registers on.
 * - line 46, `$this->repository->find($id)` — the repository-pattern call.
 *   Same reason as line 41.
 * - lines 51-53, `findMany`, `findOr`, `first` — names that extend or
 *   paraphrase a resolution method.
 * - lines 54-55, `User::$connection` and `User::{$this->finder}($id)` — a
 *   static property and a dynamic method name. Both are silent through the
 *   name comparison alone, because no token other than a T_STRING can carry
 *   the content `find`: the sniff's T_STRING check on the member is a type
 *   guard, not a behavioural branch, and deleting it changes no result here.
 *   Stated plainly rather than left to imply coverage.
 * - lines 56 and 58, `resolveWith(User::Find, $id)` and `return User::Find;`
 *   — a class constant, not a call. Line 56 is what pins the
 *   "next token opens a parenthesis" check: it puts a comma, a parameter and
 *   a closing parenthesis after the constant, which is exactly the token run
 *   an argument list would produce, so without the check the constant fetch
 *   is read as `Find($id)` and flagged.
 * - line 63, `$model::find($id)` — a variable class name. The receiver has to
 *   be a class *name*; a variable one cannot be resolved to a model.
 * - lines 68-70, `self::`, `static::` and `parent::` — relative scopes, which
 *   tokenize as T_SELF/T_STATIC/T_PARENT rather than T_STRING. A controller
 *   resolving through its own scope is not resolving a model.
 * - line 72, `User::find(...)` — first-class callable syntax. The `...` is
 *   not a parameter, so nothing is being resolved yet. Like lines 54-55 this
 *   is silent through a later check: the sniff's "first argument is a
 *   T_VARIABLE" check is a type guard, since no other token can carry a
 *   parameter's `$name` as its content, and deleting it changes no result.
 * - lines 77 and 82, `User::find(id: 1)` and `User::find(id: $this->userId)`
 *   — the literal and the property read again, this time behind a named
 *   argument. Stepping over the label does not weaken either check: what
 *   follows the label still has to be a bare variable, and a bare variable
 *   still has to name a parameter.
 * - line 87, `User::find(columns: ['*'], id: $id)` — the accepted false
 *   negative that comes with reading only the *first* argument. Named
 *   arguments may be given in any order, so a resolution can be moved out of
 *   first position; the sniff reads the first one, finds an array literal and
 *   stops. Pinned here so the boundary the standard's doc states is the one
 *   the sniff actually has.
 * - lines 92 and 97, protected and private methods — a route never binds into
 *   one, so a lookup there is ordinary code.
 * - lines 105, 111 and 115, the same call in a non-`Controller` class, a
 *   plain function and a file-scope closure — outside a controller action
 *   there is no route parameter to have bound.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MANUAL_RESOLUTION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every manual resolution is flagged once, at the finder's own line and
 * column:
 *
 * - lines 10 and 15, `find` and `findOrFail` — both shipped finders.
 * - line 20, `User::FIND($id)` — PHP method names are case-insensitive, so
 *   the comparison is too.
 * - line 25, a method with no visibility keyword — implicitly public, and
 *   `getMethodProperties()` reports it as such.
 * - line 30, `Models\User::find($id)` — a qualified class name. The token
 *   before `::` is still the trailing T_STRING, and the column moves with it.
 * - line 35, `User::find($id, ['name'])` — Eloquent's second `$columns`
 *   argument. Only the first argument decides, so the extra one is ignored
 *   rather than silencing the report.
 * - line 40, a resolution from the *second* parameter — the sniff matches any
 *   of the method's parameters by name, not just the first.
 * - line 45, a call nested inside an array literal — the report position
 *   follows the finder, not the statement.
 * - line 51, `findOrFail($id)` inside a closure that inherits `$id` through
 *   `use` — the parameter really is the action's, so this is a true positive
 *   and the reason parameters are read from the enclosing named method rather
 *   than from the closure.
 * - line 57, `User::find(id: $id)` — the same resolution written with a named
 *   argument. The label is a separate token run (T_PARAM_NAME, T_COLON) in
 *   front of the very same variable, so it is stepped over rather than read
 *   as the argument.
 * - line 62, `User::findOrFail(id: $id, columns: ['name'])` — the named form
 *   in a call that carries a second argument, so the label skip is shown to
 *   land on the variable rather than run on to the next label. It does not
 *   pin the comma branch of the "token after the variable ends the argument"
 *   check: line 35 already covers that branch, and deleting `T_COMMA` from it
 *   silences both lines together. Said outright rather than left to imply
 *   coverage of its own.
 */
it('flags every manual resolution at its position', function () use ($warningsByPosition): void {
    expect(analyzeFixture(MANUAL_RESOLUTION, 'failing.php')->getErrors())->toBe([])
        ->and($warningsByPosition('failing.php'))->toBe([
            10 => [22 => MANUAL_RESOLUTION_WARNING],
            15 => [22 => MANUAL_RESOLUTION_WARNING],
            20 => [22 => MANUAL_RESOLUTION_WARNING],
            25 => [22 => MANUAL_RESOLUTION_WARNING],
            30 => [29 => MANUAL_RESOLUTION_WARNING],
            35 => [22 => MANUAL_RESOLUTION_WARNING],
            40 => [22 => MANUAL_RESOLUTION_WARNING],
            45 => [33 => MANUAL_RESOLUTION_WARNING],
            51 => [26 => MANUAL_RESOLUTION_WARNING],
            57 => [22 => MANUAL_RESOLUTION_WARNING],
            62 => [22 => MANUAL_RESOLUTION_WARNING],
        ]);
});

/**
 * The message names the model, the parameter and the action, so the report
 * says which signature to change. `User::FIND($id)` is asserted because the
 * name comparison is case-insensitive while the message is not: the source
 * spelling of the model has to survive into the output, and the parameter and
 * method names come from the source too.
 */
it('names the model, the parameter and the action in the warning', function (): void {
    $warnings = analyzeFixture(MANUAL_RESOLUTION, 'failing.php')->getWarnings();

    expect($warnings[20][22][0]['message'])
        ->toContain('Model User')
        ->toContain('$id')
        ->toContain('shout()');
});

/**
 * Parameters are read from the enclosing *named* method, never from a closure,
 * an arrow function or an anonymous class nested inside it. That is what makes
 * failing.php line 51 (`use ($id)`) a true positive, and it is worth the two
 * false positives it costs, both pinned here so neither changes unnoticed:
 *
 * - lines 10 and 16, a closure and an arrow function whose own parameter
 *   shadows the action's `$id`. The inner `$id` is a collection element, not a
 *   route segment.
 * - line 24, a public method of an anonymous class declared inside the action.
 *   An anonymous class tokenizes as T_ANON_CLASS, so the innermost T_CLASS is
 *   still `UserController` and the innermost T_FUNCTION is `resolve()`.
 *
 * Separating them from a closure that genuinely inherits the route parameter
 * needs the closure's own parameter and `use` lists resolved, which the
 * standard does not ask for. The rule reports warnings precisely so a
 * reviewer can wave a case like these through.
 *
 * A *named function* declared in a method body is the nested scope that is
 * not seen through, and the absent lines say so:
 *
 * - line 33, `resolveUser()` declared inside the action, and line 44, one
 *   declared inside a closure inside the action. Both tokenize as T_FUNCTION,
 *   so both would answer the innermost-T_FUNCTION lookup and be judged as if
 *   they were the action itself. Neither is routed to, and a named function
 *   inherits nothing from the scope around it, so `$unrelated` can never be a
 *   route segment. `isMethod()` is what keeps them silent: it reads the
 *   declaration's own innermost condition, which is the enclosing function
 *   rather than a class. Deleting that check makes both lines report, which
 *   is the mutation this assertion catches.
 */
it('judges nested scopes by the enclosing method', function () use ($warningsByPosition): void {
    expect(analyzeFixture(MANUAL_RESOLUTION, 'nested-scopes.php')->getErrors())->toBe([])
        ->and($warningsByPosition('nested-scopes.php'))->toBe([
            10 => [26 => MANUAL_RESOLUTION_WARNING],
            16 => [66 => MANUAL_RESOLUTION_WARNING],
            24 => [30 => MANUAL_RESOLUTION_WARNING],
        ]);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so it hands the sniff shapes the
 * parser would reject. Two of them, both silent:
 *
 * - line 12, a statement stranded in the class body with no method around it.
 *   This is what pins the sniff's `$functionPtr === false` guard: the token
 *   has a T_CLASS condition but no T_FUNCTION one, and without the guard the
 *   `false` reaches `getMethodProperties()`, which throws.
 * - line 15, a call running off the end of the file with its argument list
 *   still open.
 *
 * The five `=== false` guards on the sniff's findNext() results read as what
 * handles the second one, but all five are defensive only: each was deleted in
 * turn and every fixture in this directory reported exactly the same
 * violations, because PHP resolves `$tokens[false]` to `$tokens[0]`, the open
 * tag, which fails the comparison on the next line anyway. The fifth, the one
 * on the named-argument colon, is unreachable for a second reason: PHPCS only
 * spells a label T_PARAM_NAME when a colon follows it, so a label with no
 * colon after it never gets that far. They are kept for saying so outright
 * instead of leaning on either fact. Stated here because no fixture can pin
 * them.
 *
 * Line 9 keeps the assertion honest: the file still has to report the call
 * that precedes the damage, so a sniff that fell silent on the whole file
 * would fail here rather than pass. It also records that the truncation costs
 * the file its class scope only from the point it breaks — PHPCS still maps
 * the closed `UserController` above it, which is why line 9 is judged at all.
 */
it('handles mid-edit source without falling over', function () use ($warningsByPosition): void {
    expect(analyzeFixture(MANUAL_RESOLUTION, 'unterminated.php')->getErrors())->toBe([])
        ->and($warningsByPosition('unterminated.php'))->toBe([
            9 => [22 => MANUAL_RESOLUTION_WARNING],
        ]);
});

/**
 * Pins the two severity decisions the standard's doc advertises. The sniff
 * cannot see the route table and cannot tell an Eloquent model from any other
 * class carrying a static `find()`, so every report is a warning a reviewer
 * judges — never an error that fails the run. And rewriting the lookup means
 * editing the route definition in another file, so nothing is auto-fixable.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(MANUAL_RESOLUTION, 'failing.php');

    expect($file->getWarningCount())->toBe(11)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

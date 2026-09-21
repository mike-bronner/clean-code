<?php

/**
 * Tests the custom CleanCode.Naming.ModelNamingConventions sniff (Models:
 * Naming Conventions, #44). Fixtures live in
 * tests/fixtures/ModelNamingConventionsSniff/ and follow the fixture contract:
 * passing.php is clean, failing.php carries one instance of each violation
 * code, and the descriptively named fixtures beside them each isolate one
 * resolution rule the sniff depends on.
 *
 * There is no autofixed.php: the rule is report-only — renaming an identifier
 * or rewriting a legacy accessor is a semantic change a fixer must not make —
 * and 'reports every violation as unfixable' pins that decision.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const MODEL_NAMING_CONVENTIONS = 'CleanCode.Naming.ModelNamingConventions';

const MODEL_NAMING_BOOLEAN_PROPERTY = MODEL_NAMING_CONVENTIONS . '.BooleanPropertyPrefix';

const MODEL_NAMING_BOOLEAN_METHOD = MODEL_NAMING_CONVENTIONS . '.BooleanMethodPrefix';

const MODEL_NAMING_FIND_PREFIX = MODEL_NAMING_CONVENTIONS . '.FindMethodPrefix';

const MODEL_NAMING_FIND_MODEL_NAME = MODEL_NAMING_CONVENTIONS . '.FindModelName';

const MODEL_NAMING_GET_PREFIX = MODEL_NAMING_CONVENTIONS . '.GetMethodPrefix';

const MODEL_NAMING_LEGACY_ACCESSOR = MODEL_NAMING_CONVENTIONS . '.LegacyAttributeAccessor';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MODEL_NAMING_CONVENTIONS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every rule the standard defines, each flagged at its own declaration under
 * its own code so a consumer can tune them individually. The column is the
 * declared name's own position, which is what separates a report about the
 * name from one about the line it happens to sit on: the promoted properties
 * at 80 and 81 are indented differently from the plain ones at 13 and 19, and
 * the `protected` property at 15 starts three columns further along than the
 * `private` one above it.
 */
it('flags every violation at its own line and column under its own code', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'failing.php');

    expect(violationTuples($file))->toBe([
        // Boolean property not phrased as a yes/no question — the third only
        // borrows the letters of `is` ($isolated).
        ['line' => 13, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 15, 'column' => 20, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 19, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        // Boolean method not phrased as a yes/no question — the second only
        // borrows the letters of `can` (candidate()).
        ['line' => 21, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        ['line' => 27, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        // Single-model return without the `find` prefix — the second writes
        // its nullability as `User|null` rather than `?User`.
        ['line' => 32, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        ['line' => 37, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        // `find`-prefixed but silent about the model it returns.
        ['line' => 42, 'column' => 12, 'source' => MODEL_NAMING_FIND_MODEL_NAME],
        // Collection return without the `get` prefix.
        ['line' => 47, 'column' => 12, 'source' => MODEL_NAMING_GET_PREFIX],
        // Legacy accessor style, read and write halves alike.
        ['line' => 52, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
        ['line' => 57, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
        // Root-anchored return type that does resolve into a Models namespace
        // — fully qualified is not an exemption.
        ['line' => 64, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        // Qualified name whose first segment resolves through an import:
        // App\Domain\Models\Account.
        ['line' => 71, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        // Promoted properties, flagged on their own parameter line; the plain
        // $title parameter beside them declares nothing.
        ['line' => 80, 'column' => 22, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 81, 'column' => 24, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
    ]);

    expect($file->getWarnings())->toBe([]);
});

/**
 * A model that lives outside a `Models` namespace is still recognised through
 * the Eloquent base class it extends. Each class in the fixture spells its
 * base differently — a plain import, an aliased one (`Model as
 * EloquentModel`), the conventional `Authenticatable` alias of
 * `Illuminate\Foundation\Auth\User`, and a fully qualified name — and none of
 * them can be matched on the name as written.
 */
it('recognises a model by its Eloquent base class', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'model-by-base-class.php');

    expect(violationTuples($file))->toBe([
        // Plain import of the Eloquent base.
        ['line' => 20, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 22, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        // Base imported under an alias: only resolution reaches
        // Illuminate\Database\Eloquent\Model.
        ['line' => 34, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        ['line' => 36, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        // `Authenticatable` is an alias, not a class name — it resolves to
        // Illuminate\Foundation\Auth\User, whose short name is `User`, so
        // neither the alias nor the resolved short name identifies it. Only
        // the fully qualified name does.
        ['line' => 50, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
        // Fully qualified base, no import at all.
        ['line' => 62, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
    ]);
});

/**
 * The naming rules describe how a model exposes data; a plain service class
 * carrying the very same declarations must go unreported — and so must a class
 * extending something merely *called* `Model`, which the fixture aliases onto
 * a plain value object. Projects owning their own `Model` do exactly that, so
 * the base name as written proves nothing.
 */
it('leaves a non-model class alone', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'not-a-model.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * An unimported `Model` resolves against the enclosing namespace, so it is the
 * project's own base class and not Eloquent's — reaching Eloquent's requires a
 * `use` statement or a leading backslash.
 */
it('treats an unimported base class as the project\'s own', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'unimported-base.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every name the sniff interprets goes through the import map first. Read as
 * written, none of these three violations exists: `CollectionAlias` is not in
 * the collection list, and `findLedgerById()` appears to name the very model
 * it returns.
 */
it('resolves aliased types before it interprets them', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'aliased-imports.php');

    expect(violationTuples($file))->toBe([
        // Resolves to Illuminate\Support\Collection.
        ['line' => 24, 'column' => 12, 'source' => MODEL_NAMING_GET_PREFIX],
        // Resolves to App\Domain\Models\Account.
        ['line' => 31, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        // Named after the alias rather than the model behind it.
        ['line' => 39, 'column' => 12, 'source' => MODEL_NAMING_FIND_MODEL_NAME],
    ]);
});

/**
 * The costly direction: correct code that the same blindness would report. A
 * method named after the model it really returns must not be told to rename
 * itself after a file-local alias.
 */
it('does not report correct names behind aliased types', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'aliased-imports-passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * `use function` / `use const` import from PHP's separate symbol tables and
 * never name a type, so they must not enter the class import map — where they
 * would resolve a return type under the imported namespace and report correct
 * code. The marker appears in three shapes, all covered by the fixture: on an
 * individual item of a mixed group, before a group prefix, and on a plain
 * statement — and it marks a constant as readily as a function, so the fixture
 * carries both keywords in the shapes that can express them.
 *
 * Every one of those imports is *used* as a return type. That is what makes
 * this assertion depend on the screen: an unused import would leave the const
 * half of the check free to be deleted with the suite still green.
 *
 * Screening them must not cost the class item beside them, which the silent,
 * correctly named `findUserById(): User` proves is still imported.
 */
it('keeps function and const imports out of the class import map', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'group-use-mixed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * PHP identifies class names, `use` aliases and method names
 * case-insensitively, so the sniff has to as well: a name spelled differently
 * from the symbol it refers to is still that symbol. Matched on source casing,
 * none of these violations exists — the aliased base class resolves to nothing
 * and takes its whole class dark with it, the acronym-drifted return type is
 * not the imported model, and neither legacy accessor ends in `Attribute`.
 */
it('identifies case-mismatched symbols', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'case-mismatched-symbols.php');

    expect(violationTuples($file))->toBe([
        // The class is a model only through `extends eloquentmodel`, the
        // mis-cased spelling of an aliased Eloquent base — so this property is
        // reported only if that resolved.
        ['line' => 24, 'column' => 17, 'source' => MODEL_NAMING_BOOLEAN_PROPERTY],
        // `ApiToken` is the imported `APIToken`: a model, and this method
        // lacks the `find` prefix.
        ['line' => 32, 'column' => 12, 'source' => MODEL_NAMING_FIND_PREFIX],
        // Identified case-insensitively, judged case-sensitively: the model is
        // spelled `APIToken`, so `findApiToken` does not name it. The judging
        // half must stay strict.
        ['line' => 43, 'column' => 12, 'source' => MODEL_NAMING_FIND_MODEL_NAME],
        // Live legacy accessors — Eloquent finds them through method_exists(),
        // which folds case.
        ['line' => 57, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
        ['line' => 62, 'column' => 12, 'source' => MODEL_NAMING_LEGACY_ACCESSOR],
    ]);

    expect($file->getWarnings())->toBe([]);
});

/**
 * The costly direction of the same invariant. Under a `Models` namespace a
 * mis-cased alias that misses the import map falls through to
 * namespace-qualification, manufacturing a name with a `Models` segment — so
 * the sniff reads a value object as a model and a collection as a single
 * instance, and reports correct code. The mis-cased override is worse than
 * noise: acting on the advice breaks the override.
 */
it('does not invent violations from case-mismatched symbols', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'case-mismatched-symbols-passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The declarations that carry no usable signal — untyped properties (promoted
 * or not), non-boolean promoted properties, plain parameters, missing or union
 * return types, `array` and other builtins, magic methods, Eloquent's own
 * override points, group-imported relation types, non-model classes from other
 * namespaces, root-anchored (fully qualified) return types that resolve
 * outside a Models namespace, and locals inside a method body — must all stay
 * silent rather than guess.
 */
it('skips ambiguous and exempt declarations', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'edge-cases.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * An abstract method ends at a semicolon rather than a body, so its parameters
 * sit at what looks like class-body level. They must not be read as model
 * properties (`bool $strict` is not a `$strict` property), and the walk over
 * the class body has to pick up again after them — which the reported
 * `expired()` on the far side proves.
 *
 * Two guards hold this contract: the declaration skip in endOfMethod() keeps
 * the parameters out of member discovery, and the catch in processProperty()
 * absorbs the exception PHPCS raises if one ever reaches it anyway. Breaking
 * either alone leaves this assertion green; breaking both makes it error,
 * which is the point — a consumer's PHPCS run must never abort on an abstract
 * model method.
 */
it('does not read abstract-method parameters as properties', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'abstract-model.php');

    expect(violationTuples($file))->toBe([
        ['line' => 20, 'column' => 12, 'source' => MODEL_NAMING_BOOLEAN_METHOD],
    ]);
});

/**
 * Report-only, pinned per violation rather than on the file's total: a fixer
 * added to one of the six codes would leave getFixableCount() non-zero, and
 * every flag here has to stay false for the standard's "no automatic
 * renaming" decision to hold.
 */
it('reports every violation as unfixable', function (): void {
    $file = analyzeFixture(MODEL_NAMING_CONVENTIONS, 'failing.php');

    expect($file->getErrorCount())->toBe(15)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, 15, false));
});

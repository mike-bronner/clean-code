<?php

/**
 * Tests the custom CleanCode.Models.MemberOrdering sniff (Models:
 * Organization, #75). Fixtures live in tests/fixtures/MemberOrderingSniff/.
 *
 * The sniff carries five ordering rules over three kinds of member, so the
 * fixtures are split by what they settle rather than by rule:
 *
 * - failing.php    one violation of each of the five rules, at a known line,
 *                  column, and source.
 * - passing.php    the compliant form of all five, plus every near-miss the
 *                  sniff must stay silent on — a class with no model-shaped
 *                  parent, single-member classes, an empty class, the three
 *                  method categories interleaved, magic methods, and a nested
 *                  anonymous class whose members answer to it and not to the
 *                  class around it.
 * - anonymous-class.php
 *                  the same five rules broken inside a *top-level* anonymous
 *                  class, plus the three boundaries of the gate around it.
 * - property-hooks.php
 *                  PHP 8.4 hooks, whose bodies the property walk has to leave
 *                  alone without leaving the hooked property itself alone.
 * - the rest       one shape each, named for what it exercises.
 *
 * Every "no violations" assertion here says which behaviour it withdraws.
 * A silent fixture is only evidence when the fixture would report under the
 * opposite reading, so each such test names the mutation it catches — all of
 * which were run against a scratch copy of the sniff before this file was
 * written, and each of which reddened exactly the test that claims it.
 *
 * The rule is detection-only, so there is no autofixed fixture: reordering
 * members means moving doc blocks, attributes, and comments bound to a
 * declaration only by adjacency, and a trait conflict block makes the trait
 * uses order-dependent outright. The sniff's docblock records that tradeoff.
 *
 * CleanCode/ruleset.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const MEMBER_ORDERING = 'CleanCode.Models.MemberOrdering';

const MEMBER_ORDERING_TRAIT_ORDER = MEMBER_ORDERING . '.TraitOrder';

const MEMBER_ORDERING_MULTIPLE_TRAITS = MEMBER_ORDERING . '.MultipleTraitsPerLine';

const MEMBER_ORDERING_PROPERTY_ORDER = MEMBER_ORDERING . '.PropertyOrder';

const MEMBER_ORDERING_PROPERTY_GROUP = MEMBER_ORDERING . '.PropertyGroupOrder';

const MEMBER_ORDERING_RELATIONSHIP = MEMBER_ORDERING . '.RelationshipMethodOrder';

const MEMBER_ORDERING_ACCESSOR = MEMBER_ORDERING . '.AccessorMethodOrder';

const MEMBER_ORDERING_METHOD = MEMBER_ORDERING . '.MethodOrder';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MEMBER_ORDERING);
});

/**
 * One violation of each of the standard's five rules, each reported on the
 * member that is in the wrong place rather than on the class or on the member
 * it should have followed:
 *
 * - line 8, `use Alpha;` after `use Zebra;` — rule 1's alphabetical half,
 *   reported on the trait name, hence column 9 rather than the statement's 5.
 * - line 9, `use Beta, Gamma;` — rule 1's one-per-line half, reported on the
 *   `use` keyword, because the defect is the statement and not either name.
 * - line 13, `public $alpha` after `public $zulu` — rule 2's alphabetical half.
 * - line 17, `protected $table` after `private $early` — rule 2's grouping
 *   half. A separate code from the line above it, because the two halves fail
 *   for different reasons and a consumer may want to silence one and keep the
 *   other.
 * - line 24, `author(): BelongsTo` after `comments(): HasMany` — rule 3.
 * - line 33, `getTitle()` after `setTitle()` — rule 4.
 * - line 42, `archive()` after `publish()` — rule 5.
 *
 * Columns are asserted alongside the lines: every method violation lands on
 * the name token, so a sniff that reported the `function` keyword or the
 * visibility modifier instead would still satisfy the lines.
 */
it('reports one violation of each of the five ordering rules', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 8, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
            ['line' => 9, 'column' => 5, 'source' => MEMBER_ORDERING_MULTIPLE_TRAITS],
            ['line' => 13, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 17, 'column' => 22, 'source' => MEMBER_ORDERING_PROPERTY_GROUP],
            ['line' => 24, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
            ['line' => 33, 'column' => 21, 'source' => MEMBER_ORDERING_ACCESSOR],
            ['line' => 42, 'column' => 21, 'source' => MEMBER_ORDERING_METHOD],
        ]);
});

/**
 * The messages name the member and the member it belongs before, so a
 * consumer can act on the report without opening the file, and the
 * multiple-traits message counts the traits it found.
 *
 * The count is not decoration: it is what pins the name scan stopping at the
 * `{` that opens a conflict-resolution block. See the conflict-block test
 * below, where the same message is the assertion.
 */
it('names both members in every ordering message', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'failing.php');
    $messages = violationMessagesByLine($file->getErrors());

    expect($messages[8][0])->toContain('Trait Alpha is out of alphabetical order; it belongs before Zebra')
        ->and($messages[9][0])->toContain('this one declares 2')
        ->and($messages[13][0])->toContain('Property $alpha is out of alphabetical order; it belongs before $zulu')
        ->and($messages[17][0])->toContain('Property $table is protected and follows a private property')
        ->and($messages[24][0])->toContain('Relationship method author() is out of alphabetical order')
        ->and($messages[33][0])->toContain('Accessor getTitle() is out of alphabetical order')
        ->and($messages[42][0])->toContain('Method archive() is out of alphabetical order');
});

/**
 * The compliant form of every rule, and — the half that makes this fixture
 * evidence rather than decoration — every near-miss shape the sniff must stay
 * silent on. Each of these was confirmed by mutation against a scratch copy;
 * removing the named guard reddens this test:
 *
 * - `PlainService` breaks all five rules and has no model-shaped parent. Gate
 *   removed: 3 violations.
 * - `SingleMemberModel` has exactly one trait, one property, and one method,
 *   the boundary at which "out of order" cannot be decided.
 * - `EmptyModel` has no members at all.
 * - `InteriorSingletonGroupModel` puts a one-property `protected` group between
 *   a two-property public group and a two-property private group — the boundary
 *   `SingleMemberModel` cannot reach, where the lone group is the class's only
 *   group and nothing sits after it to be compared against. Two mutations of
 *   the per-group boundary were run, each named by its edit and each counted
 *   from the run rather than from a reading of the code:
 *
 *   - The same-group guard (`$rank === $previousRank`) removed from the
 *     alphabetical branch, so a comparison carries across a group boundary.
 *     This class reports twice, on `$table` and `$charlie`.
 *   - The group-order branch widened from `$rank < $previousRank` to `<=`, so
 *     it fires inside a group. This class reports twice, on `$zulu` and
 *     `$delta`.
 *
 *   Neither kill is this class's alone — `CompliantModel` reports under both
 *   too, once under the first (`$memoised`) and three times under the second
 *   (`$beta`, `$table`, `$rendered`). What this class adds is the shape rather
 *   than a mutation nothing else catches: it is the only fixture in the suite
 *   where a group of one has populated groups on both sides of it, so a future
 *   reset that special-cases a singleton group has something to fail against.
 * - `InterleavedModel` puts the three method categories in mixed order, each
 *   internally alphabetical — the assertion that keeps the deliberate decision
 *   not to enforce a sequence *between* the categories from drifting.
 * - `CompliantModel`'s constructor promotes `$zeta` before `$alphaPromoted`.
 *   Promoted properties belong to the constructor's signature, not the class
 *   body's member list. Parenthesis check removed: PHPCS throws outright,
 *   because a promoted parameter is not a member var.
 * - `MagicAndNestedModel` puts `__toString()` after `zulu()`. Magic-method
 *   skip removed: 1 violation, since `_` sorts ahead of every letter.
 * - The anonymous class inside it is compliant read on its own, and every one
 *   of its members sorts before the last member of its kind the outer class
 *   declares — `use Alpha` before `use Zulu`, `$alpha` before `$zulu`, `beta()`
 *   before `zulu()`. That is what makes it evidence for the conditions check,
 *   which appears at three call sites; each was removed on its own and each
 *   reports exactly the member it wrongly pooled: the trait walk on `Alpha`,
 *   the property walk on `$alpha`, the method walk on `beta()`. One violation
 *   apiece.
 *
 *   The anonymous class is checked in its own right now rather than skipped, so
 *   the conditions check no longer decides *whether* its members are ordered,
 *   only *against what*. Its compliant form is what this fixture asserts;
 *   anonymous-class.php asserts the reports.
 */
it('is silent on compliant models and on every near-miss shape', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * An anonymous class is a class, and the standard's five rules reach it.
 *
 * PHP_CodeSniffer retokenizes `new class … {` from T_CLASS to T_ANON_CLASS, so
 * a sniff registering T_CLASS alone never runs on one at all — every rule went
 * unchecked inside `new class extends Model { … }`, at the top level and
 * nested. Registering both is the whole fix: process() and the three walks key
 * off $stackPtr and its scope bounds, not off which token opened the class.
 *
 * The assertion is the same seven-row shape failing.php asserts for a named
 * class, on the same five rules in the same order, which is what pins the two
 * to the same behaviour rather than merely to non-silence. Mutation: register()
 * back to `[T_CLASS]` and this fixture reports nothing at all — 8 violations to
 * 0.
 *
 * The three classes after it are the gate's boundaries, and their silence is
 * the rest of the assertion:
 *
 * - `new class('anonymous', 1) extends Model` — an argument list sits between
 *   `class` and `extends`. It reports its reversed traits (line 64), which is
 *   what says the extends clause is still found past the arguments.
 * - `new class { … }` — no extends clause. findExtendedClassName() returns
 *   false and the gate closes.
 * - `new class extends Controller` — a parent that is not model-shaped. Both
 *   declare the same reversed traits as the class above, so their silence is
 *   the gate's and not the fixture's.
 */
it('checks an anonymous class against all five rules', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'anonymous-class.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 21, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
            ['line' => 22, 'column' => 5, 'source' => MEMBER_ORDERING_MULTIPLE_TRAITS],
            ['line' => 26, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 30, 'column' => 22, 'source' => MEMBER_ORDERING_PROPERTY_GROUP],
            ['line' => 37, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
            ['line' => 46, 'column' => 21, 'source' => MEMBER_ORDERING_ACCESSOR],
            ['line' => 55, 'column' => 21, 'source' => MEMBER_ORDERING_METHOD],
            ['line' => 64, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
        ]);
});

/**
 * A PHP 8.4 property hook's body is not a list of properties, and the property
 * it hangs off still is one.
 *
 * PHP_CodeSniffer opens no scope for a hook, so every `$this`, hook parameter,
 * and hook local inside one reaches the property walk with the class as its
 * innermost condition — and the locals with no enclosing parentheses either, so
 * neither of the walk's other two tests excludes them. The declaration test
 * does. The sibling TooManyFieldsSniff and LongVariableSniff carry the same
 * test against the same tokenizer behaviour.
 *
 * Both readings are pinned by a mutation, because either half alone would pass
 * against a sniff that got the other half wrong:
 *
 * - `HookedModel` is compliant and its hook bodies name `$zulu`, `$aardvark`,
 *   and `$this` — each placed so that reading it as a property reports. The
 *   declaration test removed: 5 violations here, up from 2.
 * - `MisorderedHookedModel` and the anonymous class after it put a hooked
 *   `$zulu` before a plain `$alpha`, which is the report asserted below. A
 *   sniff filtering hooked properties out along with their bodies is silent on
 *   both: confirmed by mutation, 2 violations to 0.
 *
 * The anonymous class is the intersection of the two fixes this fixture and
 * anonymous-class.php each cover on their own — it is reached only through
 * T_ANON_CLASS, and its hook body stays out only through the declaration test.
 */
it('reads a hooked property without reading its hook body', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'property-hooks.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 39, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 50, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

/**
 * Trait uses written the ways that make a naive name scan get the wrong
 * answer. The fixture is in alphabetical order under the sniff's actual
 * comparison, so the one reported violation is the multi-trait declaration and
 * every other shape's evidence is the silence around it:
 *
 * - `use \Beta;` between `use Alpha;` and `use Bravo;`. The leading separator
 *   is stripped before comparing; left on, `\` sorts after `B` and `Bravo`
 *   reports. Confirmed by mutation: 2 violations.
 * - `use Zulu\Charlie;` last. The whole written name is compared, not the
 *   short name; compared on `Charlie` alone it sorts before `Delta` and
 *   reports. Confirmed by mutation: 2 violations.
 * - `use Delta, Epsilon, Zeta { Delta::render insteadof Epsilon, Zeta; }`. The
 *   scan stops at the `{`, so the names inside the conflict block — which are
 *   references to traits already declared, and include a comma — are not
 *   counted as further declarations. Confirmed by mutation: the count in the
 *   message becomes 4. That count is why this test asserts the message and not
 *   just the source: a scan running to the `;` reports on the same line with
 *   the same code.
 */
it('reads trait names past separators, qualifiers, and a conflict block', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'conflict-block.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 19, 'column' => 5, 'source' => MEMBER_ORDERING_MULTIPLE_TRAITS],
        ])
        ->and(violationMessagesByLine($file->getErrors())[19][0])->toContain('this one declares 3');
});

/**
 * Two shapes of the same question — where a property declaration starts — which
 * the property walk has to answer to tell a member from the locals inside a
 * property hook's body:
 *
 * - `public $delta, $bravo;` declares two properties from one statement. The
 *   standard orders properties rather than statements, so both are checked and
 *   the report lands on the second name's column — the only thing that
 *   distinguishes checking both from checking the statement's first name alone.
 *   A comma is not a statement boundary, which is what lets `$bravo` find the
 *   same `public` `$delta` does.
 * - `AttributedPropertyModel` writes attributes in front of both its
 *   properties, one before the first and two before the second, and the second
 *   is out of order. The attributes are stepped over to reach the modifier
 *   behind them; step over only the whitespace and the declaration is read as
 *   starting at `#[`, which is no modifier, so the property is passed over
 *   entirely and its disorder goes unreported. Confirmed by mutation: replacing
 *   the stepping loop with a single findNext() drops this fixture from 2
 *   violations to 1.
 */
it('checks every property of a multi-property or attributed declaration', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'multi-property.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 20, 'column' => 20, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 30, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

/**
 * Two properties of the comparison, each pinned by the count rather than by
 * silence:
 *
 * - `$charlie, $alpha, $bravo` reports once, on `$alpha`. The displaced member
 *   becomes the baseline for the ones after it, so `$bravo` — correctly placed
 *   relative to `$alpha` — stays silent. Without that reset: 2 violations.
 * - `$Bravo, $charlie, $Delta` reports nothing. Under a case-sensitive
 *   comparison `$Delta` sorts ahead of `$charlie`: 2 violations.
 */
it('reports each displaced member once and ignores letter case', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'cascade-and-case.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 21, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

/**
 * Which ordering rule each method answers to. Every method in this fixture is
 * placed so that reading it as the wrong category puts it out of alphabetical
 * order, so silence is the assertion — and each reading was confirmed by
 * mutation against a scratch copy:
 *
 * - `RelationReturnTypeModel` — a relation return type is recognised
 *   qualified (`\Illuminate\…\HasMany`), nullable (`?BelongsTo`), as one arm
 *   of a union (`HasOne|MorphTo`), and as the abstract base (`Relation`).
 *   Dropping the `?` from the trimmed characters: 1 violation. Taking the
 *   first namespace qualifier instead of the last: 1 violation. Dropping the
 *   union split: 3 violations — one here, and one in each DNF class below,
 *   whose `|null` arm needs the same split before the parentheses matter.
 * - `DnfLeadingRelationModel` and `DnfTrailingRelationModel` — PHP 8.2's DNF
 *   spelling, `(HasMany&Countable)|null` and `(Countable&HasOne)|null`. The
 *   parenthesis reaches the comparison on the relation itself, and which one it
 *   is depends on where the relation sits inside the intersection, so the two
 *   characters need a shape each: dropping `(` alone reddens the first class,
 *   dropping `)` alone reddens the second, and dropping both reddens both — 1
 *   violation per class, confirmed by all three mutations separately. The
 *   violation is one the sniff invents against an unrelated method, because a
 *   misread relationship is ordered against the ordinary methods instead of the
 *   relationships. One class per shape is deliberate: sharing one would let the
 *   first misread method take the baseline and silence the second.
 * - `NotARelationModel` — a method with no declared return type, and a
 *   protected one, are ordinary methods. Dropping the public test: 1
 *   violation. The no-hint case needs no guard of its own: an absent hint is
 *   the empty string, whose short name matches nothing.
 * - `AccessorModel` — `get*`/`set*` are accessors, but a public method
 *   returning a relation answers to rule 3 whatever it is named. Classifying
 *   accessors first: 1 violation. Dropping the accessor category outright:
 *   2 violations — one here, one in `LowercasePrefixModel`.
 * - `LowercasePrefixModel` — `getter()` and `settle()` merely start with those
 *   letters; an accessor needs a capital after the prefix. Dropping the
 *   `[A-Z]`: 1 violation.
 */
it('classifies each method by its declared return type and its name', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'classification.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The four non-visibility keywords a property declaration can lead with, which
 * the property walk has to accept alongside the visibility modifiers to tell a
 * member from a local inside a property hook's body.
 *
 * A declaration written `public readonly string $x;` is answered by its
 * `public` alone, so it says nothing about the other four keywords. Each class
 * in the fixture leads its out-of-order property with one of them and nothing
 * else, which is what makes the four independently reachable — and
 * independently killable: emptying the modifier list drops all four violations,
 * and removing any single entry from it drops exactly the one class that leads
 * with that keyword. All five mutations were run against a scratch copy.
 *
 * One class per keyword is deliberate. Sharing one would let the first
 * unrecognised property be skipped and its baseline carry over, so a second
 * keyword's disorder could go unreported for a reason that is not its own.
 */
it('reads a property declared with a non-visibility modifier', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'property-modifiers.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 26, 'column' => 18, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 33, 'column' => 21, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 40, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 47, 'column' => 9, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

/**
 * An extends clause written as a qualified name, which is how a model is
 * declared in a file that imports nothing.
 *
 * findExtendedClassName() returns the parent exactly as written, so the gate
 * reduces it to its short name before comparing — the FQCN is not resolvable at
 * lint time, and `\Illuminate\Database\Eloquent\Relations\Pivot` and `Pivot`
 * put the same short name in this file's tokens.
 *
 * `QualifiedPivotModel` is the row that settles it: `Pivot` is one of the
 * configured parent names, and the qualified spelling matches neither that list
 * nor the "ends in Model" fallback until the qualifiers come off. Mutation:
 * comparing the written name whole (`$qualifiers = [$parent];`) and this class
 * goes silent. `QualifiedEloquentModel` is the spelling the sniff's own docblock
 * names, so it is here for the reader, but it survives that mutation through the
 * fallback — its violation is the shape, not the evidence.
 *
 * `QualifiedController` is the other side of the gate. Its properties are
 * reversed exactly like the two above and it reports nothing, which is what says
 * stripping qualifiers is not the same as opening the gate on any parent written
 * with a separator in it.
 */
it('reads the short name of a namespace-qualified parent', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'qualified-parent.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 29, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 36, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

/**
 * A method fragment the tokenizer could not finish reading, inside a class it
 * did open — the method-level counterpart of the test below, whose whole class
 * never opened.
 *
 * `public function` with no name leaves the name-token search nothing to find
 * before the class closer, and the fragment is passed over. The name is read
 * from that same token, so there is one answer rather than two that can
 * disagree: getDeclarationName() has no parenthesis to stop at here and would
 * search on to the end of the file, naming the fragment after whatever came
 * next.
 *
 * The fixture's properties are out of order on purpose. Their single violation
 * is what says the gate is open and the walks ran on this class, so the
 * fragment's silence is the guard's and not the fixture's. Mutation: drop the
 * guard and `zulu()` is compared against the content of the file's first token,
 * which sorts before it — 2 violations, not 1.
 */
it('passes over a method the tokenizer never named', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'truncated-method.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 24, 'column' => 12, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

/**
 * A model-shaped class the tokenizer never opened. findExtendedClassName()
 * returns false without a scope_opener, so the gate closes before the walks
 * begin, and process() re-reads both bounds as a second line of defence.
 *
 * The two are pinned as a pair rather than separately: removing either alone
 * leaves this fixture silent, and removing both makes PHPCS abort the file
 * with a TypeError — a walk with a null end runs to the end of the file and
 * orders this class's absent members against whatever follows.
 */
it('passes over a class the tokenizer never opened', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The two properties a consuming ruleset can configure, set the way a
 * `<property>` element sets them, and asserted against the same fixture under
 * the shipped defaults.
 *
 * The pair is what makes either half evidence. `configured.php` holds a model
 * of each kind — `ConfiguredModel extends Entity` with reversed `Association`
 * relationships, and `ShippedParentModel extends Pivot` with reversed
 * properties — so configuring the sniff moves the single reported line from
 * one class to the other. A test asserting only the configured run would hold
 * just as well against properties that were never read.
 *
 * Both are set by direct assignment, as the sibling DisallowAlwaysOnEagerLoading
 * test sets its own list: these are array-typed properties, and a ruleset
 * declares them with `type="array"`, so the string-valued XML path
 * analyzeFixtureWithRulesetProperties() models is not the one a consumer takes
 * for either of them.
 */
it('takes its model parents and relation return types from the ruleset', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'configured.php', static function (object $sniff): void {
        $sniff->modelParentClasses = ['Entity'];
        $sniff->relationReturnTypes = ['Association'];
    });

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 22, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
        ]);
});

it('gates on the shipped parents and relation types when nothing is configured', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'configured.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 39, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
        ]);
});

/**
 * The complement of the two evaluation tests below, and what stops the first of
 * them passing against an inert fixture: this sniff reports all four purely
 * alphabetical rules on the very file Slevomat has nothing to say about.
 */
it('reports all four alphabetical rules on the evaluation fixture', function (): void {
    $file = analyzeFixture(MEMBER_ORDERING, 'alphabetical-only.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 18, 'column' => 9, 'source' => MEMBER_ORDERING_TRAIT_ORDER],
            ['line' => 22, 'column' => 19, 'source' => MEMBER_ORDERING_PROPERTY_ORDER],
            ['line' => 29, 'column' => 21, 'source' => MEMBER_ORDERING_RELATIONSHIP],
            ['line' => 38, 'column' => 21, 'source' => MEMBER_ORDERING_ACCESSOR],
            ['line' => 47, 'column' => 21, 'source' => MEMBER_ORDERING_METHOD],
        ]);
});

/**
 * The evaluation of whether a shipped sniff already enforces this standard, run
 * against the pinned Slevomat version rather than asserted from its
 * documentation — this is the assertion a Slevomat upgrade would have to change
 * the answer to.
 *
 * Both halves are asserted, because either alone proves nothing:
 *
 * - `alphabetical-only.php` breaks the four rules that are nothing but
 *   alphabetical order and breaks nothing else — one trait per use statement,
 *   visibility groups in the right sequence. The whole Slevomat standard, every
 *   sniff of it active, reports not one violation from the three sniffs the
 *   sniff's docblock evaluates. Alphabetical order is four of the standard's
 *   five rules and nothing shipped speaks about it. The test above proves this
 *   sniff does report all four on the same file, so the silence here is
 *   Slevomat's and not the fixture's.
 * - `failing.php` additionally puts a protected property after a private one
 *   and declares two traits in one statement, and exactly those two sources
 *   appear. Without this row the row above would pass just as well if Slevomat
 *   had been pointed at something it never reports on at all.
 *
 * The match is scoped to those three sniffs rather than to Slevomat as a whole,
 * because both fixtures trip plenty of unrelated Slevomat opinions (public
 * properties, non-final classes, blank lines around braces) that have nothing
 * to do with ordering.
 */
it('settles which of the pinned Slevomat sniffs cover which rules', function (
    string $fixture,
    array $expected
): void {
    $file = analyzeWithStandard(
        'SlevomatCodingStandard',
        fixturePath('MemberOrderingSniff', $fixture)
    );

    $matched = array_values(array_unique(array_filter(
        array_merge(...array_values(allViolationSourcesByLine($file))),
        static fn (string $violation): bool => preg_match(
            '/^SlevomatCodingStandard\\.Classes\\.(ClassStructure|PropertyDeclaration|TraitUseDeclaration)\\./',
            $violation
        ) === 1
    )));

    sort($matched);

    expect($matched)->toBe($expected);
})->with([
    'nothing shipped covers the alphabetical rules' => ['alphabetical-only.php', []],
    'the grouping and one-per-line halves are covered' => ['failing.php', [
        'SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder',
        'SlevomatCodingStandard.Classes.TraitUseDeclaration.MultipleTraitsPerDeclaration',
    ]],
]);

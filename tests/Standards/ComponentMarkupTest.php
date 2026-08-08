<?php

/**
 * Tests the custom CleanCode.Livewire.ComponentMarkup sniff (Livewire:
 * Components, #46). Fixtures live in tests/fixtures/ComponentMarkupSniff/.
 *
 * The sniff reads Blade markup with regular expressions, so every assertion
 * here is really an assertion about a *heuristic*: what it must see, and —
 * just as load-bearing — what it must stay quiet about. Each "no violations"
 * case below therefore names the shape it protects and is paired with a
 * fixture that does fire; on their own the negatives would pass equally well
 * against a sniff that never speaks.
 *
 * Violations are reported on the line rather than a token, because the
 * analysis runs over reconstructed markup and not the token stream. Column 1
 * throughout is that, not an accident.
 *
 * Detection only: every fix needs a value a machine cannot derive (which
 * attributes belong on the root, what a `wire:key` should be keyed to), so
 * there is no autofixed fixture and every reported violation is unfixable.
 */

declare(strict_types=1);

const COMPONENT_MARKUP = 'CleanCode.Livewire.ComponentMarkup';

const ROOT_ELEMENT_ATTRIBUTES = COMPONENT_MARKUP . '.RootElementAttributes';

const MISSING_WIRE_KEY_IN_LOOP = COMPONENT_MARKUP . '.MissingWireKeyInLoop';

const ADJACENT_COMPONENT_NOT_WRAPPED = COMPONENT_MARKUP . '.AdjacentComponentNotWrapped';

const TEMPLATE_KEY_MISMATCH = COMPONENT_MARKUP . '.TemplateKeyMismatch';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COMPONENT_MARKUP);
});

/**
 * The compliant view carries every near-miss the heuristics must not trip on:
 *
 * - line 1, a bare `<div class="…">` root — the shape the standard asks for.
 * - line 5, `<button wire:click="increment">`. A `wire:` attribute is only
 *   forbidden on the *root* element; on a child it is ordinary Livewire.
 * - lines 8-10, a component in a `@foreach` carrying its own `wire:key`.
 * - lines 13-18, two adjacent components, each wrapped in a `<template>`
 *   whose `wire:key` matches the component's own.
 * - lines 20-26, commented-out component markup, HTML and Blade comment form
 *   alike. Both blocks hold an adjacent, unwrapped pair; without the comment
 *   blanking they would be reported.
 * - lines 30-32, `<x-menu-item>` in a loop. `<x-…>` is Blade's component
 *   namespace, shared with ordinary Blade components that owe no `wire:key`,
 *   so it is not read as a Livewire component.
 * - line 35, a lone component with nothing beside it.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COMPONENT_MARKUP, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The violating view, one shape per code:
 *
 * - line 1, `wire:model` on the root element.
 * - line 3, a component in a `@foreach` with no `wire:key`.
 * - lines 7-9, three adjacent components, none wrapped in a `<template>`.
 *   The middle one belongs to two adjacent pairs and is still reported once.
 * - line 16, a component wrapped in a `<template wire:key="right">` while the
 *   component itself is keyed `wrong`.
 *
 * Line 13's component is wrapped under a matching key and stays unreported,
 * which is what keeps the mismatch assertion from passing against a sniff
 * that simply flags every wrapped component.
 */
it('flags each violating shape exactly once, on its own line', function (): void {
    expect(violationTuples(analyzeFixture(COMPONENT_MARKUP, 'failing.php')))->toBe([
        ['line' => 1, 'column' => 1, 'source' => ROOT_ELEMENT_ATTRIBUTES],
        ['line' => 3, 'column' => 1, 'source' => MISSING_WIRE_KEY_IN_LOOP],
        ['line' => 7, 'column' => 1, 'source' => ADJACENT_COMPONENT_NOT_WRAPPED],
        ['line' => 8, 'column' => 1, 'source' => ADJACENT_COMPONENT_NOT_WRAPPED],
        ['line' => 9, 'column' => 1, 'source' => ADJACENT_COMPONENT_NOT_WRAPPED],
        ['line' => 16, 'column' => 1, 'source' => TEMPLATE_KEY_MISMATCH],
    ]);
});

it('reports every violation as unfixable', function (): void {
    $flags = violationFixableFlags(analyzeFixture(COMPONENT_MARKUP, 'failing.php'));

    expect($flags)->toHaveCount(6)
        ->and($flags)->each->toBeFalse();
});

/**
 * A `<template>` with no `wire:key` at all is not a valid wrapper either: the
 * standard asks for the *same* key as the component, and a wrapper that
 * carries none cannot match one. Both components here are keyed and adjacent,
 * so the only thing left to fail on is the wrapper.
 */
it('flags an adjacent component whose template wrapper carries no key', function (): void {
    expect(violationTuples(analyzeFixture(COMPONENT_MARKUP, 'template-without-key.php')))->toBe([
        ['line' => 5, 'column' => 1, 'source' => TEMPLATE_KEY_MISMATCH],
        ['line' => 8, 'column' => 1, 'source' => TEMPLATE_KEY_MISMATCH],
    ]);
});

/**
 * The gate, and the reason plain Blade stays quiet. This fixture is a
 * dropdown partial: an Alpine `x-data` root and two adjacent `<x-…>` tags,
 * which is exactly the root-attribute violation the sniff reports — in a
 * Livewire view. Nothing in it names Livewire, so nothing in it is judged
 * against a Livewire rule. Remove the gate and line 1 is reported.
 */
it('says nothing about a view that is not recognisably Livewire', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'not-livewire.php')->getErrors())->toBe([]);
});

/**
 * Where the source does not show the loop, the sniff does not claim to see
 * it. Both fixtures hold a keyless component and an unbalanced loop
 * directive: one opened and never closed (the body continues in an
 * `@include`), one closed without ever being opened (a fragment whose loop
 * belongs to its parent). Guessing the region's other end would report a
 * component that may well not be in a loop at all.
 */
it('says nothing about a loop whose directive is unbalanced', function (string $fixture): void {
    expect(analyzeFixture(COMPONENT_MARKUP, $fixture)->getErrors())->toBe([]);
})->with([
    'unclosed @foreach' => 'unbalanced-loop.php',
    'orphan @endforeach' => 'orphan-loop-close.php',
]);

/**
 * A view built entirely from `@livewire(…)` calls has no element tag to read
 * a root from, and no component *tag* to key. It is recognisably Livewire —
 * so the gate lets it through — and every check has to survive finding
 * nothing rather than crash on the empty match.
 */
it('says nothing about a Livewire view with no element tag', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'no-root-element.php')->getErrors())->toBe([]);
});

/**
 * Every prefix in the sniff's FRAMEWORK_ATTRIBUTE_PREFIXES list is a separate
 * claim the standard doc makes to users, so each gets a root of its own that
 * fires on it. One fixture per prefix, because only the *first* framework
 * attribute on the *first* tag is ever reported — stacking them on one root
 * would test `wire:` five times.
 *
 * The reported attribute name is asserted, not just the code: a test that only
 * checked RootElementAttributes fired would pass on a sniff that matched the
 * wrong prefix and named the wrong attribute.
 */
it('flags every framework-attribute prefix on a root element', function (
    string $fixture,
    string $attribute
): void {
    $file = analyzeFixture(COMPONENT_MARKUP, $fixture);

    expect(violationTuples($file))->toBe([
        ['line' => 1, 'column' => 1, 'source' => ROOT_ELEMENT_ATTRIBUTES],
    ])->and($file->getErrors()[1][1][0]['message'])->toContain($attribute);
})->with([
    'Alpine x- directive' => ['root-alpine-attribute.php', 'x-data'],
    '@ event shorthand' => ['root-event-attribute.php', '@click'],
    ': bind shorthand' => ['root-bound-attribute.php', ':class'],
    '{{ }} attribute echo' => ['root-echo-attribute.php', '{{'],
]);

/**
 * A view whose first tag is itself a `<livewire:…>` invocation has no root of
 * its own to judge — that tag is a *child* component being rendered, and the
 * `wire:key` on it is the very attribute MissingWireKeyInLoop and
 * TemplateKeyMismatch require elsewhere. Reading it as a root element made the
 * sniff contradict its own rules.
 *
 * The `wire:click` further down is what makes this a component view at all
 * (see the next case), so the silence here is the first-tag guard's doing and
 * not the component-view gate's.
 */
it('says nothing about a view whose first tag is a component invocation', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'child-component-first.php')->getErrors())->toBe([]);
});

/**
 * The same guard, one level out: a partial that renders adjacent components
 * opens on the `<template wire:key="...">` wrapper this very standard requires
 * around them. Judging that wrapper as a root element reported the fixture for
 * doing exactly what the sniff demands — `wire:key` on a `<template>` is what
 * TemplateKeyMismatch insists on, so it can never be a root violation.
 *
 * The `wire:click` at the end is the view's own Livewire directive, so the gate
 * passes for a reason independent of the wrappers and the silence here is the
 * first-tag guard's doing. Nothing else is reported either: the two components
 * are adjacent, wrapped, and keyed to match.
 */
it('says nothing about a view whose first tag wraps a component', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'template-wrapper-first.php')->getErrors())->toBe([]);
});

/**
 * The root-element rule belongs to a component's *own* view. This fixture is
 * an ordinary page layout — an Alpine shell that happens to embed a Livewire
 * widget — so its `<div x-data>` is the layout's root, not a component's.
 *
 * It passes the Livewire gate (it contains a `<livewire:…>` tag), which is
 * exactly why the gate alone is not enough: the only `wire:` in the file is
 * the `wire:key` on that embedded tag, so the view writes no Livewire
 * directive of its own and its root is left alone.
 */
it('says nothing about the root of a view that merely embeds a component', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'embeds-component.php')->getErrors())->toBe([]);
});

/**
 * Livewire's tag syntax lets a component wrap content, so a
 * `<livewire:card-icon />` inside an open `<livewire:card>` is that card's
 * child — not the component next to it. Reading the two as unwrapped siblings
 * flagged both, and demanded a `<template>` wrapper around a nested child that
 * the standard never asks for.
 */
it('says nothing about a component nested inside another component', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'nested-components.php')->getErrors())->toBe([]);
});

/**
 * The paired positive for the case above: two content-wrapping components that
 * really do sit next to each other are still reported. Without this, the
 * nesting fixture would pass equally well against a sniff that had simply
 * stopped looking at any component with a closing tag.
 *
 * Only the two `<livewire:card>` tags are flagged. Their `<livewire:card-icon>`
 * children are each an only child of a different parent, so neither is
 * adjacent to anything.
 */
it('still flags adjacent components that each wrap their own content', function (): void {
    expect(violationTuples(analyzeFixture(COMPONENT_MARKUP, 'nested-components-adjacent.php')))
        ->toBe([
            ['line' => 2, 'column' => 1, 'source' => ADJACENT_COMPONENT_NOT_WRAPPED],
            ['line' => 5, 'column' => 1, 'source' => ADJACENT_COMPONENT_NOT_WRAPPED],
        ]);
});

/**
 * The fixtures above are `.php` files so that they satisfy the repository's
 * fixture contract, and `LocalFile` tokenises them the same way either
 * extension would. This one is a real `.blade.php`, run through the *whole*
 * master ruleset, so the wiring the standard actually ships — the extension
 * registration in rules.xml, plus the sniff reaching a Blade file through it —
 * is asserted end to end rather than assumed.
 *
 * Assertions are scoped to this sniff's own sources, per CONTRIBUTING, so a
 * sibling standard landing in rules.xml cannot break them.
 */
it('flags a real .blade.php view through the master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('ComponentMarkupSniff', 'component.blade.php'));

    $sources = array_map(
        static fn (array $violation): array => [$violation['line'], $violation['source']],
        array_filter(
            violationTuples($file),
            static fn (array $violation): bool => str_starts_with($violation['source'], COMPONENT_MARKUP . '.')
        )
    );

    expect(array_values($sources))->toBe([
        [1, ROOT_ELEMENT_ATTRIBUTES],
        [2, ADJACENT_COMPONENT_NOT_WRAPPED],
        [3, ADJACENT_COMPONENT_NOT_WRAPPED],
    ]);
});

/**
 * The sniff must stay linear in the size of the view.
 *
 * Every earlier version re-read the markup from offset 0 for each component
 * tag — once to find the preceding `<template>` and once to count the lines
 * before it — which made a pass quadratic. The cost fell on *well-formed*
 * input: the view below is correctly wrapped and correctly keyed, so the work
 * happened whether or not anything was ever reported, and a large generated
 * view was enough to stall a lint run for minutes.
 *
 * A wall-clock budget is a blunt instrument, so the margin is deliberately
 * enormous rather than tight. Measured on this fixture (8,000 components,
 * ~750 KB): the quadratic implementation took **22.7s**, the linear one
 * **0.11s**. Five seconds sits ~45x above the linear cost and ~4.5x below the
 * quadratic one, so the case fails on a genuine regression to n² and does not
 * fail on a slow or loaded runner.
 */
it('scans a large well-formed view in linear time', function (): void {
    $components = 8000;
    $view = "<div wire:poll class=\"feed\">\n";

    for ($index = 0; $index < $components; $index++) {
        $view .= "    <template wire:key=\"k{$index}\">\n"
            . "        <livewire:item-{$index} wire:key=\"k{$index}\" />\n"
            . "    </template>\n";
    }

    // Its own directory, because purgeStagedFixtures() removes the parent.
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-perf-', true);
    mkdir($directory, 0700);

    $path = $directory . '/large-view.blade.php';
    stagedFixtures($path);
    file_put_contents($path, $view . "</div>\n");

    $started = microtime(true);
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $elapsed = (microtime(true) - $started);

    // Wrapped and keyed throughout, so the only report is the root element's
    // own wire:poll. Asserting it pins that the run really did analyse the
    // view rather than bailing out early and finishing fast for free.
    expect(violationSourcesByLine($file->getErrors()))->toBe([1 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($elapsed)->toBeLessThan(5.0);
});

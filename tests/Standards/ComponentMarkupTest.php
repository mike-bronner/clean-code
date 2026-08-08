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

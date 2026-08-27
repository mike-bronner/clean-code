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

use MikeBronner\CleanCode\Sniffs\Livewire\ComponentMarkupSniff;

const COMPONENT_MARKUP = 'CleanCode.Livewire.ComponentMarkup';

const ROOT_ELEMENT_ATTRIBUTES = COMPONENT_MARKUP . '.RootElementAttributes';

const MISSING_WIRE_KEY_IN_LOOP = COMPONENT_MARKUP . '.MissingWireKeyInLoop';

const ADJACENT_COMPONENT_NOT_WRAPPED = COMPONENT_MARKUP . '.AdjacentComponentNotWrapped';

const TEMPLATE_KEY_MISMATCH = COMPONENT_MARKUP . '.TemplateKeyMismatch';

/**
 * A page layout, `%s` standing in for the attribute a case puts on its link: an
 * Alpine root that would be reported as a component root if the view were
 * judged, and an embedded component. Whether line 1 is reported is therefore
 * exactly the component-view gate's answer, and nothing else.
 */
const LAYOUT_CARRYING = '<div x-data="{ open: false }" class="app-shell">
    <a %s href="/dashboard">Dashboard</a>
    <livewire:notifications-bell wire:key="bell" />
</div>
';

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
 * The same layout, in the two shapes that still carry a `wire:` attribute of
 * their own outside the component tag — and are still not component views.
 *
 * - `embeds-navigating-component.php`: a `wire:navigate` link. Livewire's
 *   navigation, cloak, offline, transition and ignore directives are at home
 *   on any page; only a directive bound to a component says the view is one.
 * - `embeds-wrapped-components.php`: the `<template wire:key="...">` wrappers
 *   this standard *requires* around adjacent components. Reading their key as
 *   the view's own directive reported a layout for obeying the standard.
 *
 * Both are the shape `embeds-component.php` protects, one attribute further
 * out. Nothing else is reported in either: the wrapped pair is adjacent,
 * wrapped and keyed to match, and the lone bell is beside nothing.
 */
it('says nothing about a layout whose only wire: attribute is any view\'s', function (
    string $fixture
): void {
    expect(analyzeFixture(COMPONENT_MARKUP, $fixture)->getErrors())->toBe([]);
})->with([
    'wire:navigate link' => 'embeds-navigating-component.php',
    'wire:key template wrappers' => 'embeds-wrapped-components.php',
]);

/**
 * The gate's proof set, one case per directive.
 *
 * Each name in OWN_WIRE_DIRECTIVE is a separate claim that seeing it means the
 * view is a component's own, so each is exercised at the position that decides
 * it: on the layout's own markup, where the only thing standing between the
 * root and a report is this answer. Drop a name from the set and its case goes
 * silent; widen the set back to any `wire:` attribute and the any-view cases
 * below all start reporting.
 *
 * The list is restated here rather than read from the sniff, so that removing a
 * directive from the constant fails a test instead of shrinking one.
 */
it('judges the root of a view carrying a component-bound directive', function (
    string $attribute
): void {
    $path = stageSource(sprintf(LAYOUT_CARRYING, $attribute));
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);

    expect(violationSourcesByLine($file->getErrors()))->toBe([1 => [ROOT_ELEMENT_ATTRIBUTES]]);
})->with([
    'wire:model' => 'wire:model="query"',
    'wire:click' => 'wire:click="save"',
    'wire:submit' => 'wire:submit="save"',
    'wire:change' => 'wire:change="save"',
    'wire:keydown' => 'wire:keydown="save"',
    'wire:keyup' => 'wire:keyup="save"',
    'wire:blur' => 'wire:blur="save"',
    'wire:focus' => 'wire:focus="save"',
    'wire:poll' => 'wire:poll',
    'wire:init' => 'wire:init="load"',
    'wire:confirm' => 'wire:confirm="Are you sure?"',
    'a modifier chain' => 'wire:model.live.debounce.500ms="query"',
]);

/**
 * The other side of the same gate, and the regression these fixtures exist for.
 *
 * Every directive here is one Livewire documents for ordinary pages — a link
 * that navigates, a shell that hides until load, a wrapper's key — so none of
 * them makes the layout a component's own view, and its root stays unjudged.
 * Reading any `wire:` attribute as proof reported all of them.
 *
 * The identical layout under the previous case *is* reported, so these are not
 * passing against a sniff that has simply stopped looking.
 */
it('leaves the root of a view carrying only an any-view directive alone', function (
    string $attribute
): void {
    $path = stageSource(sprintf(LAYOUT_CARRYING, $attribute));
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);

    expect($file->getErrors())->toBe([]);
})->with([
    'wire:navigate' => 'wire:navigate',
    'wire:navigate.hover' => 'wire:navigate.hover',
    'wire:current' => 'wire:current="active"',
    'wire:cloak' => 'wire:cloak',
    'wire:offline' => 'wire:offline',
    'wire:transition' => 'wire:transition',
    'wire:ignore' => 'wire:ignore',
    'wire:key' => 'wire:key="link"',
]);

/**
 * A binding on the component tag itself belongs to the *child*.
 * `<livewire:search-box wire:model="query" />` binds the child's property from
 * its parent, so it is a directive about the child and says nothing about the
 * view holding it — which is a page layout here, with a root of its own.
 *
 * The same `wire:model` on a plain element of the same layout *is* proof and
 * *is* reported (see the case above), so this is the tag being skipped and not
 * the directive going unrecognised.
 */
it('does not read a binding on a component tag as the view\'s own', function (): void {
    $path = stageSource(
        '<div x-data="{ open: false }" class="app-shell">' . "\n"
            . '    <livewire:search-box wire:model="query" wire:key="search" />' . "\n"
            . "</div>\n"
    );

    expect(analyzeWithSniffs([COMPONENT_MARKUP], $path)->getErrors())->toBe([]);
});

/**
 * A directive is an attribute, not a string that looks like one. A view quoting
 * `wire:click` in its prose — a styleguide page, a docs partial — writes no
 * Livewire directive at all, and its root is a layout's.
 */
it('does not read a wire: directive quoted in text as the view\'s own', function (): void {
    $path = stageSource(
        '<div x-data="{ open: false }" class="app-shell">' . "\n"
            . '    <p>Bind a button with wire:click="save" to call the method.</p>' . "\n"
            . '    <livewire:notifications-bell wire:key="bell" />' . "\n"
            . "</div>\n"
    );

    expect(analyzeWithSniffs([COMPONENT_MARKUP], $path)->getErrors())->toBe([]);
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

/**
 * The same guarantee for the loop check, which the case above cannot reach.
 *
 * That view contains no `@foreach` at all, so it never asks which loop a
 * component sits in — and the answer used to be found by rescanning every loop
 * region in the file, once per component tag. A view of N keyed components in N
 * loops is quadratic in N and reports nothing, so the cost again fell on
 * well-formed input, and again on input a downstream consumer lints in CI.
 *
 * Measured on this fixture (20,000 loops, ~1.8 MB): rescanning took **17.2s**,
 * the forward cursor **0.30s**. Five seconds sits ~17x above the linear cost
 * and ~3.4x below the quadratic one — the same deliberately wide margin as
 * above, for the same reason: the case must fail on a real regression to n²
 * and not on a loaded runner.
 */
it('scans a large view of keyed loops in linear time', function (): void {
    $loops = 20000;
    $view = "<div wire:poll class=\"feed\">\n";

    for ($index = 0; $index < $loops; $index++) {
        $view .= "    @foreach (\$rows as \$row)\n"
            . "        <livewire:card-{$index} wire:key=\"k{$index}\" />\n"
            . "    @endforeach\n";
    }

    $path = stageSource($view . "</div>\n");

    $started = microtime(true);
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $elapsed = (microtime(true) - $started);

    // Every component in a loop is keyed, so the only report is the root's own
    // wire:poll — which also pins that the loops were really walked rather than
    // skipped for free.
    expect(violationSourcesByLine($file->getErrors()))->toBe([1 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($elapsed)->toBeLessThan(5.0);
});

/**
 * Loop regions are collected one directive kind at a time — every `@foreach`
 * in the file, then every `@for`, and so on — so they do not arrive in the
 * order they appear in. Walking them with a forward cursor without sorting
 * them first steps straight past the `@for` below, because the `@foreach`
 * further down the file was collected ahead of it, and the keyless component
 * inside it goes unreported.
 *
 * The keyed component in the second loop is the control: it proves the later
 * region is still being read, so this is the ordering and not the loop check
 * having stopped.
 */
it('flags a keyless component in a loop that a later directive kind follows', function (): void {
    $path = stageSource(
        "<div wire:poll class=\"feed\">\n"
            . "    @for (\$i = 0; \$i < 3; \$i++)\n"
            . "        <livewire:tick-item />\n"
            . "    @endfor\n"
            . "\n"
            . "    @foreach (\$rows as \$row)\n"
            . "        <livewire:row-item :row=\"\$row\" />\n"
            . "    @endforeach\n"
            . "</div>\n"
    );

    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        1 => [ROOT_ELEMENT_ATTRIBUTES],
        3 => [MISSING_WIRE_KEY_IN_LOOP],
        7 => [MISSING_WIRE_KEY_IN_LOOP],
    ]);
});

/**
 * A component in a loop nested inside another loop is still in a loop, and the
 * regions the two produce overlap. The keyless one is reported; the keyed one
 * beside it is not, which is what keeps this from passing against a sniff that
 * flags every component in sight.
 */
it('flags a keyless component inside nested loops', function (): void {
    $path = stageSource(
        "<div wire:poll class=\"feed\">\n"
            . "    @while (\$page->hasMore())\n"
            . "        @foreach (\$rows as \$row)\n"
            . "            <livewire:row-item :row=\"\$row\" />\n"
            . "            <livewire:row-note wire:key=\"note\" />\n"
            . "        @endforeach\n"
            . "    @endwhile\n"
            . "</div>\n"
    );

    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        1 => [ROOT_ELEMENT_ATTRIBUTES],
        4 => [MISSING_WIRE_KEY_IN_LOOP, ADJACENT_COMPONENT_NOT_WRAPPED],
        5 => [ADJACENT_COMPONENT_NOT_WRAPPED],
    ]);
});

/**
 * The comment blanking must stay linear on a view holding unclosed comments.
 *
 * An unclosed `<!--` is an ordinary editing mistake, and the lazy
 * `/<!--.*?-->/s` this replaced re-read the rest of the file from every opener
 * in it. The cost was paid in reconstructMarkup(), *before* the Livewire gate,
 * so it fell on every view PHPCS was pointed at rather than only Livewire ones
 * — and on a downstream consumer's CI, which is what lints unreviewed content.
 *
 * Measured on this fixture (16,000 openers, ~480 KB) through the sniff in
 * process: the lazy pattern took **34.7s**, the forward scan **0.06s**. Five
 * seconds sits ~80x above the linear cost and ~7x below the quadratic one —
 * the same deliberately wide margin the two cases above take, for the same
 * reason.
 *
 * The reported violations are the second half of the case, and they are why
 * this is not only a timing test: an unclosed comment must leave the markup
 * after it readable rather than swallow the file to its end.
 */
it('scans a view of unclosed comments in linear time', function (): void {
    $openers = 16000;
    $view = "<div wire:poll class=\"feed\">\n"
        . str_repeat("    <!-- a note nobody closed\n", $openers)
        . "    @foreach (\$rows as \$row)\n"
        . "        <livewire:row-item :row=\"\$row\" />\n"
        . "    @endforeach\n"
        . "</div>\n";

    $path = stageSource($view);

    $started = microtime(true);
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $elapsed = (microtime(true) - $started);

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        1 => [ROOT_ELEMENT_ATTRIBUTES],
        ($openers + 3) => [MISSING_WIRE_KEY_IN_LOOP],
    ])->and($elapsed)->toBeLessThan(5.0);
});

/**
 * A Blade comment is closed by `--}}` and an HTML comment by `-->`, and one of
 * the two being unclosed says nothing about the other.
 *
 * The scan remembers a closer as absent only for its own opener, and this is
 * the case that holds it to that: an unclosed `<!--` above a well-formed
 * `{{-- … --}}` must not stop the Blade comment from being blanked. The
 * commented-out pair inside it is adjacent and unwrapped, so failing to blank
 * it reports two violations that are not in the view at all.
 */
it('blanks a Blade comment below an unclosed HTML comment', function (): void {
    $path = stageSource(
        "<div wire:poll class=\"feed\">\n"
            . "    <!-- a note nobody closed\n"
            . "    {{-- <livewire:one /><livewire:two /> --}}\n"
            . "</div>\n"
    );

    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);

    expect(violationSourcesByLine($file->getErrors()))->toBe([1 => [ROOT_ELEMENT_ATTRIBUTES]]);
});

/**
 * The element-tag read must stay linear on a view whose tags do not parse.
 *
 * The pattern's unquoted attribute run now stops at `<` as well as `>`, so a
 * tag it cannot complete is abandoned at the next tag. Without that, a view of
 * tag openers with an unpaired quote below them — and no `>` in between — was
 * re-read from every opener, once per opener, by the every-tag scan
 * isComponentView() makes.
 *
 * Measured on this fixture (16,000 openers, ~80 KB) through the sniff in
 * process: the unbounded class took **22.3s**, the bounded one **0.05s**. The
 * file is a sixth the size of the unclosed-comment case above and still costs
 * two thirds as much, which is the point: this is a second, independent
 * quadratic rather than another face of the same one. Reverting either fix
 * alone reddens only its own case.
 */
it('scans a view of unparseable tags in linear time', function (): void {
    $openers = 16000;
    $view = "<div wire:poll class=\"feed\">\n"
        . str_repeat("<a x\n", $openers)
        . "\"\n"
        . "@foreach (\$rows as \$row)\n"
        . "<livewire:row-item :row=\"\$row\" />\n"
        . "@endforeach\n";

    $path = stageSource($view);

    $started = microtime(true);
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $elapsed = (microtime(true) - $started);

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        1 => [ROOT_ELEMENT_ATTRIBUTES],
        ($openers + 4) => [MISSING_WIRE_KEY_IN_LOOP],
    ])->and($elapsed)->toBeLessThan(5.0);
});

/**
 * A view whose first element tag does not parse is left alone, rather than
 * having the *next* tag promoted to root in its place.
 *
 * The root is read at the first `<` that starts a tag, and the tag has to parse
 * there. Reading it as "the first tag the pattern matches anywhere" would step
 * over the `<div>` below — its unquoted `data-range=1<2` is not an attribute
 * list the pattern completes — and report the `<button>` under it, which is a
 * child element carrying exactly the `wire:` attribute a child is entitled to.
 * A silence is the honest answer where the source is not readable.
 */
it('says nothing about a view whose first element tag does not parse', function (): void {
    $path = stageSource(
        "<div data-range=1<2>\n"
            . "    <button wire:model=\"query\">Search</button>\n"
            . "</div>\n"
    );

    expect(analyzeWithSniffs([COMPONENT_MARKUP], $path)->getErrors())->toBe([]);
});

/**
 * The tag read can fail outright, and the component-view gate has to say so.
 *
 * ELEMENT_TAG's tag-name group and its unquoted attribute run share a
 * character set, so a `<` followed by a long unbroken run of those characters
 * and then a quote the pattern cannot pair splits every way between the two
 * groups, and preg_match_all() returns false.
 *
 * The fixture's run is 8,008 characters, which is deliberately far past the
 * point it has to clear rather than just past it. Measured against PHP 8.4's
 * default million-step pcre.backtrack_limit, the failure starts at 816
 * characters with the PCRE JIT off and 1,412 with it on — so the fixture sits
 * ~9.8x and ~5.7x above the two, and the case does not turn on a runner's JIT
 * setting or on one PCRE build counting steps slightly differently. The cost
 * is bounded by the limit itself either way: ~12ms unJITted, ~3ms JITted.
 *
 * A failed call does not leave $matches empty. It keeps whatever the engine
 * matched before it gave out — here the `<div wire:model="query" …>` the
 * fixture opens with, matched long before the run that ends the read. So the
 * old code had something to iterate, and iterating it answers "a component's
 * own view" off a read that stopped partway through the file. Which answer a
 * partial read produces is an accident of where the engine happened to stop,
 * which is the whole reason the failure needs an exit of its own.
 *
 * That accident is what this case is built to expose. The fixture's root
 * carries wire:model, so the two paths diverge in what the sniff reports:
 * answering off the partial read says "component view", and the root-element
 * check then reports RootElementAttributes on that root; refusing to answer
 * reports nothing. Restore the pre-fix `foreach` over an unchecked call and
 * this case goes red on a RootElementAttributes error it did not expect.
 *
 * The precondition below is the other layer, and it pins the fixture rather
 * than the branch: it proves, without the sniff, that this markup really does
 * drive ELEMENT_TAG to a backtrack-limit failure while still yielding the root
 * tag — so the fixture cannot rot into one that exercises nothing, and a green
 * run cannot come from the read quietly starting to succeed. Alongside the
 * errors, the sniff-level check reads warnings too: a PHP warning or error
 * escaping the run reddens this case through phpunit.xml.dist's failOnWarning,
 * and an exception through the run itself.
 *
 * The pattern is read off the sniff rather than transcribed, following
 * passiveNonOperandTokens() in tests/Helpers.php: a transcription would keep
 * passing after ELEMENT_TAG was rewritten into a pattern the fixture no longer
 * breaks.
 */
it('says nothing about a view whose element tags cannot be read at all', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'ELEMENT_TAG'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-element-tags.php')
    );

    // Read immediately: preg_last_error() is process-global and any later
    // preg_* call — including one inside expect() — would overwrite it.
    $matched = preg_match_all($pattern, $markup, $matches, PREG_SET_ORDER);
    $error = preg_last_error();

    expect($matched)->toBeFalse()
        ->and($error)->toBe(PREG_BACKTRACK_LIMIT_ERROR)
        ->and($matches)->not->toBe([])
        ->and($matches[0][1])->toBe('div')
        ->and($matches[0][2])->toContain('wire:model');

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-element-tags.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The component-tag read can fail outright, and the tag list has to say so.
 *
 * COMPONENT_TAG carries the same shape ELEMENT_TAG carries: its tag-name group
 * (`livewire:[A-Za-z0-9._-]+`) and its unquoted attribute run share a character
 * set, so a `<livewire:` followed by a long unbroken run of those characters and
 * then a quote the pattern cannot pair splits every way between the two groups,
 * and preg_match_all() returns false.
 *
 * The fixture's run is 8,008 characters — the same length unreadable-element-
 * tags.php uses, and deliberately far past the point it has to clear rather than
 * just past it. Measured against PHP 8.4's default million-step
 * pcre.backtrack_limit, on the fixture's own markup: the failure starts at 811
 * characters with the PCRE JIT off and 1,406 with it on, so the fixture sits
 * ~9.9x and ~5.7x above the two. The shared alternation is why those two numbers
 * land within a handful of characters of ELEMENT_TAG's own 816 and 1,412. The
 * cost is bounded by the limit either way: ~12ms unJITted, well under 1ms JITted.
 *
 * **This case does not tell the fixed code from the pre-fix code, and is not
 * written to.** componentTags()' failure exit is a deliberate branch, not a
 * behaviour change: the corrupted tag is the first Livewire tag in the file, so
 * the failed read collects nothing before it gives out, and an unchecked
 * `foreach` over those nought matches builds the same empty tag list the exit
 * returns. Verified by removing both early returns and re-running: the reported
 * violations are identical. A file holding a *valid* Livewire tag ahead of the
 * corrupted one would tell them apart — the exit drops that tag where the
 * fallthrough sometimes keeps it — but that is a real behaviour change on
 * partially-readable markup, out of scope here (#334) and deliberately not
 * covered by this fixture.
 *
 * What the two layers below do pin is everything else. The precondition proves,
 * without the sniff, that this markup really does drive COMPONENT_TAG to a
 * backtrack-limit failure and that it yields *no* matches when it does — so the
 * fixture cannot rot into one that exercises nothing, and the "empty either way"
 * reasoning above cannot quietly stop holding. The sniff-level layer proves the
 * failure stays a silence: nothing is reported, and no PHP warning or error
 * escapes the run (phpunit.xml.dist's failOnWarning is what makes that bite).
 *
 * The control at the end is what keeps the silence from being vacuous. Strip the
 * corrupted tag out of the same markup and the read succeeds, at which point the
 * fixture's `@foreach` — a component with no `wire:key` — is reported. So the
 * fixture does hold markup this sniff has something to say about, and the
 * silence above is the failed read's doing rather than an empty file's.
 *
 * The pattern is read off the sniff rather than transcribed, following
 * unreadable-element-tags.php's own case above: a transcription would keep
 * passing after COMPONENT_TAG was rewritten into a pattern the fixture no
 * longer breaks.
 */
it('says nothing about a view whose component tags cannot be read at all', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'COMPONENT_TAG'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-component-tags.php')
    );

    // Read immediately: preg_last_error() is process-global and any later
    // preg_* call — including one inside expect() — would overwrite it.
    $matched = preg_match_all($pattern, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $error = preg_last_error();

    expect($matched)->toBeFalse()
        ->and($error)->toBe(PREG_BACKTRACK_LIMIT_ERROR)
        ->and($matches)->toBe([]);

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-component-tags.php');

    expect(allViolationSourcesByLine($file))->toBe([])
        ->and($file->getWarnings())->toBe([]);

    // The same markup with only the corrupted tag taken out: the read now
    // finishes, and what it finds is a component in a loop with no wire:key.
    $readable = (string) preg_replace('/<livewire:(?:report-row-)+"/', '', $markup);

    expect($readable)->not->toBe($markup);

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(array_merge(...array_values(allViolationSourcesByLine($control))))
        ->toContain(MISSING_WIRE_KEY_IN_LOOP);
});

/**
 * The wrapper read can fail too, and an empty wrapper list is not a silence.
 *
 * TEMPLATE_TAG does not fail the way the two patterns above fail. Its tag name
 * is the literal word `template`, so there is no tag-name/attribute-run
 * alternation to split and no backtrack blow-up. What gives out is the
 * attribute-run group itself: it repeats over single characters, one level of
 * recursion per character, so a long enough attribute list exhausts the engine's
 * depth instead of its step budget.
 *
 * Which constant that is depends on the PCRE JIT, and the fixture is sized to
 * fail either way. Measured on PHP 8.4's defaults, against the fixture's own
 * markup: with the JIT off the failure starts at 99,995 characters and reports
 * PREG_RECURSION_LIMIT_ERROR (pcre.recursion_limit's default is 100,000, which
 * is the number that threshold is really tracking); with the JIT on the JIT's
 * own stack gives out first, from 8,190 characters, and reports
 * PREG_JIT_STACKLIMIT_ERROR. The fixture's run is 600,000 characters — ~6.0x
 * above the higher of the two thresholds, a margin in the same order as
 * unreadable-element-tags.php's own. Neither constant is
 * PREG_BACKTRACK_LIMIT_ERROR, which is the substantive point: this is a
 * different mechanism from componentTags()' failure, reachable only at a far
 * larger input, and the assertion below pins that in both directions.
 *
 * **Like the case above, this one does not tell the fixed code from the pre-fix
 * code.** The corrupted wrapper is the first one in the file, so the failed read
 * collects nothing, and the deliberate `return []` and an unchecked `foreach`
 * over nought matches produce the same empty list. Verified by removing both
 * early returns and re-running: identical output.
 *
 * What it does pin is the *effect* of that empty list, which is the part worth
 * writing down. reportUnwrapped() looks each component's wrapper up by offset,
 * so an empty list misses every lookup, and the sniff reports
 * AdjacentComponentNotWrapped against two components that are correctly wrapped
 * — a false positive. That is templateTags()' behaviour today; #334's fix makes
 * the empty list deliberate and tested, and does **not** make the false positive
 * go away. Asserting it here is a record of a known defect, not a claim that it
 * is correct.
 *
 * The control at the end is what proves that reading. Take the corrupted wrapper
 * out and the very same pair goes quiet, so the two errors above are the failed
 * read's doing and not a malformed wrapper's.
 */
it('reports correctly wrapped components when the wrapper tags cannot be read at all', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'TEMPLATE_TAG'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-template-tags.php')
    );

    // Read immediately: preg_last_error() is process-global and any later
    // preg_* call — including one inside expect() — would overwrite it.
    $matched = preg_match_all($pattern, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $error = preg_last_error();
    $jitted = (ini_get('pcre.jit') === '1');

    expect($matched)->toBeFalse()
        ->and($error)->toBe($jitted ? PREG_JIT_STACKLIMIT_ERROR : PREG_RECURSION_LIMIT_ERROR)
        ->and($error)->not->toBe(PREG_BACKTRACK_LIMIT_ERROR)
        ->and($matches)->toBe([]);

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-template-tags.php');

    expect(allViolationSourcesByLine($file))->toBe([
        29 => [ADJACENT_COMPONENT_NOT_WRAPPED],
        30 => [ADJACENT_COMPONENT_NOT_WRAPPED],
    ])->and($file->getWarnings())->toBe([]);

    // The same markup with only the corrupted wrapper taken out: the read now
    // finishes, the two wrappers are found, and the pair is left alone.
    $readable = (string) preg_replace('/<template (?:data-column-)+>/', '', $markup);

    expect($readable)->not->toBe($markup);

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(allViolationSourcesByLine($control))->toBe([]);
});

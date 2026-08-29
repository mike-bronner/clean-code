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
use MikeBronner\CleanCode\Tests\PregFailure;

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
 * Measured on this fixture (8,000 components, ~750 KB), the quadratic
 * implementation took **22.7s** and the linear one **0.11s** — but a wall-clock
 * budget between the two states the claim only as far as a shared CI runner
 * allows, and a rewrite that only shortens a walk changes no violation, so no
 * fixture reddens on it either. The sniff counts both passes instead, and this
 * is where both counts are pinned:
 *
 * - the wrapper list is matched out of the view once, not once per component
 *   tag;
 * - the line walk reads the view once end to end, not from offset 0 per tag.
 *   One pass totals at most the view's own length, whatever the tags number;
 *   a count from offset 0 per tag totals the sum of the tags' offsets, which
 *   at this size is thousands of times the view.
 *
 * The reported violation is the second half of each: a pass that had stopped
 * reading the view would count right for the wrong reason.
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

    $sniff = sniffInstance(COMPONENT_MARKUP);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    // Wrapped and keyed throughout, so the only report is the root element's
    // own wire:poll. Asserting it pins that the run really did analyse the
    // view rather than bailing out early and counting nothing for free.
    expect(violationSourcesByLine($file->getErrors()))->toBe([1 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($counted['templateTags.reads'])->toBe(
            1,
            'the wrapper list is matched out of the view once, not once per component tag'
        )
        ->and($counted['componentTags.lineBytes'])->toBeLessThan(
            strlen($view) + 7,
            'the line walk reads the view once end to end, not from offset 0 per tag'
        );
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
 * Measured on this fixture (20,000 loops, ~1.8 MB), rescanning took **17.2s**
 * and the forward cursor **0.30s** — and the same objection as above applies to
 * putting a budget between them, so the cursor's advances are counted instead.
 * A cursor that only moves forward advances once per region it steps past over
 * the whole pass, which is n-1 for n loops each holding one component: every
 * tag but the first steps past exactly the region in front of it. Asking each
 * tag which of all the regions it falls in totals n²/2 advances instead.
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

    $sniff = sniffInstance(COMPONENT_MARKUP);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    // Every component in a loop is keyed, so the only report is the root's own
    // wire:poll — which also pins that the loops were really walked rather than
    // skipped for free.
    expect(violationSourcesByLine($file->getErrors()))->toBe([1 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($counted['loopRegions.steps'])->toBe(
            $loops - 1,
            'the cursor steps past each region once over the whole pass, not once per tag'
        );
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
 * process, the lazy pattern took **34.7s** and the forward scan **0.06s** — and
 * again the counts state it where a budget between the two only approximates
 * it. A closer looked for once and then remembered as absent costs one search
 * for the file; the openers after the first are answered by the guard that
 * remembered it. Both numbers are pinned, because the search count alone would
 * read the same on a scan that had stopped finding the openers at all.
 *
 * The reported violations are the third half of the case: an unclosed comment
 * must leave the markup after it readable rather than swallow the file to its
 * end.
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

    $sniff = sniffInstance(COMPONENT_MARKUP);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        1 => [ROOT_ELEMENT_ATTRIBUTES],
        ($openers + 3) => [MISSING_WIRE_KEY_IN_LOOP],
    ])
        ->and($counted['comments.closerScans'])->toBe(
            1,
            'the absent closer is looked for once for the file, not once per opener'
        )
        ->and($counted['comments.unterminatedSkips'])->toBe(
            $openers - 1,
            'every opener after the first is answered by the guard that remembered it'
        );
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
 *
 * The cost here is PCRE's, not the sniff's, so there is no branch in this file
 * to count — but PCRE keeps the count itself. Every match attempt is bounded by
 * `pcre.backtrack_limit`, and that budget is the exact quantity the two classes
 * differ in: the bounded one abandons an unfinishable tag at the next `<` and
 * needs a fixed handful of steps whatever the run in front of it, while the
 * unbounded one splits the run every way between the tag name and the attribute
 * list and needs steps proportional to it. So the budget is lowered to a
 * hundredth of PHP's default for the duration and the same two assertions are
 * made at two sizes: one small enough that even the unbounded class fits, one
 * that only a fixed cost fits. Deterministic on any machine — a step count is
 * not a stopwatch — and the assertion is unchanged otherwise.
 *
 * Measured against this fixture at the lowered budget: the bounded class
 * completes at every size, the unbounded one exhausts the budget from 2,000
 * openers up. preg_match_all() then returns false, isComponentView() answers
 * off nothing rather than off a fragment, and the root element goes unreported
 * — so the violation list below is what reddens.
 */
it('scans a view of unparseable tags without backtracking that grows with it', function (): void {
    $budget = (string) ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', '10000');

    try {
        foreach ([200, 16000] as $openers) {
            $view = "<div wire:poll class=\"feed\">\n"
                . str_repeat("<a x\n", $openers)
                . "\"\n"
                . "@foreach (\$rows as \$row)\n"
                . "<livewire:row-item :row=\"\$row\" />\n"
                . "@endforeach\n";

            $file = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($view, "view-{$openers}.blade.php"));

            expect(violationSourcesByLine($file->getErrors()))->toBe([
                1 => [ROOT_ELEMENT_ATTRIBUTES],
                ($openers + 4) => [MISSING_WIRE_KEY_IN_LOOP],
            ], "n={$openers}: the tag read completes within a budget that does not grow with the view");
        }
    } finally {
        ini_set('pcre.backtrack_limit', $budget);
    }
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

/**
 * The gap between the root element and the first component can fail to read,
 * and a failed read must not be mistaken for an empty gap.
 *
 * opensOnAComponent() answers one question — does anything but `<template>`
 * tags and whitespace stand between the view's first element tag and its first
 * component tag — by stripping the wrappers out of that gap with
 * TEMPLATE_WRAPPER and testing what is left. `true` tells checkRootElement()
 * there is no root of this view's own to judge, and the root-element check is
 * dropped.
 *
 * TEMPLATE_WRAPPER fails the way TEMPLATE_TAG fails and not the way
 * ELEMENT_TAG/COMPONENT_TAG fail: its tag name is the literal word `template`,
 * so there is no tag-name/attribute-run alternation to split and no backtrack
 * blow-up. What gives out is the attribute-run group, which recurses once per
 * character.
 *
 * Which constant that is depends on the PCRE JIT, and the fixture is sized to
 * fail either way. Measured on PHP 8.4's defaults, against this pattern on the
 * fixture's own gap: with the JIT off the failure starts at an attribute run of
 * 99,996 characters and reports PREG_RECURSION_LIMIT_ERROR
 * (pcre.recursion_limit's default is 100,000, the number that threshold is
 * really tracking); with the JIT on the JIT's own stack gives out first, from
 * 8,191 characters, and reports PREG_JIT_STACKLIMIT_ERROR. Those are
 * TEMPLATE_WRAPPER's own numbers, re-measured rather than carried over: it
 * fails one character later than TEMPLATE_TAG does on the same subject, because
 * it adds the `<\/?` closing-tag alternative and drops the capturing group. The
 * fixture's run is 600,000 characters — ~6.0x above the higher of the two
 * thresholds, the same margin unreadable-template-tags.php keeps. Neither
 * constant is PREG_BACKTRACK_LIMIT_ERROR, and the assertion below pins that in
 * both directions.
 *
 * **Unlike the three unreadable-*-tags cases above, this one does tell the
 * fixed code from the pre-fix code, which is the point of it.** The pre-fix
 * line cast the failed read with `(string)`, so `null` became `''` and
 * `trim('') === ''` answered `true`: the root check was dropped and the
 * fixture's `wire:model` root — a genuine RootElementAttributes violation —
 * escaped in silence. Restore that cast and this case goes red on an empty
 * violation map where it expected the error. That is a detection bypass, not a
 * false positive, reachable by anyone authoring the view the sniff lints.
 *
 * The fixture is built so that this call site is the *only* read that fails.
 * The oversized run sits on a closing `</template …>` tag: ELEMENT_TAG,
 * TEMPLATE_TAG and COMPONENT_TAG all require `<` followed by a letter, so none
 * of them begins a match there and none of them ever walks the run —
 * isComponentView() and componentTags() both finish, and the root-element check
 * is genuinely reached. TEMPLATE_WRAPPER is the only pattern that matches the
 * closing form. Without that isolation the fixture would prove nothing: an
 * ELEMENT_TAG failure would make isComponentView() return false and drop the
 * root check for a different reason entirely.
 *
 * The precondition is the other layer, and it pins the fixture rather than the
 * branch: it proves, without the sniff, that this gap really does drive
 * TEMPLATE_WRAPPER to a depth failure — so the fixture cannot rot into one that
 * exercises nothing, and a green run cannot come from the read quietly starting
 * to succeed. It calls preg_replace(), not preg_match_all(), because
 * preg_replace() is what the sniff calls and `null` — not `false` — is how
 * preg_replace() reports failure. The call is unsuppressed: a PHP warning or
 * error escaping it reddens this case through phpunit.xml.dist's failOnWarning,
 * as does one escaping the sniff run below it.
 *
 * Two controls sit at the end, because the verdict alone cannot carry the
 * proof here. Shrink the same run and the read finishes, at which point the
 * sniff reaches the very same verdict — the error above is the fixed code
 * answering correctly despite the failed read. That step cannot flip the
 * verdict, and no step could: opensOnAComponent() measures its gap from the
 * root element's *own* tag (ComponentMarkupSniff.php:448, off the ELEMENT_TAG
 * match offset at :376-384), and TEMPLATE_WRAPPER's tag name is the literal
 * word `template`, so the root's opening tag — the disallowed attribute
 * included — survives every strip, readable run or not. `trim($stripped)` is
 * never `''` for a fixture that carries a real root violation, which this one
 * must. So the second control moves that attribute off the root, onto an inner
 * element, and the file falls silent. Moved rather than deleted because the
 * same attribute is what makes isComponentView() call this a component's own
 * view at all: delete it and the silence would be checkRootElement() bailing at
 * its first line, which says nothing about the root. Between them the two
 * controls answer both halves — the verdict is a judgement this sniff reached
 * about this root, not a report it would file about any root it is handed.
 *
 * The pattern is read off the sniff rather than transcribed, following
 * unreadable-element-tags.php's own case above: a transcription would keep
 * passing after TEMPLATE_WRAPPER was rewritten into a pattern the fixture no
 * longer breaks.
 */
it('judges the root when the gap before the first component cannot be read', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'TEMPLATE_WRAPPER'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-wrapper-gap-root.php')
    );

    // The gap opensOnAComponent() reads: the first element tag's start to the
    // first component tag's start.
    $elementStart = strpos($markup, '<div');
    $componentStart = strpos($markup, '<livewire:');
    $gap = substr($markup, $elementStart, ($componentStart - $elementStart));

    // The oversized run, located without a regex — the search starts inside the
    // gap, so the fixture's own prose comment (which names the tag) cannot be
    // found instead — and pinned past both measured thresholds, so the failure
    // below cannot depend on the running PHP's pcre.jit setting.
    $runStart = (strpos($markup, '</template ', $elementStart) + strlen('</template '));
    $runLength = (strpos($markup, '>', $runStart) - $runStart);

    expect($runLength)->toBeGreaterThan(99996);

    // Read immediately: preg_last_error() is process-global and any later
    // preg_* call — including one inside expect() — would overwrite it.
    $stripped = preg_replace($pattern, '', $gap);
    $error = preg_last_error();
    $jitted = (ini_get('pcre.jit') === '1');

    expect($stripped)->toBeNull()
        ->and($error)->toBe($jitted ? PREG_JIT_STACKLIMIT_ERROR : PREG_RECURSION_LIMIT_ERROR)
        ->and($error)->not->toBe(PREG_BACKTRACK_LIMIT_ERROR);

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-wrapper-gap-root.php');

    expect(allViolationSourcesByLine($file))->toBe([33 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($file->getWarnings())->toBe([]);

    // The same markup with the oversized run shrunk to one repetition: the gap
    // now reads, and the sniff reports the same violation off a gap it saw.
    //
    // Cut with substr_replace() rather than preg_replace(): a pattern walking
    // the run is itself liable to the depth failure this fixture is built to
    // cause, and a failed cut returns null, which casts to '' and would leave
    // the control asserting against an empty file that trivially reports
    // nothing.
    $readable = substr_replace($markup, 'data-column-', $runStart, $runLength);

    expect($readable)->not->toBe($markup)
        ->and(strlen($readable))->toBeLessThan(strlen($markup));

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(allViolationSourcesByLine($control))->toBe([33 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($control->getWarnings())->toBe([]);

    // The readable markup with the root's own disallowed attribute moved off
    // the root and onto an inner element: the gap still reads, the root is
    // still judged, and it now has nothing to answer for — so the file falls
    // silent. That is the flip the control above cannot make.
    //
    // Moved rather than deleted, and this is the whole care of the step.
    // wire:model is also the OWN_WIRE_DIRECTIVE that makes isComponentView()
    // call this a component's own view; delete it and checkRootElement() bails
    // at its first line, so the silence would come from the view no longer
    // being one this sniff judges at all — the same verdict off a different
    // path, which proves nothing about the root check. Kept in the file, on an
    // element that is not the root, the view is still a component's own view
    // and the root check still runs.
    //
    // Both cuts are by literal and pinned to one occurrence each, so a later
    // edit that spells either of them a second time reddens here rather than
    // widening the replacement in silence.
    $root = '<div wire:model="query" class="editor">';
    $close = '</div>';

    expect(substr_count($readable, $root))->toBe(1)
        ->and(substr_count($readable, $close))->toBe(1);

    $compliant = str_replace(
        [$root, $close],
        ['<div class="editor">', '    <span wire:model="query"></span>' . PHP_EOL . $close],
        $readable
    );

    // The gate proved open, not assumed: without this the step below would pass
    // just as well on the deleted-attribute markup this one is written to avoid.
    $isComponentView = new ReflectionMethod(ComponentMarkupSniff::class, 'isComponentView');

    expect($isComponentView->invoke(new ComponentMarkupSniff(), $compliant))->toBeTrue();

    $judged = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($compliant));

    expect(allViolationSourcesByLine($judged))->toBe([])
        ->and($judged->getWarnings())->toBe([]);
});

/**
 * The gap between two sibling components can fail to read too, and there the
 * mistake runs the other way.
 *
 * areAdjacent() answers whether nothing but whitespace and the components' own
 * `<template>` wrappers separates one sibling's element from the next, by the
 * same strip-and-trim over TEMPLATE_WRAPPER. `true` sends both siblings to
 * reportUnwrapped(), which is where AdjacentComponentNotWrapped and
 * TemplateKeyMismatch come from.
 *
 * The failure mechanism, the two measured thresholds and the fixture's
 * 600,000-character sizing are the root-gap case's above, and hold for the same
 * reason: one pattern, one call, one depth limit. The fixture uses the same
 * closing-`</template …>` isolation, so ELEMENT_TAG, TEMPLATE_TAG and
 * COMPONENT_TAG all finish and TEMPLATE_WRAPPER is the only read that fails.
 *
 * **This case tells the fixed code from the pre-fix code as well.** The pre-fix
 * `(string)` cast turned the failed read into `''`, and `trim('') === ''` said
 * the two components had nothing between them — so the sniff reported
 * AdjacentComponentNotWrapped against a pair separated by a whole paragraph
 * element. Restore that cast and this case goes red on two errors it did not
 * expect. That is a false positive on correct code, the opposite direction from
 * the root-gap case's detection bypass, and it is why one fix needs both
 * fixtures.
 *
 * Two controls sit at the end, because a silence needs both halves proved.
 * Shrink the run and the gap reads: the paragraph is still there, the pair is
 * still not adjacent, and the sniff stays quiet — the fixed code's answer on the
 * unreadable gap is the same answer the readable gap earns. Then take the
 * paragraph out of that readable markup and the pair *is* adjacent, and both
 * components are reported. So this fixture does hold a pair the sniff has
 * something to say about, and the silence above is the guard's doing rather
 * than a file the sniff was never going to speak about.
 */
it('leaves siblings alone when the gap between them cannot be read', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'TEMPLATE_WRAPPER'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-wrapper-gap-siblings.php')
    );

    // The gap areAdjacent() reads: the first component's element end to the
    // next component's start.
    $previousEnd = (strpos($markup, '>', strpos($markup, '<livewire:')) + 1);
    $currentStart = strpos($markup, '<livewire:', $previousEnd);
    $gap = substr($markup, $previousEnd, ($currentStart - $previousEnd));

    // The oversized run, located without a regex — the search starts inside the
    // gap, so the fixture's own prose comment (which names the tag) cannot be
    // found instead — and pinned past both measured thresholds, so the failure
    // below cannot depend on the running PHP's pcre.jit setting.
    $runStart = (strpos($markup, '</template ', $previousEnd) + strlen('</template '));
    $runLength = (strpos($markup, '>', $runStart) - $runStart);

    expect($runLength)->toBeGreaterThan(99996);

    // Read immediately: preg_last_error() is process-global and any later
    // preg_* call — including one inside expect() — would overwrite it.
    $stripped = preg_replace($pattern, '', $gap);
    $error = preg_last_error();
    $jitted = (ini_get('pcre.jit') === '1');

    expect($stripped)->toBeNull()
        ->and($error)->toBe($jitted ? PREG_JIT_STACKLIMIT_ERROR : PREG_RECURSION_LIMIT_ERROR)
        ->and($error)->not->toBe(PREG_BACKTRACK_LIMIT_ERROR);

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-wrapper-gap-siblings.php');

    expect(allViolationSourcesByLine($file))->toBe([])
        ->and($file->getWarnings())->toBe([]);

    // The same markup with the oversized run shrunk to one repetition: the gap
    // now reads, the paragraph between the two is seen, and the pair is still
    // left alone — the same answer the guard gives above.
    //
    // Cut with substr_replace() rather than preg_replace(), for the reason the
    // root-gap case above spells out: a pattern walking the run is liable to
    // the very failure the fixture causes.
    $readable = substr_replace($markup, 'data-column-', $runStart, $runLength);

    expect($readable)->not->toBe($markup)
        ->and(strlen($readable))->toBeLessThan(strlen($markup));

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(allViolationSourcesByLine($control))->toBe([])
        ->and($control->getWarnings())->toBe([]);

    // The readable markup with the paragraph taken out as well: nothing but
    // whitespace and wrappers is left between the two, so they really are
    // adjacent and both are reported. The silence above is the guard's, not the
    // fixture's.
    $paragraph = substr(
        $readable,
        strpos($readable, '<p>'),
        ((strpos($readable, '</p>') + strlen('</p>')) - strpos($readable, '<p>'))
    );
    $adjacent = str_replace($paragraph, '', $readable);

    expect($adjacent)->not->toBe($readable)
        ->and($paragraph)->toStartWith('<p>')
        ->and($paragraph)->toEndWith('</p>');

    $pair = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($adjacent));

    expect(array_merge(...array_values(allViolationSourcesByLine($pair))))
        ->toBe([ADJACENT_COMPONENT_NOT_WRAPPED, ADJACENT_COMPONENT_NOT_WRAPPED])
        ->and($pair->getWarnings())->toBe([]);
});

/**
 * blankComments() substitutes same-length blanks for a comment so that every
 * offset and line number after it stays where it was. A failed read cast to a
 * string is '', which shortens the blanked copy by the whole comment and drags
 * every later line number up with it.
 *
 * The assertion is a reported line number rather than "the sniff still runs",
 * because the shift is silent: the sniff reports the same violation, at the
 * wrong line. Only a fixture whose violation sits *after* a comment can tell
 * the two apart, and comment-before-violation.php is that fixture —
 * failing.php carries no comment at all, so it cannot. The two adjacent
 * components sit at lines 10 and 11, below a comment spanning lines 2 to 8:
 * drop the `?? $comment` fallback and the same run reports them at 3 and 4.
 *
 * The failure is forced at the call boundary rather than by an adversarial
 * fixture because `/[^\r\n]/` has no quantifier to backtrack over and no `/u`
 * modifier — no input drives it to failure, which is why the guard needs this
 * seam to be readable at all.
 */
it('keeps every line number after an unreadable comment where it was', function (): void {
    $expected = violationSourcesByLine(
        analyzeFixture(COMPONENT_MARKUP, 'comment-before-violation.php')->getErrors()
    );

    $blanked = PregFailure::during('preg_replace', static function (): array {
        return violationSourcesByLine(
            analyzeFixture(COMPONENT_MARKUP, 'comment-before-violation.php')->getErrors()
        );
    }, static fn (string $pattern): bool => $pattern === '/[^\r\n]/');

    expect(array_keys($expected))->toBe([1, 10, 11])
        ->and($blanked)->toBe($expected);
});

/**
 * attributeNames() reads the root element's attributes, and an empty list is
 * what checkRootElement() reads as "this root carries no Livewire, Blade or
 * Alpine attribute" — the RootElementAttributes bypass #366 closed by another
 * path. A failed value-strip must therefore not empty the list.
 *
 * Asserted through the sniff, on a fixture whose root does carry such an
 * attribute, so the answer read is the report itself and not an intermediate
 * value. Both guards are exercised: the value-strip by arming preg_replace,
 * the name read by arming preg_match_all. Remove either and the fixture's
 * RootElementAttributes error disappears.
 */
it('still reports a root attribute when the attribute list cannot be read', function (
    string $function,
    string $pattern
): void {
    $expected = violationSourcesByLine(analyzeFixture(COMPONENT_MARKUP, 'root-alpine-attribute.php')->getErrors());

    expect($expected)->not->toBe([]);

    $degraded = PregFailure::during($function, static function (): array {
        return violationSourcesByLine(analyzeFixture(COMPONENT_MARKUP, 'root-alpine-attribute.php')->getErrors());
    }, static fn (string $armed): bool => $armed === $pattern);

    expect($degraded)->toBe($expected);
})->with([
    'value strip' => ['preg_replace', '/=\s*(?:"[^"]*"|\'[^\']*\')/'],
    'name read' => ['preg_match_all', '/(?:^|\s)([^\s=<>"\'\/]+)/'],
]);

/**
 * loopRegions() drops a directive whose read gave out, so the loop it opens is
 * one the sniff does not police rather than one it polices off a fragment.
 *
 * Two things are asserted, and the second is the load-bearing one. The verdict
 * is that MissingWireKeyInLoop goes unreported, which is the direction the
 * guard's comment names. That much also holds without the guard, because a
 * failed read leaves $matches null and `foreach (null)` contributes no regions
 * either — so the verdict alone cannot tell a guard from its absence. What
 * separates them is the read itself: without the guard PHP announces the null
 * offset and the null foreach, and with it neither happens. See
 * withPhpDiagnostics() and tests/PregOverrides.php for why that is the honest
 * discriminator here.
 */
it('reports no loop key violation when the loop directives cannot be read', function (): void {
    $pattern = '/@(foreach|endforeach)\b/i';

    [$reported, $diagnostics] = withPhpDiagnostics(static function () use ($pattern): array {
        return PregFailure::during('preg_match_all', static function (): array {
            return violationSourcesByLine(analyzeFixture(COMPONENT_MARKUP, 'failing.php')->getErrors());
        }, static fn (string $armed): bool => $armed === $pattern);
    });

    $sources = [];

    foreach ($reported as $violations) {
        foreach ($violations as $source) {
            $sources[] = $source;
        }
    }

    expect($sources)->not->toContain('CleanCode.Livewire.ComponentMarkup.MissingWireKeyInLoop')
        ->and($diagnostics)->toBe([]);
});

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Livewire;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Livewire: Components (#46) — docs/standards/livewire-components.md.
 *
 * Detection is **heuristic and best-effort, by design**. A Livewire component
 * lives in a Blade view, which PHPCS hands over as a run of T_INLINE_HTML
 * tokens rather than an element tree, and Blade control flow (@if, @include,
 * @foreach over runtime data) means the *rendered* DOM shape is not decidable
 * from the template text at all. So this sniff reads the markup with regular
 * expressions and only speaks about shapes that are unambiguous in the source.
 * Everything else — a component whose loop is closed in another file, a root
 * element assembled by a directive, an @livewire() call whose key is a PHP
 * expression — is left unflagged rather than guessed at. False negatives are
 * the deliberate trade for not spamming false positives; the rest of the
 * standard stays with code review.
 *
 * Four narrowing decisions carry that trade, and each one is what keeps a
 * whole class of false positive out:
 *
 * - **The file must be recognisably Livewire markup** (a `wire:` attribute, a
 *   `<livewire:…>` tag, or an `@livewire` directive) before any check runs.
 *   Without the gate every plain Blade partial with an Alpine root would be
 *   reported, and "is this view a Livewire component?" is not otherwise
 *   answerable from one file.
 * - **The root-element rule needs more than that gate: the view must be a
 *   component's *own* view, not one that merely embeds a component.** A page
 *   or layout that drops a `<livewire:notifications-bell />` into an Alpine
 *   shell passes the gate, but its outer `<div x-data>` is the *layout's*
 *   root, not any component's, so reading it as one is a loud false positive.
 *   The view therefore has to carry a `wire:` attribute of its own — one
 *   outside every `<livewire:…>` tag — before the root is judged, and a first
 *   tag that opens a component is never read as the root: neither the
 *   `<livewire:…>` invocation itself nor the `<template>` this standard
 *   requires around it, whose `wire:key` attributes are the ones the sniff
 *   demands elsewhere rather than root-element violations.
 * - **A "component" is a `<livewire:…>` tag.** `<x-…>` is Blade's component
 *   namespace, shared by ordinary Blade components that need no `wire:key` at
 *   all, so it is not read as a Livewire component here.
 * - **`@livewire('name', …)` is not analysed.** Its key is a PHP expression
 *   argument (`key($row->id)`), not an attribute, so presence/equality cannot
 *   be read off the source.
 *
 * Adjacency is read over *siblings*, not over every component tag in document
 * order. Livewire's tag syntax allows a component to wrap content
 * (`<livewire:card>…</livewire:card>`), so the tags are walked with a depth
 * stack: a component nested inside an unclosed parent is that parent's child,
 * never the tag "next to" it.
 *
 * All four violations are reported on the line, not a token: the analysis runs
 * over reconstructed markup rather than the token stream, so a line number is
 * the finest position it can honestly claim.
 *
 * Detection only. Every fix — moving attributes off a root element, choosing a
 * `wire:key` expression, introducing a `<template>` wrapper — needs a value a
 * machine cannot derive, so there is no autofixed fixture.
 */
class ComponentMarkupSniff implements Sniff
{
    /**
     * What makes a file recognisably Livewire markup. See the gate note above.
     */
    private const LIVEWIRE_MARKUP = '/wire:[a-z]|<livewire:|@livewire\b/i';

    /**
     * An opening element tag, tolerating a quoted attribute value that itself
     * contains `>`.
     */
    private const ELEMENT_TAG = '/<([A-Za-z][A-Za-z0-9._:-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/';

    /**
     * A Livewire component tag, opening or closing: `<livewire:some-name …>`,
     * `<livewire:some-name … />`, `</livewire:some-name>`. Both forms are
     * matched by one pattern so the tags can be walked as a single stream of
     * open/close events and given a nesting depth.
     */
    private const COMPONENT_TAG =
        '/<(\/)?(livewire:[A-Za-z0-9._-]+)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/i';

    /**
     * An opening `<template …>` tag — a candidate component wrapper.
     */
    private const TEMPLATE_TAG = '/<template((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/i';

    /**
     * A `<template>` or `</template>` tag, stripped out of the gap between two
     * sibling components before the gap is tested for adjacency.
     */
    private const TEMPLATE_WRAPPER = '/<\/?template(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/i';

    /**
     * A `wire:` attribute, used on markup the `<livewire:…>` tags have been
     * removed from — so it only matches a directive the view writes itself.
     */
    private const OWN_WIRE_ATTRIBUTE = '/\bwire:[a-z]/i';

    /**
     * A `wire:key` attribute and its quoted value.
     */
    private const WIRE_KEY_ATTRIBUTE = '/\bwire:key\s*=\s*("[^"]*"|\'[^\']*\')/i';

    /**
     * Blade comments and HTML comments, blanked before analysis so that
     * commented-out markup is never reported.
     */
    private const COMMENT = '/<!--.*?-->|\{\{--.*?--\}\}/s';

    /**
     * Blade loop directives, each paired with its own `@end…` form.
     */
    private const LOOP_DIRECTIVES = [
        'foreach',
        'forelse',
        'for',
        'while',
    ];

    /**
     * Attribute-name prefixes that make an attribute a Livewire, Blade, or
     * Alpine one: `wire:model`, `x-data`, `@click`, `:class`, and a bare
     * `{{ $attributes }}` echo used in attribute position.
     */
    private const FRAMEWORK_ATTRIBUTE_PREFIXES = [
        'wire:',
        'x-',
        '@',
        ':',
        '{{',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_INLINE_HTML];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($stackPtr !== $phpcsFile->findNext(T_INLINE_HTML, 0)) {
            return;
        }

        $markup = $this->reconstructMarkup($phpcsFile);

        if (preg_match(self::LIVEWIRE_MARKUP, $markup) !== 1) {
            return;
        }

        $tags = $this->componentTags($markup);

        $this->checkRootElement($phpcsFile, $markup, $tags);
        $this->checkLoopKeys($phpcsFile, $markup, $tags);
        $this->checkAdjacentComponents($phpcsFile, $markup, $tags);
    }

    /**
     * Rebuilds the file's markup from its inline-HTML tokens, one file line per
     * markup line and each fragment at its own column.
     *
     * Everything PHPCS tokenised as PHP therefore comes back as blanks, which
     * is exactly what the heuristics want: `class="<?= $x ?>"` keeps its tag
     * structure while the interpolated PHP cannot be mistaken for markup. Blade
     * syntax is *not* PHP to the tokenizer, so `{{ … }}` and `@foreach` survive
     * intact and stay readable.
     */
    private function reconstructMarkup(File $phpcsFile): string
    {
        $lines = [];

        foreach ($phpcsFile->getTokens() as $token) {
            if ($token['code'] !== T_INLINE_HTML) {
                continue;
            }

            $line = $token['line'];
            $buffer = str_pad($lines[$line] ?? '', ($token['column'] - 1));
            $lines[$line] = $buffer . rtrim($token['content'], "\r\n");
        }

        $markup = '';

        for ($line = 1, $last = max(array_keys($lines)); $line <= $last; $line++) {
            $markup .= ($lines[$line] ?? '') . "\n";
        }

        return $this->blankComments($markup);
    }

    /**
     * Replaces every comment body with spaces, keeping line breaks so that all
     * offsets — and therefore every reported line number — stay exact.
     */
    private function blankComments(string $markup): string
    {
        return (string) preg_replace_callback(
            self::COMMENT,
            static fn (array $match): string => (string) preg_replace('/[^\r\n]/', ' ', $match[0]),
            $markup
        );
    }

    /**
     * The component's root element must carry no Livewire, Blade, or Alpine
     * attribute. The root is read as the first opening element tag in the
     * view — a component view that opens with anything else is not a shape
     * this heuristic can speak about.
     *
     * Two guards keep this off markup that has no component root to judge:
     * the view must be a component's own view (see isComponentView()), and its
     * first tag must not open a child component (see opensOnAComponent()).
     *
     * @param array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}> $tags
     */
    private function checkRootElement(File $phpcsFile, string $markup, array $tags): void
    {
        if ($this->isComponentView($markup) === false) {
            return;
        }

        if (preg_match(self::ELEMENT_TAG, $markup, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        if ($tags !== [] && $this->opensOnAComponent($markup, $match[0][1], $tags[0]['offset'])) {
            return;
        }

        $attribute = $this->frameworkAttribute($match[2][0]);

        if ($attribute === null) {
            return;
        }

        $phpcsFile->addErrorOnLine(
            'Livewire component root element <%s> carries the %s attribute; the root element must'
                . ' have no Livewire, Blade, or Alpine attributes',
            $this->lineAt($markup, $match[0][1]),
            'RootElementAttributes',
            [$match[1][0], $attribute]
        );
    }

    /**
     * Whether the view's first element tag opens a component rather than a
     * root: the `<livewire:…>` invocation itself, or only the `<template>`
     * the standard requires around it.
     *
     * Neither is a component's own root element, and reporting either would
     * contradict the sniff's own rules — the first tag's `wire:key` is the
     * attribute MissingWireKeyInLoop requires, and the wrapper's is the one
     * TemplateKeyMismatch requires. Read by testing whether anything but
     * `<template>` tags and whitespace separates the start of the first
     * element tag from the first component tag.
     */
    private function opensOnAComponent(string $markup, int $elementStart, int $componentStart): bool
    {
        $gap = substr($markup, $elementStart, ($componentStart - $elementStart));

        return trim((string) preg_replace(self::TEMPLATE_WRAPPER, '', $gap)) === '';
    }

    /**
     * Whether the view is a Livewire component's *own* view rather than one
     * that merely renders a component.
     *
     * Read from the markup with every `<livewire:…>` tag removed: what is left
     * is the view's own markup, and a `wire:` attribute in it (`wire:click`,
     * `wire:model`, `wire:poll`) is a component's own directive. A view whose
     * only `wire:` is the `wire:key` on a child component tag has none, so its
     * root belongs to a layout or page and is not judged.
     */
    private function isComponentView(string $markup): bool
    {
        $ownMarkup = (string) preg_replace(self::COMPONENT_TAG, '', $markup);

        return preg_match(self::OWN_WIRE_ATTRIBUTE, $ownMarkup) === 1;
    }

    /**
     * Every component rendered inside a Blade loop needs its own `wire:key`.
     *
     * Nesting is irrelevant here — a child component in a loop owes a key just
     * as a top-level one does — so every tag is considered, at any depth.
     *
     * @param array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}> $tags
     */
    private function checkLoopKeys(File $phpcsFile, string $markup, array $tags): void
    {
        $regions = $this->loopRegions($markup);

        if ($regions === []) {
            return;
        }

        foreach ($tags as $tag) {
            if ($this->isInsideLoop($tag['offset'], $regions) === false) {
                continue;
            }

            if ($this->wireKey($tag['attributes']) !== null) {
                continue;
            }

            $phpcsFile->addErrorOnLine(
                'Livewire component <%s> is rendered in a Blade loop without a wire:key attribute',
                $tag['line'],
                'MissingWireKeyInLoop',
                [$tag['name']]
            );
        }
    }

    /**
     * Components that sit next to one another must each be wrapped in a
     * `<template>` carrying the same `wire:key` as the component itself.
     *
     * Only true siblings are compared. Tags are grouped by the parent
     * component they were opened inside, so a component nested in an unclosed
     * `<livewire:card>` is that card's child and is never read as the tag next
     * to it.
     *
     * @param array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}> $tags
     */
    private function checkAdjacentComponents(File $phpcsFile, string $markup, array $tags): void
    {
        $templates = $this->templateTags($markup);
        $siblings = [];
        $reported = [];

        foreach ($tags as $index => $tag) {
            $siblings[$tag['parent']][] = $index;
        }

        foreach ($siblings as $group) {
            for ($index = 1, $total = count($group); $index < $total; $index++) {
                $pair = [$tags[$group[($index - 1)]], $tags[$group[$index]]];

                if ($this->areAdjacent($markup, ...$pair) === false) {
                    continue;
                }

                foreach ($pair as $tag) {
                    if (isset($reported[$tag['offset']]) === true) {
                        continue;
                    }

                    $reported[$tag['offset']] = true;
                    $this->reportUnwrapped($phpcsFile, $markup, $templates, $tag);
                }
            }
        }
    }

    /**
     * Whether nothing but whitespace and the components' own `<template>` tags
     * separates the end of one sibling's element from the start of the next.
     * Anything else between them — an element, text, a Blade directive — means
     * the source does not show them as siblings, so they are left alone.
     *
     * The gap starts at the *element's* end, so a wrapping component's own
     * `</livewire:…>` closing tag is behind it rather than inside it.
     *
     * @param array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string} $previous
     * @param array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string} $current
     */
    private function areAdjacent(string $markup, array $previous, array $current): bool
    {
        $start = $previous['elementEnd'];
        $gap = substr($markup, $start, ($current['offset'] - $start));

        return trim((string) preg_replace(self::TEMPLATE_WRAPPER, '', $gap)) === '';
    }

    /**
     * @param array<int, string>                                              $templates
     * @param array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string} $tag
     */
    private function reportUnwrapped(
        File $phpcsFile,
        string $markup,
        array $templates,
        array $tag
    ): void {
        $wrapper = $this->skipWhitespaceBackwards($markup, $tag['offset']);

        if (isset($templates[$wrapper]) === false) {
            $phpcsFile->addErrorOnLine(
                'Livewire component <%s> is adjacent to another component and is not wrapped in a'
                    . ' <template wire:key="..."> tag',
                $tag['line'],
                'AdjacentComponentNotWrapped',
                [$tag['name']]
            );

            return;
        }

        $key = $this->wireKey($templates[$wrapper]);

        if ($key !== null && $key === $this->wireKey($tag['attributes'])) {
            return;
        }

        $phpcsFile->addErrorOnLine(
            'The <template> wrapping adjacent Livewire component <%s> must carry the same wire:key'
                . ' as the component',
            $tag['line'],
            'TemplateKeyMismatch',
            [$tag['name']]
        );
    }

    /**
     * The offset ranges Blade loop directives enclose.
     *
     * A directive left open — the loop body continues in an @include, or the
     * view is a fragment — contributes no region at all, so a component under
     * it is never reported. Where the source does not show the loop, the sniff
     * does not claim to see it.
     *
     * @return array<int, array{int, int}>
     */
    private function loopRegions(string $markup): array
    {
        $regions = [];

        foreach (self::LOOP_DIRECTIVES as $directive) {
            $pattern = '/@(' . $directive . '|end' . $directive . ')\b/i';
            preg_match_all($pattern, $markup, $matches, PREG_OFFSET_CAPTURE);
            $open = [];

            foreach ($matches[1] as $match) {
                if (strcasecmp($match[0], $directive) === 0) {
                    $open[] = $match[1];

                    continue;
                }

                if ($open === []) {
                    continue;
                }

                $regions[] = [array_pop($open), $match[1]];
            }
        }

        return $regions;
    }

    /**
     * @param array<int, array{int, int}> $regions
     */
    private function isInsideLoop(int $offset, array $regions): bool
    {
        foreach ($regions as [$start, $end]) {
            if ($offset > $start && $offset < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every opening Livewire component tag in the markup, in document order,
     * each carrying the index of the component it was opened inside
     * (`parent`, -1 at the top level) and the offset its whole element ends at
     * (`elementEnd` — past `</livewire:…>` when it has one, otherwise its own
     * end).
     *
     * A component left unclosed keeps its `elementEnd` at its own tag end and
     * stays on the stack, so every later tag becomes its child. That is the
     * same silence the unbalanced-loop handling takes: where the source does
     * not close the element, the sniff does not guess where it ended.
     *
     * @return array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}>
     */
    private function componentTags(string $markup): array
    {
        preg_match_all(self::COMPONENT_TAG, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $tags = [];
        $open = [];
        $cursor = 0;
        $line = 1;

        foreach ($matches as $match) {
            $offset = $match[0][1];
            $end = ($offset + strlen($match[0][0]));

            // Counted from the previous tag rather than from offset 0. The
            // segments are disjoint, so the whole walk costs one pass over the
            // file; asking lineAt() per tag instead made it quadratic.
            $line += substr_count($markup, "\n", $cursor, ($offset - $cursor));
            $cursor = $offset;

            if ($match[1][0] === '/') {
                $this->closeComponent($tags, $open, $match[2][0], $end);

                continue;
            }

            $tags[] = [
                'offset' => $offset,
                'end' => $end,
                'elementEnd' => $end,
                'line' => $line,
                'parent' => ($open === [] ? -1 : $open[(count($open) - 1)]),
                'name' => $match[2][0],
                'attributes' => $match[3][0],
            ];

            if (str_ends_with(rtrim($match[3][0]), '/') === false) {
                $open[] = (count($tags) - 1);
            }
        }

        return $tags;
    }

    /**
     * Closes the innermost open component when a `</livewire:name>` matches
     * it. A closing tag that names something else is a shape the source does
     * not show cleanly, so it is ignored rather than used to pop the stack.
     *
     * @param array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}> $tags
     * @param array<int, int>                                                 $open
     */
    private function closeComponent(array &$tags, array &$open, string $name, int $end): void
    {
        if ($open === []) {
            return;
        }

        $innermost = $open[(count($open) - 1)];

        if (strcasecmp($tags[$innermost]['name'], $name) !== 0) {
            return;
        }

        array_pop($open);
        $tags[$innermost]['elementEnd'] = $end;
    }

    /**
     * Every opening `<template …>` tag, keyed by the offset it ends at, so a
     * component can look up the wrapper immediately before it in one array
     * read.
     *
     * Collecting them once per file is what keeps the adjacency check linear:
     * testing each component's preceding wrapper by re-matching the markup
     * from offset 0 made the pass quadratic in file size, which a large but
     * entirely well-formed view was enough to stall CI on.
     *
     * @return array<int, string>
     */
    private function templateTags(string $markup): array
    {
        preg_match_all(self::TEMPLATE_TAG, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $tags = [];

        foreach ($matches as $match) {
            $tags[($match[0][1] + strlen($match[0][0]))] = $match[1][0];
        }

        return $tags;
    }

    /**
     * The offset the whitespace run immediately before $offset starts at.
     *
     * Each run is walked at most once across the whole pass — the runs are
     * disjoint — so the wrapper lookup stays linear in the size of the file.
     */
    private function skipWhitespaceBackwards(string $markup, int $offset): int
    {
        while ($offset > 0 && ctype_space($markup[($offset - 1)]) === true) {
            $offset--;
        }

        return $offset;
    }

    /**
     * The first Livewire/Blade/Alpine attribute in an attribute list, or null.
     *
     * Quoted values are dropped before the names are read, so an interpolated
     * value (`class="{{ $classes }}"`) is not mistaken for a framework
     * attribute — the standard is about attributes the root element carries,
     * not about Blade echoing into an ordinary one.
     */
    private function frameworkAttribute(string $attributes): ?string
    {
        $names = (string) preg_replace('/=\s*(?:"[^"]*"|\'[^\']*\')/', '=', $attributes);
        preg_match_all('/(?:^|\s)([^\s=<>"\'\/]+)/', $names, $matches);

        foreach ($matches[1] as $name) {
            foreach (self::FRAMEWORK_ATTRIBUTE_PREFIXES as $prefix) {
                if (stripos($name, $prefix) === 0) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * The value of a `wire:key` attribute, or null when there is none.
     */
    private function wireKey(string $attributes): ?string
    {
        if (preg_match(self::WIRE_KEY_ATTRIBUTE, $attributes, $match) !== 1) {
            return null;
        }

        return trim($match[1], '"\'');
    }

    /**
     * The 1-based line an offset into the reconstructed markup falls on.
     */
    private function lineAt(string $markup, int $offset): int
    {
        return (substr_count($markup, "\n", 0, $offset) + 1);
    }
}

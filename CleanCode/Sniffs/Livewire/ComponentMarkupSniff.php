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
 * Three narrowing decisions carry that trade, and each one is what keeps a
 * whole class of false positive out:
 *
 * - **The file must be recognisably Livewire markup** (a `wire:` attribute, a
 *   `<livewire:…>` tag, or an `@livewire` directive) before any check runs.
 *   Without the gate every plain Blade partial with an Alpine root would be
 *   reported, and "is this view a Livewire component?" is not otherwise
 *   answerable from one file.
 * - **A "component" is a `<livewire:…>` tag.** `<x-…>` is Blade's component
 *   namespace, shared by ordinary Blade components that need no `wire:key` at
 *   all, so it is not read as a Livewire component here.
 * - **`@livewire('name', …)` is not analysed.** Its key is a PHP expression
 *   argument (`key($row->id)`), not an attribute, so presence/equality cannot
 *   be read off the source.
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
     * An opening Livewire component tag: `<livewire:some-name …>`.
     */
    private const COMPONENT_TAG = '/<(livewire:[A-Za-z0-9._-]+)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/i';

    /**
     * A `<template …>` tag sitting immediately before the offset being tested,
     * separated by whitespace only.
     */
    private const PRECEDING_TEMPLATE_TAG = '/<template((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>\s*$/i';

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

        $this->checkRootElement($phpcsFile, $markup);
        $this->checkLoopKeys($phpcsFile, $markup);
        $this->checkAdjacentComponents($phpcsFile, $markup);
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
     */
    private function checkRootElement(File $phpcsFile, string $markup): void
    {
        if (preg_match(self::ELEMENT_TAG, $markup, $match, PREG_OFFSET_CAPTURE) !== 1) {
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
     * Every component rendered inside a Blade loop needs its own `wire:key`.
     */
    private function checkLoopKeys(File $phpcsFile, string $markup): void
    {
        $regions = $this->loopRegions($markup);

        if ($regions === []) {
            return;
        }

        foreach ($this->componentTags($markup) as $tag) {
            if ($this->isInsideLoop($tag['offset'], $regions) === false) {
                continue;
            }

            if ($this->wireKey($tag['attributes']) !== null) {
                continue;
            }

            $phpcsFile->addErrorOnLine(
                'Livewire component <%s> is rendered in a Blade loop without a wire:key attribute',
                $this->lineAt($markup, $tag['offset']),
                'MissingWireKeyInLoop',
                [$tag['name']]
            );
        }
    }

    /**
     * Components that sit next to one another must each be wrapped in a
     * `<template>` carrying the same `wire:key` as the component itself.
     */
    private function checkAdjacentComponents(File $phpcsFile, string $markup): void
    {
        $tags = $this->componentTags($markup);
        $reported = [];

        for ($index = 1, $total = count($tags); $index < $total; $index++) {
            $pair = [$tags[($index - 1)], $tags[$index]];

            if ($this->areAdjacent($markup, ...$pair) === false) {
                continue;
            }

            foreach ($pair as $tag) {
                if (isset($reported[$tag['offset']]) === true) {
                    continue;
                }

                $reported[$tag['offset']] = true;
                $this->reportUnwrapped($phpcsFile, $markup, $tag);
            }
        }
    }

    /**
     * Whether nothing but whitespace and the components' own `<template>` and
     * closing tags separates the two. Anything else between them — an element,
     * text, a Blade directive — means the source does not show them as
     * siblings, so they are left alone.
     *
     * @param array{offset: int, end: int, name: string, attributes: string} $previous
     * @param array{offset: int, end: int, name: string, attributes: string} $current
     */
    private function areAdjacent(string $markup, array $previous, array $current): bool
    {
        $gap = substr($markup, $previous['end'], ($current['offset'] - $previous['end']));
        $wrappers = '/<\/?(?:template|livewire:[A-Za-z0-9._-]+)(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/i';

        return trim((string) preg_replace($wrappers, '', $gap)) === '';
    }

    /**
     * @param array{offset: int, end: int, name: string, attributes: string} $tag
     */
    private function reportUnwrapped(File $phpcsFile, string $markup, array $tag): void
    {
        $line = $this->lineAt($markup, $tag['offset']);
        $before = substr($markup, 0, $tag['offset']);

        if (preg_match(self::PRECEDING_TEMPLATE_TAG, $before, $match) !== 1) {
            $phpcsFile->addErrorOnLine(
                'Livewire component <%s> is adjacent to another component and is not wrapped in a'
                    . ' <template wire:key="..."> tag',
                $line,
                'AdjacentComponentNotWrapped',
                [$tag['name']]
            );

            return;
        }

        $key = $this->wireKey($match[1]);

        if ($key !== null && $key === $this->wireKey($tag['attributes'])) {
            return;
        }

        $phpcsFile->addErrorOnLine(
            'The <template> wrapping adjacent Livewire component <%s> must carry the same wire:key'
                . ' as the component',
            $line,
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
     * Every Livewire component tag in the markup, in document order.
     *
     * @return array<int, array{offset: int, end: int, name: string, attributes: string}>
     */
    private function componentTags(string $markup): array
    {
        preg_match_all(self::COMPONENT_TAG, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        return array_map(
            static fn (array $match): array => [
                'offset' => $match[0][1],
                'end' => ($match[0][1] + strlen($match[0][0])),
                'name' => $match[1][0],
                'attributes' => $match[2][0],
            ],
            $matches
        );
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

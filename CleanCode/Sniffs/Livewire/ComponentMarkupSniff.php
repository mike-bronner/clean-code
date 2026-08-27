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
 *   The view therefore has to carry a directive that binds to a component of
 *   its own — `wire:model`, `wire:click`, `wire:poll` and the like, on one of
 *   its own element tags — before the root is judged. A `wire:navigate` link
 *   or a `wire:key` on a wrapper is at home on any page and proves nothing.
 *   And a first tag that opens a component is never read as the root: neither
 *   the `<livewire:…>` invocation itself nor the `<template>` this standard
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
     *
     * The unquoted run stops at `<` as well as at `>`, so a tag the pattern
     * cannot complete is abandoned at the next tag rather than at the end of
     * the file. Without that, a view holding many tag openers and one unpaired
     * quote — no `>` between them — re-reads the rest of the file from every
     * opener, which is quadratic in the size of the view. The cost is the same
     * one COMMENT_DELIMITERS below describes, in the shape a character class
     * takes it.
     *
     * What it gives up is a bare `<` in an *unquoted* attribute value
     * (`<div data-range=1<2>`), which no browser reads as an attribute either;
     * a quoted `title="a < b"` is untouched. Such a tag goes unrecognised, so
     * the sniff says nothing about it — the trade the rest of the file makes.
     */
    private const ELEMENT_TAG = '/<([A-Za-z][A-Za-z0-9._:-]*)((?:"[^"]*"|\'[^\']*\'|[^<>"\'])*)>/';

    /**
     * The start of an element tag, used to anchor the root-element read. See
     * checkRootElement().
     */
    private const ELEMENT_TAG_START = '/<[A-Za-z]/';

    /**
     * A Livewire component tag, opening or closing: `<livewire:some-name …>`,
     * `<livewire:some-name … />`, `</livewire:some-name>`. Both forms are
     * matched by one pattern so the tags can be walked as a single stream of
     * open/close events and given a nesting depth.
     */
    private const COMPONENT_TAG =
        '/<(\/)?(livewire:[A-Za-z0-9._-]+)((?:"[^"]*"|\'[^\']*\'|[^<>"\'])*)>/i';

    /**
     * An opening `<template …>` tag — a candidate component wrapper.
     */
    private const TEMPLATE_TAG = '/<template((?:"[^"]*"|\'[^\']*\'|[^<>"\'])*)>/i';

    /**
     * A `<template>` or `</template>` tag, stripped out of the gap between two
     * sibling components before the gap is tested for adjacency.
     */
    private const TEMPLATE_WRAPPER = '/<\/?template(?:"[^"]*"|\'[^\']*\'|[^<>"\'])*>/i';

    /**
     * A `wire:` directive that only makes sense on a component's *own*
     * element, matched against one attribute name.
     *
     * This is a proof set, not a filter, and the direction matters. Livewire
     * also ships directives that are at home on any view — `wire:navigate`,
     * `wire:current`, `wire:cloak`, `wire:offline`, `wire:transition`,
     * `wire:ignore`, and the `wire:key` this standard puts on a wrapper — and
     * that list is open-ended, so reading "any `wire:` attribute except the
     * ones we thought of" as proof turns every directive nobody listed into a
     * false positive on somebody's layout. Read this way, an unlisted
     * directive costs a silence instead, which is the trade the rest of this
     * sniff makes too.
     *
     * Every name below names a member of a component class: a bound property,
     * a method called on an event, a component polled or initialised.
     * Livewire's event bindings accept any DOM event name, so the common ones
     * are here and a `wire:mouseenter` view simply goes unjudged.
     */
    private const OWN_WIRE_DIRECTIVE = '/^wire:(?:blur|change|click|confirm|focus|init'
        . '|keydown|keyup|model|poll|submit)\b/i';

    /**
     * A `wire:key` attribute and its quoted value.
     */
    private const WIRE_KEY_ATTRIBUTE = '/\bwire:key\s*=\s*("[^"]*"|\'[^\']*\')/i';

    /**
     * Blade comments and HTML comments, each an opener paired with the closer
     * that ends it. Blanked before analysis so that commented-out markup is
     * never reported.
     *
     * Read with strpos() rather than matched with a regular expression, and
     * that is the whole point of the pair form. A lazy `/<!--.*?-->/s` re-reads
     * the rest of the file from every unclosed `<!--` in it — an ordinary
     * editing mistake, not an attack — which is quadratic in the size of the
     * view. The cost is paid in reconstructMarkup(), before the Livewire gate
     * has had the chance to rule the file out, so it falls on every view the
     * package is pointed at rather than only on Livewire ones.
     */
    private const COMMENT_DELIMITERS = [
        ['<!--', '-->'],
        ['{{--', '--}}'],
    ];

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
     *
     * Deliberately broader than OWN_WIRE_DIRECTIVE above, because the two
     * answer different questions. This one asks what the standard forbids on a
     * root element — *any* Livewire attribute, `wire:navigate` as much as
     * `wire:model`. That one asks whether the view is a component's own at
     * all, which only a directive bound to a component can settle.
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
     *
     * One forward pass over the file. Both searches only ever move towards the
     * end of it: an opener is looked for from the last one found, and a closer
     * that is missing from the rest of the file is missing for every later
     * opener too, so it is looked for once and then remembered as absent. An
     * unclosed comment therefore costs one scan for the whole file rather than
     * one per opener.
     *
     * An opener that is never closed is skipped rather than blanked — the same
     * silence the unbalanced-loop and unclosed-component handling take, and the
     * same reading the lazy pattern this replaced gave it.
     */
    private function blankComments(string $markup): string
    {
        $blanked = '';
        $copied = 0;
        $offset = 0;
        $openers = [];
        $unterminated = [];

        while (($comment = $this->nextComment($markup, $offset, $openers)) !== null) {
            [$start, $open, $close] = $comment;
            $offset = ($start + 1);

            if (isset($unterminated[$close]) === true) {
                continue;
            }

            $end = strpos($markup, $close, ($start + strlen($open)));

            if ($end === false) {
                $unterminated[$close] = true;

                continue;
            }

            $end += strlen($close);
            $blanked .= substr($markup, $copied, ($start - $copied))
                . (string) preg_replace('/[^\r\n]/', ' ', substr($markup, $start, ($end - $start)));
            $copied = $end;
            $offset = $end;
        }

        return $blanked . substr($markup, $copied);
    }

    /**
     * The comment opener nearest to $offset, as [offset, opener, closer], or
     * null once none is left.
     *
     * $openers carries each opener's next known position between calls, null
     * once it has none left. Without it the delimiter that is not chosen would
     * be searched for again from every position the other one is found at,
     * which is the quadratic this scan exists to avoid, one delimiter over.
     *
     * @param array<string, int|null> $openers
     *
     * @return array{int, string, string}|null
     */
    private function nextComment(string $markup, int $offset, array &$openers): ?array
    {
        $nearest = null;

        foreach (self::COMMENT_DELIMITERS as [$open, $close]) {
            // -1 for "not looked for yet"; null means looked for and absent
            // from the rest of the file, which no later offset can undo.
            $known = (array_key_exists($open, $openers) === true ? $openers[$open] : -1);

            if ($known !== null && $known < $offset) {
                $found = strpos($markup, $open, $offset);
                $openers[$open] = ($found === false ? null : $found);
            }

            if ($openers[$open] === null) {
                continue;
            }

            if ($nearest === null || $openers[$open] < $nearest[0]) {
                $nearest = [$openers[$open], $open, $close];
            }
        }

        return $nearest;
    }

    /**
     * The component's root element must carry no Livewire, Blade, or Alpine
     * attribute. The root is read at the first `<` that opens a tag, and the
     * tag has to parse *there* — a component view that opens with anything
     * else is not a shape this heuristic can speak about.
     *
     * Anchoring it is what keeps the read honest. Taking the first tag the
     * pattern matches anywhere would step over an element whose attribute list
     * ELEMENT_TAG cannot complete and judge the next element in its place —
     * typically a child carrying exactly the `wire:` attribute a child is
     * entitled to, which is a false positive rather than a missed one.
     *
     * Three guards keep this off markup that has no component root to judge:
     * the view must be a component's own view (see isComponentView()), its
     * first tag must parse as an element tag, and it must not open a child
     * component (see opensOnAComponent()).
     *
     * @param array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}> $tags
     */
    private function checkRootElement(File $phpcsFile, string $markup, array $tags): void
    {
        if ($this->isComponentView($markup) === false) {
            return;
        }

        if (preg_match(self::ELEMENT_TAG_START, $markup, $start, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        if (preg_match(self::ELEMENT_TAG, $markup, $match, PREG_OFFSET_CAPTURE, $start[0][1]) !== 1) {
            return;
        }

        if ($match[0][1] !== $start[0][1]) {
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
     *
     * That read can fail outright. TEMPLATE_WRAPPER's tag name is the literal
     * word `template`, so there is no tag-name/attribute-run alternation to
     * split and no backtrack blow-up. What gives out is the attribute-run
     * group itself: it repeats over single characters, one level of recursion
     * per character, so a long enough attribute list exhausts the engine's
     * depth instead of its step budget. Measured against this pattern on PHP
     * 8.4's defaults, on the gap unreadable-wrapper-gap-root.php holds: with
     * the PCRE JIT off the failure starts at an attribute run of 99,997
     * characters and reports PREG_RECURSION_LIMIT_ERROR (pcre.recursion_limit
     * defaults to 100,000, which is the number that threshold tracks); with
     * the JIT on the JIT's own stack gives out first, from 8,192 characters,
     * and reports PREG_JIT_STACKLIMIT_ERROR. Neither is
     * PREG_BACKTRACK_LIMIT_ERROR. Those two numbers are TEMPLATE_WRAPPER's
     * own, re-measured rather than carried over from TEMPLATE_TAG: this
     * pattern adds the `<\/?` closing-tag alternative and drops the capturing
     * group, and it fails one character later than TEMPLATE_TAG does on the
     * same subject.
     *
     * preg_replace() reports that failure by returning null, and null is the
     * one answer this question cannot be asked of. Casting it to a string
     * gives `''`, and `trim('') === ''` reads as "nothing but wrappers and
     * whitespace stands between the first element tag and the first component"
     * — so a failed read would say the view opens on a component, and the
     * caller would drop the root-element check entirely. A genuinely
     * non-compliant root then escapes RootElementAttributes with the sniff
     * silent, which is a detection bypass anyone authoring the linted view can
     * reach. Returning false instead closes that direction: a gap that could
     * not be read is not a gap proven to hold only wrappers, so the root is
     * judged on its own attributes as it would have been had no wrapper been
     * there at all.
     */
    private function opensOnAComponent(string $markup, int $elementStart, int $componentStart): bool
    {
        $gap = substr($markup, $elementStart, ($componentStart - $elementStart));
        $stripped = preg_replace(self::TEMPLATE_WRAPPER, '', $gap);

        // preg_last_error() === PREG_RECURSION_LIMIT_ERROR, or
        // PREG_JIT_STACKLIMIT_ERROR where the PCRE JIT is on: the wrapper read
        // gave out, so nothing is known about what the gap holds. Not the same
        // answer as the finished read below, which returns from a gap it saw.
        if ($stripped === null) {
            return false;
        }

        return trim($stripped) === '';
    }

    /**
     * Whether the view is a Livewire component's *own* view rather than one
     * that merely renders a component.
     *
     * Read from the attribute names of the view's own element tags: a
     * component-only `wire:` directive on one of them (`wire:click`,
     * `wire:model`, `wire:poll` — see OWN_WIRE_DIRECTIVE) is a directive
     * written for a component this view *is*. A layout that merely embeds one
     * writes no such directive: what it carries is a `wire:key`, a
     * `wire:navigate`, or a binding passed to the child tag itself, none of
     * which is proof, so its root belongs to the layout and is left alone.
     *
     * That also covers the `<template wire:key="...">` wrapper this standard
     * requires around adjacent components, without a rule of its own: the
     * wrapper carries `wire:key` and nothing else, and `wire:key` is proof
     * nowhere. One question decides both, so there is no second branch here
     * to leave untested.
     *
     * Attribute names rather than raw markup, so that `wire:click` inside a
     * quoted value or in the page's own prose is not read as a directive.
     *
     * The tag read can fail outright. ELEMENT_TAG's tag-name group and its
     * unquoted attribute run share a character set, so a `<` followed by a
     * long unbroken run of those characters and then a quote the pattern
     * cannot pair splits every way between the two and exhausts
     * pcre.backtrack_limit. Measured on PHP 8.4's default million steps: from
     * 816 such characters with the PCRE JIT off, 1,412 with it on.
     *
     * preg_match_all() then returns false and leaves $matches holding whatever
     * it managed to match before it gave out — the tags above the run, not
     * nothing. Answering off that is worse than answering off nothing: the
     * loop below reads a fraction of the view and calls it the whole, so
     * whether the view is judged a component's own turns on where the engine
     * happened to stop rather than on what the view says. A view left unjudged
     * is the honest answer where nothing was read, so the failure gets its own
     * exit and the partial matches are dropped unread.
     */
    private function isComponentView(string $markup): bool
    {
        $matched = preg_match_all(self::ELEMENT_TAG, $markup, $matches, PREG_SET_ORDER);

        // preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR: the tag read gave
        // out partway, so $matches is a fragment of the view rather than the
        // view. Not the same silence as the return below, which is the answer
        // to a read that finished.
        if ($matched === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isComponentTag($match[1]) === true) {
                continue;
            }

            foreach ($this->attributeNames($match[2]) as $name) {
                if (preg_match(self::OWN_WIRE_DIRECTIVE, $name) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a tag's attributes belong to the component being rendered rather
     * than to the view rendering it.
     *
     * `<livewire:search-box wire:model="query" />` binds the *child's*
     * property from its parent, so the directive is written about the child
     * and says nothing about the view it sits in.
     */
    private function isComponentTag(string $name): bool
    {
        return stripos($name, 'livewire:') === 0;
    }

    /**
     * Every component rendered inside a Blade loop needs its own `wire:key`.
     *
     * Nesting is irrelevant here — a child component in a loop owes a key just
     * as a top-level one does — so every tag is considered, at any depth.
     *
     * Both lists arrive in ascending offset order, so the regions are walked
     * with a cursor that only ever moves forward: one pass over each. Asking
     * every tag which of all the regions it falls in instead made the pass
     * quadratic, the same defect templateTags() was written to undo — and
     * loops are the cheaper half to write, since a view with N `@foreach`
     * blocks pays it without a single component being reported.
     *
     * @param array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}> $tags
     */
    private function checkLoopKeys(File $phpcsFile, string $markup, array $tags): void
    {
        $regions = $this->loopRegions($markup);
        $cursor = 0;
        $total = count($regions);

        foreach ($tags as $tag) {
            while ($cursor < $total && $regions[$cursor][1] <= $tag['offset']) {
                $cursor++;
            }

            if ($cursor === $total) {
                return;
            }

            if ($tag['offset'] <= $regions[$cursor][0]) {
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
     * That read can fail outright. TEMPLATE_WRAPPER's tag name is the literal
     * word `template`, so there is no tag-name/attribute-run alternation to
     * split and no backtrack blow-up. What gives out is the attribute-run
     * group itself: it repeats over single characters, one level of recursion
     * per character, so a long enough attribute list exhausts the engine's
     * depth instead of its step budget. Measured against this pattern on PHP
     * 8.4's defaults, on the gap unreadable-wrapper-gap-siblings.php holds:
     * with the PCRE JIT off the failure starts at an attribute run of 99,997
     * characters and reports PREG_RECURSION_LIMIT_ERROR (pcre.recursion_limit
     * defaults to 100,000, which is the number that threshold tracks); with
     * the JIT on the JIT's own stack gives out first, from 8,192 characters,
     * and reports PREG_JIT_STACKLIMIT_ERROR. Neither is
     * PREG_BACKTRACK_LIMIT_ERROR. Those two numbers are TEMPLATE_WRAPPER's
     * own, re-measured rather than carried over from TEMPLATE_TAG: this
     * pattern adds the `<\/?` closing-tag alternative and drops the capturing
     * group, and it fails one character later than TEMPLATE_TAG does on the
     * same subject.
     *
     * preg_replace() reports that failure by returning null, and null is the
     * one answer this question cannot be asked of. Casting it to a string
     * gives `''`, and `trim('') === ''` reads as "nothing separates them" — so
     * a failed read would call any two siblings adjacent no matter what lies
     * between them, and the caller would report
     * AdjacentComponentNotWrapped/TemplateKeyMismatch against a pair the
     * source never showed as adjacent. That is a false positive on correct
     * code, the opposite direction from opensOnAComponent()'s own failure.
     * Returning false instead closes it: a gap that could not be read is not a
     * gap proven empty, and the pair is left alone the same way a gap holding
     * an element leaves it alone.
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
        $stripped = preg_replace(self::TEMPLATE_WRAPPER, '', $gap);

        // preg_last_error() === PREG_RECURSION_LIMIT_ERROR, or
        // PREG_JIT_STACKLIMIT_ERROR where the PCRE JIT is on: the wrapper read
        // gave out, so nothing is known about what the gap holds. Not the same
        // answer as the finished read below, which returns from a gap it saw.
        if ($stripped === null) {
            return false;
        }

        return trim($stripped) === '';
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
     * The offset ranges Blade loop directives enclose, in ascending order of
     * where they start.
     *
     * A directive left open — the loop body continues in an @include, or the
     * view is a fragment — contributes no region at all, so a component under
     * it is never reported. Where the source does not show the loop, the sniff
     * does not claim to see it.
     *
     * The sort is what the caller's forward cursor rests on, and it is not
     * free: the directives are matched one kind at a time, so an `@foreach`
     * late in the file is collected before a `@for` early in it, and a nested
     * loop is closed — and collected — before the loop around it. A region a
     * component sits in could otherwise be behind the cursor by the time that
     * component is reached. Overlapping regions are left overlapping: a cursor
     * only ever skips a region that ends before the tag in hand, and the tags
     * arrive in ascending order, so such a region cannot contain a later one
     * either.
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

        usort($regions, static fn (array $first, array $second): int => ($first[0] <=> $second[0]));

        return $regions;
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
     * The tag read can fail outright, the same way isComponentView()'s does.
     * COMPONENT_TAG's tag-name group and its unquoted attribute run share a
     * character set, so a `<livewire:` followed by a long unbroken run of
     * those characters and then a quote the pattern cannot pair splits every
     * way between the two and exhausts pcre.backtrack_limit. Measured on PHP
     * 8.4's default million steps, against the fixture this ships with: from
     * 811 such characters with the PCRE JIT off, 1,406 with it on. Within a
     * handful of characters of ELEMENT_TAG's own 816 and 1,412, which is what
     * the shared alternation predicts; the small gap is the surrounding markup,
     * which the failing read has to carry either way.
     *
     * The list this returns is read by every check the sniff makes, so a
     * partial read is worse here than it is anywhere else in the file: the
     * root-element guard, the loop keys, and the adjacency pairs would all be
     * answered off a fraction of the view presented as the whole. A view left
     * unjudged is the honest answer where the tags were not read, so the
     * failure gets its own exit and whatever the engine collected is dropped
     * unread.
     *
     * @return array<int, array{offset: int, end: int, elementEnd: int, line: int, parent: int,
     *     name: string, attributes: string}>
     */
    private function componentTags(string $markup): array
    {
        $matched = preg_match_all(
            self::COMPONENT_TAG,
            $markup,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        // preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR: the tag read gave
        // out partway, so $matches holds a fraction of the view rather than
        // the view. Not the same emptiness as a finished read that found no
        // component tag, which the loop below returns [] from.
        if ($matched === false) {
            return [];
        }

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
     * This read can fail too, but not by the mechanism the other two patterns
     * fail by. TEMPLATE_TAG's tag name is the literal `template`, so there is
     * no tag-name/attribute-run alternation to split and no backtrack blow-up.
     * What gives out is the attribute-run group itself: it repeats over single
     * characters, one level of recursion per character, so a long enough
     * attribute list exhausts pcre.recursion_limit instead. Measured on PHP
     * 8.4's defaults: from 99,995 characters with the PCRE JIT off, failing
     * with PREG_RECURSION_LIMIT_ERROR; with the JIT on the JIT's own stack
     * gives out first, from 8,190 characters, with PREG_JIT_STACKLIMIT_ERROR.
     * Two different constants for the same defect, decided by an ini setting —
     * neither of them PREG_BACKTRACK_LIMIT_ERROR.
     *
     * An empty list is not silence here, which is why the failure still needs
     * its own exit rather than being left to fall through. Every wrapper
     * lookup in reportUnwrapped() misses against an empty list, so a failed
     * read makes the sniff report AdjacentComponentNotWrapped against
     * components that are correctly wrapped. That false positive is this
     * method's behaviour today and the exit below does not change it: it makes
     * the empty list a deliberate answer to a read that failed rather than an
     * accident of one that stopped partway.
     *
     * @return array<int, string>
     */
    private function templateTags(string $markup): array
    {
        $matched = preg_match_all(
            self::TEMPLATE_TAG,
            $markup,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        // preg_last_error() === PREG_RECURSION_LIMIT_ERROR, or
        // PREG_JIT_STACKLIMIT_ERROR where the PCRE JIT is on: the wrapper read
        // gave out partway, so $matches holds a fraction of the view rather
        // than the view. Not the same emptiness as a finished read that found
        // no wrapper, which the loop below returns [] from.
        if ($matched === false) {
            return [];
        }

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
     * The attribute names in an attribute list, values dropped.
     *
     * Dropping the quoted values first is what keeps an interpolated one
     * (`class="{{ $classes }}"`, `x-on:keyup="$wire.save()"`) from being read
     * as an attribute name of its own — the standard is about attributes an
     * element carries, not about what Blade or Alpine echoes into one.
     *
     * @return array<int, string>
     */
    private function attributeNames(string $attributes): array
    {
        $names = (string) preg_replace('/=\s*(?:"[^"]*"|\'[^\']*\')/', '=', $attributes);
        preg_match_all('/(?:^|\s)([^\s=<>"\'\/]+)/', $names, $matches);

        return $matches[1];
    }

    /**
     * The first Livewire/Blade/Alpine attribute in an attribute list, or null.
     */
    private function frameworkAttribute(string $attributes): ?string
    {
        foreach ($this->attributeNames($attributes) as $name) {
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

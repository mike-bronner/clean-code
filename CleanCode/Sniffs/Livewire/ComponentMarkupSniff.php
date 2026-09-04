<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Livewire;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ComponentMarkupSniff implements Sniff
{
    private const LIVEWIRE_MARKUP = '/wire:[a-z]|<livewire:|@livewire\b/i';

    private const ELEMENT_TAG = "/<([A-Za-z][A-Za-z0-9._:-]*)((?:\"[^\"]*\"|'[^']*'|[^<>\"'])*)>/";

    private const ELEMENT_TAG_START = '/<[A-Za-z]/';

    private const COMPONENT_TAG
        = "/<(\\/)?(livewire:[A-Za-z0-9._-]+)((?:\"[^\"]*\"|'[^']*'|[^<>\"'])*)>/i";

    private const TEMPLATE_TAG = "/<template((?:\"[^\"]*\"|'[^']*'|[^<>\"'])*)>/i";

    private const TEMPLATE_WRAPPER = "/<\\/?template(?:\"[^\"]*\"|'[^']*'|[^<>\"'])*>/i";

    private const OWN_WIRE_DIRECTIVE = '/^wire:(?:blur|change|click|confirm|focus|init'
        . '|keydown|keyup|model|poll|submit)\b/i';

    private const WIRE_KEY_ATTRIBUTE = "/\\bwire:key\\s*=\\s*(\"[^\"]*\"|'[^']*')/i";

    private const COMMENT_DELIMITERS = [
        ['<!--', '-->'],
        ['{{--', '--}}'],
    ];

    private const LOOP_DIRECTIVES = [
        'foreach',
        'forelse',
        'for',
        'while',
    ];

    private const FRAMEWORK_ATTRIBUTE_PREFIXES = [
        'wire:',
        'x-',
        '@',
        ':',
        '{{',
    ];

    private array $scanCounts = [
        'componentTags.lineBytes' => 0,
        'templateTags.reads' => 0,
        'loopRegions.steps' => 0,
        'comments.closerScans' => 0,
        'comments.unterminatedSkips' => 0,
    ];

    public function register(): array
    {
        return [T_INLINE_HTML];
    }

    public function scanCounts(): array
    {
        return $this->scanCounts;
    }

    public function process(File $phpcsFile, $stackPtr): void
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
                $this->scanCounts['comments.unterminatedSkips']++;

                continue;
            }

            $this->scanCounts['comments.closerScans']++;
            $end = strpos($markup, $close, ($start + strlen($open)));

            if ($end === false) {
                $unterminated[$close] = true;

                continue;
            }

            $end += strlen($close);
            $comment = substr($markup, $start, ($end - $start));
            $blank = preg_replace('/[^\r\n]/', ' ', $comment);

            // A failed read leaves the comment as it stands rather than
            // dropping it: null cast to a string is '', and an empty
            // substitution shortens $blanked by the whole comment, so every
            // offset and reported line number after it in the file shifts. The
            // comment's own text is the only same-length replacement that also
            // keeps its line breaks, which str_repeat(' ', ...) would not. The
            // cost is that the comment is read as markup, which can only add a
            // report, never silence one. Same shape as the ?? $content fallback
            // in NoLogicSniff::unescapedContent().
            $blanked .= substr($markup, $copied, ($start - $copied)) . ($blank ?? $comment);
            $copied = $end;
            $offset = $end;
        }

        return $blanked . substr($markup, $copied);
    }

    private function nextComment(string $markup, int $offset, array &$openers): ?array
    {
        $nearest = null;

        foreach (self::COMMENT_DELIMITERS as [$open, $close]) {
            // -1 for "not looked for yet"; null means looked for and absent
            // from the rest of the file, which no later offset can undo.
            $known = (array_key_exists($open, $openers) === true ? $openers[$open] : -1);

            if (
                $known !== null
                && $known < $offset
            ) {
                $found = strpos($markup, $open, $offset);
                $openers[$open] = ($found === false ? null : $found);
            }

            if ($openers[$open] === null) {
                continue;
            }

            if (
                $nearest === null
                || $openers[$open] < $nearest[0]
            ) {
                $nearest = [$openers[$open], $open, $close];
            }
        }

        return $nearest;
    }

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

        if (
            $tags !== []
            && $this->opensOnAComponent($markup, $match[0][1], $tags[0]['offset'])
        ) {
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

    private function isComponentTag(string $name): bool
    {
        return stripos($name, 'livewire:') === 0;
    }

    private function checkLoopKeys(File $phpcsFile, string $markup, array $tags): void
    {
        $regions = $this->loopRegions($markup);
        $cursor = 0;
        $total = count($regions);

        foreach ($tags as $tag) {
            while (
                $cursor < $total
                && $regions[$cursor][1] <= $tag['offset']
            ) {
                $this->scanCounts['loopRegions.steps']++;
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

    private function reportUnwrapped(
        File $phpcsFile,
        string $markup,
        array $templates,
        array $tag
    ): void {
        $wrapper = $this->skipWhitespaceBackwards($markup, $tag['offset']);

        if (isset($templates[$wrapper]) === false) {
            $phpcsFile->addErrorOnLine(
                'Livewire component %s is adjacent to another component and is not wrapped in a'
                    . ' template tag carrying a wire:key attribute',
                $tag['line'],
                'AdjacentComponentNotWrapped',
                [$tag['name']]
            );

            return;
        }

        $key = $this->wireKey($templates[$wrapper]);

        if (
            $key !== null
            && $key === $this->wireKey($tag['attributes'])
        ) {
            return;
        }

        $phpcsFile->addErrorOnLine(
            'The template tag wrapping adjacent Livewire component %s must carry the same'
                . ' wire:key as the component',
            $tag['line'],
            'TemplateKeyMismatch',
            [$tag['name']]
        );
    }

    private function loopRegions(string $markup): array
    {
        $regions = [];

        foreach (self::LOOP_DIRECTIVES as $directive) {
            $pattern = "/@({$directive}|end{$directive})\\b/i";
            $matched = preg_match_all($pattern, $markup, $matches, PREG_OFFSET_CAPTURE);

            // The read reports failure two ways, and neither leaves anything
            // worth pairing: a runtime failure sets $matches to empty groups,
            // and a compile failure never writes to it at all, leaving the null
            // the caller declared. Reading the second unguarded takes an offset
            // off null on the way to the same empty answer. Dropping this
            // directive's regions unread states that answer outright — a loop
            // the sniff could not read is a loop it does not police, so
            // MissingWireKeyInLoop goes unreported for it rather than
            // misreported.
            if ($matched === false) {
                continue;
            }

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
            $this->scanCounts['componentTags.lineBytes'] += ($offset - $cursor);
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

    private function templateTags(string $markup): array
    {
        $this->scanCounts['templateTags.reads']++;
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

    private function skipWhitespaceBackwards(string $markup, int $offset): int
    {
        while (
            $offset > 0
            && ctype_space($markup[($offset - 1)]) === true
        ) {
            $offset--;
        }

        return $offset;
    }

    private function attributeNames(string $attributes): array
    {
        // Guarded before the read below, and not merged into it. Casting a
        // failed value-strip to a string gives '', preg_match_all() against ''
        // returns 0 rather than false — a finished read that found nothing —
        // and this method would then hand back [] with the read below reporting
        // success. Falling back to the raw attribute list keeps the names
        // readable; the values come with them, so an interpolated one can be
        // read as a name of its own, which costs a report that should not have
        // been made rather than a report that should have been.
        $names = preg_replace("/=\\s*(?:\"[^\"]*\"|'[^']*')/", '=', $attributes) ?? $attributes;
        $matched = preg_match_all("/(?:^|\\s)([^\\s=<>\"'\\/]+)/", $names, $matches);

        // The name read failed, so $matches[1] is either empty or, when the
        // pattern never compiled, not there at all — and an empty list is what
        // checkRootElement() reads as "this root carries no framework
        // attribute", the RootElementAttributes bypass #366 closed by a
        // different path. Splitting the list on whitespace without PCRE keeps
        // every name in play: each piece still carries its own name as its
        // prefix, which is all frameworkAttribute() and OWN_WIRE_DIRECTIVE ask
        // of it.
        if ($matched === false) {
            return $this->whitespaceSeparated($names);
        }

        return $matches[1];
    }

    private function whitespaceSeparated(string $text): array
    {
        $pieces = explode(' ', str_replace(["\t", "\n", "\r", "\v", "\f"], ' ', $text));

        return array_values(array_filter($pieces, static fn (string $piece): bool => $piece !== ''));
    }

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

    private function wireKey(string $attributes): ?string
    {
        if (preg_match(self::WIRE_KEY_ATTRIBUTE, $attributes, $match) !== 1) {
            return null;
        }

        return trim($match[1], "\"'");
    }

    private function lineAt(string $markup, int $offset): int
    {
        return (substr_count($markup, "\n", 0, $offset) + 1);
    }
}

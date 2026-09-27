<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Livewire\ComponentMarkupSniff;
use MikeBronner\CleanCode\Tests\PregFailure;

const COMPONENT_MARKUP = 'CleanCode.Livewire.ComponentMarkup';

const ROOT_ELEMENT_ATTRIBUTES = COMPONENT_MARKUP . '.RootElementAttributes';

const MISSING_WIRE_KEY_IN_LOOP = COMPONENT_MARKUP . '.MissingWireKeyInLoop';

const ADJACENT_COMPONENT_NOT_WRAPPED = COMPONENT_MARKUP . '.AdjacentComponentNotWrapped';

const TEMPLATE_KEY_MISMATCH = COMPONENT_MARKUP . '.TemplateKeyMismatch';

const LAYOUT_CARRYING = '<div x-data="{ open: false }" class="app-shell">
    <a %s href="/dashboard">Dashboard</a>
    <livewire:notifications-bell wire:key="bell" />
</div>
';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COMPONENT_MARKUP);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COMPONENT_MARKUP, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

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

it('flags an adjacent component whose template wrapper carries no key', function (): void {
    expect(violationTuples(analyzeFixture(COMPONENT_MARKUP, 'template-without-key.php')))->toBe([
        ['line' => 5, 'column' => 1, 'source' => TEMPLATE_KEY_MISMATCH],
        ['line' => 8, 'column' => 1, 'source' => TEMPLATE_KEY_MISMATCH],
    ]);
});

it('says nothing about a view that is not recognisably Livewire', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'not-livewire.php')->getErrors())->toBe([]);
});

it('says nothing about a loop whose directive is unbalanced', function (string $fixture): void {
    expect(analyzeFixture(COMPONENT_MARKUP, $fixture)->getErrors())->toBe([]);
})->with([
    'unclosed @foreach' => 'unbalanced-loop.php',
    'orphan @endforeach' => 'orphan-loop-close.php',
]);

it('says nothing about a Livewire view with no element tag', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'no-root-element.php')->getErrors())->toBe([]);
});

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

it('says nothing about a view whose first tag is a component invocation', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'child-component-first.php')->getErrors())->toBe([]);
});

it('says nothing about a view whose first tag wraps a component', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'template-wrapper-first.php')->getErrors())->toBe([]);
});

it('says nothing about the root of a view that merely embeds a component', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'embeds-component.php')->getErrors())->toBe([]);
});

it('says nothing about a layout whose only wire: attribute is any view\'s', function (
    string $fixture
): void {
    expect(analyzeFixture(COMPONENT_MARKUP, $fixture)->getErrors())->toBe([]);
})->with([
    'wire:navigate link' => 'embeds-navigating-component.php',
    'wire:key template wrappers' => 'embeds-wrapped-components.php',
]);

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

it('does not read a binding on a component tag as the view\'s own', function (): void {
    $path = stageSource(
        '<div x-data="{ open: false }" class="app-shell">' . "\n"
            . '    <livewire:search-box wire:model="query" wire:key="search" />' . "\n"
            . "</div>\n"
    );

    expect(analyzeWithSniffs([COMPONENT_MARKUP], $path)->getErrors())->toBe([]);
});

it('does not read a wire: directive quoted in text as the view\'s own', function (): void {
    $path = stageSource(
        '<div x-data="{ open: false }" class="app-shell">' . "\n"
            . '    <p>Bind a button with wire:click="save" to call the method.</p>' . "\n"
            . '    <livewire:notifications-bell wire:key="bell" />' . "\n"
            . "</div>\n"
    );

    expect(analyzeWithSniffs([COMPONENT_MARKUP], $path)->getErrors())->toBe([]);
});

it('says nothing about a component nested inside another component', function (): void {
    expect(analyzeFixture(COMPONENT_MARKUP, 'nested-components.php')->getErrors())->toBe([]);
});

it('still flags adjacent components that each wrap their own content', function (): void {
    expect(violationTuples(analyzeFixture(COMPONENT_MARKUP, 'nested-components-adjacent.php')))
        ->toBe([
            ['line' => 2, 'column' => 1, 'source' => ADJACENT_COMPONENT_NOT_WRAPPED],
            ['line' => 5, 'column' => 1, 'source' => ADJACENT_COMPONENT_NOT_WRAPPED],
        ]);
});

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

it('scans a large well-formed view in linear time', function (): void {
    $components = 8000;
    $view = "<div wire:poll class=\"feed\">\n";

    for ($index = 0; $index < $components; $index++) {
        $view .= "    <template wire:key=\"k{$index}\">\n"
            . "        <livewire:item-{$index} wire:key=\"k{$index}\" />\n"
            . "    </template>\n";
    }

    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-perf-', true);
    mkdir($directory, 0700);

    $path = $directory . '/large-view.blade.php';
    stagedFixtures($path);
    file_put_contents($path, $view . "</div>\n");

    $sniff = sniffInstance(COMPONENT_MARKUP);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([COMPONENT_MARKUP], $path);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

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

    expect(violationSourcesByLine($file->getErrors()))->toBe([1 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($counted['loopRegions.steps'])->toBe(
            $loops - 1,
            'the cursor steps past each region once over the whole pass, not once per tag'
        );
});

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

it('says nothing about a view whose first element tag does not parse', function (): void {
    $path = stageSource(
        "<div data-range=1<2>\n"
            . "    <button wire:model=\"query\">Search</button>\n"
            . "</div>\n"
    );

    expect(analyzeWithSniffs([COMPONENT_MARKUP], $path)->getErrors())->toBe([]);
});

it('says nothing about a view whose element tags cannot be read at all', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'ELEMENT_TAG'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-element-tags.php')
    );

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

it('says nothing about a view whose component tags cannot be read at all', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'COMPONENT_TAG'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-component-tags.php')
    );

    $matched = preg_match_all($pattern, $markup, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $error = preg_last_error();

    expect($matched)->toBeFalse()
        ->and($error)->toBe(PREG_BACKTRACK_LIMIT_ERROR)
        ->and($matches)->toBe([]);

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-component-tags.php');

    expect(allViolationSourcesByLine($file))->toBe([])
        ->and($file->getWarnings())->toBe([]);

    $readable = (string) preg_replace('/<livewire:(?:report-row-)+"/', '', $markup);

    expect($readable)->not->toBe($markup);

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(array_merge(...array_values(allViolationSourcesByLine($control))))
        ->toContain(MISSING_WIRE_KEY_IN_LOOP);
});

it('reports correctly wrapped components when the wrapper tags cannot be read at all', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'TEMPLATE_TAG'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-template-tags.php')
    );

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

    $readable = (string) preg_replace('/<template (?:data-column-)+>/', '', $markup);

    expect($readable)->not->toBe($markup);

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(allViolationSourcesByLine($control))->toBe([]);
});

it('judges the root when the gap before the first component cannot be read', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'TEMPLATE_WRAPPER'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-wrapper-gap-root.php')
    );

    $elementStart = strpos($markup, '<div');
    $componentStart = strpos($markup, '<livewire:');
    $gap = substr($markup, $elementStart, ($componentStart - $elementStart));

    $runStart = (strpos($markup, '</template ', $elementStart) + strlen('</template '));
    $runLength = (strpos($markup, '>', $runStart) - $runStart);

    expect($runLength)->toBeGreaterThan(99996);

    $stripped = preg_replace($pattern, '', $gap);
    $error = preg_last_error();
    $jitted = (ini_get('pcre.jit') === '1');

    expect($stripped)->toBeNull()
        ->and($error)->toBe($jitted ? PREG_JIT_STACKLIMIT_ERROR : PREG_RECURSION_LIMIT_ERROR)
        ->and($error)->not->toBe(PREG_BACKTRACK_LIMIT_ERROR);

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-wrapper-gap-root.php');

    expect(allViolationSourcesByLine($file))->toBe([33 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($file->getWarnings())->toBe([]);

    $readable = substr_replace($markup, 'data-column-', $runStart, $runLength);

    expect($readable)->not->toBe($markup)
        ->and(strlen($readable))->toBeLessThan(strlen($markup));

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(allViolationSourcesByLine($control))->toBe([33 => [ROOT_ELEMENT_ATTRIBUTES]])
        ->and($control->getWarnings())->toBe([]);

    $root = '<div wire:model="query" class="editor">';
    $close = '</div>';

    expect(substr_count($readable, $root))->toBe(1)
        ->and(substr_count($readable, $close))->toBe(1);

    $compliant = str_replace(
        [$root, $close],
        ['<div class="editor">', '    <span wire:model="query"></span>' . PHP_EOL . $close],
        $readable
    );

    $isComponentView = new ReflectionMethod(ComponentMarkupSniff::class, 'isComponentView');

    expect($isComponentView->invoke(new ComponentMarkupSniff(), $compliant))->toBeTrue();

    $judged = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($compliant));

    expect(allViolationSourcesByLine($judged))->toBe([])
        ->and($judged->getWarnings())->toBe([]);
});

it('leaves siblings alone when the gap between them cannot be read', function (): void {
    $pattern = (new ReflectionClassConstant(ComponentMarkupSniff::class, 'TEMPLATE_WRAPPER'))->getValue();
    $markup = file_get_contents(
        fixturePath('ComponentMarkupSniff', 'unreadable-wrapper-gap-siblings.php')
    );

    $previousEnd = (strpos($markup, '>', strpos($markup, '<livewire:')) + 1);
    $currentStart = strpos($markup, '<livewire:', $previousEnd);
    $gap = substr($markup, $previousEnd, ($currentStart - $previousEnd));

    $runStart = (strpos($markup, '</template ', $previousEnd) + strlen('</template '));
    $runLength = (strpos($markup, '>', $runStart) - $runStart);

    expect($runLength)->toBeGreaterThan(99996);

    $stripped = preg_replace($pattern, '', $gap);
    $error = preg_last_error();
    $jitted = (ini_get('pcre.jit') === '1');

    expect($stripped)->toBeNull()
        ->and($error)->toBe($jitted ? PREG_JIT_STACKLIMIT_ERROR : PREG_RECURSION_LIMIT_ERROR)
        ->and($error)->not->toBe(PREG_BACKTRACK_LIMIT_ERROR);

    $file = analyzeFixture(COMPONENT_MARKUP, 'unreadable-wrapper-gap-siblings.php');

    expect(allViolationSourcesByLine($file))->toBe([])
        ->and($file->getWarnings())->toBe([]);

    $readable = substr_replace($markup, 'data-column-', $runStart, $runLength);

    expect($readable)->not->toBe($markup)
        ->and(strlen($readable))->toBeLessThan(strlen($markup));

    $control = analyzeWithSniffs([COMPONENT_MARKUP], stageSource($readable));

    expect(allViolationSourcesByLine($control))->toBe([])
        ->and($control->getWarnings())->toBe([]);

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

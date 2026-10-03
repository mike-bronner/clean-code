<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const COMBINED_CONSTRUCTOR = 'CleanCode.Constructors.DisallowCombinedConstructor';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COMBINED_CONSTRUCTOR);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns once per mode signal, under the signal\'s own code', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 33, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 51, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 67, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 69, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(4);
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0);
});

it('warns on every branching, declaration, and argument-reader shape', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 33, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 35, 'column' => 19, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 37, 'column' => 20, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 41, 'column' => 17, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 49, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 55, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 59, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 61, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 63, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 77, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 83, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 85, 'column' => 31, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 89, 'column' => 21, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 95, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 98, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 103, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 121, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 122, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 125, 'column' => 18, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 141, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 149, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 160, 'column' => 36, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 176, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 178, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 194, 'column' => 18, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 200, 'column' => 39, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 216, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 222, 'column' => 53, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 228, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 245, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 247, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 264, 'column' => 37, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 266, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 280, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 296, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 313, 'column' => 40, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 316, 'column' => 26, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 333, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 337, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 352, 'column' => 34, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 353, 'column' => 24, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 365, 'column' => 17, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 379, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 381, 'column' => 19, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 401, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 416, 'column' => 51, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 431, 'column' => 37, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 448, 'column' => 24, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 470, 'column' => 14, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 472, 'column' => 45, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
    ]);
});

it('scans repeated uses of one parameter in linear time', function (): void {
    $size = 4000;
    $source = "<?php\n\nclass ScaleProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
        . '        $this->mode = ' . implode(' . ', array_fill(0, $size, '$flag')) . "\n"
        . "            ? new Mailer()\n            : new NullLogger();\n    }\n}\n";

    $sniff = sniffInstance(COMBINED_CONSTRUCTOR);
    $before = $sniff->cacheCounts();
    $file = analyzeWithSniffs([COMBINED_CONSTRUCTOR], stageGeneratedFixture('repeated-uses.php', $source));
    $counted = cacheCountsDelta($before, $sniff->cacheCounts());

    expect($file->getWarningCount())->toBe($size, 'every use is still reported')
        ->and($counted['selectorCache.hits'])->toBe(
            $size - 1,
            "n={$size}: every use after the first stops on a scan already run"
        )
        ->and($counted['selectorCache.steps'])->toBeLessThan(
            $file->numTokens,
            "n={$size}: the scan steps once per position of the body, not once per use"
        );
});

it('scans a many-armed match in linear time', function (): void {
    $size = 4000;
    $arms = implode("\n", array_map(
        static fn (int $index): string => "            \$flag => new Mode{$index}(),",
        range(0, $size - 1)
    ));
    $source = "<?php\n\nclass ScaleProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
        . "        \$this->mode = match (true) {\n{$arms}\n"
        . "            default => throw new LogicException('unreachable'),\n        };\n    }\n}\n";

    $sniff = sniffInstance(COMBINED_CONSTRUCTOR);
    $before = $sniff->cacheCounts();
    $file = analyzeWithSniffs([COMBINED_CONSTRUCTOR], stageGeneratedFixture('many-armed-match.php', $source));
    $counted = cacheCountsDelta($before, $sniff->cacheCounts());

    expect($file->getWarningCount())->toBe($size, 'every arm condition is still reported')
        ->and($counted['branchVerdicts.walks'])->toBe(
            1,
            "n={$size}: the arms are enumerated once for the match, not once per arm"
        )
        ->and($counted['branchVerdicts.hits'])->toBe(
            $size - 1,
            "n={$size}: every arm after the first reads the enumeration already run"
        );
});

it('scans a long if chain in linear time', function (): void {
    $size = 4000;
    $links = "        if (\$flag) {\n            \$this->mode = new Mode0();\n        }";

    for ($index = 1; $index < $size; $index++) {
        $links .= " elseif (\$flag) {\n            \$this->mode = new Mode{$index}();\n        }";
    }

    $source = "<?php\n\nclass ScaleProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
        . $links . " else {\n            throw new LogicException('unreachable');\n        }\n    }\n}\n";

    $sniff = sniffInstance(COMBINED_CONSTRUCTOR);
    $before = $sniff->cacheCounts();
    $file = analyzeWithSniffs([COMBINED_CONSTRUCTOR], stageGeneratedFixture('long-if-chain.php', $source));
    $counted = cacheCountsDelta($before, $sniff->cacheCounts());

    expect($file->getWarningCount())->toBe($size, 'every link condition is still reported')
        ->and($counted['chainHead.steps'])->toBe(
            $size,
            "n={$size}: each link is stepped over once, not once per link in front of it"
        )
        ->and($counted['chainHead.hits'])->toBe(
            $size - 1,
            "n={$size}: every link after the first reads the head a walk already recorded"
        );
});

it('settles a nested ternary chain in one walk', function (): void {
    $sniff = sniffInstance(COMBINED_CONSTRUCTOR);

    foreach ([2, 4, 8] as $size) {
        $else = '';

        for ($level = $size; $level >= 1; $level--) {
            $else .= " : new Mode{$level}()";
        }

        $source = "<?php\n\nclass TernaryChainProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
            . '        $this->mode = ' . str_repeat('$flag ? ', $size) . 'new Deepest()' . $else . ";\n    }\n}\n";

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getWarningCount())->toBe($size, "n={$size}: every level's condition is still reported")
            ->and($counted['ternarySides.walks'])->toBe(
                1,
                "n={$size}: the chain is walked once, not once per level"
            )
            ->and($counted['ternarySides.hits'])->toBe(
                $size - 1,
                "n={$size}: every level after the first answers from the walk already run"
            );
    }
});

it('settles an unterminated ternary chain in one walk', function (): void {
    $sniff = sniffInstance(COMBINED_CONSTRUCTOR);

    foreach ([2, 4, 8] as $size) {
        $source = "<?php\n\nclass TruncatedTernaryProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
            . '        $this->mode = ' . str_repeat('$flag ? ', $size) . "new Deepest();\n    }\n}\n";

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getWarningCount())->toBe($size, "n={$size}: every level's condition is still reported")
            ->and($counted['ternarySides.walks'])->toBe(
                1,
                "n={$size}: the chain is walked once, not once per level"
            )
            ->and($counted['ternarySides.hits'])->toBe(
                $size - 1,
                "n={$size}: every level after the first answers from the walk already run"
            );
    }
});

it('reads a block\'s closing brace as a grouping preceder', function (): void {
    $source = "<?php\n\nclass BlockBraceBeforeGroupedSubject\n{\n"
        . "    public function __construct(mixed \$source)\n    {\n"
        . "        if (\$this->ready) { \$this->prepare(); }\n"
        . "        (\$source) instanceof Mailer\n"
        . "            ? \$this->transport = new Mailer()\n"
        . "            : \$this->transport = new NullLogger();\n    }\n}\n";

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 8, 'column' => 10, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
    ]);
});

it('reads a dynamic member name\'s closing brace as the name it ends', function (): void {
    $source = "<?php\n\nclass DynamicNameCallResultInstanceofSubject\n{\n"
        . "    public function __construct(mixed \$source, string \$name)\n    {\n"
        . "        if (\$this->{'resolve'}(\$source) instanceof Mailer) {\n"
        . "            \$this->transport = new Mailer();\n"
        . "        } elseif (self::{'resolve'}(\$source) instanceof NullLogger) {\n"
        . "            \$this->transport = new NullLogger();\n"
        . "        } elseif (\$this?->{\$name}(\$source) instanceof Mailer) {\n"
        . "            \$this->transport = new Mailer();\n"
        . "        } elseif (is_string(\$this->{\$name}(\$source))) {\n"
        . "            \$this->transport = new NullLogger();\n"
        . "        }\n    }\n}\n";

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect($file->getWarningCount())->toBe(0)
        ->and($file->getErrorCount())->toBe(0);
});

it('reads a name a use-function import redirects as the imported function', function (): void {
    $source = <<<'PHP'
<?php

namespace App\Domain;

use function App\Validation\is_callable;

class ImportedPredicateName
{
    public function __construct(mixed $source)
    {
        if (is_callable($source)) {
            $this->transport = new Mailer();
        } else {
            $this->transport = new NullLogger();
        }

        if (is_array($source)) {
            $this->rows = $source;
        } else {
            $this->rows = [];
        }
    }
}
PHP;

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 17, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
    ]);
});

it('reads a first-class callable to an argument reader as no call at all', function (): void {
    $source = <<<'PHP'
<?php

class FirstClassCallableArgumentReader
{
    public function __construct()
    {
        $this->reader = func_get_args(...);
        $this->counter = func_num_args(/* not a call either */...);
        $this->spread = func_num_args(.../* nor is this one */);
        $this->named = func_get_args/* still not a call */(...);
        $this->arguments = func_get_args();
    }
}
PHP;

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 11, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
    ]);
});

it('stops reading a parameter\'s name once the body re-binds it', function (): void {
    $source = <<<'PHP'
<?php

class ForeachTarget
{
    public function __construct(bool $legacy, array $items)
    {
        $this->rows = ($legacy ? $items : []);

        foreach ($items as $legacy) {
            if ($legacy) {
                $this->rows[] = $legacy;
            }
        }
    }
}

class CatchVariable
{
    public function __construct(bool $legacy)
    {
        if ($legacy) {
            $this->mode = 'legacy';
        }

        try {
            $this->boot();
        } catch (\RuntimeException $legacy) {
            if ($legacy) {
                $this->mode = 'failed';
            }
        }
    }
}

class StaticLocal
{
    public function __construct(bool $legacy)
    {
        if ($legacy) {
            $this->mode = 'legacy';
        }

        static /* still a declaration */ $legacy = false;

        $this->seen = $legacy ? 1 : 0;
    }
}

class LateStaticBinding
{
    public function __construct(bool $legacy)
    {
        $this->mode = static::resolve($legacy ? 1 : 0);
        $this->clone = new static($legacy ? true : false);
        $this->makers = [static function (): int { return 1; }, $legacy ? 1 : 0];
    }
}

class GlobalImport
{
    public function __construct(bool $legacy)
    {
        if ($legacy) {
            $this->mode = 'legacy';
        }

        global $legacy;

        $this->seen = $legacy ? 1 : 0;
    }
}
PHP;

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 7, 'column' => 24, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 21, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 39, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 53, 'column' => 39, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 54, 'column' => 35, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 55, 'column' => 65, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 63, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
    ]);
});

it('reads a name a re-binding construct only spells as still the parameter', function (): void {
    $source = <<<'PHP'
<?php

class BracedMemberName
{
    public function __construct(bool $legacy, array $items, object $target)
    {
        foreach ($items as $target->{/* the name, not a block */$legacy}) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class VariableMemberName
{
    public function __construct(bool $legacy, array $items, object $target)
    {
        foreach ($items as $target->$legacy) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class SubscriptKey
{
    public function __construct(bool $legacy, array $items, array $target)
    {
        foreach ($items as $target[$legacy]) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class StaticPropertyName
{
    public function __construct(bool $legacy, array $items)
    {
        foreach ($items as Registry::$legacy) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class DestructuredElementKey
{
    public function __construct(bool $legacy, array $rows)
    {
        foreach ($rows as [$legacy => $row]) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class ListedElementKey
{
    public function __construct(bool $legacy, array $rows)
    {
        foreach ($rows as list($legacy => $row)) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class VariableVariableImport
{
    public function __construct(bool $legacy)
    {
        global $$legacy;

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class BracedVariableVariableImport
{
    public function __construct(bool $legacy)
    {
        global ${$legacy};

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class MemberContainer
{
    public function __construct(bool $legacy, array $items)
    {
        foreach ($items as $legacy/* the container */->slot) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class SubscriptContainer
{
    public function __construct(bool $legacy, array $items, string $key)
    {
        foreach ($items as $legacy[$key]) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class StaticPropertyContainer
{
    public function __construct(bool $legacy, array $items)
    {
        foreach ($items as $legacy::$slot) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class NullsafeMemberName
{
    public function __construct(bool $legacy, array $items, object $target)
    {
        foreach ($items as $target?->$legacy) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}

class NullsafeMemberContainer
{
    public function __construct(bool $legacy, array $items)
    {
        foreach ($items as $legacy?->slot) {
        }

        $this->mode = $legacy ? 'legacy' : 'modern';
    }
}
PHP;

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 10, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 21, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 32, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 43, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 54, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 65, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 75, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 85, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 96, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 107, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 118, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 129, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 140, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
    ]);
});

it('re-binds a name a construct writes bare', function (): void {
    $sources = [
        'value target' => 'foreach ($items as $legacy) {',
        'key target' => 'foreach ($items as $legacy => $row) {',
        'destructured element' => 'foreach ($items as [$first, $legacy]) {',
        'listed element' => 'foreach ($items as list($first, $legacy)) {',
        'by-reference value' => 'foreach ($items as &$legacy) {',
    ];

    foreach ($sources as $shape => $header) {
        $source = <<<PHP
        <?php

        class BareTarget
        {
            public function __construct(bool \$legacy, array \$items)
            {
                {$header}
                    \$this->mode = \$legacy ? 'legacy' : 'modern';
                }
            }
        }
        PHP;

        $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

        expect(tuplesFromMessages($file->getWarnings()))->toBe([], $shape);
    }
});

it('reads a name a static initializer only reads as still the parameter', function (): void {
    $sources = [
        'bare read' => 'static $mode = $legacy;',
        'nested read' => 'static $result = strtoupper((string) $legacy);',
        'second declarator' => 'static $first = 1, $result = $legacy;',
        'argument-list comma' => 'static $result = sprintf("%s", $legacy, 1);',
    ];

    foreach ($sources as $shape => $declaration) {
        $source = <<<PHP
        <?php

        class StaticInitializerRead
        {
            public function __construct(bool \$legacy)
            {
                {$declaration}

                if (\$legacy) {
                    \$this->mode = 'legacy';
                } else {
                    \$this->mode = 'modern';
                }
            }
        }
        PHP;

        $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

        expect(tuplesFromMessages($file->getWarnings()))->toBe([
            ['line' => 9, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ], $shape);
    }
});

it('re-binds a name a static declarator writes, wherever it stands in the list', function (): void {
    $sources = [
        'only declarator' => 'static $legacy = false;',
        'second declarator' => 'static $first = 1, $legacy = false;',
    ];

    foreach ($sources as $shape => $declaration) {
        $source = <<<PHP
        <?php

        class StaticDeclarator
        {
            public function __construct(bool \$legacy)
            {
                {$declaration}

                if (\$legacy) {
                    \$this->mode = 'legacy';
                } else {
                    \$this->mode = 'modern';
                }
            }
        }
        PHP;

        $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

        expect(tuplesFromMessages($file->getWarnings()))->toBe([], $shape);
    }
});

it('reports a mode switch written in a static initializer', function (): void {
    $source = <<<'PHP'
    <?php

    class BranchInInitializer
    {
        public function __construct(bool $legacy)
        {
            static $mode = $legacy ? 'legacy' : 'modern';
        }
    }
    PHP;

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 7, 'column' => 24, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
    ]);
});

it('reads a ternary\'s sides no further than the constructor holding it', function (): void {
    $source = <<<'PHP'
    <?php

    class CutShortTernary
    {
        public function __construct(bool $legacy)
        {
            $this->mode = $legacy ? 'legacy'
        }
    }

    : throw new RuntimeException('a side belonging to no ternary');
    PHP;

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 7, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
    ]);
});

it('reads a type hint that cannot be normalised as written', function (): void {
    $expected = allViolationSourcesByLine(analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_replace',
            static fn (): array => allViolationSourcesByLine(analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/\s+/'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});

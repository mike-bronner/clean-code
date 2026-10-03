<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Constructors\NoLogicSniff;
use PHP_CodeSniffer\Util\Tokens;

const NO_LOGIC = 'CleanCode.Constructors.NoLogic';

const NO_LOGIC_FOUND = NO_LOGIC . '.LogicFound';

const NO_LOGIC_CHAINED = 'CleanCode.Models.DisallowChainedPropertyFetch';

const NO_LOGIC_CHAINED_ERROR = NO_LOGIC_CHAINED . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_LOGIC);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every non-assignment statement once, at its first token', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 24, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 31, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 34, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 37, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 40, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 43, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 47, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 54, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 59, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 62, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 65, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 76, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 83, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 86, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 89, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 92, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 110, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 111, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 112, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 113, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 114, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 115, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 116, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 117, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 118, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 119, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 120, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 157, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 158, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 159, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 160, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 161, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 180, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 197, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 221, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 222, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 223, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 224, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 225, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 226, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 227, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 228, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 229, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 231, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 264, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 265, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 266, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 267, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 268, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 269, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 270, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 271, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 272, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 302, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 303, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 304, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 305, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 306, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ['line' => 307, 'column' => 9, 'source' => NO_LOGIC_FOUND],
    ]);
});

it('flags every spelling of an invoking assignment target', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'failing.php')), 'line');

    expect($lines)
        ->toContain(221)
        ->toContain(222)
        ->toContain(223)
        ->toContain(224)
        ->toContain(225)
        ->toContain(226)
        ->toContain(227)
        ->toContain(228)
        ->toContain(229)
        ->not->toContain(230)
        ->toContain(231);
});

it('reads backslash parity the way PHP does, in both directions', function (
    string $key,
    bool $interpolates
): void {
    $source = "<?php\nclass ParityProbe {\nprivate array \$items;\n"
        . "public function __construct(\$value) {\n\$this->items[$key] = \$value;\n}\n"
        . "private function key(): string { return 'k'; } }\n";

    expect(isset(analyzeStdinSource([NO_LOGIC], $source)->getErrors()[5]))->toBe($interpolates);
})->with([
    '{$…}, no backslash'      => ['"{$this->key()}"', true],
    '{$…}, one backslash'     => ['"\\{$this->key()}"', false],
    '{$…}, two backslashes'   => ['"\\\\{$this->key()}"', true],
    '{$…}, three backslashes' => ['"\\\\\\{$this->key()}"', false],
    '{$…}, four backslashes'  => ['"\\\\\\\\{$this->key()}"', true],
    '${…}, no backslash'      => ['"${resolveKey()}"', true],
    '${…}, one backslash'     => ['"\\${resolveKey()}"', false],
    '${…}, two backslashes'   => ['"\\\\${resolveKey()}"', true],
    '${…}, three backslashes' => ['"\\\\\\${resolveKey()}"', false],
]);

const GROUPING_PROBES = [
    'T_PLUS' => '+($this->a)',
    'T_MINUS' => '-($this->a)',
    'T_MULTIPLY' => '$this->a * ($this->b)',
    'T_DIVIDE' => '$this->a / ($this->b)',
    'T_MODULUS' => '$this->a % ($this->b)',
    'T_POW' => '$this->a ** ($this->b)',
    'T_BITWISE_AND' => '$this->a & ($this->b)',
    'T_BITWISE_OR' => '$this->a | ($this->b)',
    'T_BITWISE_XOR' => '$this->a ^ ($this->b)',
    'T_BITWISE_NOT' => '~($this->a)',
    'T_SL' => '$this->a << ($this->b)',
    'T_SR' => '$this->a >> ($this->b)',
    'T_STRING_CONCAT' => '$this->prefix . ($this->a)',
    'T_IS_EQUAL' => '$this->a == ($this->b)',
    'T_IS_NOT_EQUAL' => '$this->a != ($this->b)',
    'T_IS_IDENTICAL' => '$this->a === ($this->b)',
    'T_IS_NOT_IDENTICAL' => '$this->a !== ($this->b)',
    'T_IS_GREATER_OR_EQUAL' => '$this->a >= ($this->b)',
    'T_IS_SMALLER_OR_EQUAL' => '$this->a <= ($this->b)',
    'T_GREATER_THAN' => '$this->a > ($this->b)',
    'T_LESS_THAN' => '$this->a < ($this->b)',
    'T_SPACESHIP' => '$this->a <=> ($this->b)',
    'T_BOOLEAN_AND' => '$this->a && ($this->b)',
    'T_BOOLEAN_OR' => '$this->a || ($this->b)',
    'T_BOOLEAN_NOT' => '!($this->a)',
    'T_LOGICAL_AND' => '$this->a and ($this->b)',
    'T_LOGICAL_OR' => '$this->a or ($this->b)',
    'T_LOGICAL_XOR' => '$this->a xor ($this->b)',
    'T_INLINE_THEN' => '$this->a ? ($this->a) : 0',
    'T_INLINE_ELSE' => '$this->a ? 0 : ($this->b)',
    'T_COALESCE' => '$this->a ?? ($this->b)',
    'T_INSTANCEOF' => '$this->other instanceof ($this->prefix)',
    'T_ASPERAND' => '@($this->a)',
    'T_INT_CAST' => '(int) ($this->a)',
    'T_DOUBLE_CAST' => '(float) ($this->a)',
    'T_STRING_CAST' => '(string) ($this->a)',
    'T_ARRAY_CAST' => '(array) ($this->a)',
    'T_OBJECT_CAST' => '(object) ($this->a)',
    'T_BOOL_CAST' => '(bool) ($this->a)',
    'T_UNSET_CAST' => '(unset) ($this->a)',
    'T_BINARY_CAST' => '(binary) ($this->a)',
    'T_OPEN_PARENTHESIS' => '(($this->a))',
    'T_OPEN_SQUARE_BRACKET' => '($this->a)',
    'T_OPEN_SHORT_ARRAY' => '[($this->a)][0]',
    'T_COMMA' => '[$this->a, ($this->b)][0]',
    'T_DOUBLE_ARROW' => '[1 => ($this->a)][1]',
    'T_OPEN_CURLY_BRACKET' => '{($this->prefix)}',
];

it('accepts a grouping parenthesis after every listed preceder', function (string $probe): void {
    $target = str_starts_with($probe, '{') ? "\$this->$probe" : "\$this->items[$probe]";
    $source = "<?php\nclass GroupingProbe { private array \$items; private int \$a = 1;\n"
        . "private int \$b = 2; private string \$prefix = 'p'; private \$other;\n"
        . "public function __construct(\$value) {\n$target = \$value;\n} }\n";

    expect(analyzeStdinSource([NO_LOGIC], $source)->getErrors())->toBe([]);
})->with(GROUPING_PROBES);

it('probes every entry in the grouping-preceder enumeration', function (): void {
    $enumerated = (new ReflectionClass(NoLogicSniff::class))
        ->getConstant('GROUPING_PARENTHESIS_PRECEDERS');
    $probed = array_map(constant(...), array_keys(GROUPING_PROBES));

    sort($enumerated);
    sort($probed);

    expect($probed)->toBe($enumerated);
});

const WRITING_PROBES = [
    'T_EQUAL' => '$this->a = $this->b',
    'T_PLUS_EQUAL' => '$this->a += 1',
    'T_MINUS_EQUAL' => '$this->a -= 1',
    'T_MUL_EQUAL' => '$this->a *= 2',
    'T_DIV_EQUAL' => '$this->a /= 2',
    'T_MOD_EQUAL' => '$this->a %= 2',
    'T_POW_EQUAL' => '$this->a **= 2',
    'T_CONCAT_EQUAL' => '$this->prefix .= "x"',
    'T_AND_EQUAL' => '$this->a &= 1',
    'T_OR_EQUAL' => '$this->a |= 1',
    'T_XOR_EQUAL' => '$this->a ^= 1',
    'T_SL_EQUAL' => '$this->a <<= 1',
    'T_SR_EQUAL' => '$this->a >>= 1',
    'T_COALESCE_EQUAL' => '$this->a ??= 1',
    'T_INC' => '$this->a++',
    'T_DEC' => '--$this->a',
];

it('flags every writing assignment target', function (string $probe): void {
    $source = "<?php\nclass WritingProbe { private array \$items; private int \$a = 1;\n"
        . "private int \$b = 2; private string \$prefix = 'p';\n"
        . "public function __construct(\$value) {\n\$this->items[$probe] = \$value;\n} }\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([['line' => 5, 'column' => 1, 'source' => NO_LOGIC_FOUND]]);
})->with(WRITING_PROBES);

it('probes every token the target scan rejects as a write', function (): void {
    $sniff = new ReflectionClass(NoLogicSniff::class);

    $rejected = array_diff(
        array_merge(
            array_values(Tokens::$assignmentTokens),
            $sniff->getConstant('WRITING_TOKENS')
        ),
        $sniff->getConstant('NON_WRITING_ASSIGNMENT_TOKENS')
    );
    $probed = array_map(constant(...), array_keys(WRITING_PROBES));

    sort($rejected);
    sort($probed);

    expect($probed)->toBe(array_values($rejected));
});

const INVOKING_PROBES = [
    'T_BACKTICK' => '`hostname`',
    'T_NEW' => 'new InvokingProbeSeed',
    'T_CLONE' => 'clone $this->other',
    'T_EXIT' => 'exit',
    'T_PRINT' => 'print $this->prefix',
    'T_THROW' => 'throw $this->other',
    'T_YIELD' => 'yield $this->prefix',
    'T_YIELD_FROM' => 'yield from $this->items',
    'T_INCLUDE' => 'include $this->prefix',
    'T_INCLUDE_ONCE' => 'include_once $this->prefix',
    'T_REQUIRE' => 'require $this->prefix',
    'T_REQUIRE_ONCE' => 'require_once $this->prefix',
];

it('flags every invoking token in an assignment target', function (string $probe): void {
    $source = "<?php\nclass InvokingProbe { private array \$items; private string \$prefix = 'p';\n"
        . "private \$other;\n"
        . "public function __construct(\$value) {\n\$this->items[$probe] = \$value;\n} }\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([['line' => 5, 'column' => 1, 'source' => NO_LOGIC_FOUND]]);
})->with(INVOKING_PROBES);

it('flags eval in an assignment target through the call scan', function (): void {
    $source = "<?php\nclass EvalProbe { private array \$items; private string \$prefix = 'p';\n"
        . "private \$other;\n"
        . "public function __construct(\$value) {\n\$this->items[eval(\$this->prefix)] = \$value;\n} }\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([['line' => 5, 'column' => 1, 'source' => NO_LOGIC_FOUND]]);
});

const BLOCK_STATEMENT_PROBES = [
    'T_IF' => "if (\$value) {\n    \$this->x();\n}",
    'T_ELSEIF' => "if (\$value) {\n    \$this->x();\n} elseif (\$value) {\n    \$this->y();\n}",
    'T_ELSE' => "if (\$value) {\n    \$this->x();\n} else {\n    \$this->y();\n}",
    'T_FOR' => "for (\$i = 0; \$i < 1; \$i++) {\n    \$this->x();\n}",
    'T_FOREACH' => "foreach ([] as \$v) {\n    \$this->x();\n}",
    'T_WHILE' => "while (\$value) {\n    \$this->x();\n}",
    'T_DO' => "do {\n    \$this->x();\n} while (\$value);",
    'T_SWITCH' => "switch (\$value) {\n    default:\n        \$this->x();\n}",
    'T_TRY' => "try {\n    \$this->x();\n} catch (Throwable \$e) {\n    \$this->y();\n}",
    'T_CATCH' => "try {\n    \$this->x();\n} catch (Throwable \$e) {\n    \$this->y();\n}",
    'T_FINALLY' => "try {\n    \$this->x();\n} finally {\n    \$this->y();\n}",
    'T_DECLARE' => "declare(ticks=1) {\n    \$this->x();\n}",
    'T_FUNCTION' => "function blockProbeHelper()\n{\n    echo 1;\n}",
];

const ALTERNATIVE_SYNTAX_PROBES = [
    'T_ENDIF' => "if (\$value):\n    \$this->x();\nendif;",
    'T_ENDFOR' => "for (\$i = 0; \$i < 1; \$i++):\n    \$this->x();\nendfor;",
    'T_ENDFOREACH' => "foreach ([] as \$v):\n    \$this->x();\nendforeach;",
    'T_ENDWHILE' => "while (\$value):\n    \$this->x();\nendwhile;",
    'T_ENDSWITCH' => "switch (\$value):\n    default:\n        \$this->x();\nendswitch;",
    'T_ENDDECLARE' => "declare(ticks=1):\n    \$this->x();\nenddeclare;",
];

it('ends a block statement at its own structure, not at a semicolon inside it', function (
    string $probe
): void {
    $tail = 6 + substr_count($probe, "\n") + 1;
    $body = '        ' . str_replace("\n", "\n        ", $probe);
    $source = "<?php\nclass BlockProbe\n{\n    public function __construct(\$value)\n    {\n"
        . $body . "\n        \$this->tail();\n    }\n\n"
        . "    private function x(): void {}\n\n    private function y(): void {}\n\n"
        . "    private function tail(): void {}\n}\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([
            ['line' => 6, 'column' => 9, 'source' => NO_LOGIC_FOUND],
            ['line' => $tail, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ]);
})->with(BLOCK_STATEMENT_PROBES + ALTERNATIVE_SYNTAX_PROBES);

const BRACKET_DEPTH_PROBES = [
    'T_OPEN_SQUARE_BRACKET' => "\$this->items['k']",
    'T_CLOSE_SQUARE_BRACKET' => "\$this->items['k']",
    'T_OPEN_SHORT_ARRAY' => '$this->items[[1, 2][0]]',
    'T_CLOSE_SHORT_ARRAY' => '$this->items[[1, 2][0]]',
    'T_OPEN_PARENTHESIS' => '$this->items[($this->a)]',
    'T_CLOSE_PARENTHESIS' => '$this->items[($this->a)]',
    'T_OPEN_CURLY_BRACKET' => '$this->{$this->prefix}',
    'T_CLOSE_CURLY_BRACKET' => '$this->{$this->prefix}',
];

it('counts bracket depth so a compliant target keeps its top-level operator', function (
    string $target
): void {
    $source = "<?php\nclass BracketProbe { private array \$items; private int \$a = 1;\n"
        . "private string \$prefix = 'p';\n"
        . "public function __construct(\$value) {\n$target = \$value;\n} }\n";

    expect(analyzeStdinSource([NO_LOGIC], $source)->getErrors())->toBe([]);
})->with(BRACKET_DEPTH_PROBES);

const INTERPOLATION_PROBES = [
    'T_DOUBLE_QUOTED_STRING' => '"{$this->key()}"',
    'T_HEREDOC' => "<<<KEY\n{\$this->key()}\nKEY",
];

it('flags a call hidden in every interpolatable string token', function (string $probe): void {
    $source = "<?php\nclass InterpolationProbe { private array \$items;\n"
        . "public function __construct(\$value) {\n\$this->items[$probe] = \$value;\n"
        . "}\nprivate function key(): string { return 'k'; } }\n";

    expect(array_column(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()), 'line'))
        ->toBe([4]);
})->with(INTERPOLATION_PROBES);

const NO_LOGIC_ENUMERATIONS = [
    'ALTERNATIVE_SYNTAX_CLOSERS' => 'ALTERNATIVE_SYNTAX_PROBES',
    'BLOCK_STATEMENT_TOKENS' => 'BLOCK_STATEMENT_PROBES',
    'BRACKET_CLOSERS' => 'BRACKET_DEPTH_PROBES',
    'BRACKET_OPENERS' => 'BRACKET_DEPTH_PROBES',
    'CONTINUATION_KEYWORDS' => 'BLOCK_STATEMENT_PROBES',
    'GROUPING_PARENTHESIS_PRECEDERS' => 'GROUPING_PROBES',
    'INTERPOLATABLE_STRING_TOKENS' => 'INTERPOLATION_PROBES',
    'INVOKING_TOKENS' => 'INVOKING_PROBES',
    'NON_WRITING_ASSIGNMENT_TOKENS' => 'GROUPING_PROBES',
    'WRITING_TOKENS' => 'WRITING_PROBES',
];

it('claims every hand-enumerated token list in the sniff', function (): void {
    $tokenValues = array_filter(
        get_defined_constants(),
        static fn (string $name): bool => str_starts_with($name, 'T_'),
        ARRAY_FILTER_USE_KEY
    );

    $lists = ['tokens' => [], 'other' => []];

    foreach ((new ReflectionClass(NoLogicSniff::class))->getReflectionConstants() as $constant) {
        $value = $constant->getValue();

        if (is_array($value) === false) {
            continue;
        }

        $isTokenList = $value !== [] && array_diff($value, array_values($tokenValues)) === [];
        $lists[$isTokenList ? 'tokens' : 'other'][] = $constant->getName();
    }

    sort($lists['tokens']);

    expect($lists['tokens'])->toBe(array_keys(NO_LOGIC_ENUMERATIONS))
        ->and($lists['other'])->toBe(['GROUP_CLOSER_KEYS']);
});

it('probes every member of every hand-enumerated token list', function (
    string $enumeration,
    string $probeSet
): void {
    $members = (new ReflectionClass(NoLogicSniff::class))->getConstant($enumeration);
    $probed = array_map(constant(...), array_keys(constant($probeSet)));

    expect($members)->not->toBe([])
        ->and(array_values(array_diff($members, $probed)))->toBe([]);
})->with(array_map(
    static fn (string $enumeration, string $probeSet): array => [$enumeration, $probeSet],
    array_keys(NO_LOGIC_ENUMERATIONS),
    array_values(NO_LOGIC_ENUMERATIONS)
));

$groupCloserProbeSource = static function (): string {
    return "<?php\nclass CloserProbe { private array \$items; private \$other;\n"
        . "public function __construct(\$value) {\n"
        . "\$this->items = array_map(function (\$i) { \$x = \$i; return \$x; }, [1, 2]);\n"
        . "\$this->other = match (\$value) { default => 1 };\n"
        . "\$this->anon = new class { public function m() { \$y = 1; return \$y; } };\n"
        . "\$this->fn = fn (\$i) => \$i;\n"
        . "for (\$i = 0; \$i < 1; \$i++) { \$this->items[] = \$i; }\n} }\n";
};

it('keeps the group-closer keys honest about what the tokeniser guarantees', function () use (
    $groupCloserProbeSource
): void {
    $keys = (new ReflectionClass(NoLogicSniff::class))->getConstant('GROUP_CLOSER_KEYS');

    expect($keys)->toContain('bracket_closer')
        ->and($keys)->toContain('scope_closer');

    $tokens = analyzeStdinSource([NO_LOGIC], $groupCloserProbeSource())->getTokens();
    $scopedBraces = 0;
    $nestedSemicolons = 0;

    $bracedBetween = static function (array $tokens, int $from, int $to): bool {
        for ($ptr = $from + 1; $ptr < $to; $ptr++) {
            if (
                $tokens[$ptr]['code'] === T_OPEN_CURLY_BRACKET
                && ($tokens[$ptr]['bracket_closer'] ?? 0) > $to
            ) {
                return true;
            }
        }

        return false;
    };

    foreach ($tokens as $ptr => $token) {
        if ($token['code'] === T_OPEN_CURLY_BRACKET && isset($token['scope_closer'])) {
            $scopedBraces++;

            expect($token['bracket_closer'] ?? null)->toBe($token['scope_closer']);
        }

        if ($token['code'] !== T_SEMICOLON || ($token['nested_parenthesis'] ?? []) === []) {
            continue;
        }

        $nestedSemicolons++;
        $opener = array_key_last($token['nested_parenthesis']);

        if ($bracedBetween($tokens, $opener, $ptr) === true) {
            continue;
        }

        expect($tokens[$tokens[$opener]['parenthesis_owner'] ?? $opener]['code'])->toBe(T_FOR);
    }

    expect($scopedBraces)->toBeGreaterThan(0)
        ->and($nestedSemicolons)->toBeGreaterThan(0);
});

it('carries only group-closer attributes the tokeniser emits', function () use (
    $groupCloserProbeSource
): void {
    $keys = (new ReflectionClass(NoLogicSniff::class))->getConstant('GROUP_CLOSER_KEYS');
    $tokens = analyzeStdinSource([NO_LOGIC], $groupCloserProbeSource())->getTokens();
    $carriers = array_fill_keys($keys, 0);

    foreach ($tokens as $pointer => $token) {
        foreach ($keys as $key) {
            if (isset($token[$key]) === true && $token[$key] > $pointer) {
                $carriers[$key]++;
            }
        }
    }

    expect($keys)->not->toBe([]);

    foreach ($carriers as $key => $carried) {
        expect($carried)->toBeGreaterThan(0, "no token in the corpus carries {$key} forward");
    }
});

it('leaves a reading assignment target alone', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'passing.php')), 'line');

    expect($lines)
        ->not->toContain(330)
        ->not->toContain(331)
        ->not->toContain(332)
        ->not->toContain(333);
});

it('leaves a reading assignment target and an invoking right-hand side alone', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'passing.php')), 'line');

    expect($lines)
        ->not->toContain(230)
        ->not->toContain(231)
        ->not->toContain(234)
        ->not->toContain(237)
        ->not->toContain(238)
        ->not->toContain(239)
        ->not->toContain(301);
});

it('leaves a multi-hop assignment target to the chained-fetch sniff', function (): void {
    $staged = stageFixtureOutsideTests(fixturePath('NoLogicSniff', 'passing.php'));
    $sources = allViolationSourcesByLine(analyzeWithSniffs([NO_LOGIC, NO_LOGIC_CHAINED], $staged));

    expect($sources[360] ?? [])->toBe([NO_LOGIC_CHAINED_ERROR])
        ->and($sources[361] ?? [])->toBe([NO_LOGIC_CHAINED_ERROR]);
});

it('reports a chained construct once, never per clause', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'failing.php')), 'line');

    expect($lines)
        ->not->toContain(26)
        ->not->toContain(28)
        ->not->toContain(42)
        ->not->toContain(49)
        ->not->toContain(51)
        ->not->toContain(56)
        ->not->toContain(78)
        ->not->toContain(80);
});

it('does not report the terminator of an alternative-syntax construct', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'failing.php')), 'line');

    expect($lines)
        ->not->toContain(82)
        ->not->toContain(85)
        ->not->toContain(88)
        ->not->toContain(91)
        ->not->toContain(95);
});

it('reports the failing fixture as errors, never warnings', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'failing.php');

    expect($file->getErrorCount())->toBe(59)
        ->and($file->getWarningCount())->toBe(0);
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'failing.php');

    expect($file->getErrorCount())->toBe(59)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

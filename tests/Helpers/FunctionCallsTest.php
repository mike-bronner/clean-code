<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Util\Tokens;

it('rejects every shape that is not a call to a global function', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'passing.php'), 'probe');

    expect($verdicts)->toBe([
        'probeImported' => [false, false],
        'probeSource' => [false],
        'probeAliased' => [false, false],
        'probeListedFirst' => [false, false],
        'probeListedLast' => [false, false],
        'probeListedAliased' => [false],
        'probeListedAlias' => [false, false],
        'probeListedTrailing' => [false, false],
        'probeGrouped' => [false, false],
        'probeRenamed' => [false],
        'probeGroupAlias' => [false, false],
        'probeMixed' => [false, false],
        'probeMethod' => [false],
        'probeNullsafeMethod' => [false],
        'probeStatic' => [false],
        'probeDeclared' => [false],
        'probeByReference' => [false],
        'probeMethodDeclaration' => [false],
        'probeByReferenceMethod' => [false],
        'probeInstance' => [false],
        'probeGlobalInstance' => [false],
        'probeRelativeInstance' => [false],
        'probeNamespacedInstance' => [false],
        'probeQualified' => [false],
        'probeRelative' => [false],
        'probeAttribute' => [false],
        'probeNotCalled' => [false],
    ]);
});

it('accepts a call that reaches PHP own global function', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'failing.php'), 'probe');

    expect($verdicts)->toBe([
        'probeClassImport' => [false, true],
        'probeConstantImport' => [false, true],
        'probeAliasedAway' => [false, true],
        'probeAliasTarget' => [false],
        'probeOtherBlock' => [false, true],
        'probeSegmentNamed' => [false, true],
        'probeBare' => [true],
        'probeFullyQualified' => [true],
        'probeBitwiseOperator' => [true],
        'probeInsideClosure' => [true],
        'probeInsideCapture' => [true, true],
        'probeCaptureLeak' => [false, true],
        'probeInsideMethod' => [true],
    ]);
});

it('rests a closure capture list on syntax rather than on its measured span', function (): void {
    $file = parseFixture('FunctionCalls', 'failing.php');
    $tokens = $file->getTokens();
    $captures = [];

    foreach ($tokens as $pointer => $token) {
        if ($token['code'] !== T_CLOSURE) {
            continue;
        }

        $usePtr = $file->findNext(T_USE, ($pointer + 1), $token['scope_opener']);

        if ($usePtr !== false) {
            $captures[$usePtr] = $token['scope_closer'];
        }
    }

    expect($captures)->toHaveCount(3);

    foreach ($captures as $usePtr => $scopeCloser) {
        $end = $file->findNext(T_SEMICOLON, ($usePtr + 1));
        $first = $file->findNext(Tokens::$emptyTokens, ($usePtr + 1), null, true);

        expect($tokens[$first]['code'])->toBe(T_OPEN_PARENTHESIS);

        expect($file->findNext(T_OPEN_USE_GROUP, ($usePtr + 1), $end))->toBeFalse();

        expect($end)->toBeLessThan($scopeCloser);
    }
});

it('resolves the shapes that depend on the namespace in force', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'global-namespace.php'), 'probe');

    expect($verdicts)->toBe([
        'probeSelfImport' => [false, true],
        'probeSelfSame' => [false, false, true],
        'probeSourceRenamed' => [false],
        'probeSelfAlias' => [false, false],
        'probeRelativeGlobal' => [true],
        'probeRelativeInstance' => [false],
    ]);
});

it('scopes an import to its own braced namespace block', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'braced-namespaces.php'), 'probe');

    expect($verdicts)->toBe([
        'probeBracedImport' => [false, false, true, true],
    ]);
});

it('keeps a trait use out of the import scan', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'trait-adaptation.php'), 'probe');

    expect($verdicts)->toBe([
        'probeAdaptationLeak' => [true, false, false],
    ]);
});

const FUNCTION_CALLS_PROBE_SNIFF = 'CleanCode.Constructors.DisallowCombinedConstructor';

it('reads its stream once per analysis, not once per name', function (): void {
    foreach ([2, 4, 8] as $size) {
        $source = "<?php\n\nclass PredicateProbe\n{\n    public function __construct(mixed \$value)\n    {\n"
            . '        $this->mode = ' . implode(' || ', array_fill(0, $size, 'is_string($value)'))
            . " ? 1 : 2;\n    }\n}\n";

        $sniff = sniffInstance(FUNCTION_CALLS_PROBE_SNIFF);
        $before = $sniff->analysisCounts();
        $file = analyzeStdinSource([FUNCTION_CALLS_PROBE_SNIFF], $source);
        $counted = cacheCountsDelta($before, $sniff->analysisCounts());

        expect($file->getWarningCount())->toBe($size, "n={$size}: every predicate is still reported")
            ->and($counted['builds'])->toBe(
                1,
                "n={$size}: the stream is read once, not once per name"
            )
            ->and($counted['hits'])->toBe(
                $size - 1,
                "n={$size}: every name after the first answers from the analysis already built"
            );
    }
});

it('tells two streams apart', function (): void {
    $importing = "<?php\n\nnamespace App;\n\nuse function App\\Vendor\\is_string;\n\n"
        . "class Importing\n{\n    public function __construct(mixed \$value)\n    {\n"
        . "        \$this->mode = is_string(\$value) ? 1 : 2;\n    }\n}\n";
    $bare = "<?php\n\nnamespace App;\n\nclass Bare\n{\n    public function __construct(mixed \$value)\n    {\n"
        . "        \$this->mode = is_string(\$value) ? 1 : 2;\n    }\n}\n";

    $first = analyzeStdinSource([FUNCTION_CALLS_PROBE_SNIFF], $importing);
    $second = analyzeStdinSource([FUNCTION_CALLS_PROBE_SNIFF], $bare);

    expect($first->getWarningCount())->toBe(0, 'the redirected name is not PHP own predicate')
        ->and($second->getWarningCount())->toBe(1, 'the bare name in the next stream still is');
});

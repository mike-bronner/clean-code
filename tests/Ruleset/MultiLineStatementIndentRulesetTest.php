<?php

declare(strict_types=1);

const MULTI_LINE_STATEMENT_INDENT_RULESET = 'CleanCode.WhiteSpace.MultiLineStatementIndent';
const FUNCTION_CALL_SIGNATURE_INDENT = 'PSR2.Methods.FunctionCallSignature.Indent';

it('excludes the call-signature indent code from the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MULTI_LINE_STATEMENT_INDENT_RULESET)
        ->and($ruleset->ruleset)->toHaveKey(FUNCTION_CALL_SIGNATURE_INDENT)
        ->and($ruleset->ruleset[FUNCTION_CALL_SIGNATURE_INDENT]['severity'] ?? null)->toBe(0);
});

it('still reports both lines the excluded code measured', function (): void {
    $source = <<<'PHP'
        <?php

        declare(strict_types=1);

        function misindented(string $first, string $second): void
        {
            report(
                        $first,
                    $second,
              );
        }

        PHP;

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-mlsi-coverage-', true) . '.php';
    file_put_contents($path, $source);

    try {
        $sources = allViolationSourcesByLine(analyzeWithMasterRuleset($path));
    } finally {
        unlink($path);
    }

    expect($sources[8] ?? [])->toBe([MULTI_LINE_STATEMENT_INDENT_RULESET . '.IncorrectIndent'])
        ->and($sources[10] ?? [])->toBe([MULTI_LINE_STATEMENT_INDENT_RULESET . '.CloseBracketIndent']);
});

it('converges on a statement that starts where a comment closes', function (): void {
    $source = <<<'PHP'
        <?php

        declare(strict_types=1);

        function annotated(array $context): void
        {
            /* explains the call
               across two lines */ report(
                    $context
                );

            $sum = 1  +  2;
        }

        PHP;

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-mlsi-converge-', true) . '.php';
    file_put_contents($path, $source);

    try {
        $file = analyzeWithMasterRuleset($path);
        $converged = $file->fixer->fixFile();
        $fixed = $file->fixer->getContents();
    } finally {
        unlink($path);
    }

    expect($converged)->toBeTrue()
        ->and($fixed)->toBe(str_replace('1  +  2', '1 + 2', $source));
});

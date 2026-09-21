<?php

/**
 * Pins the one wiring decision CleanCode.WhiteSpace.MultiLineStatementIndent
 * (Indentation: Multi-Line Statements, issue #38) required in the master
 * CleanCode/ruleset.xml: PSR2.Methods.FunctionCallSignature.Indent is excluded from the
 * PSR12 reference.
 *
 * The two sniffs measure the same thing on a multi-line call — the argument
 * lines and the closing bracket — and both auto-fix it, so left together they
 * report every such violation twice and disagree about one shape. Where a
 * statement starts on the line a multi-line comment closes on, PHPCS emits one
 * token per physical line of the comment and folds that line's leading
 * whitespace into the token, so the fragment's column is 1 and PSR2 reads the
 * call as sitting at indent 0. `phpcbf` then alternates between the two
 * answers until it exhausts its 50-pass budget and abandons the file whole,
 * discarding every other sniff's fixes in it — which is the failure this
 * exclusion exists to prevent, and the reason the assertions below check the
 * fixer's convergence rather than only the violation counts.
 *
 * Excluding a sniff is only safe if nothing it caught goes unreported, so the
 * coverage half is asserted first, against the same shapes PSR2 owned.
 */

declare(strict_types=1);

const MULTI_LINE_STATEMENT_INDENT_RULESET = 'CleanCode.WhiteSpace.MultiLineStatementIndent';
const FUNCTION_CALL_SIGNATURE_INDENT = 'PSR2.Methods.FunctionCallSignature.Indent';

/**
 * Each shape below is written to a temporary file rather than committed as a
 * fixture, the way the scale tests in tests/Standards do: they are a handful of
 * lines each, and what every one of them is for is the indentation itself,
 * which a fixture file would separate from the assertion reading it.
 */

it('excludes the call-signature indent code from the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MULTI_LINE_STATEMENT_INDENT_RULESET)
        ->and($ruleset->ruleset)->toHaveKey(FUNCTION_CALL_SIGNATURE_INDENT)
        ->and($ruleset->ruleset[FUNCTION_CALL_SIGNATURE_INDENT]['severity'] ?? null)->toBe(0);
});

/**
 * The coverage half of the exclusion. Both lines PSR2 measured on a multi-line
 * call — an argument, and the closing bracket — still report under the master
 * ruleset, now from the sniff that replaced it, and each reports exactly once
 * rather than twice.
 */
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

/**
 * The convergence half, asserted through the damage rather than through a
 * status flag. A statement starting on the line a comment closes on is the
 * shape the two sniffs disagreed about, and it is documented compliant —
 * passing.php carries it. So the file below pairs that shape with one ordinary,
 * unrelated, fixable violation somewhere else in it: doubled spaces around an
 * operator, which Squiz.WhiteSpace.OperatorSpacing owns.
 *
 * When the fixer gives up it discards the whole file, not the disputed lines,
 * so before the exclusion the operator spacing came back unfixed even though
 * nothing about it was ever in dispute. That is the assertion — the spacing is
 * fixed and the comment-opened statement is returned exactly as written.
 *
 * The unrelated violation is also what makes the run meaningful: `fixFile()`
 * returns false both when it gives up and when there is nothing to fix at all
 * (Fixer.php:144-148), so a file with only compliant lines in it would return
 * false either way and assert nothing.
 */
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

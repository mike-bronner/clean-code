<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the "Indentation: Methods" clean-code standard: a method body must
 * not nest control structures more than 2 levels deep. Deep nesting signals
 * mixed concerns and is a prompt to refactor (extract a method, invert a
 * condition, use a guard clause).
 *
 * Nesting is counted per control structure — `if`/`elseif`/`else`, loops
 * (`for`/`foreach`/`while`/`do`), `switch`/`match`, `try`/`catch`/`finally`,
 * and closures. `case`/`default` labels do NOT add a level (they belong to the
 * enclosing `switch`), and `elseif`/`else`/`catch`/`finally` sit at the same
 * level as the `if`/`try` they continue rather than nesting beneath it.
 *
 * Each control structure whose level exceeds the maximum is reported at its own
 * line, so every excess nesting level is flagged individually. The rule applies
 * only inside a function/method body (a closure inside a method still counts as
 * a nesting level); top-level script code is out of scope.
 *
 * Not auto-fixable: reducing nesting requires a semantic refactor that cannot
 * be applied safely by a token rewriter.
 */
class MethodNestingLevelSniff implements Sniff
{
    /**
     * A method body may nest control structures at most this many levels deep.
     */
    private const MAX_NESTING_LEVEL = 2;

    /**
     * Control structures that each add one level of nesting. `case`/`default`
     * and the enclosing function/class are deliberately excluded; `elseif`,
     * `else`, `catch`, and `finally` are included so statements inside those
     * blocks are counted at the correct depth.
     *
     * @var array<int|string, true>
     */
    private const NESTING_TOKENS = [
        T_IF => true,
        T_ELSEIF => true,
        T_ELSE => true,
        T_FOR => true,
        T_FOREACH => true,
        T_WHILE => true,
        T_DO => true,
        T_SWITCH => true,
        T_MATCH => true,
        T_TRY => true,
        T_CATCH => true,
        T_FINALLY => true,
        T_CLOSURE => true,
    ];

    /**
     * The control-structure openers reported when they exceed the limit.
     * `elseif`/`else`/`catch`/`finally` are not listed: each continues a
     * construct whose opener (`if`/`try`) sits at the same level and is already
     * reported, so listing them would double-report one nesting level.
     *
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_IF,
            T_FOR,
            T_FOREACH,
            T_WHILE,
            T_DO,
            T_SWITCH,
            T_MATCH,
            T_TRY,
            T_CLOSURE,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$stackPtr]['conditions'];

        // Braceless/abstract/interface bodies and the trailing while of a
        // do-while have no scope to measure.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        // The standard governs method bodies; skip top-level script code and
        // closures not enclosed by a function.
        if (in_array(T_FUNCTION, $conditions, true) === false) {
            return;
        }

        $level = 1;

        foreach ($conditions as $conditionCode) {
            if (isset(self::NESTING_TOKENS[$conditionCode]) === true) {
                $level++;
            }
        }

        if ($level <= self::MAX_NESTING_LEVEL) {
            return;
        }

        $phpcsFile->addError(
            'Method nesting level (%s) exceeds the maximum of %s; refactor to reduce nesting',
            $stackPtr,
            'MaxExceeded',
            [$level, self::MAX_NESTING_LEVEL]
        );
    }
}

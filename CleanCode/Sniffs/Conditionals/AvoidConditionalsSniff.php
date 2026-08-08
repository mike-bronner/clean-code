<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces the "Conditionals: Avoid Conditionals" standard.
 *
 * Reports one **warning** per conditional branch, because "avoid where
 * possible" is a judgement the reader makes, not a defect the tokenizer can
 * prove. The sniff's job is to make each branch visible and countable; whether
 * a given branch was avoidable stays with code review.
 *
 * Coverage — exactly four constructs are reported, one warning each:
 *
 * | Construct                | Code                | Reported at        |
 * |--------------------------|---------------------|--------------------|
 * | `if` (incl. `else if`)   | `IfStatement`       | the `if` keyword   |
 * | `elseif`                 | `ElseIfStatement`   | the `elseif`       |
 * | ternary `?:` (incl. `?:`)| `TernaryExpression` | the `?`            |
 * | `switch`                 | `SwitchStatement`   | the `switch`       |
 *
 * Brace-less and alternative-syntax (`if:`/`endif`, `switch:`/`endswitch`)
 * forms are the same tokens, so they are covered identically.
 *
 * Deliberately **not** reported, and why:
 *
 * - `else` — it introduces no new condition. The standard's own rationale is
 *   cyclomatic complexity, and that metric counts `if`/`elseif`/`case`, never
 *   the `else`. Reporting it would double-count a single decision.
 * - `case` / `default` — a `switch` is reported once, at the keyword, because
 *   the remedy (replace the whole construct with a mapping array, `match`, or
 *   polymorphism) is one action, not one per arm.
 * - `match` — the recommended *replacement* for a branching `switch`/`if`
 *   chain, not a conditional to avoid.
 * - `??`, `??=`, `?->` — null-default and null-safe operators. They collapse a
 *   branch into an expression rather than adding one; this repo's ruleset
 *   already pushes code toward them.
 * - `?string` nullable type hints — punctuation, not a branch.
 * - loops (`while`, `for`, `foreach`, `do`) and `catch` — iteration and error
 *   handling, each covered by its own standard.
 *
 * Detection only: there is no general mechanical rewrite of a conditional, so
 * nothing here is auto-fixable. The one shape that *does* have a safe
 * mechanical fix — the boolean-return `if` (`if ($x) { return true; } return
 * false;`) — is auto-fixed by
 * `SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn`,
 * wired into the master `rules.xml` alongside this sniff. Applying that fixer
 * deletes the `if`, so this sniff's warning on it disappears with it. See
 * docs/standards/conditionals-avoid-conditionals.md.
 */
class AvoidConditionalsSniff implements Sniff
{
    /**
     * Token code => the construct name used in the message, and the violation
     * code it is reported under. Keyed in the order register() returns.
     *
     * @var array<int|string, array{string, string}>
     */
    private const CONSTRUCTS = [
        T_IF => ['if', 'IfStatement'],
        T_ELSEIF => ['elseif', 'ElseIfStatement'],
        T_INLINE_THEN => ['ternary', 'TernaryExpression'],
        T_SWITCH => ['switch', 'SwitchStatement'],
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return array_keys(self::CONSTRUCTS);
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // register() returns exactly the keys of CONSTRUCTS, so PHP_CodeSniffer
        // never dispatches a code that is absent from it — no guard needed.
        [$construct, $violationCode] = self::CONSTRUCTS[$tokens[$stackPtr]['code']];

        $phpcsFile->addWarning(
            'Avoid conditionals where possible: "%s" adds a branch, which raises cyclomatic complexity.'
                . ' Prefer polymorphism, a mapping array, or match where one applies.',
            $stackPtr,
            $violationCode,
            [$construct]
        );
    }
}

<?php

/**
 * Arrow functions, which the standard counts as anonymous functions exactly as
 * it counts closures, and which PHPCS makes the sniff work for.
 *
 * PHPCS records a closure in the `conditions` of the tokens inside it and does
 * not record an arrow function in the conditions of the tokens inside *its*
 * body. Read off `conditions` alone, the two halves of each pair below would
 * measure a level apart while reading identically — and the arrow-function half
 * would be the one that went unreported, which is the failure a linter must not
 * have. The sniff reads the arrow function's recorded scope span instead, so the
 * pairs measure the same.
 *
 * Every method here is at a violating depth on purpose: the compliant twins
 * (`closureBodyIsLevelTwo`, `arrowFunctionBodyIsLevelTwo`) live in passing.php,
 * where a level that started counting one too high would show.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\MethodNestingLevel;

class MethodNestingLevelArrowFunctions
{
    /**
     * The arrow function is itself the third level and is reported at the `fn`,
     * and its inline `match` body is the fourth. Deleting T_FN from register()
     * drops the first report; ignoring the arrow's span drops the second.
     */
    public function overNestedArrowFunction(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                $fn = fn () =>
                    match ($item) {
                        default => 0,
                    };
            }
        }
    }

    /**
     * A closure inside an arrow-function body: level 3, the pair-mate of
     * closureInsideClosureBody() below and the case that reads as three levels
     * to any reader. It is invisible to `conditions` — the closure's are
     * T_CLASS, T_FUNCTION, T_IF and nothing else — so the arrow's span is the
     * only thing that puts it at 3 rather than 2.
     */
    public function closureInsideArrowBody(): void
    {
        if ($this->flag) {
            $fn = fn () => (function (): void {
                echo 'x';
            })();
        }
    }

    /**
     * The same nesting written with two closures. It measures 3 off `conditions`
     * alone, so asserting the two together is what makes the pair a statement
     * about the standard rather than about PHPCS's token table.
     */
    public function closureInsideClosureBody(): void
    {
        if ($this->flag) {
            $fn = function (): void {
                (function (): void {
                    echo 'x';
                })();
            };
        }
    }

    /**
     * One arrow function inside another: the inner `fn` is level 3 and reported.
     * The outer is level 2 and is not.
     */
    public function arrowFunctionInsideArrowBody(): void
    {
        if ($this->flag) {
            $fn = fn () => fn () => 1;
        }
    }

    /**
     * The `if` here is level 3 — method, arrow function, closure — and it is the
     * one shape that reaches past the arrow function to a *nearer* enclosing
     * scope. Its conditions are T_FUNCTION and T_CLOSURE, and the closure opens
     * after the `fn` does, so a depth walk bounded at the nearest enclosing scope
     * rather than at the method body would start inside the arrow function and
     * measure 2. Deliberately without an enclosing `if`, so the arrow function is
     * carrying the level on its own.
     */
    public function structureInsideClosureInsideArrowBody(): void
    {
        $fn = fn () => (function (): void {
            if ($this->flag) {
                $x = 1;
            }
        })();
    }
}

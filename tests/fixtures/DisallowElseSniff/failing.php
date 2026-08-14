<?php

/**
 * Every shape CleanCode.Conditionals.DisallowElse reports — one per `else`
 * and one per `elseif` — split into the shapes its fixer rewrites and the
 * shapes it declines. Exact lines: tests/Standards/DisallowElseTest.php.
 */

declare(strict_types=1);

// A file-scope else. PHPMD's rule is method/function-aware and misses this
// one; the sniff reports it, because the else is just as avoidable here.
if (PHP_INT_SIZE === 8) {
    $architecture = '64-bit';
} else {
    $architecture = '32-bit';
}

class FailingConditionals
{
    public function plainElse(bool $flag): int
    {
        if ($flag) {
            $result = 1;
        } else {
            $result = 2;
        }

        return $result;
    }

    public function elseAfterEarlyReturn(bool $flag): int
    {
        if ($flag) {
            return 1;
        } else {
            return 2;
        }
    }

    public function elseIfChain(bool $flag, bool $other): int
    {
        if ($flag) {
            $result = 1;
        } elseif ($other) {
            $result = 2;
        } else {
            $result = 3;
        }

        return $result;
    }

    public function elseSpaceIfChain(bool $flag, bool $other): int
    {
        if ($flag) {
            $result = 1;
        } else if ($other) {
            $result = 2;
        } else {
            $result = 3;
        }

        return $result;
    }

    public function nestedElse(bool $flag, bool $other): int
    {
        if ($flag) {
            if ($other) {
                $result = 1;
            } else {
                $result = 2;
            }
        } else {
            $result = 3;
        }

        return $result;
    }

    public function bracelessElse(bool $flag): int
    {
        if ($flag)
            $result = 1;
        else
            $result = 2;

        return $result;
    }

    public function alternativeSyntaxElse(bool $flag): int
    {
        if ($flag):
            $result = 1;
        else:
            $result = 2;
        endif;

        return $result;
    }

    public function elseInsideClosure(bool $flag): callable
    {
        return static function () use ($flag): int {
            if ($flag) {
                return 1;
            } else {
                return 2;
            }
        };
    }

    // ------------------------------------------------------------------
    // Shapes the fixer rewrites: every branch before the keyword ends in a
    // terminating statement, and the layout is the canonical `} else {` /
    // `} elseif (…) {` one.
    // ------------------------------------------------------------------

    public function elseIfAfterEarlyReturn(bool $flag, bool $other): int
    {
        if ($flag) {
            return 1;
        } elseif ($other) {
            return 2;
        }

        return 3;
    }

    public function elseSpaceIfAfterThrow(bool $flag, bool $other): int
    {
        if ($flag) {
            throw new \RuntimeException('no first branch');
        } else if ($other) {
            return 2;
        }

        return 3;
    }

    public function terminatingChain(bool $flag, bool $other): int
    {
        if ($flag) {
            return 1;
        } elseif ($other) {
            return 2;
        } else {
            return 3;
        }
    }

    public function elseAfterContinue(array $items): array
    {
        $kept = [];

        foreach ($items as $item) {
            if ($item === null) {
                continue;
            } else {
                $kept[] = $item;
            }
        }

        return $kept;
    }

    public function elseAfterBreak(array $items): int
    {
        $seen = 0;

        foreach ($items as $item) {
            if ($item === null) {
                break;
            } else {
                $seen++;
            }
        }

        return $seen;
    }

    public function elseAfterExit(bool $flag): int
    {
        if ($flag) {
            exit(1);
        } else {
            return 2;
        }
    }

    /**
     * The dedent must not reach into a heredoc: its body lines and its
     * closing marker each carry their own leading spaces inside a single
     * token, and PHP strips the marker's indentation from every line, so
     * moving one without the other would change the string.
     */
    public function elseBodyWithHeredoc(bool $flag): string
    {
        if ($flag) {
            return 'first';
        } else {
            $message = <<<TEXT
                indented inside the heredoc
                TEXT;

            return $message;
        }
    }

    /**
     * The same guarantee for a multi-line double-quoted string, whose
     * continuation lines PHPCS tokenizes as string tokens rather than
     * whitespace.
     */
    public function elseBodyWithMultilineString(bool $flag): string
    {
        if ($flag) {
            return 'first';
        } else {
            $message = "first line
    second line";

            return $message;
        }
    }

    // ------------------------------------------------------------------
    // Shapes the fixer declines. One method per guard, so removing a guard
    // turns exactly one of these fixable and fails the fixable-flag
    // assertion in tests/Standards/DisallowElseTest.php.
    // ------------------------------------------------------------------

    /**
     * The `elseif` branch terminates but the `if` branch before it does not,
     * so unwrapping the `else` would let the `$flag` branch fall through into
     * `$result = 3`. Only a chain-wide walk sees that; checking the
     * immediately preceding branch alone would call this fixable.
     */
    public function chainWhereOnlyTheLastBranchTerminates(bool $flag, bool $other): int
    {
        if ($flag) {
            $result = 1;
        } elseif ($other) {
            return 2;
        } else {
            $result = 3;
        }

        return $result;
    }

    public function commentBeforeTheKeyword(bool $flag): int
    {
        if ($flag) {
            return 1;
        } /* keep the fallback */ else {
            return 2;
        }
    }

    public function commentBeforeTheBrace(bool $flag): int
    {
        if ($flag) {
            return 1;
        } else /* the fallback */ {
            return 2;
        }
    }

    public function commentBetweenElseAndIf(bool $flag, bool $other): int
    {
        if ($flag) {
            return 1;
        } else /* not an elseif */ if ($other) {
            return 2;
        }

        return 3;
    }

    public function keywordOnItsOwnLine(bool $flag): int
    {
        if ($flag) {
            return 1;
        }
        else {
            return 2;
        }
    }

    public function elseIfOnItsOwnLine(bool $flag, bool $other): int
    {
        if ($flag) {
            return 1;
        }
        elseif ($other) {
            return 2;
        }

        return 3;
    }

    public function inlineElseBody(bool $flag): int
    {
        if ($flag) {
            return 1;
        } else { return 2; }
    }

    public function commentTrailingTheClosingBrace(bool $flag): int
    {
        if ($flag) {
            return 1;
        } else {
            return 2;
        } // the fallback
    }

    public function nestedConstructAsLastStatement(bool $flag, array $items): int
    {
        if ($flag) {
            foreach ($items as $item) {
                return $item;
            }
        } else {
            return 0;
        }
    }

    public function emptyPrecedingBranch(bool $flag): int
    {
        if ($flag) {
        } else {
            return 2;
        }
    }

    public function bracelessElseIf(bool $flag, bool $other): int
    {
        $result = 0;

        if ($flag)
            return 1;
        elseif ($other)
            $result = 2;

        return $result;
    }

    public function alternativeSyntaxElseIf(bool $flag, bool $other): int
    {
        $result = 0;

        if ($flag):
            $result = 1;
        elseif ($other):
            $result = 2;
        endif;

        return $result;
    }

    /**
     * The opening-brace half of the inline-body guard. `inlineElseBody` above
     * puts the whole body on the keyword's line, so the closing-brace guard
     * catches it first; here the body opens on that line and closes on its
     * own, which only the opening-brace guard sees. Unwrapping it would leave
     * `} $log[] = 'first';`.
     */
    public function elseBodyOpeningOnTheKeywordLine(bool $flag): array
    {
        $log = [];

        if ($flag) {
            return ['early'];
        } else { $log[] = 'first';
            $log[] = 'second';
        }

        return $log;
    }

    // ------------------------------------------------------------------
    // Two more shapes the fixer rewrites, kept apart from the block above
    // because what they pin is the casing of its output rather than the
    // gates: PHP keywords are case-insensitive, and the rewrite keeps
    // whichever casing the source used. autofixed.php holds `IF` and `If`
    // for these two, not `if`.
    // ------------------------------------------------------------------

    public function upperCaseElseIf(bool $flag, bool $other): int
    {
        if ($flag) {
            return 1;
        } ELSEIF ($other) {
            return 2;
        }

        return 3;
    }

    public function mixedCaseElseSpaceIf(bool $flag, bool $other): int
    {
        if ($flag) {
            return 1;
        } ELSE If ($other) {
            return 2;
        }

        return 3;
    }
}

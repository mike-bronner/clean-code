<?php

/**
 * The function-scope matrix, flagged direction.
 *
 * function-scopes.php proves a body bounds the search. This file proves the
 * bound *limits* rather than exempts: a branch that lives inside the body,
 * alongside the check, is the check's own branch and must still be reported —
 * including when the whole body is written inside some outer branch's
 * condition, which is exactly where an over-broad exemption would go silent.
 *
 * Every violation here sits inside an inline anonymous class's method, the form
 * that keyword-matching used to miss in both directions at once.
 */

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

final class BranchesInsideInlineMethods
{
    /**
     * An `if` inside the method body, with the whole anonymous class written
     * inside an enclosing `if` condition.
     */
    public function ifInsideAnInlineMethod(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                if ($value instanceof \RuntimeException) {
                    return true;
                }

                return false;
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * A ternary inside the method body.
     */
    public function ternaryInsideAnInlineMethod(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return $value instanceof \RuntimeException ? true : false;
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * A `match` arm condition inside the method body.
     */
    public function matchArmInsideAnInlineMethod(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return match (true) {
                    $value instanceof \RuntimeException => true,
                    default => false,
                };
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * A `switch` case label inside the method body.
     */
    public function switchCaseInsideAnInlineMethod(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                switch (true) {
                    case $value instanceof \RuntimeException:
                        return true;
                    default:
                        return false;
                }
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * A `while` condition inside the method body.
     */
    public function whileInsideAnInlineMethod(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                while (get_class($value) === \RuntimeException::class) {
                    return true;
                }

                return false;
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * An introspection *function* rather than `instanceof`, in an `if` inside
     * the method body — the other reported code, on the same path.
     */
    public function introspectionFunctionInsideAnInlineMethod(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                if (is_a($value, \RuntimeException::class)) {
                    return true;
                }

                return false;
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * A plain branch after every inline body in the method has closed. The
     * bound must not leak past the body it belongs to.
     */
    public function branchAfterTheInlineBodyHasClosed(array $values, object $value): string
    {
        $matched = array_filter($values, new class {
            public function __invoke(object $candidate): bool
            {
                return $candidate instanceof \Throwable;
            }
        });

        if ($value instanceof \RuntimeException) {
            return 'runtime';
        }

        return $matched === [] ? 'none' : 'some';
    }
}

/**
 * Declarations that open no body at all. They are `function` tokens with no
 * scope, so the scope index has to leave them out rather than record a body
 * that never opened; a branch in the next real body must still be reported.
 */
interface Classifier
{
    public function classify(object $value): string;
}

abstract class PartialClassifier implements Classifier
{
    abstract public function describe(object $value): string;

    public function classify(object $value): string
    {
        if ($value instanceof \RuntimeException) {
            return 'runtime';
        }

        return 'other';
    }
}

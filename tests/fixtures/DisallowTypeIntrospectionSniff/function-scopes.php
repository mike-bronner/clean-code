<?php

/**
 * The function-scope matrix, silent direction.
 *
 * Every form of function body PHP has, written *inside* every branch-deciding
 * position the sniff recognises. In each cell the check decides what the body
 * returns; the enclosing construct then branches on the result of calling it.
 * The check is a predicate in all of them, so none may be flagged.
 *
 * passing.php already carries the closure and arrow-function rows. This file
 * adds the row that keyword-matching missed — a method body written inline, as
 * an anonymous class — and pairs it against the same eight positions, so the
 * grid is filled rather than sampled.
 */

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

final class InlineMethodPredicates
{
    /**
     * An `if` condition.
     */
    public function insideAnIfCondition(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return $value instanceof \Throwable;
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * An `elseif` condition — a separate token from `if`, so a separate cell.
     */
    public function insideAnElseIfCondition(array $values): string
    {
        if ($values === []) {
            return 'empty';
        } elseif (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return get_class($value) === \RuntimeException::class;
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * A `while` condition.
     */
    public function insideAWhileCondition(array $values): string
    {
        while (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return is_a($value, \Throwable::class);
            }
        })) {
            array_pop($values);
        }

        return 'drained';
    }

    /**
     * A `switch` subject.
     */
    public function insideASwitchSubject(array $values): string
    {
        switch (count(array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return $value instanceof \Throwable;
            }
        }))) {
            case 0:
                return 'none';
            default:
                return 'some';
        }
    }

    /**
     * A `switch` case label.
     */
    public function insideASwitchCaseLabel(array $values): string
    {
        switch (count($values)) {
            case count(array_filter($values, new class {
                public function __invoke(object $value): bool
                {
                    return gettype($value) === 'object';
                }
            })):
                return 'all';
            default:
                return 'some';
        }
    }

    /**
     * A `match` subject.
     */
    public function insideAMatchSubject(array $values): string
    {
        return match (count(array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return is_subclass_of($value, \Throwable::class);
            }
        }))) {
            0 => 'none',
            default => 'some',
        };
    }

    /**
     * A `match` arm condition.
     */
    public function insideAMatchArmCondition(array $values): string
    {
        return match (true) {
            array_filter($values, new class {
                public function __invoke(object $value): bool
                {
                    return $value instanceof \Throwable;
                }
            }) !== [] => 'some',
            default => 'none',
        };
    }

    /**
     * A ternary condition.
     */
    public function insideATernaryCondition(array $values): string
    {
        return array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                return get_debug_type($value) === 'object';
            }
        }) !== []
            ? 'some'
            : 'none';
    }

    /**
     * A named method of the anonymous class rather than `__invoke`, called
     * immediately. The body is the same kind of scope whatever it is named.
     */
    public function insideAnIfConditionViaANamedMethod(object $value): string
    {
        if ((new class {
            public function isFailure(object $value): bool
            {
                return $value instanceof \Throwable;
            }
        })->isFailure($value)) {
            return 'failure';
        }

        return 'ok';
    }

    /**
     * A `static` prefix opens the same tokens, so the closure and arrow
     * function rows hold in their static form too.
     */
    public function insideAnIfConditionViaStaticCallbacks(array $values): string
    {
        if (array_filter($values, static fn (object $value): bool => $value instanceof \Throwable)) {
            return 'some';
        }

        if (array_filter($values, static function (object $value): bool {
            return $value instanceof \Throwable;
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * Bodies nested two deep: an anonymous class inside a closure inside the
     * condition. The innermost body is the one that bounds the check.
     */
    public function insideNestedBodies(array $values): string
    {
        if (array_filter($values, function (object $value): bool {
            return (new class {
                public function isFailure(object $value): bool
                {
                    return $value instanceof \Throwable;
                }
            })->isFailure($value);
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * The reverse nesting — a closure inside an anonymous class's method,
     * itself inside the condition.
     */
    public function insideReverseNestedBodies(array $values): string
    {
        if (array_filter($values, new class {
            public function __invoke(object $value): bool
            {
                $predicate = fn (object $candidate): bool => $candidate instanceof \Throwable;

                return $predicate($value);
            }
        })) {
            return 'some';
        }

        return 'none';
    }
}

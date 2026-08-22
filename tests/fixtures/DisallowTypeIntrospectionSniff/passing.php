<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

final class Reporter
{
    private bool $verbose = false;

    public function __construct(private object $logger)
    {
    }

    /**
     * Naming the type in an exception message reports it; it decides nothing.
     */
    public function reject(object $value): string
    {
        throw new \InvalidArgumentException('Unsupported: ' . get_class($value));
    }

    public function log(mixed $value): void
    {
        $this->logger->debug(sprintf('received %s', gettype($value)));
    }

    public function check(object $value): void
    {
        assert($value instanceof \Throwable);
    }

    /**
     * A predicate reports a type; its caller decides what to do with it.
     */
    public function isThrowable(object $value): bool
    {
        return $value instanceof \Throwable;
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(object $value): array
    {
        return ['type' => get_class($value), 'id' => spl_object_id($value)];
    }

    /**
     * A ternary that merely *reports* the type is not branching on it.
     */
    public function report(object $value): string
    {
        return $this->verbose ? get_class($value) : 'hidden';
    }

    /**
     * An argument sitting beside an unrelated ternary is not its condition.
     */
    public function beside(object $value): string
    {
        return sprintf('%s/%s', get_class($value), $this->verbose ? 'on' : 'off');
    }

    public function insideABranchBody(object $value): string
    {
        if ($this->verbose) {
            return 'verbose: ' . get_class($value);
        }

        return 'quiet';
    }

    public function insideACaseBody(object $value): string
    {
        switch ($this->verbose) {
            case true:
                return 'verbose: ' . get_class($value);
            default:
                return 'quiet';
        }
    }

    public function insideAMatchResult(object $value): string
    {
        return match ($this->verbose) {
            true => 'verbose: ' . get_class($value),
            default => 'quiet',
        };
    }

    /**
     * A same-named method is not the global introspection function.
     */
    public function byMethod(object $value): string
    {
        if ($this->gettype($value) === 'thing') {
            return 'thing';
        }

        return 'other';
    }

    private function gettype(object $value): string
    {
        return 'thing';
    }

    /**
     * Nor is a static call, nor a nullsafe one, on a same-named helper.
     */
    public function byQualifiedCall(object $value): string
    {
        if (Helpers::gettype($value) === 'thing') {
            return 'thing';
        }

        if ($this->logger?->gettype($value) === 'thing') {
            return 'thing';
        }

        return 'other';
    }

    /**
     * Nor is a constructor call on a class of the same name. PHP keeps class
     * and function names in separate symbol tables, so the class declared at
     * the foot of this file is a different symbol from `get_class()`, and
     * `new` reaches the class whatever the function would have done here.
     */
    public function byConstructorCall(object $value): string
    {
        if (new get_class($value)) {
            return 'thing';
        }

        return new gettype($value) ? 'thing' : 'other';
    }

    /**
     * `new` still reaches a class when the class is named root-qualified. The
     * keyword governs the name however the name is spelled, so the leading `\`
     * changes which class is built — one declared in the global namespace
     * rather than in this file's — and changes nothing about `new` not being a
     * function call.
     *
     * The two branch positions are the same pair `byConstructorCall()` uses,
     * because the spelling is the only difference being pinned here.
     */
    public function byRootQualifiedConstructorCall(object $value): string
    {
        if (new \get_class($value)) {
            return 'thing';
        }

        return new \gettype($value) ? 'thing' : 'other';
    }

    /**
     * A name of two or more qualifying segments is read as one name, which is
     * what lets the keyword in front of it be found however long the name is.
     * The walk over the segments is the same walk `byRootQualifiedConstructorCall()`
     * ends after one step, run to its end instead.
     *
     * Three positions, because the walk decides three different answers here:
     * the two constructor calls are `new` reaching a class in another namespace
     * (the keyword sits before the whole name, not before its last segment),
     * and the plain call is that namespace's own function of the name, which
     * bare-call resolution never reaches. A walk that stopped at the last
     * segment would read all three as the global function and report every one
     * of them.
     */
    public function byMultiSegmentQualifiedName(object $value): string
    {
        if (new \App\Vendor\get_class($value)) {
            return 'thing';
        }

        if (\App\Vendor\get_class($value) === 'thing') {
            return 'named';
        }

        return new \App\Vendor\gettype($value) ? 'thing' : 'other';
    }

    /**
     * A constant sharing an introspection function's name is not a call.
     */
    public function byConstant(): string
    {
        if (GETTYPE === 1) {
            return 'one';
        }

        return 'other';
    }

    /**
     * Another namespace's function of the same name is not the global one.
     */
    public function byNamespacedFunction(object $value): string
    {
        if (Helpers\get_class($value) === 'thing') {
            return 'thing';
        }

        return 'other';
    }

    /**
     * A bare name that is not a call is not introspection either.
     */
    public function byConstantName(): string
    {
        if (self::class === 'nope') {
            return 'never';
        }

        return 'other';
    }

    /**
     * An arrow function passed into an `if` condition is a predicate: the
     * check decides what the callback returns, `array_filter` decides the
     * branch.
     */
    public function arrowFnInsideAnIfCondition(array $values): string
    {
        if (array_filter($values, fn (object $value): bool => $value instanceof \Throwable)) {
            return 'some';
        }

        return 'none';
    }

    /**
     * The braced closure form of the same predicate.
     */
    public function closureInsideAnIfCondition(array $values): string
    {
        if (array_filter($values, function (object $value): bool {
            return $value instanceof \Throwable;
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * A callback inside a `while` condition.
     */
    public function arrowFnInsideAWhileCondition(array $values): string
    {
        while (array_filter($values, fn (object $value): bool => is_a($value, \Throwable::class))) {
            array_pop($values);
        }

        return 'drained';
    }

    /**
     * A callback inside a `switch` subject.
     */
    public function arrowFnInsideASwitchSubject(array $values): string
    {
        switch (count(array_filter($values, fn (object $value): bool => $value instanceof \Throwable))) {
            case 0:
                return 'none';
        }

        return 'some';
    }

    /**
     * A callback inside a `match` subject.
     */
    public function arrowFnInsideAMatchSubject(array $values): string
    {
        return match (count(array_map(fn (object $value): string => get_class($value), $values))) {
            0 => 'none',
            default => 'some',
        };
    }

    /**
     * A callback inside a ternary's condition — the `?` belongs to the caller,
     * not to the arrow function.
     */
    public function arrowFnInsideATernaryCondition(array $values): string
    {
        return array_filter($values, fn (object $value): bool => $value instanceof \Throwable)
            ? 'some'
            : 'none';
    }

    /**
     * A callback inside a `match` arm's condition.
     */
    public function arrowFnInsideAMatchArmCondition(array $values): string
    {
        return match (true) {
            array_filter($values, fn (object $value): bool => $value instanceof \Throwable) !== [] => 'some',
            default => 'none',
        };
    }

    /**
     * A callback inside a `switch` case label. The arrow function carries no
     * return type on purpose: a `: bool` would end the backward walk at its
     * own colon, hiding whether the callback boundary is what stopped it.
     */
    public function arrowFnInsideASwitchCaseLabel(array $values): string
    {
        switch (1) {
            case count(array_filter($values, fn ($value) => gettype($value) === 'object')):
                return 'one';
        }

        return 'other';
    }

    /**
     * The same, with `instanceof` rather than an introspection function.
     */
    public function arrowFnInsideASwitchCaseLabelWithInstanceof(array $values): string
    {
        switch (1) {
            case count(array_filter($values, fn ($value) => $value instanceof \Throwable)):
                return 'one';
        }

        return 'other';
    }

    /**
     * A callback assigned to a variable, then used in a condition: the same
     * predicate, merely defined outside the condition's parentheses.
     */
    public function arrowFnByVariable(array $values): string
    {
        $isError = fn (object $value): bool => $value instanceof \Throwable;

        if (array_filter($values, $isError)) {
            return 'some';
        }

        return 'none';
    }

    /**
     * First-class callable syntax builds a `Closure` referring to the function;
     * it introspects nothing where it is written, exactly like the arrow-function
     * predicate above. The caller that eventually invokes the reference decides
     * what to do with each answer.
     */
    public function firstClassCallableInsideAnIfCondition(array $values): string
    {
        if (count(array_unique(array_map(get_class(...), $values))) > 1) {
            return 'mixed';
        }

        return 'uniform';
    }

    /**
     * The same reference in a ternary condition and a `match` subject — the
     * positions a call would be reported in.
     */
    public function firstClassCallableInOtherConditions(array $values): string
    {
        $labels = array_map(gettype(...), $values);

        return match (count(array_unique(array_map(get_debug_type(...), $values)))) {
            0 => $labels === [] ? 'empty' : 'never',
            default => 'some',
        };
    }
}

/**
 * The classes `byConstructorCall()` constructs. Naming a class after a global
 * function is legal PHP — the two live in separate symbol tables — which is
 * why `new name(` has to be read as a constructor call and not as the
 * introspection function of that name.
 *
 * `byRootQualifiedConstructorCall()` names the global namespace's classes of
 * the same names instead, which this file cannot also declare — it declares one
 * namespace, and these are in it. That changes nothing the sniff reads: it
 * resolves no class, and the `new` in front of the name is the whole of what
 * makes either form a constructor call.
 */
final class get_class
{
    public function __construct(private object $value)
    {
    }
}

final class gettype
{
    public function __construct(private object $value)
    {
    }
}

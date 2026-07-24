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
    public function closureInsideAWhileCondition(array $values): string
    {
        while (array_filter($values, fn (object $value): bool => is_a($value, \Throwable::class))) {
            array_pop($values);
        }

        return 'drained';
    }

    /**
     * A callback inside a `switch` subject.
     */
    public function closureInsideASwitchSubject(array $values): string
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
    public function closureInsideAMatchSubject(array $values): string
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
    public function closureInsideATernaryCondition(array $values): string
    {
        return array_filter($values, fn (object $value): bool => $value instanceof \Throwable)
            ? 'some'
            : 'none';
    }

    /**
     * A callback inside a `match` arm's condition.
     */
    public function closureInsideAMatchArmCondition(array $values): string
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
    public function closureInsideASwitchCaseLabel(array $values): string
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
    public function closureInsideASwitchCaseLabelWithInstanceof(array $values): string
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
    public function closureByVariable(array $values): string
    {
        $isError = fn (object $value): bool => $value instanceof \Throwable;

        if (array_filter($values, $isError)) {
            return 'some';
        }

        return 'none';
    }
}

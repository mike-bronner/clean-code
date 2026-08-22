<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

/**
 * PHP 8.4 property hooks, from both sides of the scope rule.
 *
 * A hook body is a function body: what is written inside it decides what
 * reading or writing the property yields. PHP_CodeSniffer opens no scope for
 * one, though — a hook gets no scope pointers at all, and every token inside it
 * reports the class as its innermost condition — so a bound resolved from
 * declaration tokens alone cannot see it, and the hook's own predicate is then
 * measured against whatever condition textually encloses the property.
 *
 * Both directions are pinned here, because they are the two ways the bound can
 * be wrong. The silent direction: a hook predicate reports a type, so it stays
 * silent even where the property is declared inside a branch condition. The
 * reported direction: a branch written *inside* a hook body is that body's own
 * branch, so it is reported, exactly as it is inside a closure.
 */
final class HookedReport
{
    private string $stored = '';

    public function __construct(private object $value)
    {
    }

    /**
     * A predicate, in both hook forms. The caller that reads the property
     * decides what to do with the answer.
     */
    public bool $isFailure {
        get => $this->value instanceof Failure;
    }

    public bool $isFailureBlock {
        get {
            return $this->value instanceof Failure;
        }
    }

    /**
     * The reported direction, one branch position per hook. Each of these
     * decides which of the hook's own branches runs.
     */
    public string $label {
        get {
            if ($this->value instanceof Failure) {
                return 'failure';
            }

            return 'ok';
        }
    }

    public string $shortLabel {
        get => $this->value instanceof Failure ? 'failure' : 'ok';
    }

    public string $matched {
        get {
            return match (true) {
                $this->value instanceof Failure => 'failure',
                default => 'ok',
            };
        }
    }

    public string $switched {
        get {
            switch (true) {
                case $this->value instanceof Failure:
                    return 'failure';

                default:
                    return 'ok';
            }
        }
    }

    /**
     * A `set` hook takes a parameter, so its body is preceded by a parenthesised
     * list rather than following the hook name directly. The branch inside is
     * the hook's own all the same.
     */
    public string $note {
        set (string $note) {
            $this->stored = $this->value instanceof Failure ? strtolower($note) : $note;
        }
    }

    /**
     * A hook body written *inside* an enclosing branch condition — the shape
     * the bound has to see. The property is declared on an anonymous class
     * built in the condition, so the hook's predicate sits inside the `if`'s
     * parentheses; it decides what reading the property yields, and the `if`
     * branches on what the call around it returns.
     */
    public function arrowHookInsideAnIfCondition(object $value): string
    {
        if (self::probe(new class ($value) {
            public function __construct(private object $value)
            {
            }

            public bool $isFailure {
                get => $this->value instanceof Failure;
            }
        })) {
            return 'some';
        }

        return 'none';
    }

    /**
     * The same shape with the block form of the hook, and in a ternary
     * condition rather than an `if`.
     */
    public function blockHookInsideATernaryCondition(object $value): string
    {
        return self::probe(new class ($value) {
            public function __construct(private object $value)
            {
            }

            public bool $isFailure {
                get {
                    return $this->value instanceof Failure;
                }
            }
        }) ? 'some' : 'none';
    }

    /**
     * A plain branch in an ordinary method, written after every hook above has
     * closed. A bound that lost its place inside a hook list would report this
     * one differently — or not at all.
     */
    public function decidesOnItsOwn(object $value): string
    {
        if ($value instanceof Failure) {
            return 'failure';
        }

        return 'ok';
    }

    private static function probe(object $candidate): bool
    {
        return $candidate->isFailure;
    }
}

/**
 * An interface's hook is abstract: it declares that the property is readable,
 * and no body at all. There is nothing here for the bound to hold, which is the
 * boundary the class above cannot reach — every hook it declares has a body.
 */
interface Labelled
{
    public string $label { get; }
}

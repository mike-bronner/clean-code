<?php

declare(strict_types=1);

namespace Acme\Compliant;

class Reporter
{
    private int $seed;

    /** @var array<int, mixed> */
    private array $buffer = [];

    public function __construct()
    {
        // Magic method — the standard's stated exception.
        $this->seed = func_get_arg(0);
    }

    public function summarize(string $label, int $count): string
    {
        return $label . ': ' . $count;
    }

    // A variadic is a declared parameter: named, typed, visible in the
    // signature.
    public function join(string ...$parts): string
    {
        return implode(', ', $parts);
    }

    public function delegate(Collector $collector): array
    {
        // Same-named members, not PHP's functions.
        return $collector->func_get_args()
            + $collector?->func_get_arg(0)
            + Collector::func_num_args();
    }

    public function threshold(): int
    {
        // A constant that merely shares the name is not a call.
        return FUNC_NUM_ARGS;
    }

    public function namespaced(): array
    {
        // A different function that merely shares the name.
        return \Acme\Support\func_get_args();
    }

    public function relative(): array
    {
        // `namespace\` resolves against Acme\Compliant with no fallback to the
        // global namespace, so this is not PHP's function either.
        return namespace\func_get_args();
    }

    // A return-by-reference declaration: the `&` sits between the keyword and
    // the name, but this is still a declaration and not a call.
    public function &func_num_args(): array
    {
        return $this->buffer;
    }

    public function instantiate(): object
    {
        // Instantiating a same-named class is not a call to PHP's function.
        return new func_get_arg();
    }

    public function instantiateQualified(): object
    {
        // Qualifying the name changes which symbol it reaches, never what the
        // construct is: a leading separator reaches the global namespace's
        // same-named *class*, and `new` can no more be a call here than it is
        // in the unqualified instantiation above.
        return new \func_get_args();
    }

    public function __call(string $name, array $arguments): array
    {
        return func_get_args();
    }

    public static function __callStatic(string $name, array $arguments): int
    {
        return func_num_args();
    }

    public function __toString(): string
    {
        return (string) FUNC_NUM_ARGS();
    }
}

// Declaring a same-named function in a namespace is not a call to PHP's.
function func_get_args(): array
{
    return [];
}

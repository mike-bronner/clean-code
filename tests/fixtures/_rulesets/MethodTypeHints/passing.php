<?php

// Compliant: every parameter and return is natively type-hinted.
class FullyHintedMethods
{
    public function __construct(private string $name)
    {
    }

    public function __destruct()
    {
    }

    public function __clone()
    {
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function add(int $left, int $right): int
    {
        return $left + $right;
    }

    public function find(?string $identifier): ?\DateTimeImmutable
    {
        return $identifier === null ? null : new \DateTimeImmutable($identifier);
    }

    public function parse(int|string $value): int|false
    {
        return is_int($value) ? $value : false;
    }

    public function merge(\Countable&\ArrayAccess $subject): void
    {
        $subject[] = count($subject);
    }

    public function accept(mixed $value): mixed
    {
        return $value;
    }

    public function fail(): never
    {
        throw new \RuntimeException('failed');
    }

    public static function make(string $name): static
    {
        return new static($name);
    }
}

interface HintedContract
{
    public function resolve(string $key): object;
}

// Compliant edge cases: closures and arrow functions.
class ClosureEdgeCases
{
    public function closures(): void
    {
        $hinted = function (int $value): int {
            return $value * 2;
        };

        $voidHinted = function (): void {
            // no return value, hint present
        };

        // Closures returning a value are not checked for a return hint.
        $returningValue = function ($value) {
            return $value;
        };

        // Arrow functions are not checked at all.
        $arrow = fn ($value) => $value + 1;

        $hinted(1);
        $voidHinted();
        $returningValue(2);
        $arrow(3);
    }
}

// Compliant: methods carrying @inheritDoc are skipped — the signature is the
// parent's. One child isolates the parameter-sniff skip, the other the
// return-sniff skip; both parents are fully hinted so the only unhinted
// signatures in this file are the ones @inheritDoc must silence.
class HintedParent
{
    public function withParameter(string $value): void
    {
        echo $value;
    }

    public function withReturn(int $value): int
    {
        return $value;
    }
}

class InheritDocParameterSkip extends HintedParent
{
    /**
     * @inheritDoc
     */
    public function withParameter($value): void
    {
        parent::withParameter((string) $value);
    }
}

class InheritDocReturnSkip extends HintedParent
{
    /**
     * @inheritDoc
     */
    public function withReturn(int $value)
    {
        return parent::withReturn($value);
    }
}

// Compliant: free functions are covered by the same rules — #70 owns
// parameter/return hints for every callable, not just class methods. A fully
// hinted free function produces no violation.
function compliantFreeFunction(int $value): int
{
    return $value * 2;
}

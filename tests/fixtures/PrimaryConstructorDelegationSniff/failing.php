<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Constructors.PrimaryConstructorDelegation.
 *
 * Named constructors obtaining an instance while bypassing the primary
 * constructor: `unserialize()`, reflection, a deserializer's output returned
 * raw, the same `unserialize()` behind each nullable return type, and one
 * behind the root-qualified spelling of the class's own name. Every one is
 * reported on its `function` keyword, since the defect is the absence of
 * delegation across the whole method.
 *
 * The last three are what pin the return-type breadth from the reporting side.
 * A sniff reading `?self`, `self|null`, or `\Snapshot` as "not a named
 * constructor" falls silent on them, and silence is what a compliant fixture
 * expects anyway, so only this side can detect it.
 *
 * The class also carries a compliant named constructor and a primary
 * constructor, so a sniff that reported every static method would not match
 * this file either.
 */

final class Snapshot
{
    public function __construct(private readonly array $attributes)
    {
    }

    public static function fromAttributes(array $attributes): self
    {
        return new self($attributes);
    }

    public static function fromSerialized(string $payload): self
    {
        return unserialize($payload);
    }

    public static function fromReflection(array $attributes): static
    {
        $reflection = new ReflectionClass(self::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        return $instance;
    }

    public static function fromJson(string $json): Snapshot
    {
        return (new SnapshotDeserializer())->deserialize($json);
    }

    public static function tryFromSerialized(string $payload): ?self
    {
        return unserialize($payload) ?: null;
    }

    public static function fromCache(string $key): self|null
    {
        return unserialize(apcu_fetch($key)) ?: null;
    }

    public static function fromRoot(string $payload): \Snapshot
    {
        return unserialize($payload);
    }
}

/**
 * An anonymous class has no name, so `self` and `static` are the only spellings
 * that can reach its primary constructor — and this named constructor uses
 * neither. Reported like any other, which is what keeps the sniff from having
 * to compare against a class name that does not exist.
 */
$registry = new class () {
    public function __construct(private readonly array $entries = [])
    {
    }

    public static function empty(): self
    {
        return unserialize('');
    }
};

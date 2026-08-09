<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Constructors.PrimaryConstructorDelegation.
 *
 * Four named constructors, each obtaining its instance while bypassing the
 * primary constructor: `unserialize()`, reflection, a deserializer's output
 * returned raw, and the same `unserialize()` behind a nullable return type.
 * Every one is reported on its `function` keyword, since the defect is the
 * absence of delegation across the whole method.
 *
 * The nullable one is what pins the return-type breadth from the reporting
 * side: a sniff reading `?self` as "not a named constructor" falls silent on
 * it, which no compliant fixture can detect.
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

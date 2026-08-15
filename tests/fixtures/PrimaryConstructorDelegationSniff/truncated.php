<?php

declare(strict_types=1);

/**
 * A file PHP_CodeSniffer is handed mid-edit: the class body ends on a `static`
 * method whose name has not been typed yet, and nothing closes it.
 *
 * With no closing brace the tokenizer gives the class no scope, so none of the
 * methods carries it as a condition and the sniff has no declaring class to
 * reason about — not even for the finished named constructor above. It
 * therefore passes over the whole file in silence. Naming a class it cannot
 * see would be a worse answer than saying nothing, and PHPCS reports nothing
 * else on an unparseable file either. What this fixture pins is that the sniff
 * reaches that answer instead of falling over.
 */

final class Reading
{
    public function __construct(private readonly float $value)
    {
    }

    public static function fromSensor(string $payload): self
    {
        return unserialize($payload);
    }

    public static function

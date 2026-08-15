<?php

declare(strict_types=1);

namespace App\Fixtures;

use RuntimeException;

// Every class below holds state by one of the four routes the sniff accepts —
// a member variable, a promoted constructor parameter, an `extends` clause, or
// a trait `use` — and every interface, trait and enum is a shape the sniff must
// not speak about at all. Nothing here may produce a violation.

interface Payable
{
    public function amount(): int;
}

trait HasTimestamps
{
    private ?string $createdAt = null;
}

trait Stringable
{
    public function toString(): string
    {
        return static::class;
    }
}

enum Currency: string
{
    case Usd = 'usd';
    case Eur = 'eur';
}

// A conventional instance property.
class Invoice
{
    private int $total = 0;

    public function total(): int
    {
        return $this->total;
    }
}

// A static member variable is state too.
class InvoiceRegistry
{
    protected static array $seen = [];

    public function remember(string $number): void
    {
        static::$seen[] = $number;
    }
}

// The only state is constructor-promoted, which the property-promotion
// standard (#47) makes the preferred spelling — so it has to count.
class Money
{
    public function __construct(private readonly int $amount)
    {
    }

    public function amount(): int
    {
        return $this->amount;
    }
}

// Inherited state. Money declares $amount, so a subclass that declares nothing
// of its own still encapsulates data.
class Refund extends Money
{
}

// The idiomatic empty exception subclass — the exact shape "own-declaration"
// semantics would false-flag.
class PaymentFailed extends RuntimeException
{
}

// Trait-composed state: $createdAt is HasTimestamps', and becomes Post's.
class Post
{
    use HasTimestamps;

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }
}

// A property hook declares a real property, however it is spelled.
class Temperature
{
    public int $celsius {
        get => $this->celsius;
        set => $value;
    }
}

// The near-misses that must not fool the sniff into a *false negative*: this
// class declares its own property, so its silence is not evidence either way —
// it is here so the shapes below sit in compliant code rather than in
// failing.php, where they would be indistinguishable from the violation.
class Order
{
    private array $lines = [];

    public function add(string $sku, int $quantity): void
    {
        $line = ['sku' => $sku, 'quantity' => $quantity];
        $this->lines[] = $line;
    }

    public function each(callable $callback): void
    {
        $lines = $this->lines;

        (static function () use ($lines, $callback): void {
            foreach ($lines as $line) {
                $callback($line);
            }
        })();
    }
}

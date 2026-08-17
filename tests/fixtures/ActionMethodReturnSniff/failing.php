<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ActionMethodReturn;

class Order
{
    /**
     * A declared return type other than void/never.
     */
    public function setReference(string $reference): string
    {
        return $reference;
    }

    /**
     * The nullable spelling. The `?` only adds a third state to the same
     * value, so it is stripped before the type is read.
     */
    public function saveInvoice(): ?int
    {
        return 1;
    }

    /**
     * A union. `null` drops out with the `?` above, and what is left is still
     * a value.
     */
    public function updateTotal(): int|float
    {
        return 1;
    }

    /**
     * A union whose members include `static`. It hands back either the object
     * or a value, and the value is the half this rule is about — so the fluent
     * exemption does not reach it.
     */
    public function addLine(): static|false
    {
        return false;
    }

    /**
     * No declared return type, and a `return` with an expression after it.
     */
    public function deleteLine(int $line)
    {
        return $this->lines[$line];
    }

    /**
     * No declared type, and one path returns `$this` while another returns a
     * result. That mix is exactly the command-query blur the rule names, so the
     * fluent exemption does not apply.
     */
    public function applyDiscount(float $rate)
    {
        if ($rate === 0.0) {
            return $this;
        }

        return $this->total * $rate;
    }

    /**
     * `$this->total` is a value built from `$this`, not `$this` itself.
     */
    public function resetTotal()
    {
        return $this->total;
    }

    /**
     * The verb is the whole name.
     */
    public function set(): string
    {
        return 'set';
    }

    /**
     * An underscore ends the camelCase word just as a capital does.
     */
    public function store_line(): int
    {
        return 1;
    }

    /**
     * A qualified return type naming this very class. It is not compared
     * against the class name: resolving it needs the file's imports, and a
     * wrong answer there would silence a real finding.
     */
    public function attachNote(): \MikeBronner\CleanCode\Tests\Fixtures\ActionMethodReturn\Order
    {
        return $this;
    }

    /**
     * A static method is still a method.
     */
    public static function postPayment(): bool
    {
        return true;
    }

    /**
     * The last of the declared-type shapes, on a plain instance method.
     */
    public function detachNote(): string
    {
        return '';
    }

    /**
     * A single-token return expression that is not `$this`. Matching the fluent
     * body on "one token, then the semicolon" alone would read this as the
     * builder idiom and exempt it.
     */
    public function updateStatus(string $status)
    {
        $status = strtoupper($status);

        return $status;
    }
}

abstract class Payment
{
    /**
     * No body, but the declaration alone says a value comes back.
     */
    abstract public function sendReceipt(): bool;
}

interface Refundable
{
    /**
     * An interface method, same reasoning.
     */
    public function removeRefund(): bool;
}

trait Adjusts
{
    public function clearAdjustments(): array
    {
        return [];
    }
}

enum Status: string
{
    case Draft = 'draft';

    public function applyLabel(): string
    {
        return $this->value;
    }
}

$renderer = new class () {
    /**
     * A method of an anonymous class is a method.
     */
    public function setMode(): string
    {
        return 'html';
    }
};

/**
 * `null` on its own is a declared type, not a nullable marker. It is neither
 * `void` nor `never`, so a value comes back — and none of these three has a body
 * to fall back on, which is what makes reading the type the whole answer.
 */
interface Cancellable
{
    public function removeCancellation(): null;
}

abstract class Reminder
{
    abstract public function sendReminder(): null;
}

class Receipt
{
    public function storeReceipt(): null
    {
    }

    /**
     * Parenthesised, and still not `$this`. Taking the parentheses off is what
     * lets `return ($this);` be read as the builder idiom, and this is the half
     * that proves taking them off does not swallow the expression with them.
     */
    public function updateReceipt()
    {
        return ($this->number);
    }
}

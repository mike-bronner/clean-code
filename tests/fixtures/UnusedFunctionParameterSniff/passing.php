<?php

/**
 * Compliant code plus the near-miss shapes the rule must stay silent on.
 *
 * The compliant half declares a parameter and then uses it, in every construct
 * the sniff registers on: function, method, constructor, closure, and arrow
 * function. The near-miss half declares a parameter and never uses it, in the
 * shapes both PHPMD and rules.xml deliberately exempt — bodyless declarations,
 * promoted constructor properties, and the magic methods whose signature is
 * fixed by PHP.
 *
 * Uses that are easy to lose to a naive token walk are here on purpose: a
 * parameter read only inside an interpolated string, only inside a heredoc,
 * and only inside a nested closure.
 */

declare(strict_types=1);

interface Greeter
{
    // A bodyless declaration cannot use anything, so $name is not "unused".
    public function greet(string $name): string;
}

abstract class Job
{
    // Same for an abstract method.
    abstract public function run(string $payload): void;
}

function usesItsParameter(string $used): string
{
    return $used;
}

function usesEveryParameter(string $first, string $second): string
{
    return $first . $second;
}

function usesParameterInInterpolatedString(string $interpolated): string
{
    return "value: {$interpolated}";
}

function usesParameterInHeredoc(string $embedded): string
{
    return <<<TEXT
        value: {$embedded}
        TEXT;
}

function usesParameterInNestedClosure(string $captured): callable
{
    return function () use ($captured): string {
        return $captured;
    };
}

class Report
{
    // Promoted constructor properties become class state, so a body that
    // never mentions them is not a defect.
    public function __construct(private string $title, private int $rank)
    {
    }

    public function render(string $prefix): string
    {
        return $prefix . $this->title;
    }

    // PHP fixes the signature of these, so an unused parameter cannot be
    // removed. Both tools exempt them.
    public function __set(string $name, mixed $value): void
    {
        throw new LogicException('read-only');
    }

    public function __get(string $name): mixed
    {
        return null;
    }
}

$doubler = static function (int $value): int {
    return $value * 2;
};

$tripler = static fn (int $value): int => $value * 3;

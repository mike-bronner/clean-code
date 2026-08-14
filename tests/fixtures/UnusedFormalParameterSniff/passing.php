<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedFormalParameter;

/**
 * Every shape CleanCode.DeadCode.UnusedFormalParameter must stay silent on.
 *
 * Two kinds of thing live here. The first is the plainly compliant case — a
 * parameter the body reads. The second, and the reason this fixture is worth
 * more than the first kind alone, is the near-miss set: each of the exemptions
 * the sniff implements, in the spelling that exercises it, so that removing an
 * exemption from the sniff reddens this file rather than passing unnoticed.
 */

// A plain read.
function plainRead(string $subject): void
{
    echo $subject;
}

// Read through interpolation, in each spelling the sniff recognises.
function interpolatedRead(string $alpha, string $beta, string $gamma): void
{
    echo "plain $alpha";
    echo "braced {$beta}";
    echo "dollar-braced ${gamma}";
}

// Read inside a multi-line heredoc, whose body is tokenized one token per
// physical line — the read is not on the line the heredoc opens on.
function heredocRead(string $delta): void
{
    echo <<<TEXT
    first line, which does not mention it
    second line, which does: $delta
    TEXT;
}

// Read only from inside a nested closure and a nested arrow function. Both are
// inside the outer body's scope, so both count — PHPMD counts them too.
function nestedRead(string $epsilon, string $zeta): callable
{
    $closure = function () use ($epsilon): void {
        echo $epsilon;
    };

    $closure();

    return fn (): string => $zeta;
}

// func_get_args() reaches every parameter, so none of them is dead. PHPMD
// exempts the whole signature the same way.
function reachedThroughFuncGetArgs(string $eta, string $theta): void
{
    var_dump(func_get_args());
}

// func_get_args() called from inside a nested closure still exempts the
// signature — confirmed against PHPMD 2.15.0.
function funcGetArgsNested(string $iota): void
{
    $inner = function (): void {
        var_dump(func_get_args());
    };

    $inner();
}

// compact() naming the parameter is a read of that parameter.
function compactNamed(string $kappa): array
{
    return compact('kappa');
}

// The near-miss that makes the word boundary load-bearing: the body reads
// $lambdaExtra, which contains "$lambda" as a prefix. $lambda itself is read
// too, on its own line — delete that line and this fixture must redden.
function prefixNearMiss(string $lambda): void
{
    $lambdaExtra = 'unrelated';

    echo $lambdaExtra;
    echo $lambda;
}

interface Contract
{
    // Bodyless: an interface method cannot use anything.
    public function handle(string $payload): void;

    public function describe(string $subject): string;
}

trait ParentTrait
{
    public function fromTrait(string $value): string
    {
        return $value;
    }
}

abstract class BaseHandler implements Contract
{
    use ParentTrait;

    // Bodyless: an abstract method cannot use anything.
    abstract public function run(string $input): void;

    public function describe(string $subject): string
    {
        return $subject;
    }
}

class SameFileOverrides extends BaseHandler
{
    // Overrides an abstract method of a parent declared in this same file.
    public function run(string $input): void
    {
        echo 'ran';
    }

    // Implements an interface method, the interface declared in this same file
    // and reached through the parent's `implements` clause.
    public function handle(string $payload): void
    {
        echo 'handled';
    }

    // Overrides a concrete method of the same-file parent.
    public function describe(string $subject): string
    {
        return 'fixed';
    }

    // Overrides a method the same-file parent draws from a trait, which is
    // what PDepend's getAllMethods() would report for that parent.
    public function fromTrait(string $value): string
    {
        return 'fixed';
    }
}

class TransitiveChild extends SameFileOverrides
{
    // Resolves through two levels of same-file ancestry.
    public function run(string $input): void
    {
        echo 'ran again';
    }
}

class Annotated extends \Vendor\Unknown\Base
{
    /**
     * @inheritdoc
     */
    public function bare(string $first): void
    {
        echo 'x';
    }

    /**
     * {@inheritdoc}
     */
    public function braced(string $second): void
    {
        echo 'x';
    }

    /**
     * @inheritDoc
     */
    public function mixedCase(string $third): void
    {
        echo 'x';
    }

    // PHP 8.3 rejects this attribute at compile time unless the method really
    // does override something, so it is a compiler-checked proof of the fact
    // the sniff otherwise needs a whole-project type map to establish.
    #[\Override]
    public function attributed(string $fourth): void
    {
        echo 'x';
    }

    // The attribute still counts when it is not the only one, and when it
    // sits above the modifiers rather than beside them.
    #[\Deprecated]
    #[\Override]
    public function multipleAttributes(string $fifth): void
    {
        echo 'x';
    }
}

// A constructor's own parameter, read in the body like any other. The promoted
// spelling below is exempt without being read; this one is not.
class PlainConstructorReads
{
    public function __construct(string $reason)
    {
        echo $reason;
    }
}

// A closure and an arrow function reading the parameters they declare
// themselves. Both constructs are reported by this sniff when the parameter is
// dead, so the compliant shape of each belongs here.
$closureReads = function (string $mu): string {
    return $mu;
};

$arrowReads = fn (string $nu): string => $nu;

// Every compact() call in the body names its parameters, not just the first
// one: $xi is named by the second call and by nothing else.
function compactInSecondCall(string $omicron, string $xi): array
{
    if ($omicron === 'first') {
        return compact('omicron');
    }

    return compact('xi');
}

// PHP resolves an attribute name case-insensitively, so this is the same
// attribute as #[\Override] and carries the same proof.
class LowercaseOverride extends \Vendor\Unknown\Base
{
    #[\override]
    public function handle(string $sixth): void
    {
        echo 'x';
    }
}

class Promoted
{
    // A promoted property is class state whatever the body does, in each of
    // the spellings that promote.
    public function __construct(
        private string $visibility,
        protected readonly int $readonlyToo,
    ) {
    }
}

class FixedSignatures
{
    public function __get(string $name): mixed
    {
        return null;
    }

    public function __set(string $name, mixed $value): void
    {
    }

    public function __isset(string $name): bool
    {
        return false;
    }

    public function __unset(string $name): void
    {
    }

    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return null;
    }

    public static function __set_state(array $properties): object
    {
        return new self();
    }
}

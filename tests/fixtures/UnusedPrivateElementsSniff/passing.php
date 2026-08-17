<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedPrivateElements;

/**
 * Every private member below is referenced somewhere in its own body, and
 * every near-miss shape the sniff must stay silent on sits alongside them.
 */
class FullyUsedClass
{
    private string $used = 'value';

    private static int $counter = 0;

    protected string $notPrivate = 'visible';

    public string $alsoNotPrivate = 'visible';

    public function run(): string
    {
        static::$counter++;

        return $this->used . $this->helper() . self::staticHelper() . $this->notPrivate;
    }

    private function helper(): string
    {
        return 'helper';
    }

    private static function staticHelper(): string
    {
        return 'static-helper';
    }
}

/**
 * A private method and a private property that share a name, each referenced
 * through the syntax that belongs to it. Neither may be flagged: the `(`
 * lookahead has to attribute `$this->tally()` to the method and `$this->tally`
 * to the property.
 */
class SharedNameBothUsed
{
    private int $tally = 0;

    public function report(): int
    {
        return $this->tally + $this->tally();
    }

    private function tally(): int
    {
        return 1;
    }
}

/**
 * The indirect references the sniff deliberately honours: a callable array, a
 * compact() name, and string interpolation.
 */
class ReferencedThroughStrings
{
    private string $viaCompact = 'a';

    private string $viaInterpolation = 'b';

    public function run(): array
    {
        $viaCompact = $this->viaCompact;

        return [
            [$this, 'viaCallable'],
            compact('viaCompact'),
            "value: {$this->viaInterpolation}",
        ];
    }

    private function viaCallable(): void
    {
    }
}

/**
 * Magic methods are invoked implicitly and promoted constructor properties are
 * parameters, so neither is ever collected as a declaration.
 */
class ImplicitAndPromoted
{
    private function __construct(private int $promoted)
    {
    }

    public function __toString(): string
    {
        return 'x';
    }

    public static function make(): self
    {
        return new self(1);
    }
}

/**
 * A trait's private members are flattened into the consuming class and may be
 * used only there, so the sniff never scans a trait body. `$secret` and
 * `secretHelper()` are unreferenced here and must still not be flagged.
 */
trait UnscannedTrait
{
    private string $secret = 'used by the consumer';

    private function secretHelper(): void
    {
    }
}

/**
 * An enum's private methods are reachable only from the enum body, so the enum
 * is scanned. Both members below are referenced. (An enum declares no
 * properties — PHP forbids enum state — so only methods can appear here.)
 */
enum FullyUsedEnum: string
{
    case Draft = 'draft';

    case Published = 'published';

    public function label(): string
    {
        return $this->prefix() . self::suffix();
    }

    private function prefix(): string
    {
        return $this->value;
    }

    private static function suffix(): string
    {
        return '!';
    }
}

/**
 * An anonymous class body is scanned on its own pass, against its own usages.
 * Both private members below are used inside it.
 */
class HostsAnonymousClass
{
    public function make(): object
    {
        return new class () {
            private string $inner = 'inner';

            public function read(): string
            {
                return $this->inner . $this->innerHelper();
            }

            private function innerHelper(): string
            {
                return 'helper';
            }
        };
    }
}

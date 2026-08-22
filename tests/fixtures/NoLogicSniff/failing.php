<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Constructors.NoLogic.
 *
 * Every statement a constructor may not hold, each on its own line, so the
 * behaviour test can assert exact line and column numbers. The compliant
 * statements interleaved here — the property assignments at the top of each
 * constructor, and every statement nested inside a flagged construct — are
 * absent from that expectation, which is what proves they are not flagged.
 */

final class BraceControlStructures
{
    private int $a;

    private int $total;

    public function __construct(int $a, array $items)
    {
        $this->a = $a;
        if ($a > 0) {
            $this->a = 0;
        } elseif ($a < 0) {
            $this->a = 1;
        } else {
            $this->a = 2;
        }
        for ($i = 0; $i < 10; $i++) {
            $this->total = $i;
        }
        foreach ($items as $item) {
            $this->total = $item;
        }
        while ($a > 0) {
            $a--;
        }
        do {
            $a++;
        } while ($a < 10);
        switch ($a) {
            case 1:
                break;
        }
        try {
            $this->a = $a;
        } catch (\Throwable $e) {
            $this->a = 0;
        } finally {
            $this->a = 1;
        }
        if ($a > 1) {
            $this->a = 3;
        } else if ($a < 1) {
            $this->a = 4;
        }
        match ($a) {
            default => 1,
        };
        {
            $this->a = 5;
        }
        return;
    }
}

final class AlternativeSyntaxControlStructures
{
    private int $a;

    public function __construct(int $a, array $items)
    {
        $this->a = $a;
        if ($a > 0):
            $this->a = 1;
        elseif ($a < 0):
            $this->a = 2;
        else:
            $this->a = 3;
        endif;
        foreach ($items as $item):
            $this->a = $item;
        endforeach;
        for ($i = 0; $i < 2; $i++):
            $this->a = $i;
        endfor;
        while ($a > 0):
            $a--;
        endwhile;
        switch ($a):
            case 1:
                break;
        endswitch;
    }
}

final class CallsAndComputation
{
    private int $a;

    private int $total;

    private array $items;

    public function __construct(int $a)
    {
        $this->a = $a;
        $this->configure();
        doSomething($a);
        $local = $a + 1;
        $this->total++;
        $this->getConfig()->value = 5;
        $this->items[$this->key()] = $a;
        $this->loadDefaults()['key'] = 1;
        $this->total += 1;
        $this->total .= 'x';
        $this->total ??= 2;
        throw new \RuntimeException('unreachable but tokenized');
    }

    private function configure(): void
    {
    }

    private function getConfig(): self
    {
        return $this;
    }

    private function key(): string
    {
        return 'k';
    }

    private function loadDefaults(): array
    {
        return [];
    }
}

/**
 * `parent::__construct(…)` is delegation only when the statement is exactly
 * that call. Anything trailing it runs on every instantiation, a bare
 * `parent::__construct` is not even a call, and `parent::__construct(...)` is
 * the first-class callable syntax: it builds a Closure and throws it away, so
 * the parent constructor never runs.
 */
final class ParentDelegationLookalikes extends BraceControlStructures
{
    private int $a;

    public function __construct(int $a)
    {
        $this->a = $a;
        parent::__construct($a) or $this->boot();
        parent::__construct($a)->initializeExtra();
        parent::__construct;
        parent::__construct(...);
        \ParentClass::__construct($a);
    }

    private function boot(): void
    {
    }
}

/**
 * PHP method names are case-insensitive, so an upper-cased declaration is the
 * same constructor and its body is inspected the same way.
 */
final class UpperCasedConstructor
{
    private int $a;

    public function __CONSTRUCT(int $a)
    {
        $this->a = $a;
        doSomething($a);
    }
}

/**
 * List destructuring writes properties without ever matching the
 * property-assignment form: the statement begins with `[`, not `$this`, so the
 * documented "Known boundaries" entry that calls it flagged is pinned here.
 */
final class ListDestructuringIntoProperties
{
    private int $a;

    private int $b;

    public function __construct(array $pair)
    {
        [$this->a, $this->b] = $pair;
    }
}

/**
 * An assignment target invokes in three spellings, and only the first carries a
 * parenthesis for a token scan to find. PHPCS collapses an interpolated string
 * into one opaque token — one per physical line for a multi-line one — so a call
 * inside `{$…}`/`${…}` never surfaces a parenthesis at all, and a backtick runs
 * a shell command without one either.
 *
 * Both interpolation syntaxes carry a call on their own: `${resolveKey()}` holds
 * one with no `{$` anywhere in it. Backslash parity decides which of these really
 * interpolate — `\\` escapes only itself, so `"\\${resolveKey()}"` and
 * `"\\{$this->key()}"` both keep their opener, while the odd counts do not.
 */
final class InvokingAssignmentTargets
{
    private array $items;

    private string $prefix;

    public function __construct(string $value)
    {
        $this->items[`hostname`] = $value;
        $this->items["{$this->key()}"] = $value;
        $this->items["${$this->key()}"] = $value;
        $this->items["{$this->prefix}"] = $value;
        $this->items["prefix {$this->key()} suffix"] = $value;
        $this->items["\\{$this->key()}"] = $value;
        $this->items["${resolveKey()}"] = $value;
        $this->items["\\${resolveKey()}"] = $value;
        $this->items["opens
        here {$this->key()}"] = $value;
        $this->items[<<<KEY
        {$this->key()}
        KEY] = $value;
    }

    private function key(): string
    {
        return 'k';
    }
}

/**
 * The invoking spellings a grouping-aware parenthesis check must still catch.
 *
 * Only a parenthesis that follows something callable opens an argument list, so
 * the check reads the token before each one. These are the spellings where that
 * token is not an operator: a name, a variable, a closing bracket, or one of
 * PHP's construct keywords. `new class {…}` and `clone $obj` carry no argument
 * list at all — their only parenthesis is the grouping one the check now lets
 * through — so they are rejected on the keyword instead.
 */
final class InvokingWithoutACallName
{
    private array $items;

    private $factory;

    private $handlers;

    private $seed;

    public function __construct($value)
    {
        $this->items[(new class { public int $k = 1; })->k] = $value;
        $this->items[(clone $this->seed)->k] = $value;
        $this->items[(new Ancestor())->k] = $value;
        $this->items[($this->factory)()] = $value;
        $this->items[$this->handlers['k']()] = $value;
        $this->items[$this->{'key'}()] = $value;
        $this->items[match (true) { default => 1 }] = $value;
        $this->items[(fn (): int => $this->k())()] = $value;
        $this->items[isset($this->seed) ? 1 : 2] = $value;
    }

    private function k(): int
    {
        return 1;
    }
}

/**
 * The writing spellings a target scan has to catch wherever they hide.
 *
 * A subscript is part of the target, so an assignment operator or an increment
 * inside one runs on every instantiation exactly as a call there would. Each is
 * spelled nested on purpose: the compound assignments and the increment above
 * (113, 117 to 119) are the statement's own top-level operator and reach the
 * rejection down a different path, having no `=` at all.
 */
final class WritingAssignmentTarget
{
    private array $items;

    private int $total;

    private int $a;

    private int $b;

    public function __construct($value)
    {
        $this->items[$this->total += 1] = $value;
        $this->items[$this->total++] = $value;
        $this->items[--$this->total] = $value;
        $this->items[$this->a = $this->b] = $value;
        $this->items[$this->total <<= 1] = $value;
        $this->items[$this->total ??= 2] = $value;
    }
}

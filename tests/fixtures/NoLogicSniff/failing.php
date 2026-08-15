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
 * `parent::__construct(...)` is delegation only when the statement is exactly
 * that call. Anything trailing it runs on every instantiation, and a bare
 * `parent::__construct` is not even a call.
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

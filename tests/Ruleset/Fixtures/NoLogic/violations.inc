<?php

namespace Fixtures\NoLogic;

class LogicInConstructor
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
        $this->configure();
        doSomething($a);
        $local = $a + 1;
        $this->total++;
        $this->getConfig()->value = 5;
        $this->items[$this->key()] = $a;
        $this->loadDefaults()['key'] = 1;
        throw new \RuntimeException('unreachable but tokenized');
    }

    private function configure(): void
    {
    }
}

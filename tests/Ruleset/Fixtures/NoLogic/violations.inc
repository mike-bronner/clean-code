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
        }
        $this->configure();
        doSomething($a);
        $local = $a + 1;
        $this->total++;
        throw new \RuntimeException('unreachable but tokenized');
    }

    private function configure(): void
    {
    }
}

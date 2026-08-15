<?php

declare(strict_types=1);

namespace App\Billing;

class Guard
{
    public function run(): void
    {
        try {
            $this->work();
        } catch (\Exception $exception) {
            unset($exception);
        }
    }

    private function work(): void
    {
    }
}

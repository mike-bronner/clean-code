<?php

declare(strict_types=1);

namespace App\Services;

class AnonymousFactoryService
{
    public function make(): object
    {
        // An anonymous class has no name to judge and must be ignored, even
        // though it would carry neither the "Service" suffix nor any other.
        return new class () {
        };
    }
}

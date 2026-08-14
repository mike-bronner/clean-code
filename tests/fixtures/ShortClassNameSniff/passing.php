<?php

namespace Ab;

use App\Fo;

interface Bar
{
}

trait Log
{
}

enum Job
{
}

class Foo implements Bar
{
    use Log;

    public function fo(): string
    {
        $ab = new class () {
        };

        Fo::of($ab);

        return Fo::class;
    }
}

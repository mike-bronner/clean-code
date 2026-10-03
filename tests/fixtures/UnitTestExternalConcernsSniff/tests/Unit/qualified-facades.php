<?php

declare(strict_types=1);

// A facade reached through a relative or a rooted name is still the facade.

namespace Tests\Unit;

class QualifiedFacadesTest
{
    public function testFakesThroughQualifiedNames(): void
    {
        namespace\Http::fake();
        \Illuminate\Support\Facades\Http::fake();
    }
}

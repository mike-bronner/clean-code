<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Support\Refresher as RefreshDatabase;
use Support\RefreshDatabaseHelper;
use Support\Traits\Sortable;
use Support\Traits\Timestampable;
use function Illuminate\Foundation\Testing\refreshDatabase;
use const Support\HTTP;
use Illuminate\Foundation\Testing\{function refreshDatabase, const REFRESH_DATABASE};

class CalculatorTest
{
    use Sortable;

    use Sortable, Timestampable {
        Timestampable::touch insteadof Sortable;
    }

    public function testTheSubjectAlone(): void
    {
        $calculator = new Calculator();

        $this->assertSame(4, $calculator->add(2, 2));
    }

    public function testNamesThatMerelyResembleTheWatchedOnes(): void
    {
        $helper = new RefreshDatabaseHelper();
        $collection = new Collection([HTTP, refreshDatabase()]);

        $this->assertNotNull($helper);
        $this->assertNotNull($collection);
    }

    public function testFacadeCallsThatInstallNoDouble(): void
    {
        Http::assertNothingSent();
        Http::assertSent(static fn (): bool => true);
        Cache::fake();
        Router::fake();

        $mode = Http::fake;

        $this->assertNotNull($mode);
    }

    public function testCallsThatOnlyLookLikeHttpKernelCalls(): void
    {
        $repository = new Repository();

        $repository->get('orders');
        $repository->getJson('orders');

        $this->getName();
        $this->postProcess();

        $accessor = $this->get;

        $this->assertNotNull($accessor);
    }

    public function testAClosureCaptureIsNotAnImport(): void
    {
        $refreshDatabase = true;

        $capture = function () use ($refreshDatabase): bool {
            return $refreshDatabase;
        };

        $this->assertTrue($capture());
    }
}

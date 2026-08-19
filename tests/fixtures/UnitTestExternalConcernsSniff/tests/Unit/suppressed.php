<?php

declare(strict_types=1);

namespace Tests\Unit;

class CustomTestCaseTest
{
    public function testSuppressedLinesAreSkipped(): void
    {
        // phpcs:ignore CleanCode.Testing.UnitTestExternalConcerns.HttpRequest
        $this->get('config.key');

        // @codingStandardsIgnoreLine
        $this->postJson('config.key');

        $this->patch('config.key'); // phpcs:ignore CleanCode.Testing.UnitTestExternalConcerns.HttpRequest

        $this->put('config.key');
    }
}

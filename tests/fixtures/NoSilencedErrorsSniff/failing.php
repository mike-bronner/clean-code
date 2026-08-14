<?php

/**
 * Violating code for Generic.PHP.NoSilencedErrors — the sniff that replaces
 * PHPMD's CleanCode/ErrorControlOperator rule.
 *
 * Every `@` below is the error-control operator, and each one must be reported
 * at its own line and column. The shapes cover the two PHPMD documents
 * (a suppressed function call and a suppressed array read) plus the other
 * expressions the operator is routinely stuck in front of.
 *
 * Everything sits inside a method or a function, which is the only scope PHPMD
 * inspects — the file-scope divergence is pinned separately in divergences.php.
 */

declare(strict_types=1);

class SuppressingFixture
{
    /**
     * @param array<string, string> $data
     */
    public function suppress(array $data, string $path, callable $callback): void
    {
        $file = @fopen($path, 'r');
        $key = @$data['missing'];
        $property = @$this->undefinedProperty;
        $result = @$this->run();
        $called = @$callback();
        $chained = @(new SuppressingFixture())->run();
        $both = @$data['left'] . @$data['right'];

        @unlink($path);
        @include $path;

        unset($file, $key, $property, $result, $called, $chained, $both);
    }

    public function run(): string
    {
        return 'ran';
    }
}

function suppressInAFunction(array $data): string
{
    return @$data['missing'];
}

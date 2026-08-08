<?php

declare(strict_types=1);

class ExemptedRenderer
{
    public function __construct(bool $verbose)
    {
        $inner = function ($force = true): void {
            error_log((string) $force);
        };

        $inner($verbose);
    }

    public function getResultsFiltered(bool $strict): array
    {
        return [$strict];
    }

    public function render(bool $draft): string
    {
        return (string) $draft;
    }

    public function factory(): object
    {
        return new class {
            public function toggle(bool $on): void
            {
                error_log((string) $on);
            }
        };
    }
}

function exemptedHelper(bool $quiet): void
{
    error_log((string) $quiet);
}

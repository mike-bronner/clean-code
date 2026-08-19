<?php

declare(strict_types=1);

namespace App\Fixtures;

// 6 signatures — one past the shipped maximum of 5, which is the smallest
// interface the rule reports. The violation belongs to the width of the whole
// contract, so it is reported on the declaration (line 11, column 1) rather
// than on any single signature.
interface OneOverTheMaximum
{
    public function first(): void;

    public function second(): void;

    public function third(): void;

    public function fourth(): void;

    public function fifth(): void;

    public function sixth(): void;
}

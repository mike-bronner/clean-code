<?php

declare(strict_types=1);

namespace App\Fixtures;

// 6 signatures, one past the shipped maximum. Used to pin maxMethods in both
// directions from one file: it reports under the shipped default and falls
// silent the moment a consuming ruleset raises the ceiling to 6. A property
// that never reached the sniff would leave both runs identical.
interface Configured
{
    public function first(): void;

    public function second(): void;

    public function third(): void;

    public function fourth(): void;

    public function fifth(): void;

    public function sixth(): void;
}

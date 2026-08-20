<?php

declare(strict_types=1);

namespace App\Fixtures;

// An interface whose body is never closed — the shape PHPCS hands a sniff
// mid-edit. The tokeniser leaves it with no scope_opener and no scope_closer,
// so there is no body to count, and the 6 signatures below are not a count
// this file supports yet.
interface Truncated
{
    public function first(): void;

    public function second(): void;

    public function third(): void;

    public function fourth(): void;

    public function fifth(): void;

    public function sixth(): void;

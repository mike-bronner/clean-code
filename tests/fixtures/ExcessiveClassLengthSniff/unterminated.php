<?php

declare(strict_types=1);

namespace App\Fixtures;

// Deliberately malformed: the class body is never closed. PHP_CodeSniffer
// tokenises the file but leaves the class without a scope_closer, so there is
// no end line to measure from. The sniff has to stay silent rather than read a
// key that is not there — measuring nothing is the only honest answer, and a
// file this broken has a parse error to fix first anyway.

class Unterminated
{
    public function run(): void
    {
    }

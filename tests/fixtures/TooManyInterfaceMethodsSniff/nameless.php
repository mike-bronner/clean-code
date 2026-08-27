<?php

declare(strict_types=1);

namespace App\Fixtures;

// The `interface` keyword with no name yet, the other mid-edit shape. PHPCS
// still resolves the braces, so the 6 signatures below *are* counted — but
// getDeclarationName() returns null and the message has no interface to name,
// so the sniff says nothing. There is no anonymous-interface syntax in PHP for
// this guard to swallow: only a half-typed declaration reaches it.
interface
{
    public function first(): void;

    public function second(): void;

    public function third(): void;

    public function fourth(): void;

    public function fifth(): void;

    public function sixth(): void;
}
